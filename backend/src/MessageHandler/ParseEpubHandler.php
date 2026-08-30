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
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
final readonly class ParseEpubHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectStorage $storage,
        private ProjectStructureWriter $writer,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
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
        } catch (InvalidEpubException) {
            $project->setStatus(ProjectStatus::Failed);
            // Trafia do interfejsu. Techniczny powod z wyjatku nie niesie
            // uzytkownikowi zadnej uzytecznej informacji - plik i tak trzeba
            // wgrac jeszcze raz. Ten worker dziala w oddzielnym procesie CLI,
            // bez zadania HTTP w tle, wiec trans() rozwiazuje sie do jezyka
            // domyslnego (pl) w produkcji - tak samo jak przed tym zadaniem.
            // Wiadomosc leci synchronicznie w testach (config/packages/test/
            // messenger.yaml), wiec tam dziedziczy jezyk zadania, ktore ja wyslalo.
            $project->setErrorMessage($this->translator->trans('upload.structure_unreadable'));
        }

        $project->touch();
        $this->entityManager->flush();
    }
}
