<?php

namespace App\Enums;

enum BracketStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'In Progress',
            self::Completed => 'Completed',
        };
    }
}
