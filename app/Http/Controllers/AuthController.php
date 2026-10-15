<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    /**
     * Show login page
     */
    public function showLogin(Request $request)
    {
        return view('auth.login', [
            'workspace' => Tenancy::dedicatedCompany(),
            'prefillEmail' => $request->query('email'),
        ]);
    }

    /**
     * Handle login request.
     *
     * 1. Normal sign-in against the current database (main domain = shared
     *    tenants, sub-domain = that company's own database).
     * 2. Unconfirmed emails are refused with a "resend confirmation" option.
     * 3. On the main domain, people who belong to a dedicated workspace are
     *    checked against THEIR database and handed over to their sub-domain.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::validate($credentials)) {
            $user = Auth::getProvider()->retrieveByCredentials(['email' => $credentials['email']]);

            if (! $user->hasVerifiedEmail()) {
                return back()->withInput($request->only('email'))
                    ->with('unverified_email', $user->email)
                    ->withErrors(['email' => 'Please confirm your email address first — check your inbox for the link we sent you.']);
            }

            $company = $user->company;

            if (! Tenancy::isDedicated() && $company && $company->isDedicated()) {
                // Shouldn't happen (users move to the tenant DB), but never sign a
                // dedicated company's user into the shared database.
                return redirect()->away($company->workspaceUrl('/login?email=' . urlencode($user->email)));
            }

            Auth::login($user, $remember);
            $request->session()->regenerate();
            $user->forceFill(['last_login_at' => now()])->saveQuietly();

            return redirect()->intended(route($user->homeRoute()))
                ->with('success', 'Welcome back, ' . strtok($user->name, ' ') . '!');
        }

        // Main domain: maybe this person works for a dedicated workspace.
        if (! Tenancy::isDedicated() && ($company = Tenancy::directoryCompanyFor($credentials['email']))
            && $company->isActive() && $company->database_name) {

            $handoff = Tenancy::runInDedicated($company, function () use ($credentials, $remember) {
                if (! Auth::validate($credentials)) {
                    return null;
                }

                $user = Auth::getProvider()->retrieveByCredentials(['email' => $credentials['email']]);

                if (! $user->hasVerifiedEmail()) {
                    return 'unverified';
                }

                return URL::temporarySignedRoute('workspace.handoff', now()->addMinutes(2), [
                    'user' => $user->id,
                    'remember' => $remember ? 1 : 0,
                ]);
            });

            if ($handoff === 'unverified') {
                return back()->withInput($request->only('email'))
                    ->withErrors(['email' => 'Please confirm your email address first — check your inbox for the link we sent you.']);
            }

            if ($handoff) {
                return redirect()->away($handoff);
            }
        }

        return back()->withErrors([
            'email' => 'Those details don\'t match our records. Double-check your email and password.',
        ])->withInput($request->only('email', 'remember'));
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been signed out. See you soon! 👋');
    }
}
