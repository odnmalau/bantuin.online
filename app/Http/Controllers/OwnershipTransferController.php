<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOwnershipTransferRequest;
use App\Models\OwnershipTransfer;
use App\Models\TeamMembership;
use App\Models\User;
use App\OwnershipTransferStatus;
use App\Services\OwnershipTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OwnershipTransferController extends Controller
{
    public function store(StoreOwnershipTransferRequest $request, OwnershipTransferService $transfers): RedirectResponse
    {
        $team = $request->user()->currentTeam()->firstOrFail();
        $recipient = TeamMembership::query()->findOrFail($request->validated('membership_id'));
        $transfers->propose($team, $recipient, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ownership Transfer proposed.')]);

        return back();
    }

    public function show(Request $request, string $token, OwnershipTransferService $transfers): RedirectResponse
    {
        $transfer = $transfers->findByPlainToken($token);

        if ($transfer === null) {
            return to_route('login')->with('status', __('This Ownership Transfer link is invalid or expired.'));
        }

        if ($transfer->status === OwnershipTransferStatus::Pending && $transfer->expires_at->isPast()) {
            $transfer->update(['status' => OwnershipTransferStatus::Expired]);

            return to_route('login')->with('status', __('This Ownership Transfer link is invalid or expired.'));
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            $request->session()->put(OwnershipTransferService::SESSION_PENDING_ID, $transfer->id);

            return to_route('login')->with('status', __('Sign in with the intended recipient account.'));
        }

        if ($transfer->recipientMembership->user_id !== $user->id) {
            abort(403);
        }

        try {
            $transfers->accept($transfer, $user);
        } catch (ValidationException $exception) {
            /** @var string|null $status */
            $status = collect($exception->errors())->flatten()->first();

            return to_route('login')->with('status', $status);
        }

        return to_route('team-settings.edit');
    }

    public function destroy(OwnershipTransfer $ownershipTransfer, OwnershipTransferService $transfers): RedirectResponse
    {
        Gate::authorize('revoke', $ownershipTransfer);
        $transfers->revoke($ownershipTransfer, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ownership Transfer revoked.')]);

        return back();
    }
}
