<?php

declare(strict_types=1);

namespace App\Translation;

/**
 * A translation is unusable, either because the engine answered badly or
 * because a human's edit broke data integrity. Retryable on the engine path:
 * the same prompt asked again often produces a good answer, because the fault
 * is the model's sampling, not the request.
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
        return new self('The translation is empty.', TranslationRejectionReason::Empty);
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
