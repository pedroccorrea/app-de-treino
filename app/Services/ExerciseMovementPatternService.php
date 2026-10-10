<?php

namespace App\Services;

use App\Enums\AiFeature;
use App\Enums\AiTask;
use App\Enums\MovementPattern;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Services\AI\AiManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Classifica automaticamente o padrão de movimento de cada exercício, sob
 * demanda, com a mesma lógica do ExerciseEquipmentService (Phase 42):
 * 1. palavras-chave no nome (grátis, síncrono);
 * 2. se nenhuma palavra resolver, UMA pergunta à IA — agrupando todos os
 *    exercícios pendentes numa única chamada — e a resposta é salva.
 *
 * O grupo muscular principal não é classificado aqui: ele já existe em todo
 * exercício (primary_muscle_group, enum MuscleGroup) e é reaproveitado.
 * Exercícios já classificados (movement_pattern não nulo) nunca são
 * reprocessados.
 */
class ExerciseMovementPatternService
{
    /**
     * Regras avaliadas na ordem: a primeira que casar vence. As mais
     * específicas vêm antes (ex: "leg curl" antes de "curl",
     * "crucifixo inverso" antes de "crucifixo", "elevação lateral" antes de
     * qualquer outra "elevação"). Aplicadas ao nome sem acentos e minúsculo.
     *
     * @var array<int, array{0: string, 1: MovementPattern}>
     */
    private const KEYWORD_RULES = [
        ['/\b(panturrilhas?|gemeos|calf|calves)\b/', MovementPattern::Calf],
        ['/\b(elevacao lateral|elevacoes laterais|lateral raises?)\b/', MovementPattern::LateralRaise],
        ['/\b(triceps|testa|frances|coice|kickback|pushdown)\b/', MovementPattern::ElbowExtension],
        ['/\b(flexora|leg curl|nordico|nordic)\b/', MovementPattern::KneeFlexion],
        ['/\b(rosca|biceps|curl)\b/', MovementPattern::ElbowFlexion],
        ['/\b(extensora|leg extension)\b/', MovementPattern::KneeExtension],
        ['/\b(crucifixo inverso|voador inverso|reverse fly|face ?pull)\b/', MovementPattern::HorizontalPull],
        ['/\b(crucifixo|voador|peck ?deck|crossover|cross over|fly)\b/', MovementPattern::ChestFly],
        ['/\b(puxada|pulldown|barra fixa|pull ?ups?|chin ?ups?)\b/', MovementPattern::VerticalPull],
        ['/\b(remada|row|serrote|cavalinho)\b/', MovementPattern::HorizontalPull],
        ['/\b(desenvolvimento|militar|overhead|arnold|shoulder press)\b/', MovementPattern::VerticalPush],
        ['/\b(supino|flexao de bracos|flexoes|chest press|bench press)\b/', MovementPattern::HorizontalPush],
        ['/\b(terra|stiff|rdl|good morning|bom dia|elevacao pelvica|hip thrust|glute bridge|swing)\b/', MovementPattern::HipHinge],
        ['/\b(agachamento|squat|leg press|hack|afundo|passada|bulgaro|lunge|avanco)\b/', MovementPattern::Squat],
        ['/\b(abdominal|abdominais|prancha|crunch|infra)\b/', MovementPattern::Core],
    ];

    public function __construct(private readonly AiManager $aiManager) {}

    public function classifyByKeywords(string $name): ?MovementPattern
    {
        $normalized = Str::of($name)->ascii()->lower()->squish()->value();

        foreach (self::KEYWORD_RULES as [$pattern, $movement]) {
            if (preg_match($pattern, $normalized)) {
                return $movement;
            }
        }

        return null;
    }

