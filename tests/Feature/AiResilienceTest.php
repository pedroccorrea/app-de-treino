<?php

use App\Models\User;
use App\Models\Workout;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Chaos-style resilience tests for every Gemini-backed endpoint: none of
 * them may ever bubble up a fatal 500, regardless of how badly the AI (or
 * the network) misbehaves.
 */
function fakeGeminiNetworkTimeout(): void
{
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received');
    });
}

function fakeGeminiBrokenMarkdown(): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => "```json\n{ isso nao é json válido, apenas lixo solto ```"],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);
}

function fakeGeminiValidOcrResponse(): void
{
    $fixture = file_get_contents(base_path('tests/Fixtures/ocr-workout-sheet.json'));

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => "```json\n{$fixture}\n```"],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────
// Teste 1: Falha de rede / timeout
// ─────────────────────────────────────────────────────────────────────────

test('scanning a workout sheet never returns a fatal 500 when Gemini times out', function () {
    fakeGeminiNetworkTimeout();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 500, 'image/jpeg'),
    ]);

    $response->assertStatus(302);
    $response->assertRedirect(route('workouts.index'));
    $response->assertSessionHas('error', 'Não foi possível ler esta imagem. Tente tirar uma foto mais nítida ou aproximada.');
    expect($user->workouts()->count())->toBe(0);
});

test('opening a workout session never returns a fatal 500 when Gemini times out, since it never calls the AI synchronously', function () {
    fakeGeminiNetworkTimeout();
    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    // A completed session in the past would make the background
    // overload-suggestions request try to reach Gemini, but the active
    // session screen itself must never wait on it.
    $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay()->subHour(),
        'completed_at' => now()->subDay(),
    ]);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('workout-sessions.show', $session))
        ->assertOk();

    Http::assertNothingSent();
});

test('the background overload-suggestions request degrades to an empty list without a fatal 500 when Gemini times out', function () {
    fakeGeminiNetworkTimeout();
    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay()->subHour(),
        'completed_at' => now()->subDay(),
    ]);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson(route('workout-sessions.overload-suggestions', $session))
        ->assertOk()
        ->assertExactJson(['suggestions' => []]);
});

// ─────────────────────────────────────────────────────────────────────────
// Teste 2: IA devolve lixo / markdown quebrado
// ─────────────────────────────────────────────────────────────────────────

test('scanning a workout sheet handles broken markdown from the AI gracefully', function () {
    fakeGeminiBrokenMarkdown();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('workouts.scan'), [
        'image' => UploadedFile::fake()->create('ficha.jpg', 500, 'image/jpeg'),
    ]);

    $response->assertStatus(302);
    $response->assertRedirect(route('workouts.index'));
    $response->assertSessionHas('error', 'Não foi possível ler esta imagem. Tente tirar uma foto mais nítida ou aproximada.');
    expect($user->workouts()->count())->toBe(0);
});

test('the background overload-suggestions request falls back to no suggestions when the AI returns broken markdown', function () {
    fakeGeminiBrokenMarkdown();
    $user = User::factory()->create();
    $workout = Workout::factory()->for($user)->create();

    $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now()->subDay()->subHour(),
        'completed_at' => now()->subDay(),
    ]);

    $session = $user->workoutSessions()->create([
        'workout_id' => $workout->id,
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson(route('workout-sessions.overload-suggestions', $session))
        ->assertOk()
        ->assertExactJson(['suggestions' => []]);
});

// ─────────────────────────────────────────────────────────────────────────
// Teste 3: Upload de imagem pesada
// ─────────────────────────────────────────────────────────────────────────

test('scanning tolerates an 8MB workout sheet photo without exhausting memory', function () {
    fakeGeminiValidOcrResponse();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('workouts.scan'), [
        // 8MB, comfortably inside the 10MB ScanWorkoutRequest limit but
        // large enough to stress base64-encoding it in memory.
        'image' => UploadedFile::fake()->create('ficha-grande.jpg', 8 * 1024, 'image/jpeg'),
    ]);

    $workout = $user->workouts()->firstOrFail();

    $response->assertRedirect(route('workouts.show', $workout));
    $response->assertSessionHas('success');
    expect($workout->exercises()->count())->toBe(4);
});
