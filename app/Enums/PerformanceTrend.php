<?php

namespace App\Enums;

/**
 * Tendência do desempenho recente de um exercício, calculada em PHP pelo
 * RecentPerformanceService (nunca pela IA).
 */
enum PerformanceTrend: string
{
    case Rising = 'subindo';
    case Stable = 'estavel';
    case Falling = 'caindo';

    public function label(): string
    {
        return match ($this) {
            self::Rising => 'Subindo',
            self::Stable => 'Estável',
            self::Falling => 'Caindo',
        };
    }
}
