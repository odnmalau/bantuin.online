<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\RankingOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the authenticated user dashboard.
     */
    public function __invoke(Request $request, RankingOverview $rankingOverview): Response
    {
        $user = $request->user();

        if ($user?->can('viewAny', Campaign::class)) {
            return Inertia::render('dashboard', [
                'overview' => Inertia::defer(fn (): array => $rankingOverview->build($user->current_team_id)),
                'personalLanding' => false,
            ]);
        }

        return Inertia::render('dashboard', [
            'overview' => null,
            'personalLanding' => true,
        ]);
    }
}
