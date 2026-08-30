<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Blad zapisany przez workera. Worker nie ma zadania HTTP, wiec nie zna jezyka
 * uzytkownika - zapisuje kod, a zdanie sklada z niego frontend przy odczycie.
 */
enum WorkerError: string
{
    case EpubUnreadable = 'epub_unreadable';
    case OllamaUnreachableProject = 'ollama_unreachable_project';
    case OllamaUnreachableSegment = 'ollama_unreachable_segment';
    case ModelInvalidTranslation = 'model_invalid_translation';
}
