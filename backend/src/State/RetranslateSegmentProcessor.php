<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Message\TranslateSegmentMessage;
use App\Preview\SegmentPlaceholderExposer;
use App\Repository\SegmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProcessorInterface<mixed, Segment>
 */
final readonly class RetranslateSegmentProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SegmentRepository $segments,
        private MessageBusInterface $messageBus,
        private SegmentPlaceholderExposer $exposer,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Segment
    {
        if (!$data instanceof Segment) {
            throw new NotFoundHttpException($this->translator->trans('segment.not_found'));
        }

        if (SegmentStatus::Processing === $data->getStatus()) {
            // Lancuch juz nad nim pracuje; dwa tlumaczenia bilyby sie o ten
            // sam wiersz.
            throw new ConflictHttpException($this->translator->trans('segment.already_translating'));
        }

        // Budzet prob liczy sie od nowa - inaczej segment, ktory go wyczerpal,
        // dostalby "failed" jeszcze przed pierwszym zapytaniem modelu.
        $this->segments->resetAttempts($data);

        // Encja w tozsamosci Doctrine'a pamieta zuzyty budzet, bo UPDATE
        // powyzej omija ORM. Handler dostalby ja nieswieza i odmowil pracy.
        $this->entityManager->refresh($data);

        $this->messageBus->dispatch(new TranslateSegmentMessage((string) $data->getId()));

        $this->exposer->expose($data);

        return $data;
    }
}
