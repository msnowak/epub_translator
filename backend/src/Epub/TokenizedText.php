<?php

declare(strict_types=1);

namespace App\Epub;

final readonly class TokenizedText
{
    /**
     * @param array<string, string> $placeholders token number => opening markup
     */
    public function __construct(
        public string $text,
        public array $placeholders,
    ) {
    }
}
