<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session login for the Vue dashboard (Sanctum SPA authentication).
 *
 * The SPA first calls GET /sanctum/csrf-cookie, then posts here. On success
 * Laravel issues an HttpOnly session cookie; every later /api request from
 * the same origin is authenticated by that cookie.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // Prevent session fixation. Only stateful (browser) requests carry
        // a session; anything else simply receives no cookie.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return new UserResource(Auth::guard('web')->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
