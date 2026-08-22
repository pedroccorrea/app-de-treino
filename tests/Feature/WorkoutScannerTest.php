<?php

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

function fakeGeminiResponse(string $text): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);
}

test('guests are redirected to login when scanning a workout sheet', function () {
    $this->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ])->assertRedirect(route('login'));
});

test('scanning a workout sheet requires a valid image file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('workouts.scan'), [
            'image' => UploadedFile::fake()->create('ficha.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('image');
});

test('scanning a workout sheet requires the image field', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('workouts.scan'), [])
        ->assertSessionHasErrors('image');
});

test('authenticated user can scan a paper workout sheet and import it without duplicating existing exercises', function () {
    $fixture = file_get_contents(base_path('tests/Fixtures/ocr-workout-sheet.json'));

    // The AI is faked to prove the service sanitizes markdown code fences
    // around the JSON payload, as required by the AI integration rules.
    fakeGeminiResponse("```json\n{$fixture}\n```");

    $user = User::factory()->create();

    $existingExercise = Exercise::query()->create([
        'user_id' => null,
        'name' => 'Supino Reto Barra',
        'primary_muscle_group' => MuscleGroup::Chest,
        'secondary_muscle_groups' => [],
    ]);

    $response = $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
    ]);

    $workout = $user->workouts()->firstOrFail();

    $response->assertRedirect(route('workouts.show', $workout));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('workouts', [
        'id' => $workout->id,
        'user_id' => $user->id,
        'name' => 'Treino A - Peito e Tríceps',
    ]);

    expect($workout->exercises()->count())->toBe(4);

    // The exercise that already existed in the catalog must be reused, not duplicated.
    $this->assertDatabaseHas('workout_exercises', [
        'workout_id' => $workout->id,
        'exercise_id' => $existingExercise->id,
        'target_sets' => 4,
        'target_reps' => '10',
    ]);
    expect(Exercise::where('name', 'Supino Reto Barra')->count())->toBe(1);

    // The other three exercises have no match in the catalog and are created for the user.
    $newExercise = Exercise::where('name', 'Crucifixo Inclinado com Halteres')->first();
    expect($newExercise)->not->toBeNull();
    expect($newExercise->user_id)->toBe($user->id);
    expect($newExercise->primary_muscle_group)->toBe(MuscleGroup::Chest);

    $tricepsExercise = Exercise::where('name', 'Tríceps Corda no Pulley')->first();
    expect($tricepsExercise)->not->toBeNull();
    expect($tricepsExercise->primary_muscle_group)->toBe(MuscleGroup::Triceps);

    $this->assertDatabaseHas('workout_exercises', [
        'workout_id' => $workout->id,
        'exercise_id' => $tricepsExercise->id,
        'target_sets' => 4,
        'target_reps' => '12',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
});

test('scanning fails gracefully with a flash error when the AI response is not valid json', function () {
    fakeGeminiResponse('isso não é um JSON válido.');

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('workouts.scan'), [
            'image' => UploadedFile::fake()->create('ficha.jpg', 100, 'image/jpeg'),
        ]);

    $response->assertRedirect(route('workouts.index'));
    $response->assertSessionHas('error');

    expect($user->workouts()->count())->toBe(0);
});

test('scanning a workout sheet rejects images larger than 10MB', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('workouts.scan'), [
            'image' => UploadedFile::fake()->create('ficha.jpg', 10241, 'image/jpeg'),
        ])
        ->assertSessionHasErrors('image');
});
