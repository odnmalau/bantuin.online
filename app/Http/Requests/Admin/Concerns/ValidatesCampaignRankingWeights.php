<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Campaign;
use Illuminate\Validation\Validator;

trait ValidatesCampaignRankingWeights
{
    /**
     * @return array<string, array<int, string>>
     */
    protected function rankingWeightRules(): array
    {
        return [
            'ranking_weights' => ['required', 'array'],
            'ranking_weights.resume_score' => ['required', 'integer', 'min:0', 'max:100'],
            'ranking_weights.assessment_score' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    protected function rankingWeightAfterValidators(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $weights = $this->input('ranking_weights');

                if (! is_array($weights)) {
                    return;
                }

                $total = Campaign::rankingWeightsTotal($weights);

                if ($total !== 100) {
                    $validator->errors()->add(
                        'ranking_weights',
                        __('Ranking weights must total 100. Current total: :total.', ['total' => $total]),
                    );
                }
            },
        ];
    }
}
