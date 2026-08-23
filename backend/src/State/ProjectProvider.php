<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Project;
use App\Repository\SegmentRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Wraps the Doctrine providers and fills in the progress counters. Counting on
 * read keeps the numbers correct by construction - there is no denormalised
 * counter that can drift from the segments table.
 *
 * One provider serves both read operations on purpose: countByStatus() works
 * per project either way, and two classes doing the same counting is how the
 * item operation drifted into reporting zeros in the first place.
 *
 * @implements ProviderInterface<Project>
 */
final readonly class ProjectProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Project> $collectionProvider
     * @param ProviderInterface<Project> $itemProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        private SegmentRepository $segments,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable|Project|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $projects = $this->collectionProvider->provide($operation, $uriVariables, $context);

            if (!is_iterable($projects)) {
                throw new \LogicException('The Doctrine collection provider returned a single item.');
            }

            foreach ($projects as $project) {
                $project->setSegmentCounts($this->segments->countByStatus($project));
            }

            return $projects;
        }

        $project = $this->itemProvider->provide($operation, $uriVariables, $context);

        // OwnerExtension odfiltrowuje cudzy projekt, wiec provider oddaje tu
        // null i API Platform odpowiada 404 - obcy projekt ma byc nie do
        // odroznienia od nieistniejacego.
        if (!$project instanceof Project) {
            return null;
        }

        $project->setSegmentCounts($this->segments->countByStatus($project));

        return $project;
    }
}
