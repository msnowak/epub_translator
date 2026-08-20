<?php

declare(strict_types=1);

namespace App\Message;

final readonly class TranslateNextSegmentMessage implements AsyncMessageInterface
{
    public function __construct(
        public string $projectId,
    ) {
    }
}
