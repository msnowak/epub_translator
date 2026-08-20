<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ProjectStatus;
use App\Epub\InvalidEpubException;
use App\Epub\ProjectStructureWriter;
use App\Message\ParseEpubMessage;
use App\Repository\ProjectRepository;
use App\Storage\ProjectStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ParseEpubHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectStorage $storage,
        private ProjectStructureWriter $writer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ParseEpubMessage $message): void
    {
        $project = $this->projects->find(Uuid::fromString($message->projectId));

        if (null === $project) {
            // Projekt skasowany, zanim worker doszedl do wiadomosci - nic do zrobienia.
            return;
        }

        try {
            $this->writer->write($project, $this->storage->path($project));
            $project->setStatus(ProjectStatus::Ready);
            $project->setErrorMessage(null);
        } catch (InvalidEpubException $exception) {
            $project->setStatus(ProjectStatus::Failed);
            $project->setErrorMessage($exception->getMessage());
        }

        $project->touch();
        $this->entityManager->flush();
    }
}
