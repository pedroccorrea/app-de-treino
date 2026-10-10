<?php

namespace App\Enums;

/**
 * Which product feature triggered an AI call. Only used to label the usage
 * log written by AiManager (so token spend can be attributed per feature);
 * it never changes how the request is routed — that is AiTask's job.
 */
enum AiFeature: string
{
    case WorkoutScan = 'workout_scan';
    case ProgressiveOverload = 'progressive_overload';
    case StartingLoad = 'starting_load';
    case EquipmentClassification = 'equipment_classification';
    case MovementPatternClassification = 'movement_pattern_classification';
    case Diagnostic = 'diagnostic';

    public function label(): string
    {
        return match ($this) {
            self::WorkoutScan => 'Scan da ficha',
            self::ProgressiveOverload => 'Sugestões de progressão',
            self::StartingLoad => 'Carga inicial',
            self::EquipmentClassification => 'Classificação de equipamento',
            self::MovementPatternClassification => 'Classificação de movimento',
            self::Diagnostic => 'Diagnóstico (ai:doctor)',
        };
    }
}
