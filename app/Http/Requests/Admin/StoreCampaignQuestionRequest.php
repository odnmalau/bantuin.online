<?php

namespace App\Http\Requests\Admin;

use App\Models\CampaignSection;
use App\QuestionGradingMode;
use App\QuestionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignQuestionRequest extends FormRequest
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
        $type = (string) $this->input('type');
        $questionType = QuestionType::tryFrom($type);
        $options = $this->stringList('options_text');

        if ($type === QuestionType::YesNo->value && $options === null) {
            $options = ['Yes', 'No'];
        }

        $this->merge([
            'grading_mode' => $this->input(
                'grading_mode',
                $questionType === null ? null : QuestionGradingMode::forQuestionType($questionType)->value,
            ),
            'options' => $options,
            'correct_answer' => $this->stringList('correct_answer_text'),
            'skill_tags' => $this->stringList('skill_tags_text'),
            'ai_generated' => $this->boolean('ai_generated'),
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
            'campaign_section_id' => ['required', 'integer', 'exists:campaign_sections,id'],
            'type' => ['required', Rule::enum(QuestionType::class)],
            'grading_mode' => ['required', Rule::enum(QuestionGradingMode::class)],
            'prompt' => ['required', 'string'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:500'],
            'correct_answer' => ['nullable', 'array'],
            'correct_answer.*' => ['string', 'max:500'],
            'expected_rubric' => ['nullable', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:1000'],
            'difficulty' => ['required', 'string', 'in:easy,medium,hard'],
            'skill_tags' => ['nullable', 'array'],
            'skill_tags.*' => ['string', 'max:100'],
            'ai_generated' => ['required', 'boolean'],
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

                $type = QuestionType::tryFrom((string) $this->input('type'));

                if ($type === null) {
                    return;
                }

                if ($type->usesDeterministicGrading() && $this->input('correct_answer') === null) {
                    $validator->errors()->add('correct_answer_text', __('A correct answer is required for auto-graded question types.'));
                }

                if ($type === QuestionType::MultipleChoice && count($this->input('options', [])) < 2) {
                    $validator->errors()->add('options_text', __('Multiple choice questions need at least two answer options.'));
                }

                if (! $type->usesDeterministicGrading() && blank($this->input('expected_rubric'))) {
                    $validator->errors()->add('expected_rubric', __('A rubric is required for AI-graded text questions.'));
                }
            },
        ];
    }

    /**
     * Convert textarea input into a trimmed string list.
     *
     * @return array<int, string>|null
     */
    private function stringList(string $key): ?array
    {
        $value = $this->input($key);

        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\r\n]+/', (string) $value) ?: [];
        }

        $items = array_values(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $items),
            fn (string $item): bool => $item !== '',
        ));

        return $items === [] ? null : $items;
    }
}
