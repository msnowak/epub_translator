<?php

declare(strict_types=1);

namespace App\Translation;

/**
 * The engine answered, but the answer is unusable. Retryable: the same prompt
 * asked again often produces a good answer, because the fault is the model's
 * sampling, not the request.
 */
final class TranslationRejectedException extends \RuntimeException
{
    private function __construct(
        string $message,
        public readonly TranslationRejectionReason $reason,
    ) {
        parent::__construct($message);
    }

    public static function emptyTranslation(): self
    {
        return new self('The engine returned an empty translation.', TranslationRejectionReason::Empty);
    }

    public static function tokenIntegrity(string $message): self
    {
        return new self($message, TranslationRejectionReason::TokenIntegrity);
    }

    public static function echoedSource(): self
    {
        return new self('The engine echoed the source text back unchanged.', TranslationRejectionReason::Echo);
    }
}
