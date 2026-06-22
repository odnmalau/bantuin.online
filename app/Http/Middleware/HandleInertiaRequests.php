<?php

namespace App\Http\Middleware;

use App\Models\User;
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
            'auth' => [
                'user' => $this->sharedUser($request),
            ],
            'sidebarOpen' => $this->sidebarOpen($request),
            'authFeatures' => [
                'google' => filled(config('services.google.client_id')),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, role: string}|null
     */
    private function sharedUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return [
            ...$user->only(['id', 'name', 'email']),
            'role' => $user->role->value,
        ];
    }

    private function sidebarOpen(Request $request): bool
    {
        if (! $request->hasCookie('sidebar_state')) {
            return true;
        }

        return $request->cookie('sidebar_state') === 'true';
    }
}
