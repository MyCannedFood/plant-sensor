<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Could not authenticate with Google. Please try again.',
            ]);
        }

        // Find or create user
        $user = User::firstWhere('google_id', $googleUser->getId());

        if (! $user) {
            // Check if user exists with same email
            $user = User::firstWhere('email', $googleUser->getEmail());

            if ($user) {
                // Link Google account to existing user
                $user->google_id = $googleUser->getId();
                $user->avatar = $googleUser->getAvatar();
                $user->save();
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => null, // No password for OAuth users
                    'email_verified_at' => now(),
                ]);
            }
        }

        // Log the user in (creates session cookie)
        Auth::guard('web')->login($user, true);

        // Regenerate session to prevent fixation
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Redirect to frontend dashboard
        return redirect(env('FRONTEND_URL'));
    }
}