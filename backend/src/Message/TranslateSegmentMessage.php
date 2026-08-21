<?php

declare(strict_types=1);

namespace App\Message;

final readonly class TranslateSegmentMessage implements AsyncMessageInterface
{
    public function __construct(
        public string $segmentId,
    ) {
    }
}
