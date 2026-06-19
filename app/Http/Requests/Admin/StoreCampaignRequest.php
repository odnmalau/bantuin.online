<?php

namespace App\Http\Requests\Admin;

use App\CampaignStatus;

class StoreCampaignRequest extends UpdateCampaignRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'status' => $this->input('status', CampaignStatus::Draft->value),
        ]);
    }
}
