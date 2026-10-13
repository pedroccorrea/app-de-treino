<?php

use App\Models\User;
use App\Models\Workout;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Simula a resposta da IA (nunca chama a API real) com os exercícios dados.
 *
 * @param  array<int, array<string, mixed>>  $exercises
 */
function fakeScanWithExercises(array $exercises): void
{
    $text = json_encode([
        'workout_name' => 'Treino Combinado',
        'exercises' => $exercises,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => $text]]]],
            ],
        ], 200),
    ]);
}

/**
 * Escaneia a ficha simulada e devolve [nome do exercício => grupo gravado],
 * na ordem da ficha.
 *
 * @return array<string, ?int>
 */
function scanAndReadGroups(User $user): array
{
    test()->actingAs($user)
        ->post(route('workouts.scan'), [
            'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
        ])
        ->assertSessionHas('success');

    /** @var Workout $workout */
    $workout = $user->workouts()->firstOrFail();

    return $workout->workoutExercises()
        ->with('exercise')
        ->orderBy('order')
        ->get()
        ->mapWithKeys(fn ($row) => [
            $row->exercise->name => $row->superset_group !== null ? (int) $row->superset_group : null,
        ])
        ->all();
}

function scannedExercise(string $name, mixed $group, string $muscleGroup = 'biceps'): array
{
    return [
        'name' => $name,
        'target_sets' => 3,
        'target_reps' => '12',
        'target_reps_min' => 12,
        'target_reps_max' => 12,
        'muscle_group' => $muscleGroup,
        'superset_group' => $group,
    ];
}

test('scan de bi-set ("A + B") cria os dois exercícios vizinhos no mesmo grupo', function () {
    fakeScanWithExercises([
        scannedExercise('Supino Reto com Barra', null, 'chest'),
        scannedExercise('Rosca Direta', 1),
        scannedExercise('Tríceps Corda na Polia', 1, 'triceps'),
        scannedExercise('Abdominal Supra', null, 'abs'),
    ]);

    $user = User::factory()->create();

    expect(scanAndReadGroups($user))->toBe([
        'Supino Reto com Barra' => null,
        'Rosca Direta' => 1,
        'Tríceps Corda na Polia' => 1,
        'Abdominal Supra' => null,
    ]);
});

test('scan de tri-set cria os três exercícios no mesmo grupo', function () {
    fakeScanWithExercises([
        scannedExercise('Elevação Lateral com Halteres', 2, 'shoulders'),
        scannedExercise('Elevação Frontal com Halteres', 2, 'shoulders'),
        scannedExercise('Crucifixo Inverso na Máquina', 2, 'shoulders'),
        scannedExercise('Rosca Direta', null),
    ]);

    $user = User::factory()->create();

    // O número que a IA usou não importa: os grupos são renumerados 1, 2...
    expect(scanAndReadGroups($user))->toBe([
        'Elevação Lateral com Halteres' => 1,
        'Elevação Frontal com Halteres' => 1,
        'Crucifixo Inverso na Máquina' => 1,
        'Rosca Direta' => null,
    ]);
});

test('scan com grupo de 1 exercício grava o exercício sem grupo', function () {
    fakeScanWithExercises([
        scannedExercise('Rosca Direta', 1),
        scannedExercise('Tríceps Corda na Polia', null, 'triceps'),
        scannedExercise('Rosca Martelo', 2),
    ]);

    $user = User::factory()->create();

    expect(scanAndReadGroups($user))->toBe([
        'Rosca Direta' => null,
        'Tríceps Corda na Polia' => null,
        'Rosca Martelo' => null,
    ]);
});

test('scan com grupo em posições não vizinhas não junta quem não é vizinho', function () {
    fakeScanWithExercises([
        scannedExercise('Rosca Direta', 1),
        scannedExercise('Supino Reto com Barra', null, 'chest'),
        scannedExercise('Tríceps Corda na Polia', 1, 'triceps'),
        scannedExercise('Rosca Martelo', 3),
        scannedExercise('Tríceps Testa', 3, 'triceps'),
        scannedExercise('Abdominal Supra', null, 'abs'),
        scannedExercise('Rosca Concentrada', 3),
    ]);

    $user = User::factory()->create();

    expect(scanAndReadGroups($user))->toBe([
        'Rosca Direta' => null,
        'Supino Reto com Barra' => null,
        'Tríceps Corda na Polia' => null,
        'Rosca Martelo' => 1,
        'Tríceps Testa' => 1,
        'Abdominal Supra' => null,
        'Rosca Concentrada' => null,
    ]);
});

test('scan com grupo inválido grava sem grupo e não quebra a importação', function (mixed $invalidGroup) {
    fakeScanWithExercises([
        scannedExercise('Rosca Direta', $invalidGroup),
        scannedExercise('Tríceps Corda na Polia', $invalidGroup, 'triceps'),
        scannedExercise('Rosca Martelo', null),
    ]);

    $user = User::factory()->create();

    expect(scanAndReadGroups($user))->toBe([
        'Rosca Direta' => null,
        'Tríceps Corda na Polia' => null,
        'Rosca Martelo' => null,
    ]);
})->with([
    'texto' => ['bi-set'],
    'negativo' => [-1],
    'zero' => [0],
    'fração' => [1.5],
    'booleano' => [true],
    'lista' => [[1]],
]);

test('scan aceita grupo enviado como texto numérico', function () {
    fakeScanWithExercises([
        scannedExercise('Rosca Direta', '4'),
        scannedExercise('Tríceps Corda na Polia', ' 4 ', 'triceps'),
    ]);

    $user = User::factory()->create();

    expect(scanAndReadGroups($user))->toBe([
        'Rosca Direta' => 1,
        'Tríceps Corda na Polia' => 1,
    ]);
});

test('scan de ficha sem o campo de grupo continua criando exercícios sozinhos', function () {
    $exercises = [
        scannedExercise('Rosca Direta', null),
        scannedExercise('Tríceps Corda na Polia', null, 'triceps'),
    ];

    fakeScanWithExercises(array_map(function (array $exercise) {
        unset($exercise['superset_group']);

        return $exercise;
    }, $exercises));

    $user = User::factory()->create();

    expect(scanAndReadGroups($user))->toBe([
        'Rosca Direta' => null,
        'Tríceps Corda na Polia' => null,
    ]);
});

test('o prompt do scan orienta a IA a reconhecer exercícios combinados', function () {
    fakeScanWithExercises([scannedExercise('Rosca Direta', null)]);

    scanAndReadGroups(User::factory()->create());

    Http::assertSent(function (Request $request) {
        $prompt = collect(data_get($request->data(), 'contents.0.parts', []))
            ->pluck('text')
            ->filter()
            ->implode("\n");

        foreach (['bi-set', 'tri-set', 'conjugado', 'super-série', '"+"', 'chaves', '"superset_group"'] as $term) {
            if (! str_contains($prompt, $term)) {
                return false;
            }
        }

        return true;
    });
});
