<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnershipTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->user()?->currentTeam()->first();

        return $team instanceof Team && $this->user()->can('transferOwnership', $team);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'membership_id' => [
                'required',
                'integer',
                Rule::exists('team_memberships', 'id')->where(fn ($query) => $query
                    ->where('team_id', $this->user()->current_team_id)
                    ->whereNull('ended_at')),
            ],
        ];
    }
}
