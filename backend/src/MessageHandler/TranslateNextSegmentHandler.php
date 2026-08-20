<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Project;
use App\Entity\ProjectStatus;
use App\Entity\SegmentStatus;
use App\Message\TranslateNextSegmentMessage;
use App\Repository\ProjectRepository;
use App\Repository\SegmentRepository;
use App\Translation\SegmentTranslator;
use App\Translation\TranslationEngineException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * One link of the self-resuming chain: translate one segment, then queue the
 * next link. It never throws. The message carries a project id, so a transport
 * retry would pick a different segment than the one that failed and leave the
 * first stuck in "processing", blocking finalisation forever - every failure is
 * therefore resolved here, not by the transport.
 */
#[AsMessageHandler]
final readonly class TranslateNextSegmentHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private SegmentRepository $segments,
        private SegmentTranslator $translator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(TranslateNextSegmentMessage $message): void
    {
        $project = $this->projects->find(Uuid::fromString($message->projectId));

        if (null === $project || ProjectStatus::Translating !== $project->getStatus()) {
            // Pauza, anulowanie albo skasowany projekt. To jest caly mechanizm
            // zatrzymywania: nikt nie przerywa lancucha, lancuch sam sprawdza.
            return;
        }

        $segment = $this->segments->claimNextPending($project);

        if (null === $segment) {
            $this->finalise($project);

            return;
        }

        try {
            $this->translator->translate($segment, $this->segments->findPreviousTranslated($segment));
        } catch (TranslationEngineException) {
            $segment->setStatus(SegmentStatus::Pending);
            $project->setStatus(ProjectStatus::Paused);
            $project->setErrorMessage('Serwer Ollama jest nieosiągalny. Sprawdź, czy działa, i wznów tłumaczenie.');
            $project->touch();
            $this->entityManager->flush();

            return;
        }

        $project->touch();
        $this->entityManager->flush();

        $this->messageBus->dispatch(new TranslateNextSegmentMessage($message->projectId));
    }

    private function finalise(Project $project): void
    {
        if ($this->segments->hasProcessing($project)) {
            // Inny lancuch jeszcze pracuje - projekt domknie ten, ktory skonczy
            // jako ostatni. Bez tego pierwszy konczacy oglosilby sukces przed czasem.
            return;
        }

        $project->setStatus($this->segments->hasFailed($project)
            ? ProjectStatus::CompletedWithErrors
            : ProjectStatus::Completed);
        $project->setErrorMessage(null);
        $project->touch();
        $this->entityManager->flush();
    }
}
