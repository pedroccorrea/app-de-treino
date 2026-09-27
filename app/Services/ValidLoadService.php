<?php

namespace App\Services;

use App\Enums\Equipment;
use App\Enums\LoadMode;

/**
 * Regras determinísticas de "cargas que existem de verdade" em cada tipo de
 * equipamento. Não conversa com IA nem com banco: recebe o equipamento, a
 * forma de registro da carga e a carga atual, e devolve as cargas válidas
 * vizinhas. É a fonte da verdade tanto para a lista enviada no prompt de
 * progressão quanto para a rede de segurança que arredonda a resposta da IA.
 *
 * Internamente tudo é feito em centésimos de kg (inteiros) para que somas
 * como 40 + 2,5 nunca virem 42,499999.
 *
 * Existem dois tipos de "escada" de cargas:
 * - absoluta (halteres, máquina de placas, kettlebell): os valores válidos
 *   são sempre os mesmos, independentemente da carga atual;
 * - relativa (barra/máquina com anilhas, peso corporal com anilha extra): os
 *   valores válidos são a carga atual ± múltiplos do menor par de anilhas,
 *   porque o peso da barra/do trenó varia de academia para academia.
 */
class ValidLoadService
{
    /** Passo padrão dos botões + e − (o "CARGA · 2,5" da sessão ativa). */
    public const DEFAULT_INCREMENT = 2.5;

    /** @var list<int> kg */
    private const KETTLEBELLS = [4, 6, 8, 10, 12, 14, 16, 20, 24, 28, 32];

    /**
     * "outro" (ou exercício ainda não classificado) mantém o comportamento
     * antigo: nenhuma lista de cargas, IA livre e passo padrão nos botões.
     */
    public function isRestricted(Equipment $equipment): bool
    {
        return $equipment !== Equipment::Other;
    }

    public function nextAbove(Equipment $equipment, LoadMode $mode, float $current): ?float
    {
        if (! $this->isRestricted($equipment)) {
            return null;
        }

        return $this->fromCents($this->aboveCents($equipment, $mode, $this->toCents($current), $this->toCents($current)));
    }

    public function nextBelow(Equipment $equipment, LoadMode $mode, float $current): ?float
    {
        if (! $this->isRestricted($equipment)) {
            return null;
        }

        return $this->fromCents($this->belowCents($equipment, $mode, $this->toCents($current), $this->toCents($current)));
    }

    /**
     * As próximas cargas válidas abaixo e acima da carga atual, das mais
     * próximas para as mais distantes. Vazio para "outro".
     *
     * @return array{below: list<float>, above: list<float>}
     */
    public function nearbyLoads(Equipment $equipment, LoadMode $mode, float $current, int $count = 3): array
    {
        $result = ['below' => [], 'above' => []];

        if (! $this->isRestricted($equipment)) {
            return $result;
        }

        $anchor = $this->toCents($current);

        foreach (['below', 'above'] as $direction) {
            $cursor = $anchor;

            for ($i = 0; $i < $count; $i++) {
                $cursor = $direction === 'above'
                    ? $this->aboveCents($equipment, $mode, $anchor, $cursor)
                    : $this->belowCents($equipment, $mode, $anchor, $cursor);

                if ($cursor === null) {
                    break;
                }

                $result[$direction][] = $this->fromCents($cursor);
            }
        }

        return $result;
    }

    /**
     * A carga atual (se ela própria for válida) e as vizinhas válidas, em
     * ordem crescente — é a lista entregue à IA no prompt.
     *
     * @return list<float>
     */
    public function candidateLoads(Equipment $equipment, LoadMode $mode, float $current, int $count = 3): array
    {
        if (! $this->isRestricted($equipment)) {
            return [];
        }

        $nearby = $this->nearbyLoads($equipment, $mode, $current, $count);
        $middle = $this->isValidCents($equipment, $mode, $this->toCents($current), $this->toCents($current))
            ? [$this->fromCents($this->toCents($current))]
            : [];

        return [...array_reverse($nearby['below']), ...$middle, ...$nearby['above']];
    }

    public function isValid(Equipment $equipment, LoadMode $mode, float $current, float $load): bool
    {
        if (! $this->isRestricted($equipment)) {
            return true;
        }

        return $this->isValidCents($equipment, $mode, $this->toCents($current), $this->toCents($load));
    }

    /**
     * Rede de segurança: leva uma carga qualquer (ex: a sugestão da IA) para
     * o valor válido mais próximo. Em caso de empate, fica com o menor.
     */
    public function roundToValid(Equipment $equipment, LoadMode $mode, float $current, float $candidate): float
    {
        if (! $this->isRestricted($equipment)) {
            return $candidate;
        }

        $anchor = $this->toCents($current);
        $cents = $this->toCents($candidate);

        if ($this->isValidCents($equipment, $mode, $anchor, $cents)) {
            return $this->fromCents($cents);
        }

        $below = $this->belowCents($equipment, $mode, $anchor, $cents);
        $above = $this->aboveCents($equipment, $mode, $anchor, $cents);

        if ($below === null || $above === null) {
            return $this->fromCents($below ?? $above ?? $anchor);
        }

        return $this->fromCents(($cents - $below) <= ($above - $cents) ? $below : $above);
    }

