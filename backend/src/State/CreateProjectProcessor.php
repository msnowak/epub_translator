<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use App\Entity\ProjectStatus;
use App\Entity\User;
use App\Epub\InvalidEpubException;
use App\Epub\UploadedEpubValidator;
use App\Message\ParseEpubMessage;
use App\Storage\ProjectStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProcessorInterface<mixed, Project>
 */
final readonly class CreateProjectProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private UploadedEpubValidator $epubValidator,
        private ProjectStorage $storage,
        private MessageBusInterface $messageBus,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Project
    {
        $request = $context['request'] ?? null;

        if (!$request instanceof Request) {
            throw new \LogicException('The create-project processor requires the HTTP request in its context.');
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('The firewall must authenticate the user before this processor runs.');
        }

        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException($this->translator->trans('upload.no_file'));
        }

        try {
            $this->epubValidator->validate($file->getPathname());
        } catch (InvalidEpubException) {
            throw new UnprocessableEntityHttpException($this->translator->trans('upload.not_epub'));
        }

        $project = new Project(
            $user,
            trim((string) $request->request->get('title', '')),
            trim((string) $request->request->get('targetLanguage', '')),
            trim((string) $request->request->get('ollamaModel', '')),
            $file->getClientOriginalName(),
        );

        $sourceLanguage = trim((string) $request->request->get('sourceLanguage', ''));
        $customPrompt = trim((string) $request->request->get('customPrompt', ''));
        $project->setSourceLanguage('' === $sourceLanguage ? null : $sourceLanguage);
        $project->setCustomPrompt('' === $customPrompt ? null : $customPrompt);
        $project->setStatus(ProjectStatus::Parsing);

        $violations = $this->validator->validate($project);

        if (0 !== \count($violations)) {
            throw new UnprocessableEntityHttpException((string) $violations->get(0)->getMessage());
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        $project->setStoragePath($this->storage->store($file, $project));
        $this->entityManager->flush();

        $this->messageBus->dispatch(new ParseEpubMessage((string) $project->getId()));

        return $project;
    }
}
