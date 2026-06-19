<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Show the Google sign-in page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canUseGoogle' => filled(config('services.google.client_id')),
            'status' => $request->session()->get('status'),
        ]);
    }
}
