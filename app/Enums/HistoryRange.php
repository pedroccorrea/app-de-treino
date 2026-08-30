<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum HistoryRange: string
{
    case FourWeeks = '4w';
    case TwelveWeeks = '12w';
    case OneYear = '1y';

    public function label(): string
    {
        return match ($this) {
            self::FourWeeks => 'Últimas 4 semanas',
            self::TwelveWeeks => 'Últimas 12 semanas',
            self::OneYear => 'Último ano',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::FourWeeks => '4S',
            self::TwelveWeeks => '12S',
            self::OneYear => '1A',
        };
    }

    public function startDate(): Carbon
    {
        return match ($this) {
            self::FourWeeks => now()->subWeeks(4),
            self::TwelveWeeks => now()->subWeeks(12),
            self::OneYear => now()->subYear(),
        };
    }

    /**
     * Parses the `range` query string value, tolerating a missing/unknown
     * value by falling back to the 4-week default instead of throwing.
     */
    public static function fromQuery(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::FourWeeks;
    }
}
