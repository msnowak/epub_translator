<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\SegmentStatus;
use App\Message\TranslateSegmentMessage;
use App\Repository\SegmentRepository;
use App\Translation\SegmentTranslator;
use App\Translation\TranslationEngineException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Retranslates one segment, independent of the project chain. Like the chain
 * handler it never throws: there is no queue behind it to stall, and a single
 * paragraph failing is not a reason to retry the whole message.
 */
#[AsMessageHandler]
final readonly class TranslateSegmentHandler
{
    public function __construct(
        private SegmentRepository $segments,
        private SegmentTranslator $translator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(TranslateSegmentMessage $message): void
    {
        $segment = $this->segments->find(Uuid::fromString($message->segmentId));

        if (null === $segment) {
            // Projekt skasowany, zanim worker doszedl do wiadomosci.
            return;
        }

        try {
            $this->translator->translate($segment, $this->segments->findPreviousTranslated($segment));
        } catch (TranslationEngineException) {
            // Bez lancucha nie ma czego pauzowac - segment dostaje komunikat
            // i czeka na kolejne klikniecie uzytkownika.
            $segment->setStatus(SegmentStatus::Failed);
            $segment->setErrorMessage('Serwer Ollama jest nieosiągalny. Sprawdź, czy działa, i spróbuj ponownie.');
        }

        $this->entityManager->flush();
    }
}
