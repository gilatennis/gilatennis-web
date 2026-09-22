<?php

namespace App\Enums;

enum TennisMatchEventType: string
{
    case Point = 'point';
    case Server = 'server';

    public function label(): string
    {
        return match ($this) {
            self::Point => 'Point',
            self::Server => 'Server',
        };
    }
}
