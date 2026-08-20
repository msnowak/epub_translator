<?php

declare(strict_types=1);

namespace App\Entity;

enum ProjectStatus: string
{
    case Parsing = 'parsing';
    case Ready = 'ready';
    case Translating = 'translating';
    case Paused = 'paused';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
