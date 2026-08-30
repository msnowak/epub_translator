<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Segment;
use App\Preview\SegmentPlaceholderExposer;
use App\Repository\ProjectRepository;
use App\Security\ProjectVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The whole book's segments, for the list of paragraphs that failed. A missing
 * project and somebody else's project answer alike - OwnerExtension would only
 * empty the list, and an empty list reads as "yours, but nothing in it".
 *
 * @implements ProviderInterface<Segment>
 */
final readonly class ProjectSegmentCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Segment> $collectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        private ProjectRepository $projects,
        private Security $security,
        private SegmentPlaceholderExposer $exposer,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $projectId = $uriVariables['projectId'] ?? null;

        $project = match (true) {
            $projectId instanceof Uuid => $this->projects->find($projectId),
            \is_string($projectId) && Uuid::isValid($projectId) => $this->projects->find(Uuid::fromString($projectId)),
            default => null,
        };

        if (null === $project || !$this->security->isGranted(ProjectVoter::VIEW, $project)) {
            throw new NotFoundHttpException($this->translator->trans('project.not_found'));
        }

        $segments = $this->collectionProvider->provide($operation, $uriVariables, $context);

        if (!is_iterable($segments)) {
            // Ten provider jest podpiety wylacznie pod GetCollection.
            throw new \LogicException('The Doctrine collection provider returned a single item.');
        }

        $this->exposer->exposeAll($segments);

        return $segments;
    }
}
