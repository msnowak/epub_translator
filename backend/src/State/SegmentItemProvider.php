<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Segment;
use App\Preview\SegmentPlaceholderExposer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * One segment, with the same read shape as the collections. The editor polls
 * this while a paragraph it asked to retranslate is still being worked on.
 *
 * @implements ProviderInterface<Segment>
 */
final readonly class SegmentItemProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Segment> $itemProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        private SegmentPlaceholderExposer $exposer,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Segment
    {
        $segment = $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$segment instanceof Segment) {
            return null;
        }

        $this->exposer->expose($segment);

        return $segment;
    }
}
