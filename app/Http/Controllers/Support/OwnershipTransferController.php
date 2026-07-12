<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreOwnershipTransferRequest;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Services\OwnershipTransferService;
use Illuminate\Http\RedirectResponse;

class OwnershipTransferController extends Controller
{
    public function store(StoreOwnershipTransferRequest $request, Team $team, OwnershipTransferService $transfers): RedirectResponse
    {
        $transfers->proposeByOperator($team, TeamMembership::query()->findOrFail($request->integer('membership_id')), $request->user(), $request->string('reason')->trim()->toString());

        return back();
    }
}
