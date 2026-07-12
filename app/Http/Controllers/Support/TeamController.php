<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\CampaignInvitation;
use App\Models\ExamSession;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $teams = Team::query()
            ->select(['id', 'name', 'status', 'created_at', 'deactivated_at'])
            ->when($request->string('search')->trim()->isNotEmpty(), fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($request->string('search')->trim()->toString()).'%']))
            ->withCount(['memberships', 'invitations', 'campaigns'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('support/teams/index', ['teams' => $teams]);
    }

    public function show(Team $team): Response
    {
        $campaignIds = $team->campaigns()->pluck('id');

        return Inertia::render('support/teams/show', [
            'team' => $team->only(['id', 'name', 'status', 'created_at', 'deactivated_at']),
            'memberships' => $team->memberships()->with('user:id,name,email')->orderBy('id')->get()->map(fn (TeamMembership $membership): array => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'started_at' => $membership->started_at->toISOString(),
                'ended_at' => $membership->ended_at?->toISOString(),
                'last_used_at' => $membership->last_used_at?->toISOString(),
            ])->all(),
            'invitations' => $team->invitations()->orderByDesc('id')->get()->map(fn (TeamInvitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'status' => $invitation->status->value,
                'actor_context' => $invitation->actor_context,
                'invited_by' => $invitation->invited_by,
                'expires_at' => $invitation->expires_at->toISOString(),
                'accepted_at' => $invitation->accepted_at?->toISOString(),
                'revoked_at' => $invitation->revoked_at?->toISOString(),
            ])->all(),
            'counts' => [
                'campaigns' => $campaignIds->count(),
                'campaign_invitations' => CampaignInvitation::query()->whereIn('campaign_id', $campaignIds)->count(),
                'assessments' => Assessment::query()->whereIn('campaign_id', $campaignIds)->count(),
                'exam_sessions' => ExamSession::query()->whereIn('campaign_id', $campaignIds)->count(),
            ],
        ]);
    }
}
