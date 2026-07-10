<?php

namespace App\Http\Controllers;

use App\Services\RankingOverview;
use App\Services\RecentCampaigns;
use App\UserRole;
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
        RecentCampaigns $recentCampaigns,
    ): Response {
        $user = $request->user();

        if ($user?->role === UserRole::Admin) {
            return Inertia::render('dashboard', [
                'overview' => Inertia::defer(function () use ($rankingOverview, $recentCampaigns): array {
                    $overview = $rankingOverview->build();

                    return [
                        'summary' => $overview['summary'],
                        'charts' => $overview['charts'],
                        'recent_campaigns' => $recentCampaigns->build(),
                    ];
                }),
            ]);
        }

        return Inertia::render('dashboard', [
            'overview' => null,
        ]);
    }
}
