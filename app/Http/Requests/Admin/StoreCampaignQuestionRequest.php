<?php

namespace App\Http\Requests\Admin;

use App\Models\CampaignSection;
use App\QuestionGradingMode;
use App\QuestionType;
use App\Services\AuthoredQuestion;
use App\Services\AuthoredQuestionValidationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignQuestionRequest extends FormRequest
{
    private ?AuthoredQuestion $authoredQuestion = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare adapter-local fields for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
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
            'grading_mode' => ['nullable', Rule::enum(QuestionGradingMode::class)],
            'prompt' => ['required', 'string'],
            'options_text' => ['nullable'],
            'correct_answer_text' => ['nullable'],
            'expected_rubric' => ['nullable', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:1000'],
            'difficulty' => ['required', 'string', 'in:easy,medium,hard'],
            'skill_tags_text' => ['nullable'],
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

                $this->appendAuthoredQuestionErrors($validator);
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function questionAttributes(): array
    {
        return [
            ...$this->authoredQuestion()->toAttributes(),
            'campaign_section_id' => $this->integer('campaign_section_id'),
            'ai_generated' => $this->boolean('ai_generated'),
            'is_required' => $this->boolean('is_required'),
            'sort_order' => $this->integer('sort_order'),
        ];
    }

    private function appendAuthoredQuestionErrors(Validator $validator): void
    {
        try {
            $this->authoredQuestion = AuthoredQuestion::fromFormInput($this->authoredQuestionInput());
        } catch (AuthoredQuestionValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        }
    }

    private function authoredQuestion(): AuthoredQuestion
    {
        return $this->authoredQuestion ??= AuthoredQuestion::fromFormInput($this->authoredQuestionInput());
    }

    /**
     * @return array<string, mixed>
     */
    private function authoredQuestionInput(): array
    {
        return $this->only([
            'type',
            'grading_mode',
            'prompt',
            'options_text',
            'correct_answer_text',
            'expected_rubric',
            'points',
            'difficulty',
            'skill_tags_text',
        ]);
    }
}
