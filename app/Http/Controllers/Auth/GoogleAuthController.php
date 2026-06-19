<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthenticateGoogleUser;
use App\Services\Auth\GoogleAuthenticationException;
use App\Support\Auth\PostLoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(
        Request $request,
        AuthenticateGoogleUser $authenticateGoogleUser,
        PostLoginRedirect $postLoginRedirect,
    ): RedirectResponse {
        if ($request->filled('error')) {
            return $this->redirectToLogin(__('Google sign-in was cancelled.'));
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return $this->redirectToLogin(__('Your Google sign-in session expired. Please try again.'));
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectToLogin(__('Unable to sign in with Google. Please try again.'));
        }

        try {
            $user = $authenticateGoogleUser->authenticate($googleUser);
        } catch (GoogleAuthenticationException $exception) {
            return $this->redirectToLogin($exception->getMessage());
        }

        Auth::login($user, remember: true);

        return $postLoginRedirect->toResponse($request, $user);
    }

    private function redirectToLogin(string $status): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('status', $status);
    }
}
