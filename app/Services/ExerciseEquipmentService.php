<?php

namespace App\Services;

use App\Enums\AiTask;
use App\Enums\Equipment;
use App\Models\Exercise;
use App\Services\AI\AiManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Classifica automaticamente o equipamento de cada exercício, sob demanda
 * (quando o exercício é criado ou usado). O usuário nunca escolhe nem
 * corrige o equipamento.
 *
 * Ordem de tentativa:
 * 1. palavras-chave no nome (grátis, síncrono);
 * 2. se nenhuma palavra resolver, UMA pergunta à IA — agrupando todos os
 *    exercícios pendentes numa única chamada — e a resposta é salva.
 *
 * Exercícios já classificados (equipment não nulo) nunca são reprocessados.
 */
class ExerciseEquipmentService
{
    /**
     * Regras avaliadas na ordem: a primeira que casar vence. Por isso as mais
     * específicas vêm antes (ex: "barra fixa" antes de "barra", "hack" antes
     * de "máquina"). Os padrões são aplicados ao nome em minúsculas e sem
     * acentos.
     *
     * @var array<int, array{0: string, 1: Equipment}>
     */
    private const KEYWORD_RULES = [
        ['/\bkettlebells?\b/', Equipment::Kettlebell],
        ['/\b(halter|halteres?|dumbbells?)\b/', Equipment::Dumbbells],
        ['/\bsmith\b/', Equipment::BarbellPlates],
        ['/\b(leg ?press|hack)\b/', Equipment::PlateLoadedMachine],
        ['/\b(barra fixa|paralelas?|mergulho|flexao|flexoes|pull ?ups?|chin ?ups?|prancha|peso corporal|burpees?)\b/', Equipment::Bodyweight],
        ['/\b(maquina|polia|cabos?|crossover|cross over|pulley|pulldown|cadeira|mesa flexora|voador|peck ?deck)\b/', Equipment::SelectorizedMachine],
        ['/\b(barra|barbell|terra|agachamento livre)\b/', Equipment::BarbellPlates],
    ];

    public function __construct(private readonly AiManager $aiManager) {}

    public function classifyByKeywords(string $name): ?Equipment
    {
        $normalized = Str::of($name)->ascii()->lower()->squish()->value();

        foreach (self::KEYWORD_RULES as [$pattern, $equipment]) {
            if (preg_match($pattern, $normalized)) {
                return $equipment;
            }
        }

        return null;
    }

    /**
     * Garante que todos os exercícios informados tenham equipamento: resolve
     * o que der por palavra-chave e faz no máximo UMA chamada à IA para o
     * restante. Se a IA estiver indisponível, os pendentes ficam sem
     * classificação (tratados como "outro") e serão tentados no próximo uso.
     *
     * @param  iterable<int, Exercise>  $exercises
     */
    public function ensureClassified(iterable $exercises): void
    {
        $pending = $this->classifyPendingByKeywords($exercises);

        if ($pending->isNotEmpty()) {
            $this->classifyWithAi($pending);
        }
    }

    /**
     * Só a etapa síncrona e gratuita (palavras-chave). Devolve os exercícios
     * que continuam sem classificação e precisariam da IA.
     *
     * @param  iterable<int, Exercise>  $exercises
     * @return Collection<int, Exercise>
     */
    public function classifyPendingByKeywords(iterable $exercises): Collection
    {
        return collect($exercises)
            ->filter(fn (Exercise $exercise) => $exercise->equipment === null)
            ->unique('id')
            ->reject(function (Exercise $exercise) {
                $equipment = $this->classifyByKeywords($exercise->name);

                if ($equipment === null) {
                    return false;
                }

                $this->store($exercise, $equipment);

                return true;
            })
            ->values();
    }

    /**
     * Para chamar a partir de fluxos de request (salvar ficha, iniciar
     * sessão): palavras-chave na hora e, se sobrar algo, a IA só depois que
     * a resposta já foi enviada ao usuário — a classificação nunca atrasa a
     * tela.
     *
     * @param  iterable<int, Exercise>  $exercises
     */
    public function ensureClassifiedInBackground(iterable $exercises): void
    {
        $pending = $this->classifyPendingByKeywords($exercises);

        if ($pending->isEmpty()) {
            return;
        }

        $ids = $pending->pluck('id')->all();

        defer(fn () => $this->classifyWithAi(Exercise::query()->whereKey($ids)->whereNull('equipment')->get()));
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     */
    private function classifyWithAi(Collection $exercises): void
    {
        if ($exercises->isEmpty()) {
            return;
        }

        try {
            $response = $this->aiManager->generateStructured($this->buildPrompt($exercises), task: AiTask::FastText);
        } catch (\Throwable $e) {
            Log::warning('Falha ao classificar equipamento de exercícios via IA.', [
                'exercise_ids' => $exercises->pluck('id')->all(),
                'message' => $e->getMessage(),
            ]);

            return;
        }

        $answers = collect(is_array($response['classifications'] ?? null) ? $response['classifications'] : [])
            ->filter(fn (mixed $item) => is_array($item) && is_numeric($item['exercise_id'] ?? null))
            ->mapWithKeys(fn (array $item) => [(int) $item['exercise_id'] => $item['equipment'] ?? null]);

        // A IA já foi consultada para estes exercícios: o que ela não
        // conseguiu classificar vira "outro" e fica salvo, para nunca ser
        // perguntado de novo.
        foreach ($exercises as $exercise) {
            $answer = $answers->get($exercise->id);
            $equipment = is_string($answer) ? Equipment::tryFrom(mb_strtolower(trim($answer))) : null;

            $this->store($exercise, $equipment ?? Equipment::Other);
        }
    }

    /**
     * @param  Collection<int, Exercise>  $exercises
     */
    private function buildPrompt(Collection $exercises): string
    {
        $allowed = implode(', ', array_map(fn (Equipment $case) => $case->value, Equipment::cases()));

        $list = json_encode(
            $exercises->map(fn (Exercise $exercise) => ['exercise_id' => $exercise->id, 'name' => $exercise->name])->values()->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        return <<<PROMPT
        Você é um especialista em musculação. Classifique o equipamento usado em cada exercício abaixo.

        Valores permitidos para "equipment": {$allowed}.
        - halteres: exercícios feitos com halteres.
        - barra_anilhas: barra reta, barra W ou smith carregados com anilhas.
        - maquina_anilhas: leg press, hack e máquinas similares carregadas com anilhas.
        - maquina_placas: máquinas de pino ou pilha de placas, polia e cabo.
        - kettlebell: exercícios com kettlebell.
        - peso_corporal: exercícios com o próprio peso do corpo (barra fixa, paralelas, flexão etc.).
        - outro: quando não for possível determinar o equipamento.

        Exercícios:
        {$list}

        Use exclusivamente os exercise_id listados acima.

        Responda SOMENTE com um JSON no seguinte formato, sem markdown, sem comentários e sem texto adicional:
        {
          "classifications": [
            { "exercise_id": 1, "equipment": "halteres" }
          ]
        }
        PROMPT;
    }

    /**
     * Grava só se ainda estiver nulo: uma classificação existente nunca é
     * sobrescrita, nem mesmo se dois requests concorrentes chegarem aqui.
     */
    private function store(Exercise $exercise, Equipment $equipment): void
    {
        Exercise::query()
            ->whereKey($exercise->id)
            ->whereNull('equipment')
            ->update(['equipment' => $equipment->value]);

        $exercise->setAttribute('equipment', $equipment);
        $exercise->syncOriginalAttribute('equipment');
    }
}
