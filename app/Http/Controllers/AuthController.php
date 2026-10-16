<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
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
            'prefillCode' => $request->query('code', $request->cookie('zp_company_code')),
        ]);
    }

    /**
     * Sign-in = company code + email + password.
     *
     * The company code tells us which company (and, for dedicated workspaces,
     * which database) to check. On a dedicated sub-domain the code is implied.
     * Unconfirmed emails are refused with a "resend confirmation" option.
     */
    public function login(Request $request)
    {
        $dedicatedHost = Tenancy::isDedicated();

        $request->merge(['company_code' => strtoupper(trim(preg_replace('/\s+/', '', (string) $request->input('company_code'))))]);

        $request->validate([
            'company_code' => $dedicatedHost ? 'nullable' : 'required|string|max:16',
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'company_code.required' => 'Enter your company code — your administrator can find it in Settings → Team & roles.',
        ]);

        $email = strtolower($request->input('email'));
        $password = $request->input('password');
        $remember = $request->boolean('remember');
        $fail = fn (string $field, string $msg) => back()->withInput($request->only('email', 'company_code', 'remember'))->withErrors([$field => $msg]);

        // ---------------------------------------------------- dedicated host
        if ($dedicatedHost) {
            return $this->attemptHere(['email' => $email, 'password' => $password], $remember, $request)
                ?? $fail('email', 'Those details don\'t match our records. Double-check your email and password.');
        }

        // ---------------------------------------------------- main domain
        $company = Company::on(Tenancy::landlordConnection())->where('company_code', $request->input('company_code'))->first();

        if (! $company) {
            return $fail('company_code', 'We don\'t recognise that company code. Check it with your administrator.');
        }

        Cookie::queue('zp_company_code', $company->company_code, 60 * 24 * 365);

        if ($company->isDedicated()) {
            if (! $company->isActive() || ! $company->database_name) {
                return $fail('company_code', 'Your company\'s workspace isn\'t ready yet. Please try again later.');
            }

            $handoff = Tenancy::runInDedicated($company, function () use ($email, $password, $remember) {
                $creds = ['email' => $email, 'password' => $password];
                if (! Auth::validate($creds)) {
                    return null;
                }
                $user = Auth::getProvider()->retrieveByCredentials(['email' => $email]);
                if (! $user->hasVerifiedEmail()) {
                    return 'unverified';
                }

                return URL::temporarySignedRoute('workspace.handoff', now()->addMinutes(2), ['user' => $user->id, 'remember' => $remember ? 1 : 0]);
            });

            if ($handoff === 'unverified') {
                return $fail('email', 'Please confirm your email address first — check your inbox for the link we sent you.');
            }

            return $handoff ? redirect()->away($handoff)
                : $fail('email', 'Those details don\'t match our records for this company.');
        }

        return $this->attemptHere(['email' => $email, 'password' => $password, 'company_id' => $company->id], $remember, $request)
            ?? $fail('email', 'Those details don\'t match our records for this company.');
    }

    /**
     * Try the credentials against the current database. Returns a redirect on
     * success / unverified, or null when the details are wrong.
     */
    private function attemptHere(array $credentials, bool $remember, Request $request)
    {
        if (! Auth::validate($credentials)) {
            return null;
        }

        $user = Auth::getProvider()->retrieveByCredentials(collect($credentials)->except('password')->all());

        if (! $user->hasVerifiedEmail()) {
            return back()->withInput($request->only('email', 'company_code'))
                ->with('unverified_email', $user->email)
                ->withErrors(['email' => 'Please confirm your email address first — check your inbox for the link we sent you.']);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route($user->homeRoute()))
            ->with('success', 'Welcome back, ' . strtok($user->name, ' ') . '!');
    }

    /* ------------------------------------------------------------------ */
    /*  Forgot company code                                                 */
    /* ------------------------------------------------------------------ */

    public function showCodeReminder()
    {
        return view('auth.company-code');
    }

    public function sendCodeReminder(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower($request->input('email'));
        $landlord = Tenancy::landlordConnection();

        $companyIds = \Illuminate\Support\Facades\DB::connection($landlord)->table('users')->whereRaw('LOWER(email) = ?', [$email])->pluck('company_id')
            ->merge(\Illuminate\Support\Facades\DB::connection($landlord)->table('tenant_user_directory')->where('email', $email)->pluck('company_id'))
            ->filter()->unique();

        $companies = Company::on($landlord)->whereIn('id', $companyIds)->get(['name', 'company_code']);

        if ($companies->isNotEmpty()) {
            try {
                \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\CompanyCodeReminderMail($companies));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'If ' . e($email) . ' belongs to a ZamPayroll account, we\'ve emailed the company code to it. 📬');
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
