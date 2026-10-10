<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Correção de uma série no histórico (Phase 45). Mesmos limites de carga e
 * repetições que `LogSetRequest` aceita ao registrar a série no treino —
 * uma série corrigida nunca pode ficar num estado que o app não aceitaria
 * registrar. Carga nula = série sem carga (exibida como "—kg").
 */
class UpdateSetLogRequest extends FormRequest
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
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'reps' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'weight' => 'carga',
            'reps' => 'repetições',
        ];
    }
}
