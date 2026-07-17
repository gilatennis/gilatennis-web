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
            self::Running => [self::Completed, self::Canceled],
            self::Completed, self::Canceled => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
