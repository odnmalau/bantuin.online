<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\CampaignInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DemoLoginController extends Controller
{
    public function admin(Request $request): RedirectResponse
    {
        $user = DB::transaction(function (): User {
            $user = $this->findOrCreateUser('Demo Admin', User::DEMO_ADMIN_EMAIL);

            if ($user->current_team_id === null) {
                $team = Team::createForOwner($user, 'Demo Team');
                $user->selectCurrentTeam($team);
            }

            return $user;
        });

        return $this->login($request, $user);
    }

    public function candidate(Request $request, CampaignInvitationService $invitations): RedirectResponse
    {
        $user = $this->findOrCreateUser('Demo Candidate', User::DEMO_CANDIDATE_EMAIL);
        $invitations->acceptPendingDemoCandidateInvitations($user);

        return $this->login($request, $user);
    }

    private function findOrCreateUser(string $name, string $email): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name],
        );
    }

    private function login(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, remember: true);
        $request->session()->forget('url.intended');

        return to_route('dashboard');
    }
}
