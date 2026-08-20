<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Translation\PromptBuilder;
use App\Translation\TranslationEngineException;
use App\Translation\TranslationEngineInterface;
use App\Translation\TranslationRejectedException;
use App\Translation\TranslationValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * Shows what the model actually does with a paragraph: the assembled prompt,
 * the raw answer and the validator's verdict. Unit tests run against a fake
 * engine, so this is the only place where prompt wording can be judged against
 * a real server.
 */
#[AsCommand(
    name: 'app:translate:try',
    description: 'Translates one paragraph on the live Ollama server and shows the prompt, answer and verdict',
)]
final class TranslateTryCommand extends Command
{
    public function __construct(
        private readonly PromptBuilder $promptBuilder,
        private readonly TranslationEngineInterface $engine,
        private readonly TranslationValidator $validator,
        private readonly ProjectRepository $projects,
        #[Autowire('%env(OLLAMA_DEFAULT_MODEL)%')]
        private readonly string $defaultModel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('text', InputArgument::OPTIONAL, 'Paragraph to translate, tokens included')
            ->addOption('project', null, InputOption::VALUE_REQUIRED, 'Take language, model and prompt from this project id')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Target language code', 'pl')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Source language code, omit to let the model guess')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Ollama model, defaults to OLLAMA_DEFAULT_MODEL')
            ->addOption('prompt', null, InputOption::VALUE_REQUIRED, 'Extra instructions for the model');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $text = $input->getArgument('text');

        if (!\is_string($text) || '' === trim($text)) {
            $io->error('Give the paragraph to translate as the first argument.');

            return Command::INVALID;
        }

        $project = $this->project($input);

        if (null === $project) {
            $io->error('No project with that id.');

            return Command::INVALID;
        }

        $segment = new Segment(new Chapter($project, 0, 'OEBPS/probe.xhtml'), 0, 0, 0, $text, []);
        $request = $this->promptBuilder->build($project, $segment, null);

        $io->section('System prompt');
        $io->writeln($request->systemPrompt);

        $io->section('User prompt');
        $io->writeln($request->userPrompt);

        $io->section(\sprintf('Answer from %s', $request->model));

        try {
            $answer = $this->engine->translate($request);
        } catch (TranslationEngineException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->writeln($answer);

        $io->section('Verdict');

        try {
            $this->validator->validate($text, $answer);
        } catch (TranslationRejectedException $rejection) {
            $io->error(\sprintf('Rejected: %s', $rejection->getMessage()));

            return Command::FAILURE;
        }

        $io->success('The answer passes validation.');

        return Command::SUCCESS;
    }

    /**
     * Buduje projekt wylacznie po to, zeby PromptBuilder mial z czego czytac
     * ustawienia. Nic tu nie trafia do bazy.
     */
    private function project(InputInterface $input): ?Project
    {
        $projectId = $input->getOption('project');

        if (\is_string($projectId) && '' !== $projectId) {
            return $this->projects->find(Uuid::fromString($projectId));
        }

        $model = $input->getOption('model');
        $target = $input->getOption('target');
        $source = $input->getOption('source');
        $prompt = $input->getOption('prompt');

        $owner = new User();
        $owner->setEmail('cli@example.com');

        $project = new Project(
            $owner,
            'CLI probe',
            \is_string($target) && '' !== $target ? $target : 'pl',
            \is_string($model) && '' !== $model ? $model : $this->defaultModel,
            'probe.epub',
        );

        if (\is_string($source) && '' !== $source) {
            $project->setSourceLanguage($source);
        }

        if (\is_string($prompt) && '' !== $prompt) {
            $project->setCustomPrompt($prompt);
        }

        return $project;
    }
}
