<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
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
            'required_skills' => $this->stringList('required_skills'),
            'language' => $this->input('language', 'English'),
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
            'title' => ['required', 'string', 'max:255'],
            'role_title' => ['required', 'string', 'max:255'],
            'seniority' => ['nullable', 'string', 'max:255'],
            'job_description' => ['nullable', 'string'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:100'],
            'language' => ['required', 'string', 'max:40'],
            'threshold_score' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Convert textarea input into a trimmed string list.
     *
     * @return array<int, string>|null
     */
    protected function stringList(string $key): ?array
    {
        $value = $this->input($key);

        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        }

        $items = array_values(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $items),
            fn (string $item): bool => $item !== '',
        ));

        return $items === [] ? null : $items;
    }
}
