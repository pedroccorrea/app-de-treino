<?php

namespace App\DataTransferObjects;

use App\Enums\OverloadConfidence;

readonly class OverloadSuggestion
{
    public function __construct(
        public int $exercise_id,
        public float $suggested_load,
        public int $suggested_reps,
        public ?float $previous_load,
        public ?int $previous_reps,
        public string $rationale,
        public OverloadConfidence $confidence,
    ) {}

    /**
     * @return array{exercise_id: int, suggested_load: float, suggested_reps: int, previous_load: ?float, previous_reps: ?int, rationale: string, confidence: string}
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
        ];
    }
}
