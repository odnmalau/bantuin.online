<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class AbstractGenerateQuestionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'question_count' => $this->integer('question_count', 6),
            'difficulty' => $this->input('difficulty', $this->defaultDifficulty()),
            'question_mix' => $this->filled('question_mix') ? $this->input('question_mix') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question_count' => ['required', 'integer', 'min:1', 'max:20'],
            'difficulty' => ['required', 'string', Rule::in(['easy', 'medium', 'hard', 'mixed'])],
            'question_mix' => ['nullable', 'string', 'max:2000'],
        ];
    }

    abstract protected function defaultDifficulty(): string;
}
