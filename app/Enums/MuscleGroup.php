<?php

namespace App\Enums;

enum MuscleGroup: string
{
    case Chest = 'chest';
    case Back = 'back';
    case Shoulders = 'shoulders';
    case Biceps = 'biceps';
    case Triceps = 'triceps';
    case Quads = 'quads';
    case Hamstrings = 'hamstrings';
    case Glutes = 'glutes';
    case Calves = 'calves';
    case Abs = 'abs';
    case Forearms = 'forearms';
    case Traps = 'traps';

    public function label(): string
    {
        return match ($this) {
            self::Chest => 'Peito',
            self::Back => 'Costas',
            self::Shoulders => 'Ombros',
            self::Biceps => 'Bíceps',
            self::Triceps => 'Tríceps',
            self::Quads => 'Quadríceps',
            self::Hamstrings => 'Posterior de Coxa',
            self::Glutes => 'Glúteos',
            self::Calves => 'Panturrilhas',
            self::Abs => 'Abdômen',
            self::Forearms => 'Antebraços',
            self::Traps => 'Trapézio',
        };
    }
}