    /**
     * Garante que todos os exercícios informados tenham padrão de movimento:
     * resolve o que der por palavra-chave e faz no máximo UMA chamada à IA
     * para o restante. Se a IA estiver indisponível, os pendentes ficam sem
     * classificação (tratados como "outro") e serão tentados no próximo uso.
     *
     * @param  iterable<int, Exercise>  $exercises
     */
    public function ensureClassified(iterable $exercises): void
    {
        $pending = collect($exercises)
            ->filter(fn (Exercise $exercise) => $exercise->movement_pattern === null)
            ->unique('id')
            ->reject(function (Exercise $exercise) {
                $movement = $this->classifyByKeywords($exercise->name);

                if ($movement === null) {
                    return false;
                }

                $this->store($exercise, $movement);

                return true;
            })
            ->values();

        if ($pending->isNotEmpty()) {
            $this->classifyWithAi($pending);
        }
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     */
    private function classifyWithAi(Collection $exercises): void
    {
        try {
            $response = $this->aiManager->generateStructured($this->buildPrompt($exercises), task: AiTask::FastText, feature: AiFeature::MovementPatternClassification);
        } catch (\Throwable $e) {
            Log::warning('Falha ao classificar padrão de movimento de exercícios via IA.', [
                'exercise_ids' => $exercises->pluck('id')->all(),
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $answers = collect(is_array($response['classifications'] ?? null) ? $response['classifications'] : [])
            ->filter(fn (mixed $item) => is_array($item) && is_numeric($item['exercise_id'] ?? null))
            ->mapWithKeys(fn (array $item) => [(int) $item['exercise_id'] => $item['movement_pattern'] ?? null]);

        // A IA já foi consultada: o que ela não conseguiu classificar vira
        // "outro" e fica salvo, para nunca ser perguntado de novo.
        foreach ($exercises as $exercise) {
            $answer = $answers->get($exercise->id);
            $movement = is_string($answer) ? MovementPattern::tryFrom(mb_strtolower(trim($answer))) : null;

            $this->store($exercise, $movement ?? MovementPattern::Other);
        }
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     */
    private function buildPrompt(Collection $exercises): string
    {
        $allowed = implode(', ', array_map(fn (MovementPattern $case) => $case->value, MovementPattern::cases()));
        $muscles = implode(', ', array_map(fn (MuscleGroup $case) => $case->value, MuscleGroup::cases()));

        $list = json_encode(
            $exercises->map(fn (Exercise $exercise) => [
                'exercise_id' => $exercise->id,
                'name' => $exercise->name,
                'primary_muscle_group' => $exercise->primary_muscle_group?->value,
            ])->values()->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        return <<<PROMPT
        Você é um especialista em musculação. Classifique o padrão de movimento de cada exercício abaixo.

        Valores permitidos para "movement_pattern": {$allowed}.
        - empurrar_horizontal: supinos, flexão de braços, chest press.
        - empurrar_vertical: desenvolvimentos e press acima da cabeça.
        - puxar_horizontal: remadas, crucifixo inverso, face pull.
        - puxar_vertical: puxadas, pulldown, barra fixa.
        - agachamento: agachamentos, leg press, hack, afundos.
        - dobradica_quadril: terra, stiff, elevação pélvica, good morning.
        - flexao_cotovelo: roscas de bíceps.
        - extensao_cotovelo: extensões de tríceps.
        - elevacao_lateral: elevações laterais de ombro.
        - panturrilha: flexão plantar (panturrilha).
        - extensao_joelho: cadeira extensora.
        - flexao_joelho: mesa/cadeira flexora.
        - crucifixo: crucifixo, voador, crossover (adução horizontal).
        - core: abdominais e pranchas.
        - outro: quando não se encaixar em nenhum dos anteriores.

        Cada exercício já informa o grupo muscular principal (valores possíveis: {$muscles}); use-o só como contexto.

        Exercícios:
        {$list}

        Use exclusivamente os exercise_id listados acima.

        Responda SOMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional:
        {
          "classifications": [
            { "exercise_id": 1, "movement_pattern": "empurrar_horizontal" }
          ]
        }
        PROMPT;
    }

    /**
     * Grava só se ainda estiver nulo: uma classificação existente nunca é
     * sobrescrita, nem mesmo se dois requests concorrentes chegarem aqui.
     */
    private function store(Exercise $exercise, MovementPattern $movement): void
    {
        Exercise::query()
            ->whereKey($exercise->id)
            ->whereNull('movement_pattern')
            ->update(['movement_pattern' => $movement->value]);

        $exercise->setAttribute('movement_pattern', $movement);
        $exercise->syncOriginalAttribute('movement_pattern');
    }
}
