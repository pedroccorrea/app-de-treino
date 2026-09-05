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
     * Cada item de `actions` é uma mutação que ocorreu offline, na ordem em
     * que o dispositivo a registrou. `client_session_id` agrupa as ações que
     * pertencem à mesma sessão de treino (gerado no dispositivo antes de a
     * sessão existir no banco); `session_id` é usado quando a sessão já foi
     * criada online e o dispositivo apenas perdeu conexão no meio do treino.
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
