<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Chapter;
use App\Repository\ChapterRepository;
use App\Repository\ProjectRepository;
use App\Security\ProjectVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Chapters hang off a project, so the project decides who may see them. A
 * project that is missing and one that belongs to somebody else answer alike:
 * the existence of another reader's book must not leak.
 *
 * @implements ProviderInterface<Chapter>
 */
final readonly class ChapterCollectionProvider implements ProviderInterface
{
    public function __construct(
        private ProjectRepository $projects,
        private ChapterRepository $chapters,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return list<Chapter>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $projectId = $uriVariables['projectId'] ?? null;

        // Link z App\Entity\Chapter juz konwertuje ten identyfikator na Uuid,
        // ale surowy string zostawiamy jako fallback, gdyby ta konwersja
        // kiedys przestala dzialac dla tej trasy.
        $project = match (true) {
            $projectId instanceof Uuid => $this->projects->find($projectId),
            \is_string($projectId) && Uuid::isValid($projectId) => $this->projects->find(Uuid::fromString($projectId)),
            default => null,
        };

        if (null === $project || !$this->security->isGranted(ProjectVoter::VIEW, $project)) {
            throw new NotFoundHttpException($this->translator->trans('project.not_found'));
        }

        $chapters = $this->chapters->findForProjectInSpineOrder($project);
        $counts = $this->chapters->countByStatusForProject($project);

        foreach ($chapters as $chapter) {
            $chapter->setSegmentCounts($counts[(string) $chapter->getId()] ?? []);
        }

        return $chapters;
    }
}
