<?php

namespace App\Services;

use App\Enums\AiTask;
use App\Enums\MuscleGroup;
use App\Exceptions\AiException;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\AI\AiManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkoutScannerService
{
    public function __construct(
        private readonly AiManager $aiManager,
        private readonly WorkoutProgramService $workoutProgramService,
    ) {}

    /**
     * Reads a photo of a paper workout sheet, asks the AI (via AiManager) to
     * transcribe it, and imports the result as a new Workout for the user.
     * Exercises the AI recognizes as synonyms of an existing catalog entry
     * are reused instead of duplicated; genuinely new exercises are created
     * for the user.
     */
    public function scanAndImport(UploadedFile $image, User $user, ?int $workoutProgramId = null): Workout
    {
        // Reading, base64-encoding and transcribing a workout-sheet photo can
        // exceed the default 30s/128M PHP limits on shared hosting.
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $catalog = Exercise::query()->forUser($user)->pluck('name', 'id');

        $parsed = $this->transcribeWithAi($image, $catalog);

        return DB::transaction(function () use ($parsed, $catalog, $user, $workoutProgramId) {
            $workout = $user->workouts()->create([
                'name' => $parsed['workout_name'] ?? 'Ficha Escaneada',
                'workout_program_id' => $workoutProgramId ?? $this->workoutProgramService->getActiveProgram($user)?->id,
            ]);

            if ($workout->workout_program_id) {
                $workout->programs()->syncWithoutDetaching([$workout->workout_program_id]);
            }

            foreach ($parsed['exercises'] ?? [] as $index => $exerciseData) {
                $exercise = $this->resolveExercise($exerciseData, $catalog, $user);

                $workout->workoutExercises()->create([
                    'exercise_id' => $exercise->id,
                    'order' => $index,
                    'target_sets' => $exerciseData['target_sets'] ?? null,
                    'target_reps' => isset($exerciseData['target_reps']) ? (string) $exerciseData['target_reps'] : null,
                    'notes' => $exerciseData['notes'] ?? null,
                ]);
            }

            return $workout->load('exercises');
        });
    }

    /**
     * @param  Collection<int, string>  $catalog
     * @return array{workout_name?: string, exercises?: array<int, array{name: string, target_sets?: int, target_reps?: int|string, muscle_group?: string, notes?: string}>}
     */
    private function transcribeWithAi(UploadedFile $image, Collection $catalog): array
    {
        try {
            return $this->aiManager->analyzeImage($image, $this->buildPrompt($catalog), task: AiTask::Vision);
        } catch (\Throwable $e) {
            Log::warning('Falha ao transcrever ficha de treino via IA.', ['message' => $e->getMessage()]);

            throw new AiException('Não foi possível ler esta imagem. Tente tirar uma foto mais nítida ou aproximada.', previous: $e);
        }
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
        Você é um assistente que digitaliza fichas de treino de musculação escritas à mão ou impressas em cupons térmicos
        (o tipo de recibo estreito e de matriz de pontos comum em academias, com fontes de baixa resolução e abreviações).

        Analise a imagem enviada e extraia o nome do treino e a lista de exercícios, com as séries e repetições alvo de cada um.

        Extraia o nome do treino ou da divisão a partir do cabeçalho da ficha (ex: "Treino 1 - Costas e Ombros",
        "Treino A - Peito e Tríceps"). Use exatamente o texto do cabeçalho, sem traduzir ou reformatar.

        Cupons térmicos costumam usar abreviações. Interprete-as corretamente:
        - "S: 3" ou "Séries: 3" significa 3 séries → "target_sets": 3.
        - "Rept: 10-12" ou "Reps: 10-12" significa a faixa de repetições alvo → "target_reps": "10-12".
        - Uma faixa de repetições (ex: "8-10", "10 a 12") deve ser preservada como string em "target_reps",
          não convertida para um único número.

        Fichas de treino frequentemente indicam variações de pegada junto ao nome do exercício, como "P. PRONADA"
        (pegada pronada), "P. SUPINADA" (pegada supinada), "P. NEUTRA" (pegada neutra), "P. ABERTA" (pegada aberta)
        ou "P. FECHADA" (pegada fechada). Não inclua essa informação no campo "name" do exercício — em vez disso,
        registre o detalhe da pegada (por extenso) no campo "notes" daquele exercício.

        Para cada exercício identificado, compare o nome lido na imagem com a lista de exercícios já cadastrados abaixo.
        Se o exercício da imagem for o mesmo exercício (mesmo que escrito de forma abreviada, com sinônimo ou grafia diferente),
        você DEVE usar exatamente o nome já cadastrado no campo "name" da resposta, para não criar um exercício duplicado.
        Se não houver correspondência na lista, retorne o nome como está na imagem e classifique o grupo muscular principal
        em "muscle_group" usando um destes valores: {$muscleGroups}.

        Exercícios já cadastrados:
        {$catalogList}

        Responda SOMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional.
        O campo "notes" é opcional e só deve ser incluído quando houver informação relevante (ex: variação de pegada):
        {
          "workout_name": "string",
          "exercises": [
            {
              "name": "string",
              "target_sets": number,
              "target_reps": number,
              "muscle_group": "string",
              "notes": "string"
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
