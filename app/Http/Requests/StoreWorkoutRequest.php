<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
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
        ];
    }
}
