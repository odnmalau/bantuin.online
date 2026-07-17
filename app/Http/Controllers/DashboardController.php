<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\CampaignInvitationService;
use App\Services\RankingOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the authenticated user dashboard.
     */
    public function __invoke(
        Request $request,
        RankingOverview $rankingOverview,
        CampaignInvitationService $campaignInvitations,
    ): RedirectResponse|Response {
        $user = $request->user();

        if ($user?->can('viewAny', Campaign::class)) {
            return Inertia::render('dashboard', [
                'overview' => Inertia::defer(fn (): array => $rankingOverview->build($user->current_team_id)),
                'personalLanding' => false,
            ]);
        }

        if ($user !== null && $campaignInvitations->userHasCandidateWork($user)) {
            return to_route('candidate.exam');
        }

        return Inertia::render('dashboard', [
            'overview' => null,
            'personalLanding' => true,
        ]);
    }
}
