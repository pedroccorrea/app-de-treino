<?php

namespace App\Enums;

/**
 * Como o número de carga de uma série deve ser lido: o total levantado, o
 * peso de cada lado da barra/máquina ou o peso de cada halter/kettlebell.
 */
enum LoadMode: string
{
    case Total = 'total';
    case PerSide = 'por_lado';
    case PerDumbbell = 'por_halter';

    public function label(): string
    {
        return match ($this) {
            self::Total => 'Carga total',
            self::PerSide => 'Carga por lado',
            self::PerDumbbell => 'Peso de cada halter/kettlebell',
        };
    }
}
