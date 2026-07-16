<?php

namespace App\Enums;

enum PlayerAssessmentStatus: string
{
    case Unreviewed = 'unreviewed';
    case DoesNotMeetLevel = 'does_not_meet_level';
    case Cleared = 'cleared';

    public function label(): string
    {
        return match ($this) {
            self::Unreviewed => 'Unreviewed',
            self::DoesNotMeetLevel => 'Does not meet',
            self::Cleared => 'Cleared',
        };
    }

    public function color(): array
    {
        return match ($this) {
            self::Unreviewed => [
                'border' => 'border-neutral-300',
                'headerBg' => '',
                'wrapBg' => '',
                'selectedBg' => 'bg-neutral-900',
                'selectedText' => 'text-white',
                'unselectedText' => 'text-neutral-500',
                'pillBg' => 'bg-neutral-100',
                'pillText' => 'text-neutral-500',
                'pillBorder' => 'border-neutral-200',
            ],
            self::DoesNotMeetLevel => [
                'border' => 'border-amber-300',
                'headerBg' => 'bg-amber-50/40',
                'wrapBg' => 'bg-amber-50',
                'selectedBg' => 'bg-amber-500',
                'selectedText' => 'text-white',
                'unselectedText' => 'text-amber-700',
                'pillBg' => 'bg-amber-100',
                'pillText' => 'text-amber-800',
                'pillBorder' => 'border-amber-300',
            ],
            self::Cleared => [
                'border' => 'border-emerald-300',
                'headerBg' => '',
                'wrapBg' => 'bg-emerald-50',
                'selectedBg' => 'bg-emerald-600',
                'selectedText' => 'text-white',
                'unselectedText' => 'text-emerald-700',
                'pillBg' => 'bg-emerald-50',
                'pillText' => 'text-emerald-700',
                'pillBorder' => 'border-emerald-200',
            ],
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
