<?php

declare(strict_types=1);

namespace App\Translation;

/**
 * The engine could not be asked at all - the server is down, refusing requests
 * or answering with something that is not a translation. Distinct from
 * TranslationRejectedException, which means the engine answered and the answer
 * was unusable: this one pauses the project, that one fails one segment.
 */
class TranslationEngineException extends \RuntimeException
{
}
