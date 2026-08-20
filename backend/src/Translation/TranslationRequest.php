<?php

declare(strict_types=1);

namespace App\Translation;

final readonly class TranslationRequest
{
    public function __construct(
        public string $model,
        public string $systemPrompt,
        public string $userPrompt,
    ) {
    }
}
