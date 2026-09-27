<?php

namespace App\Enums;

/**
 * Tipo de equipamento de um exercício. Define quais cargas existem de verdade
 * para ele (ver ValidLoadService) e, por consequência, como a carga é
 * registrada (ver loadMode()). É sempre deduzido automaticamente pelo
 * ExerciseEquipmentService — o usuário nunca escolhe nem corrige.
 */
enum Equipment: string
{
    case Dumbbells = 'halteres';
    case BarbellPlates = 'barra_anilhas';
    case PlateLoadedMachine = 'maquina_anilhas';
    case SelectorizedMachine = 'maquina_placas';
    case Kettlebell = 'kettlebell';
    case Bodyweight = 'peso_corporal';
    case Other = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Dumbbells => 'Halteres',
            self::BarbellPlates => 'Barra com anilhas',
            self::PlateLoadedMachine => 'Máquina com anilhas',
            self::SelectorizedMachine => 'Máquina de placas, polia ou cabo',
            self::Kettlebell => 'Kettlebell',
            self::Bodyweight => 'Peso corporal',
            self::Other => 'Outro',
        };
    }

    /**
     * Forma de registro da carga deduzida do equipamento. Barra e máquina
     * com anilhas seguem o que o app sempre registrou: um único número de
     * carga por série, tratado como o total.
     */
    public function loadMode(): LoadMode
    {
        return match ($this) {
            self::Dumbbells, self::Kettlebell => LoadMode::PerDumbbell,
            default => LoadMode::Total,
        };
    }
}
