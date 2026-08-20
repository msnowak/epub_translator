<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Project;
use App\Repository\SegmentRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Wraps the Doctrine collection provider and fills in the progress counters.
 * Counting on read keeps the numbers correct by construction - there is no
 * denormalised counter that can drift from the segments table.
 *
 * @implements ProviderInterface<Project>
 */
final readonly class ProjectCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Project> $collectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        private SegmentRepository $segments,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $projects = $this->collectionProvider->provide($operation, $uriVariables, $context);

        if (!is_iterable($projects)) {
            // Ten provider jest podpiety wylacznie pod GetCollection, wiec
            // opakowany provider zawsze oddaje kolekcje.
            throw new \LogicException('The Doctrine collection provider returned a single item.');
        }

        foreach ($projects as $project) {
            $project->setSegmentCounts($this->segments->countByStatus($project));
        }

        return $projects;
    }
}
