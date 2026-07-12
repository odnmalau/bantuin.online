<?php

namespace App\Http\Middleware;

use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\Team;
use App\Models\User;
use App\TeamStatus;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'docsUrl' => filled(config('app.docs_url'))
                ? (string) config('app.docs_url')
                : null,
            'auth' => [
                'user' => $this->sharedUser($request),
                'teams' => $this->sharedTeams($request),
                'currentTeam' => $this->sharedCurrentTeam($request),
                'capabilities' => $this->sharedCapabilities($request),
                'readOnly' => $this->isReadOnly($request),
                'platformOperator' => $request->user()?->isPlatformOperator() ?? false,
            ],
            'sidebarOpen' => $this->sidebarOpen($request),
            'authFeatures' => [
                'google' => filled(config('services.google.client_id')),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, avatar: ?string}|null
     */
    private function sharedUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->only(['id', 'name', 'email', 'avatar']);
    }

    /**
     * @return array{id: int, name: string, status: string, role: string}|null
     */
    private function sharedCurrentTeam(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User || $user->current_team_id === null) {
            return null;
        }

        $team = $user->currentTeam()->first();

        if ($team === null) {
            return null;
        }

        $membership = $user->activeTeamMemberships()
            ->where('team_id', $team->id)
            ->first();

        if ($membership === null) {
            return null;
        }

        return [
            'id' => $team->id,
            'name' => $team->name,
            'status' => $team->status->value,
            'role' => $membership->role->value,
        ];
    }

    /**
     * @return list<array{id: int, name: string, status: string, role: string}>
     */
    private function sharedTeams(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [];
        }

        return $user->activeTeamMemberships()
            ->whereHas('team')
            ->with('team:id,name,status')
            ->get()
            ->sortBy(fn ($membership) => mb_strtolower($membership->team->name))
            ->map(fn ($membership): array => [
                'id' => $membership->team->id,
                'name' => $membership->team->name,
                'status' => $membership->team->status->value,
                'role' => $membership->role->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{createTeam: bool, viewCampaigns: bool, manageCampaigns: bool, renameTeam: bool, candidateWork: bool}
     */
    private function sharedCapabilities(Request $request): array
    {
        $user = $request->user();
        $team = $user?->currentTeam()->first();

        return [
            'createTeam' => $user !== null,
            'viewCampaigns' => $user?->can('viewAny', Campaign::class) ?? false,
            'manageCampaigns' => $user?->can('create', Campaign::class) ?? false,
            'renameTeam' => $team instanceof Team && ($user?->can('update', $team) ?? false),
            'candidateWork' => $user instanceof User && CampaignInvitation::query()
                ->acceptedForUser($user)
                ->exists(),
        ];
    }

    private function isReadOnly(Request $request): bool
    {
        return $request->user()?->currentTeam()->where('status', TeamStatus::Deactivated)->exists() ?? false;
    }

    private function sidebarOpen(Request $request): bool
    {
        if (! $request->hasCookie('sidebar_state')) {
            return true;
        }

        return $request->cookie('sidebar_state') === 'true';
    }
}
