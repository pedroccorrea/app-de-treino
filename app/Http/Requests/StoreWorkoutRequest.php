<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
use App\Models\WorkoutExercise;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'workout_program_id' => [
                'nullable',
                'integer',
                Rule::exists('workout_programs', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', Rule::enum(DayOfWeek::class)],
            'exercises' => ['nullable', 'array'],
            'exercises.*.id' => [
                'required',
                'integer',
                Rule::exists('exercises', 'id')->where(function ($query) {
                    $query->where(function ($query) {
                        $query->whereNull('user_id')
                            ->orWhere('user_id', $this->user()->id);
                    });
                }),
            ],
            'exercises.*.target_sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'exercises.*.target_reps' => ['nullable', 'string', 'max:50'],
            'exercises.*.order' => ['nullable', 'integer', 'min:0'],
            // Grupo de exercício combinado: opcional; o WorkoutService normaliza
            // (só vizinhos com o mesmo número formam grupo).
            'exercises.*.superset_group' => ['nullable', 'integer', 'min:1', 'max:'.WorkoutExercise::MAX_SUPERSET_GROUP],
        ];
    }

    /**
     * A meta de repetições aceita número ("10") ou faixa ("8-12"); qualquer
     * outro texto é guardado como está e só não vira meta numérica (RepRange).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exercises.*.target_reps.string' => 'Informe as repetições como número (ex.: 10) ou faixa (ex.: 8-12).',
            'exercises.*.target_reps.max' => 'A meta de repetições deve ter no máximo 50 caracteres.',
            'exercises.*.superset_group.integer' => 'O grupo do exercício combinado deve ser um número inteiro.',
            'exercises.*.superset_group.min' => 'O grupo do exercício combinado deve ser maior que zero.',
            'exercises.*.superset_group.max' => 'O grupo do exercício combinado deve ser no máximo :max.',
        ];
    }
}
