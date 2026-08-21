<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Message\TranslateSegmentMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProcessorInterface<mixed, Segment>
 */
final readonly class RetranslateSegmentProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Segment
    {
        if (!$data instanceof Segment) {
            throw new NotFoundHttpException('Nie znaleziono segmentu.');
        }

        if (SegmentStatus::Processing === $data->getStatus()) {
            // Lancuch juz nad nim pracuje; dwa tlumaczenia bilyby sie o ten
            // sam wiersz.
            throw new ConflictHttpException('Ten akapit jest właśnie tłumaczony.');
        }

        // Budzet prob liczy sie od nowa - inaczej segment, ktory go wyczerpal,
        // dostalby "failed" jeszcze przed pierwszym zapytaniem modelu.
        $this->entityManager->createQueryBuilder()
            ->update(Segment::class, 's')
            ->set('s.attempts', '0')
            ->set('s.errorMessage', 'NULL')
            ->where('s.id = :id')
            ->setParameter('id', $data->getId(), 'uuid')
            ->getQuery()
            ->execute();

        // Encja w tozsamosci Doctrine'a pamieta zuzyty budzet, bo UPDATE
        // powyzej omija ORM. Handler dostalby ja nieswieza i odmowil pracy.
        $this->entityManager->refresh($data);

        $this->messageBus->dispatch(new TranslateSegmentMessage((string) $data->getId()));

        return $data;
    }
}
