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

    /**
     * Reguly przejsc trzymamy przy statusie, a nie w procesorach: piec akcji
     * pytajacych o to samo w pieciu miejscach rozjechaloby sie przy szostej.
     */
    public function canStart(): bool
    {
        return \in_array($this, [self::Ready, self::Cancelled], true);
    }

    public function canPause(): bool
    {
        return self::Translating === $this;
    }

    public function canResume(): bool
    {
        return self::Paused === $this;
    }

    public function canCancel(): bool
    {
        return \in_array($this, [self::Translating, self::Paused], true);
    }

    public function canRetryFailed(): bool
    {
        return \in_array($this, [self::CompletedWithErrors, self::Paused, self::Cancelled, self::Completed], true);
    }
}
