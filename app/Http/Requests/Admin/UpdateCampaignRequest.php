<?php

namespace App\Http\Requests\Admin;

use App\CampaignStatus;
use App\Http\Requests\Admin\Concerns\ValidatesCampaignRankingWeights;
use App\Models\Campaign;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCampaignRequest extends FormRequest
{
    use ValidatesCampaignRankingWeights;

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
            'nice_to_have_skills' => $this->stringList('nice_to_have_skills'),
            'language' => $this->input('language', 'English'),
            'ranking_weights' => Campaign::normalizeRankingWeights(
                is_array($this->input('ranking_weights'))
                    ? $this->input('ranking_weights')
                    : Campaign::defaultRankingWeights(),
            ),
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
            'nice_to_have_skills' => ['nullable', 'array'],
            'nice_to_have_skills.*' => ['string', 'max:100'],
            'language' => ['required', 'string', 'max:40'],
            'threshold_score' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
            'ai_generation_notes' => ['nullable', 'string'],
            ...$this->rankingWeightRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return $this->rankingWeightAfterValidators();
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
