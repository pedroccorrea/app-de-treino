<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncWorkoutSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O JavaScript envia números vindos de inputs de formulário (às vezes
     * strings, às vezes já numéricos) e datas via `Date.prototype.toISOString()`
     * (sempre ISO 8601, ex. `2026-09-17T14:32:10.123Z`). Normaliza os campos
     * numéricos ANTES de validar — string vazia vira `null` (para as regras
     * `nullable` abaixo funcionarem de verdade) e strings numéricas viram
     * `int`/`float` — para que um payload legítimo nunca falhe a validação
     * (422) só por causa do tipo bruto que o navegador enviou.
     */
    protected function prepareForValidation(): void
    {
        $actions = collect($this->input('actions', []))
            ->map(function ($action) {
                if (! is_array($action)) {
                    return $action;
                }

                foreach (['weight', 'rpe'] as $field) {
                    if (array_key_exists($field, $action)) {
                        $action[$field] = $this->coerceNumeric($action[$field]);
                    }
                }

                foreach (['reps', 'set_number', 'workout_id', 'exercise_id', 'session_id'] as $field) {
                    if (array_key_exists($field, $action)) {
                        $action[$field] = $this->coerceNumeric($action[$field], asInt: true);
                    }
                }

                return $action;
            })
            ->all();

        $this->merge(['actions' => $actions]);
    }

    private function coerceNumeric(mixed $value, bool $asInt = false): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return $value;
        }

        return $asInt ? (int) $value : (float) $value;
    }

    /**
     * Cada item de `actions` é uma mutação que ocorreu offline, na ordem em
     * que o dispositivo a registrou. `client_session_id` agrupa as ações que
     * pertencem à mesma sessão de treino (gerado no dispositivo antes de a
     * sessão existir no banco); `session_id` é usado quando a sessão já foi
     * criada online e o dispositivo apenas perdeu conexão no meio do treino.
     * `client_set_id` identifica de forma única (UUID) cada série registrada,
     * suportando reenvios idempotentes de `log_set`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.uuid' => ['required', 'string', 'max:64'],
            'actions.*.type' => ['required', Rule::in(['start_session', 'log_set', 'finish_session'])],
            'actions.*.client_session_id' => ['required', 'string', 'max:64'],
            'actions.*.client_set_id' => ['nullable', 'string', 'max:64'],
            'actions.*.occurred_at' => ['required', 'date'],

            'actions.*.session_id' => [
                'nullable',
                'integer',
                Rule::exists('workout_sessions', 'id')->where('user_id', $this->user()->id),
            ],

            'actions.*.workout_id' => [
                'required_if:actions.*.type,start_session',
                'nullable',
                'integer',
                Rule::exists('workouts', 'id')->where('user_id', $this->user()->id),
            ],

            'actions.*.exercise_id' => [
                'required_if:actions.*.type,log_set',
                'nullable',
                'integer',
                Rule::exists('exercises', 'id')->where(function ($query) {
                    $query->whereNull('user_id')->orWhere('user_id', $this->user()->id);
                }),
            ],
            'actions.*.set_number' => ['required_if:actions.*.type,log_set', 'nullable', 'integer', 'min:1', 'max:50'],
            'actions.*.reps' => ['required_if:actions.*.type,log_set', 'nullable', 'integer', 'min:1', 'max:999'],
            'actions.*.weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'actions.*.rpe' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'actions.*.is_warmup' => ['nullable', 'boolean'],
            'actions.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
