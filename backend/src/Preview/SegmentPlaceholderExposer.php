<?php

declare(strict_types=1);

namespace App\Preview;

use App\Entity\Segment;

/**
 * Fills the read-time preview markup on segments on their way out of the API.
 * Every read path goes through here so the resource has one shape, whether it
 * came from a collection, from a PATCH or from a retranslate.
 */
final readonly class SegmentPlaceholderExposer
{
    public function __construct(
        private PlaceholderSanitizer $sanitizer,
    ) {
    }

    public function expose(Segment $segment): void
    {
        $segment->setPreviewPlaceholders($this->sanitizer->sanitize(
            $segment->getPlaceholders(),
            (string) $segment->getProject()->getId(),
            $segment->getChapter()->getHref(),
        ));
    }

    /** @param iterable<Segment> $segments */
    public function exposeAll(iterable $segments): void
    {
        foreach ($segments as $segment) {
            $this->expose($segment);
        }
    }
}
