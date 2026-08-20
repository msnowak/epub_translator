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
}
