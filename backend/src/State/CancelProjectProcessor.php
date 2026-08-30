<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Project;
use App\Entity\ProjectStatus;
use App\Repository\SegmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProcessorInterface<mixed, Project>
 */
final readonly class CancelProjectProcessor implements ProcessorInterface
{
    public function __construct(
        private SegmentRepository $segments,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
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
            throw new NotFoundHttpException($this->translator->trans('project.not_found'));
        }

        if (!$data->getStatus()->canCancel()) {
            throw new ConflictHttpException($this->translator->trans('project.cannot_cancel'));
        }

        // Zajety segment wraca do puli, zeby ponowny start nie omijal go w nieskonczonosc.
        $this->segments->resetProcessingToPending($data);

        $data->setStatus(ProjectStatus::Cancelled);
        $data->touch();
        $this->entityManager->flush();

        return $data;
    }
}
