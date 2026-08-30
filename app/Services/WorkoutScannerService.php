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
        $muscleGroups = implode(', ', array_map(fn(MuscleGroup $case) => $case->value, MuscleGroup::cases()));

        $catalogList = $catalog->isEmpty()
            ? '(nenhum exercício cadastrado ainda)'
            : $catalog->map(fn(string $name, int $id) => "- {$name}")->implode("\n");

        return <<<PROMPT
        Você é um assistente especialista em digitalização e OCR de fichas de treino de musculação (fichas escritas à mão, impressas em cupons térmicos de academias ou digitadas em aplicativos).

        Sua missão é extrair com MÁXIMA FIDELIDADE e PRECISÃO os exercícios, séries e repetições da imagem.

        DIRETRIZES DE EXTRAÇÃO E NOMENCLATURA:
        1. NOME DO TREINO:
           - Extraia o nome da divisão ou título no cabeçalho da ficha (ex: "Treino A - Peito e Tríceps", "Treino 1 - Costas e Ombros").
           - Formate em Title Case elegante.

        2. PRESERVAÇÃO DE ESPECIFICAÇÕES NO NOME DO EXERCÍCIO:
           - NUNCA simplifique ou generalize o nome de um exercício.
           - Se a ficha indicar uma variação de PEGADA (ex: "P. PRONADA", "P. SUPINADA", "NEUTRA", "ABERTA", "FECHADA", "TRIÂNGULO", "ROMANA") ou variação de EQUIPAMENTO/APARELHO (ex: "MÁQUINA", "HALTERES", "POLIA", "BARRA", "SMITH", "ARTICULADO", "INCLINADO", "DECLINADO"), essa especificação DEVE ser incorporada diretamente no campo "name" do exercício.
           - Exemplos de padronização no campo "name":
             * "PUXADOR FRENTE P. PRONADA" -> "Puxador Frente (Pegada Pronada)"
             * "REMADA CURVADA P. SUPINADA" -> "Remada Curvada (Pegada Supinada)"
             * "CRUCIFIXO INV. MAQUINA" -> "Crucifixo Inverso na Máquina"
             * "ELEVAÇÃO LATERAL HALTER" -> "Elevação Lateral com Halteres"
             * "TRICEPS CORDA POLIA" -> "Tríceps Corda na Polia"
             * "SUPINO RETO BARRA" -> "Supino Reto com Barra"

        3. FORMATAÇÃO EM TITLE CASE ELEGANTE:
           - Todos os nomes de exercícios no campo "name" DEVEM estar formatados em Title Case elegante (apenas a primeira letra de palavras relevantes em maiúscula; conectivos como 'na', 'no', 'com', 'de', 'em' em minúsculas).
           - NUNCA retorne nomes em CAIXA ALTA (ALL CAPS).

        4. FIDELIDADE AO CATÁLOGO (SEM MATCHING FORÇADO):
           - Abaixo está a lista de exercícios já cadastrados no catálogo do usuário.
           - Só reutilize o nome exato de um exercício do catálogo se houver CORRESPONDÊNCIA EXATA 1:1 de exercício, pegada e equipamento.
           - NUNCA force um match genérico (ex: se o catálogo tiver "Puxada Frontal" e a ficha contiver "Puxador Frente (Pegada Supinada)", NÃO combine — mantenha como "Puxador Frente (Pegada Supinada)").
           - Se o exercício for novo ou tiver variação não presente no catálogo, retorne o nome completo e específico formatado em Title Case e atribua o grupo muscular principal no campo "muscle_group" com um destes valores: {$muscleGroups}.

        5. SÉRIES E REPETIÇÕES:
           - "S: 3" ou "Séries: 3" -> "target_sets": 3.
           - "R: 10-12" ou "Reps: 10 a 12" -> "target_reps": "10-12" (preserve faixas de repetições como string).
           - Se houver notas de método/técnica adicionais (ex: "Drop-set na última", "Rest-pause", "Pico de contração 2s"), coloque no campo "notes".

        Exercícios já cadastrados no catálogo:
        {$catalogList}

        Responda ESTRITAMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional:
        {
          "workout_name": "string",
          "exercises": [
            {
              "name": "string",
              "target_sets": number,
              "target_reps": "string ou number",
              "muscle_group": "string",
              "notes": "string opcional"
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
            fn(string $existingName) => mb_strtolower($existingName) === mb_strtolower($name)
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
