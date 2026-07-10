<?php

namespace App\Http\Controllers;

use App\Services\RankingOverview;
use App\UserRole;
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

        if ($user?->role === UserRole::Admin) {
            return Inertia::render('dashboard', [
                'overview' => Inertia::defer(fn (): array => $rankingOverview->build()),
            ]);
        }

        return Inertia::render('dashboard', [
            'overview' => null,
        ]);
    }
}
