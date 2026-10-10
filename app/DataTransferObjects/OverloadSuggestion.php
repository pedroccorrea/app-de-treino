<?php

namespace App\DataTransferObjects;

use App\Enums\OverloadConfidence;
use App\Enums\SuggestionKind;

readonly class OverloadSuggestion
{
    /**
     * suggested_load/suggested_reps só são nulos em SuggestionKind::FirstTime:
     * sem histórico próprio nem exercício parecido, nenhum número é inventado.
     * basis é o motivo em uma linha da carga inicial (ex: "Baseado em Supino
     * Reto com Barra (40 kg × 8)").
     */
    public function __construct(
        public int $exercise_id,
        public ?float $suggested_load,
        public ?int $suggested_reps,
        public ?float $previous_load,
        public ?int $previous_reps,
        public string $rationale,
        public OverloadConfidence $confidence,
        public SuggestionKind $kind = SuggestionKind::Progression,
        public ?string $basis = null,
    ) {}

    /**
     * @return array{exercise_id: int, suggested_load: ?float, suggested_reps: ?int, previous_load: ?float, previous_reps: ?int, rationale: string, confidence: string, kind: string, basis: ?string}
     */
    public function toArray(): array
    {
        return [
            'exercise_id' => $this->exercise_id,
            'suggested_load' => $this->suggested_load,
            'suggested_reps' => $this->suggested_reps,
            'previous_load' => $this->previous_load,
            'previous_reps' => $this->previous_reps,
            'rationale' => $this->rationale,
            'confidence' => $this->confidence->value,
            'kind' => $this->kind->value,
            'basis' => $this->basis,
        ];
    }
}
