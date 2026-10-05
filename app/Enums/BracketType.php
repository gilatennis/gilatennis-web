<?php

namespace App\Enums;

enum BracketType: string
{
    case SingleElimination = 'single_elimination';
    case GroupStage = 'group_stage';

    public function label(): string
    {
        return match ($this) {
            self::SingleElimination => 'Single Elimination',
            self::GroupStage => 'Group Stage',
        };
    }
}
