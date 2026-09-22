<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Canceled => 'Canceled',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, self>
     */
    private function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Running, self::Canceled],
            self::Running => [self::Pending, self::Completed, self::Canceled],
            self::Completed => [self::Running, self::Pending, self::Canceled],
            self::Canceled => [self::Pending],
        };
    }
}
