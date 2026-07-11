<?php

namespace App\Http\Middleware;

use App\Models\Assessment;
use App\Models\Campaign;
use App\Models\CampaignQuestion;
use App\Models\CampaignSection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentTeamMember
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->current_team_id === null) {
            abort(403);
        }

        if ($user->activeTeamMemberships()->where('team_id', $user->current_team_id)->doesntExist()) {
            abort(403);
        }

        $campaign = $request->route('campaign');
        $assessment = $request->route('assessment');

        if ($campaign instanceof Campaign && $campaign->team_id !== $user->current_team_id) {
            abort(404);
        }

        if ($assessment instanceof Assessment && $assessment->campaign()->where('team_id', $user->current_team_id)->doesntExist()) {
            abort(404);
        }

        $section = $request->route('section');
        $question = $request->route('question');

        if ($section instanceof CampaignSection && (! $campaign instanceof Campaign || $section->campaign_id !== $campaign->id)) {
            abort(404);
        }

        if ($question instanceof CampaignQuestion && (! $campaign instanceof Campaign || $question->campaign_id !== $campaign->id)) {
            abort(404);
        }

        if ($campaign instanceof Campaign) {
            $ability = $request->isMethodSafe() ? 'view' : ($request->isMethod('delete') ? 'delete' : 'update');
            Gate::authorize($ability, $campaign);
        } elseif ($assessment instanceof Assessment) {
            Gate::authorize($request->isMethodSafe() ? 'view' : 'update', $assessment);
        } elseif ($assessment === null) {
            Gate::authorize($request->isMethodSafe() ? 'viewAny' : 'create', Campaign::class);
        }

        return $next($request);
    }
}