    /**
     * Próxima carga ao tocar + ($direction > 0) ou − ($direction < 0) na
     * sessão ativa. Um incremento configurado pelo usuário tem prioridade;
     * sem ele, o passo segue o equipamento (halteres acima de 10 kg sobem de
     * 2 em 2, máquina de placas de 5 em 5...). "outro" mantém o passo padrão.
     * Espelhado em resources/js/Utils/loadSteps.js.
     */
    public function stepFrom(Equipment $equipment, LoadMode $mode, float $current, int $direction, ?float $customIncrement = null): float
    {
        $increment = $customIncrement !== null && $customIncrement > 0 ? $customIncrement : null;

        if ($increment !== null || ! $this->isRestricted($equipment)) {
            $next = $current + ($direction > 0 ? 1 : -1) * ($increment ?? self::DEFAULT_INCREMENT);

            return max(0.0, $this->fromCents($this->toCents($next)));
        }

        $next = $direction > 0
            ? $this->nextAbove($equipment, $mode, $current)
            : $this->nextBelow($equipment, $mode, $current);

        return $next ?? $current;
    }

    /**
     * Menor salto de carga de um equipamento de escada relativa: um par de
     * anilhas de 2,5 kg (+5 kg no total, +2,5 kg por lado). No peso corporal,
     * o acréscimo é uma única anilha de 2,5 kg (cinto/colete).
     */
    private function relativeStepCents(Equipment $equipment, LoadMode $mode): ?int
    {
        return match ($equipment) {
            Equipment::BarbellPlates, Equipment::PlateLoadedMachine => $mode === LoadMode::PerSide ? 250 : 500,
            Equipment::Bodyweight => 250,
            default => null,
        };
    }

    private function isValidCents(Equipment $equipment, LoadMode $mode, int $anchor, int $cents): bool
    {
        $step = $this->relativeStepCents($equipment, $mode);

        if ($step !== null) {
            return $cents >= 0 && ($cents - $anchor) % $step === 0;
        }

        return match ($equipment) {
            Equipment::Dumbbells => $cents % 100 === 0
                && $cents >= 100
                && ($cents <= 1000 || $cents % 200 === 0),
            Equipment::SelectorizedMachine => $cents >= 500 && $cents % 500 === 0,
            Equipment::Kettlebell => in_array($cents, $this->kettlebellCents(), true),
            default => true,
        };
    }

    /**
     * Menor carga válida estritamente acima de $cents.
     */
    private function aboveCents(Equipment $equipment, LoadMode $mode, int $anchor, int $cents): ?int
    {
        $step = $this->relativeStepCents($equipment, $mode);

        if ($step !== null) {
            return $anchor + ($this->floorDiv($cents - $anchor, $step) + 1) * $step;
        }

        return match ($equipment) {
            Equipment::Dumbbells => $cents < 1000
                ? max(100, ($this->floorDiv($cents, 100) + 1) * 100)
                : ($this->floorDiv($cents, 200) + 1) * 200,
            Equipment::SelectorizedMachine => max(500, ($this->floorDiv($cents, 500) + 1) * 500),
            Equipment::Kettlebell => collect($this->kettlebellCents())->first(fn (int $kg) => $kg > $cents),
            default => null,
        };
    }

    /**
     * Maior carga válida estritamente abaixo de $cents (null se não houver).
     */
    private function belowCents(Equipment $equipment, LoadMode $mode, int $anchor, int $cents): ?int
    {
        $step = $this->relativeStepCents($equipment, $mode);

        if ($step !== null) {
            $below = $anchor + ($this->ceilDiv($cents - $anchor, $step) - 1) * $step;

            return $below >= 0 ? $below : null;
        }

        $below = match ($equipment) {
            Equipment::Dumbbells => $cents > 1000
                ? ($this->ceilDiv($cents, 200) - 1) * 200
                : ($this->ceilDiv($cents, 100) - 1) * 100,
            Equipment::SelectorizedMachine => ($this->ceilDiv($cents, 500) - 1) * 500,
            Equipment::Kettlebell => collect($this->kettlebellCents())->last(fn (int $kg) => $kg < $cents),
            default => null,
        };

        $minimum = match ($equipment) {
            Equipment::Dumbbells => 100,
            Equipment::SelectorizedMachine => 500,
            default => 0,
        };

        return $below !== null && $below >= $minimum ? max($below, $minimum) : null;
    }

    /**
     * @return list<int>
     */
    private function kettlebellCents(): array
    {
        return array_map(fn (int $kg) => $kg * 100, self::KETTLEBELLS);
    }

    private function floorDiv(int $a, int $b): int
    {
        return intdiv($a, $b) - (($a % $b !== 0 && ($a < 0) !== ($b < 0)) ? 1 : 0);
    }

    private function ceilDiv(int $a, int $b): int
    {
        return -$this->floorDiv(-$a, $b);
    }

    private function toCents(float $kg): int
    {
        return (int) round($kg * 100);
    }

    private function fromCents(?int $cents): ?float
    {
        return $cents === null ? null : $cents / 100;
    }
}
