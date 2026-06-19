<?php

namespace App\Services\Auth;

use App\Models\User;
use App\UserRole;
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

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user === null) {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user !== null) {
            $user->forceFill([
                'google_id' => $googleId,
                'name' => $googleUser->getName() ?? $user->name,
                'email' => $email,
            ])->save();

            return $user;
        }

        return User::query()->create([
            'name' => $googleUser->getName() ?? Str::before($email, '@'),
            'email' => $email,
            'google_id' => $googleId,
            'role' => UserRole::Candidate,
        ]);
    }
}
