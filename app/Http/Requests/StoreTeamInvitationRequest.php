<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\TeamMembershipRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->user()?->currentTeam()->first();
        $role = TeamMembershipRole::tryFrom((string) $this->input('role'));

        return $team instanceof Team
            && $role instanceof TeamMembershipRole
            && $this->user()->can('invite', [$team, $role]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => [
                'required',
                Rule::enum(TeamMembershipRole::class)->only([
                    TeamMembershipRole::Administrator,
                    TeamMembershipRole::Collaborator,
                ]),
            ],
        ];
    }
}
