<?php

namespace App\Http\Middleware;

use App\Models\Campaign;
use App\Services\CampaignLifecycleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampaignDefinitionIsEditable
{
    public function __construct(private CampaignLifecycleService $lifecycle) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $campaign = $request->route('campaign');

        if ($campaign instanceof Campaign) {
            $this->lifecycle->assertDefinitionEditable($campaign);
        }

        return $next($request);
    }
}
