<?php

namespace App\Http\Requests\Admin;

use App\Models\CampaignSection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ImportBankQuestionToCampaignRequest extends FormRequest
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
            'is_required' => $this->boolean('is_required'),
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
            'bank_question_id' => ['required', 'integer', 'exists:bank_questions,id'],
            'campaign_section_id' => ['required', 'integer', 'exists:campaign_sections,id'],
            'is_required' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom validation callbacks.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $campaign = $this->route('campaign');
                $section = CampaignSection::query()->find($this->integer('campaign_section_id'));

                if ($campaign !== null && $section !== null && $section->campaign_id !== $campaign->id) {
                    $validator->errors()->add('campaign_section_id', __('The selected section does not belong to this campaign.'));
                }
            },
        ];
    }
}
