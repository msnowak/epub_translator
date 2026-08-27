<?php

declare(strict_types=1);

namespace App\Translation;

/**
 * Why a TranslationRejectedException was thrown. Lets a catch site react to
 * the actual cause instead of guessing from the message text.
 */
enum TranslationRejectionReason
{
    /** Translation is blank after trimming. */
    case Empty;

    /** A token was dropped, invented, changed kind, or nested wrong. */
    case TokenIntegrity;

    /** The engine handed the source text back unchanged (see TranslationValidator::assertNotEchoed()). */
    case Echo;
}
