<?php

declare(strict_types=1);

namespace App\Command;

use App\Ollama\OllamaClient;
use App\Ollama\OllamaUnavailableException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:ollama:ping',
    description: 'Checks connectivity with the Ollama server and lists available models',
)]
final class OllamaPingCommand extends Command
{
    public function __construct(
        private readonly OllamaClient $client,
        #[Autowire('%env(OLLAMA_BASE_URL)%')]
        private readonly string $baseUri,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->section('Ollama');
        $io->text(\sprintf('Address: %s', $this->baseUri));

        try {
            $models = $this->client->listModels();
        } catch (OllamaUnavailableException $exception) {
            $io->error($exception->getMessage());
            $io->text([
                'Check, in order:',
                ' 1. Is the Ollama server running on the host?',
                ' 2. Does it listen on an external interface? By default it binds to',
                '    127.0.0.1, which is invisible from the container. Set',
                '    OLLAMA_HOST=0.0.0.0:<port> on the host and restart Ollama.',
                ' 3. Does the Windows firewall allow that port for the Docker network?',
                ' 4. Does OLLAMA_BASE_URL point at host.docker.internal, not localhost?',
            ]);

            return Command::FAILURE;
        }

        if ([] === $models) {
            $io->warning('Connection works, but the server has no models pulled (ollama pull ...).');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Connection works. Available models (%d):', \count($models)));
        $io->listing($models);

        return Command::SUCCESS;
    }
}
