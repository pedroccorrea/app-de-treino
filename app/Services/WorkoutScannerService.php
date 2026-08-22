<?php

namespace App\Services;

use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WorkoutScannerService
{
    /**
     * Reads a photo of a paper workout sheet, asks Gemini to transcribe it,
     * and imports the result as a new Workout for the user. Exercises the AI
     * recognizes as synonyms of an existing catalog entry are reused instead
     * of duplicated; genuinely new exercises are created for the user.
     */
    public function scanAndImport(UploadedFile $image, User $user): Workout
    {
        $catalog = Exercise::query()->forUser($user)->pluck('name', 'id');

        $parsed = $this->transcribeWithGemini($image, $catalog);

        return DB::transaction(function () use ($parsed, $catalog, $user) {
            $workout = $user->workouts()->create([
                'name' => $parsed['workout_name'] ?? 'Ficha Escaneada',
            ]);

            foreach ($parsed['exercises'] ?? [] as $index => $exerciseData) {
                $exercise = $this->resolveExercise($exerciseData, $catalog, $user);

                $workout->workoutExercises()->create([
                    'exercise_id' => $exercise->id,
                    'order' => $index,
                    'target_sets' => $exerciseData['target_sets'] ?? null,
                    'target_reps' => isset($exerciseData['target_reps']) ? (string) $exerciseData['target_reps'] : null,
                ]);
            }

            return $workout->load('exercises');
        });
    }

    /**
     * @param  Collection<int, string>  $catalog
     * @return array{workout_name?: string, exercises?: array<int, array{name: string, target_sets?: int, target_reps?: int|string, muscle_group?: string}>}
     */
    private function transcribeWithGemini(UploadedFile $image, Collection $catalog): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model');

        $response = Http::post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->buildPrompt($catalog)],
                            [
                                'inline_data' => [
                                    'mime_type' => $image->getMimeType(),
                                    'data' => base64_encode(file_get_contents($image->getRealPath())),
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException('Falha ao comunicar com a API de IA para escanear a ficha.');
        }

        $rawText = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! is_string($rawText) || trim($rawText) === '') {
            throw new RuntimeException('A IA não retornou nenhum conteúdo ao escanear a ficha.');
        }

        $sanitized = preg_replace('/^```(?:json)?\s+|\s+```$/m', '', trim($rawText));
        $parsed = json_decode($sanitized, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            throw new RuntimeException('Não foi possível interpretar os exercícios identificados pela IA.');
        }

        return $parsed;
    }

    /**
     * @param  Collection<int, string>  $catalog
     */
    private function buildPrompt(Collection $catalog): string
    {
        $muscleGroups = implode(', ', array_map(fn (MuscleGroup $case) => $case->value, MuscleGroup::cases()));

        $catalogList = $catalog->isEmpty()
            ? '(nenhum exercício cadastrado ainda)'
            : $catalog->map(fn (string $name, int $id) => "- {$name}")->implode("\n");

        return <<<PROMPT
        Você é um assistente que digitaliza fichas de treino de musculação escritas à mão ou impressas em papel.

        Analise a imagem enviada e extraia o nome do treino e a lista de exercícios, com as séries e repetições alvo de cada um.

        Para cada exercício identificado, compare o nome lido na imagem com a lista de exercícios já cadastrados abaixo.
        Se o exercício da imagem for o mesmo exercício (mesmo que escrito de forma abreviada, com sinônimo ou grafia diferente),
        você DEVE usar exatamente o nome já cadastrado no campo "name" da resposta, para não criar um exercício duplicado.
        Se não houver correspondência na lista, retorne o nome como está na imagem e classifique o grupo muscular principal
        em "muscle_group" usando um destes valores: {$muscleGroups}.

        Exercícios já cadastrados:
        {$catalogList}

        Responda SOMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional:
        {
          "workout_name": "string",
          "exercises": [
            {
              "name": "string",
              "target_sets": number,
              "target_reps": number,
              "muscle_group": "string"
            }
          ]
        }
        PROMPT;
    }

    /**
     * @param  array{name: string, muscle_group?: string}  $exerciseData
     * @param  Collection<int, string>  $catalog
     */
    private function resolveExercise(array $exerciseData, Collection $catalog, User $user): Exercise
    {
        $name = trim($exerciseData['name'] ?? '');

        $matchedId = $catalog->search(
            fn (string $existingName) => mb_strtolower($existingName) === mb_strtolower($name)
        );

        if ($matchedId !== false) {
            return Exercise::query()->findOrFail($matchedId);
        }

        // The AI only sends a muscle_group when it couldn't match an existing
        // exercise; fall back to Chest if it ever omits it for a new one.
        $muscleGroup = MuscleGroup::tryFrom(mb_strtolower($exerciseData['muscle_group'] ?? '')) ?? MuscleGroup::Chest;

        $exercise = Exercise::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'primary_muscle_group' => $muscleGroup,
            'secondary_muscle_groups' => [],
        ]);

        $catalog->put($exercise->id, $exercise->name);

        return $exercise;
    }
}
