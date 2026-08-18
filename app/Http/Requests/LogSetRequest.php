<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogSetRequest extends FormRequest
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
            'exercise_id' => [
                'required',
                'integer',
                Rule::exists('exercises', 'id')->where(function ($query) {
                    $query->where(function ($query) {
                        $query->whereNull('user_id')
                            ->orWhere('user_id', $this->user()->id);
                    });
                }),
            ],
            'set_number' => ['required', 'integer', 'min:1', 'max:50'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'reps' => ['required', 'integer', 'min:1', 'max:999'],
            'rpe' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'is_warmup' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
