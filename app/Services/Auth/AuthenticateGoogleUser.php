<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthenticateGoogleUser
{
    /**
     * Find or create a local user from a Google OAuth profile.
     */
    public function authenticate(SocialiteUser $googleUser): User
    {
        $googleId = (string) $googleUser->getId();
        $email = $googleUser->getEmail();

        if (blank($email)) {
            throw new GoogleAuthenticationException(__('Google did not return an email address for this account.'));
        }

        $email = mb_strtolower(trim($email));

        $user = User::withTrashed()
            ->where(function ($query) use ($googleId, $email): void {
                $query->where('google_id', $googleId)
                    ->orWhereRaw('LOWER(email) = ?', [$email]);
            })
            ->first();

        if ($user?->trashed()) {
            throw new GoogleAuthenticationException(__('This account has been closed and can no longer sign in.'));
        }

        $avatar = $googleUser->getAvatar();

        if ($user !== null) {
            $user->forceFill([
                'google_id' => $googleId,
                'name' => $googleUser->getName() ?? $user->name,
                'email' => $email,
                'avatar' => filled($avatar) ? $avatar : $user->avatar,
            ])->save();

            return $user;
        }

        return User::query()->create([
            'name' => $googleUser->getName() ?? Str::before($email, '@'),
            'email' => $email,
            'google_id' => $googleId,
            'avatar' => filled($avatar) ? $avatar : null,
        ]);
    }
}
