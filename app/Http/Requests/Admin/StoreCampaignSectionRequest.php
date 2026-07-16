<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCampaignSectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $campaign = $this->route('campaign');
                $currentSection = $this->route('section');

                if ($campaign === null) {
                    return;
                }

                $otherSectionCount = $campaign->sections()
                    ->when($currentSection !== null, fn ($query) => $query->whereKeyNot($currentSection->id))
                    ->count();
                $maximumContribution = 100 - $otherSectionCount;

                if ($this->integer('weight') > $maximumContribution) {
                    $validator->errors()->add(
                        'weight',
                        __('Leave at least 1% score contribution for every other section.'),
                    );
                }
            },
        ];
    }
}
