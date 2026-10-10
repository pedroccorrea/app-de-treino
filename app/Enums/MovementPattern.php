<?php

namespace App\Enums;

/**
 * Padrão de movimento de um exercício. Junto com o grupo muscular principal
 * (MuscleGroup), é o que define se dois exercícios são "parecidos" para a
 * sugestão de carga inicial de um exercício novo (ver StartingLoadService).
 * É sempre deduzido automaticamente pelo ExerciseMovementPatternService.
 */
enum MovementPattern: string
{
    case HorizontalPush = 'empurrar_horizontal';
    case VerticalPush = 'empurrar_vertical';
    case HorizontalPull = 'puxar_horizontal';
    case VerticalPull = 'puxar_vertical';
    case Squat = 'agachamento';
    case HipHinge = 'dobradica_quadril';
    case ElbowFlexion = 'flexao_cotovelo';
    case ElbowExtension = 'extensao_cotovelo';
    case LateralRaise = 'elevacao_lateral';
    case Calf = 'panturrilha';
    case KneeExtension = 'extensao_joelho';
    case KneeFlexion = 'flexao_joelho';
    case ChestFly = 'crucifixo';
    case Core = 'core';
    case Other = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::HorizontalPush => 'Empurrar horizontal',
            self::VerticalPush => 'Empurrar vertical',
            self::HorizontalPull => 'Puxar horizontal',
            self::VerticalPull => 'Puxar vertical',
            self::Squat => 'Agachamento',
            self::HipHinge => 'Dobradiça de quadril',
            self::ElbowFlexion => 'Flexão de cotovelo',
            self::ElbowExtension => 'Extensão de cotovelo',
            self::LateralRaise => 'Elevação lateral',
            self::Calf => 'Panturrilha',
            self::KneeExtension => 'Extensão de joelho',
            self::KneeFlexion => 'Flexão de joelho',
            self::ChestFly => 'Crucifixo (adução horizontal)',
            self::Core => 'Core / abdômen',
            self::Other => 'Outro',
        };
    }
}
