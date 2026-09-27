<?php

use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;

// Phase 40: a tela de descanso mostra "Próxima: Série X/Y". O projeto não tem
// runner de testes JS, então o lado do Vue é coberto por testes de contrato
// que leem os componentes do disco (mesma estratégia do ArchitecturalRulesTest).

test('the active session page exposes target_sets, the base of the rest overlay total', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->create();

    $workout = $user->workouts()->create(['name' => 'Treino Peito']);
    $workout->workoutExercises()->create([
        'exercise_id' => Exercise::first()->id,
        'order' => 0,
        'target_sets' => 4,
        'target_reps' => '8-12',
    ]);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('workout-sessions.show', $session))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('WorkoutSessions/Active')
                ->where('exercises.0.target_sets', 4)
        );
});

test('rest overlay renders the set total next to the next set number', function () {
    $overlay = file_get_contents(resource_path('js/Components/WorkoutSessions/RestTimerOverlay.vue'));

    expect($overlay)
        ->toContain('totalSets: {')
        // O total só aparece dentro do parágrafo que já é condicionado a
        // nextSetNumber — quando o próximo passo é outro exercício, nada muda.
        ->toContain('Próxima: Série {{ nextSetNumber }}<template v-if="totalSets">/{{ totalSets }}</template>');
});

test('active page feeds the rest overlay with the same total used by "Série X de Y"', function () {
    $active = file_get_contents(resource_path('js/Pages/WorkoutSessions/Active.vue'));

    // totalSetsForExercise() soma target_sets + séries extras; é a mesma função
    // que gera currentSets, cujo length alimenta o SetWaveProgress.
    expect($active)
        ->toContain('const currentTotalSets = computed(() =>')
        ->toContain('currentExercise.value ? totalSetsForExercise(currentExercise.value) : null')
        ->toContain(':total-sets="currentTotalSets"');
});
