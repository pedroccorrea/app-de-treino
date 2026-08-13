<?php

namespace Database\Seeders;

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    /**
     * Popula o catálogo global de exercícios clássicos de musculação.
     */
    public function run(): void
    {
        $exercises = [
            ['name' => 'Supino Reto com Barra', 'primary' => MuscleGroup::Chest, 'secondary' => [MuscleGroup::Triceps, MuscleGroup::Shoulders]],
            ['name' => 'Supino Inclinado com Halteres', 'primary' => MuscleGroup::Chest, 'secondary' => [MuscleGroup::Triceps, MuscleGroup::Shoulders]],
            ['name' => 'Crucifixo Reto', 'primary' => MuscleGroup::Chest, 'secondary' => [MuscleGroup::Shoulders]],
            ['name' => 'Flexão de Braços', 'primary' => MuscleGroup::Chest, 'secondary' => [MuscleGroup::Triceps, MuscleGroup::Shoulders]],
            ['name' => 'Remada Curvada com Barra', 'primary' => MuscleGroup::Back, 'secondary' => [MuscleGroup::Biceps, MuscleGroup::Traps]],
            ['name' => 'Puxada Frontal na Polia', 'primary' => MuscleGroup::Back, 'secondary' => [MuscleGroup::Biceps]],
            ['name' => 'Remada Cavalinho (T-Bar)', 'primary' => MuscleGroup::Back, 'secondary' => [MuscleGroup::Biceps, MuscleGroup::Traps]],
            ['name' => 'Pulldown na Polia Alta', 'primary' => MuscleGroup::Back, 'secondary' => [MuscleGroup::Biceps]],
            ['name' => 'Levantamento Terra', 'primary' => MuscleGroup::Back, 'secondary' => [MuscleGroup::Glutes, MuscleGroup::Hamstrings, MuscleGroup::Quads, MuscleGroup::Traps]],
            ['name' => 'Agachamento Livre', 'primary' => MuscleGroup::Quads, 'secondary' => [MuscleGroup::Glutes, MuscleGroup::Hamstrings]],
            ['name' => 'Leg Press 45°', 'primary' => MuscleGroup::Quads, 'secondary' => [MuscleGroup::Glutes]],
            ['name' => 'Cadeira Extensora', 'primary' => MuscleGroup::Quads, 'secondary' => []],
            ['name' => 'Mesa Flexora', 'primary' => MuscleGroup::Hamstrings, 'secondary' => [MuscleGroup::Glutes]],
            ['name' => 'Stiff com Barra', 'primary' => MuscleGroup::Hamstrings, 'secondary' => [MuscleGroup::Glutes, MuscleGroup::Back]],
            ['name' => 'Elevação Pélvica', 'primary' => MuscleGroup::Glutes, 'secondary' => [MuscleGroup::Hamstrings]],
            ['name' => 'Desenvolvimento com Halteres', 'primary' => MuscleGroup::Shoulders, 'secondary' => [MuscleGroup::Triceps]],
            ['name' => 'Elevação Lateral', 'primary' => MuscleGroup::Shoulders, 'secondary' => []],
            ['name' => 'Elevação Frontal', 'primary' => MuscleGroup::Shoulders, 'secondary' => []],
            ['name' => 'Rosca Direta com Barra', 'primary' => MuscleGroup::Biceps, 'secondary' => [MuscleGroup::Forearms]],
            ['name' => 'Rosca Martelo', 'primary' => MuscleGroup::Biceps, 'secondary' => [MuscleGroup::Forearms]],
            ['name' => 'Tríceps Pulley na Polia', 'primary' => MuscleGroup::Triceps, 'secondary' => []],
            ['name' => 'Tríceps Testa com Barra', 'primary' => MuscleGroup::Triceps, 'secondary' => []],
            ['name' => 'Mergulho entre Bancos', 'primary' => MuscleGroup::Triceps, 'secondary' => [MuscleGroup::Chest, MuscleGroup::Shoulders]],
            ['name' => 'Panturrilha em Pé', 'primary' => MuscleGroup::Calves, 'secondary' => []],
            ['name' => 'Abdominal Crunch', 'primary' => MuscleGroup::Abs, 'secondary' => []],
            ['name' => 'Prancha Isométrica', 'primary' => MuscleGroup::Abs, 'secondary' => [MuscleGroup::Shoulders]],
        ];

        foreach ($exercises as $exercise) {
            Exercise::query()->updateOrCreate(
                [
                    'user_id' => null,
                    'name' => $exercise['name'],
                ],
                [
                    'primary_muscle_group' => $exercise['primary'],
                    'secondary_muscle_groups' => $exercise['secondary'],
                ],
            );
        }
    }
}
