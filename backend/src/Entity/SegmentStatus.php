<?php

declare(strict_types=1);

namespace App\Entity;

enum SegmentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Translated = 'translated';
    case Failed = 'failed';
    /** Reczna poprawka uzytkownika - nigdy nie nadpisywana automatycznie. */
    case Edited = 'edited';
}
