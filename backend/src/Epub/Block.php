<?php

declare(strict_types=1);

namespace App\Epub;

final readonly class Block
{
    public function __construct(
        public int $nodeIndex,
        public string $innerHtml,
    ) {
    }
}
