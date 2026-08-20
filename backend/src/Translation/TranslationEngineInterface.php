<?php

declare(strict_types=1);

namespace App\Translation;

interface TranslationEngineInterface
{
    /**
     * @throws TranslationEngineException when the engine cannot be reached or answers with no usable content
     */
    public function translate(TranslationRequest $request): string;
}
