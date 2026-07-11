<?php

namespace App\Http\Requests;

use App\Models\TeamMembership;
use App\TeamMembershipRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMembershipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $membership = $this->route('teamMembership');

        return $membership instanceof TeamMembership
            && $this->user()?->can('changeRole', $membership) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
