<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use App\Entity\ProjectStatus;
use App\Message\TranslateNextSegmentMessage;
use App\Repository\SegmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<mixed, Project>
 */
final readonly class RetryFailedSegmentsProcessor implements ProcessorInterface
{
    public function __construct(
        private SegmentRepository $segments,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Project
    {
        if (!$data instanceof Project) {
            // Projekt nie istnieje albo nalezy do kogos innego - OwnerExtension
            // odfiltrowal go z zapytania. Jedno i drugie ma wygladac tak samo,
            // zeby istnienie cudzego zasobu nie wyciekalo.
            throw new NotFoundHttpException('Nie znaleziono projektu.');
        }

        if (!$data->getStatus()->canRetryFailed()) {
            throw new ConflictHttpException('Ponowienie jest możliwe dopiero po zakończeniu tłumaczenia.');
        }

        // Zerowanie budzetu prob jest tu istotne: bez tego segment, ktory go
        // wyczerpal, dostalby "failed" jeszcze przed pierwszym zapytaniem modelu.
        $released = $this->segments->resetFailedToPending($data);
        $this->segments->resetProcessingToPending($data);

        if (0 === $released) {
            throw new ConflictHttpException('Ten projekt nie ma segmentów do ponowienia.');
        }

        $data->setStatus(ProjectStatus::Translating);
        $data->setErrorMessage(null);
        $data->touch();
        $this->entityManager->flush();

        $this->messageBus->dispatch(new TranslateNextSegmentMessage((string) $data->getId()));

        return $data;
    }
}
