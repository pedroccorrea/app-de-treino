<?php

namespace App\Http\Requests;

use App\Models\WorkoutExercise;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncResourceMutationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Cada item de `actions` é uma mutação de CRUD (programa ou treino) que
     * ocorreu offline, na ordem em que o dispositivo a registrou.
     *
     * Referências a outra entidade (`program_id`, `workout_id`) podem ser um
     * ID real (inteiro, já existente no banco) ou uma referência temporária
     * no formato `tmp_<client_uuid>`, quando a entidade referenciada foi ela
     * mesma criada offline, no mesmo lote — por isso são validadas apenas
     * como string aqui; a resolução final (e a checagem de posse) acontece
     * em `SyncService::processResourceMutations()`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.uuid' => ['required', 'string', 'max:64'],
            'actions.*.occurred_at' => ['required', 'date'],
            'actions.*.type' => ['required', Rule::in([
                'create_program', 'update_program', 'activate_program', 'archive_program', 'delete_program',
                'create_workout', 'update_workout', 'archive_workout', 'delete_workout',
                'attach_workout', 'detach_workout',
            ])],

            'actions.*.client_uuid' => [
                'required_if:actions.*.type,create_program,create_workout',
                'nullable', 'string', 'max:64',
            ],

            'actions.*.program_id' => [
                'required_if:actions.*.type,update_program,activate_program,archive_program,delete_program,attach_workout,detach_workout',
                'nullable',
            ],
            'actions.*.workout_id' => [
                'required_if:actions.*.type,update_workout,archive_workout,delete_workout,attach_workout,detach_workout',
                'nullable',
            ],
            'actions.*.workout_program_id' => ['nullable'],

            'actions.*.name' => ['required_if:actions.*.type,create_program,update_program,create_workout,update_workout', 'nullable', 'string', 'max:255'],
            'actions.*.description' => ['nullable', 'string'],
            'actions.*.days_of_week' => ['nullable', 'array'],
            'actions.*.days_of_week.*' => ['integer', 'min:1', 'max:7'],

            'actions.*.exercises' => ['nullable', 'array'],
            'actions.*.exercises.*.id' => [
                'required',
                'integer',
                Rule::exists('exercises', 'id')->where(function ($query) {
                    $query->whereNull('user_id')->orWhere('user_id', $this->user()->id);
                }),
            ],
            'actions.*.exercises.*.target_sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'actions.*.exercises.*.target_reps' => ['nullable', 'string', 'max:50'],
            'actions.*.exercises.*.order' => ['nullable', 'integer', 'min:0'],
            // Grupo de exercício combinado: opcional; o WorkoutService normaliza
            // (só vizinhos com o mesmo número formam grupo).
            'actions.*.exercises.*.superset_group' => ['nullable', 'integer', 'min:1', 'max:'.WorkoutExercise::MAX_SUPERSET_GROUP],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'actions.*.exercises.*.superset_group.integer' => 'O grupo do exercício combinado deve ser um número inteiro.',
            'actions.*.exercises.*.superset_group.min' => 'O grupo do exercício combinado deve ser maior que zero.',
            'actions.*.exercises.*.superset_group.max' => 'O grupo do exercício combinado deve ser no máximo :max.',
        ];
    }
}
