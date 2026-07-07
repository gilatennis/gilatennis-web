<?php

namespace App\Enums;

enum TennisLevel: string
{
    case UpperBeginner = 'Upper Beginner';
    case Intermediate = 'Intermediate';

    public function label(): string
    {
        return $this->value;
    }

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
