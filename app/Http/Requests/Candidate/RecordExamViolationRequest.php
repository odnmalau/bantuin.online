<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordExamViolationRequest extends FormRequest
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
            'type' => [
                'required',
                'string',
                Rule::in([
                    'fullscreen_exit',
                    'tab_hidden',
                    'window_blur',
                    'copy',
                    'paste',
                    'cut',
                    'context_menu',
                    'navigation_attempt',
                ]),
            ],
        ];
    }
}
