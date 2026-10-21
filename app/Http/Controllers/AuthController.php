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
     * Sign-in = email + password. That's it.
     *
     * We find every company the email belongs to (shared users table and the
     * dedicated-workspace directory) and check the password against each.
     *  - one match  → signed straight in (or handed off to the dedicated workspace)
     *  - several    → a quick "which company?" picker (no second password)
     * The company code still works if someone types it, but is never required.
     */
    public function login(Request $request)
    {
        $request->merge(['company_code' => strtoupper(trim(preg_replace('/\s+/', '', (string) $request->input('company_code'))))]);
        $request->validate([
            'company_code' => 'nullable|string|max:16',
            'email'        => 'required|email',
            'password'     => 'required',
        ]);

        $email = strtolower(trim($request->input('email')));
        $password = (string) $request->input('password');
        $remember = $request->boolean('remember');
        $fail = fn (string $msg) => back()->withInput($request->only('email', 'remember'))->withErrors(['email' => $msg]);

        // On a dedicated sub-domain only that workspace's accounts count.
        if (Tenancy::isDedicated()) {
            return $this->attemptHere(['email' => $email, 'password' => $password], $remember, $request)
                ?? $fail('That email and password don\'t match. Check them and try again.');
        }

        $matches = $this->matchingAccounts($email, $password, $request->input('company_code') ?: null);

        if ($matches === []) {
            return $fail('That email and password don\'t match. Check them and try again.');
        }

        if (count($matches) === 1) {
            return $this->finishLogin($matches[0], $remember, $request);
        }

        // Several companies: remember the verified matches for 5 minutes and let them pick.
        $request->session()->put('login_choices', [
            'email'   => $email,
            'remember' => $remember,
            'expires' => now()->addMinutes(5)->timestamp,
            'matches' => $matches,
        ]);

        return redirect()->route('login.choose');
    }

    public function showChooser(Request $request)
    {
        $choices = $request->session()->get('login_choices');
        if (! $choices || $choices['expires'] < now()->timestamp) {
            return redirect()->route('login')->withErrors(['email' => 'Please sign in again.']);
        }

        return view('auth.choose-company', ['choices' => $choices]);
    }

    public function choose(Request $request)
    {
        $choices = $request->session()->pull('login_choices');
        $request->validate(['company_id' => 'required|integer']);

        if (! $choices || $choices['expires'] < now()->timestamp) {
            return redirect()->route('login')->withErrors(['email' => 'That took a little long — please sign in again.']);
        }

        $match = collect($choices['matches'])->firstWhere('company_id', (int) $request->input('company_id'));
        abort_unless($match, 403);

        return $this->finishLogin($match, (bool) $choices['remember'], $request);
    }

    /**
     * Every account (in any company) whose email + password match.
     *
     * @return array<int, array{company_id: ?int, company: string, user_id: int, dedicated: bool, role: string}>
     */
    private function matchingAccounts(string $email, string $password, ?string $code): array
    {
        $landlord = Tenancy::landlordConnection();
        $codeCompanyId = $code ? Company::on($landlord)->where('company_code', $code)->value('id') : null;
        $matches = [];

        // Shared-workspace staff, employees and platform administrators
        $users = User::on($landlord)->whereRaw('LOWER(email) = ?', [$email])->get();
        foreach ($users as $user) {
            if (! Hash::check($password, $user->password)) {
                continue;
            }
            $company = $user->company_id ? Company::on($landlord)->find($user->company_id) : null;
            if ($company && $company->isDedicated()) {
                continue; // dedicated accounts are checked in their own database below
            }
            $matches[] = [
                'company_id' => $user->company_id ? (int) $user->company_id : null,
                'company'    => $company?->name ?? 'ZamPayroll administration',
                'user_id'    => (int) $user->id,
                'dedicated'  => false,
                'role'       => $user->roleName(),
            ];
        }

        // Dedicated workspaces (their users live in their own database)
        $dedicated = \Illuminate\Support\Facades\DB::connection($landlord)->table('tenant_user_directory')->where('email', $email)->pluck('company_id');
        foreach (Company::on($landlord)->whereIn('id', $dedicated)->get() as $company) {
            if (! $company->isDedicated() || ! $company->isActive() || ! $company->database_name) {
                continue;
            }
            $user = Tenancy::runInDedicated($company, function () use ($email, $password) {
                $u = User::whereRaw('LOWER(email) = ?', [$email])->first();

                return $u && Hash::check($password, $u->password) ? ['id' => (int) $u->id, 'role' => $u->roleName()] : null;
            });
            if ($user) {
                $matches[] = ['company_id' => (int) $company->id, 'company' => $company->name, 'user_id' => $user['id'], 'dedicated' => true, 'role' => $user['role']];
            }
        }

        if ($codeCompanyId) {
            $narrowed = array_values(array_filter($matches, fn ($m) => $m['company_id'] === (int) $codeCompanyId));
            if ($narrowed) {
                return $narrowed;
            }
        }

        return $matches;
    }

    private function finishLogin(array $match, bool $remember, Request $request)
    {
        $landlord = Tenancy::landlordConnection();

        if ($match['dedicated']) {
            $company = Company::on($landlord)->findOrFail($match['company_id']);
            $handoff = Tenancy::runInDedicated($company, function () use ($match, $remember) {
                $user = User::find($match['user_id']);
                if (! $user) {
                    return null;
                }
                if (! $user->hasVerifiedEmail()) {
                    return 'unverified';
                }

                return URL::temporarySignedRoute('workspace.handoff', now()->addMinutes(2), ['user' => $user->id, 'remember' => $remember ? 1 : 0]);
            });

            if ($handoff === 'unverified') {
                return back()->withErrors(['email' => 'Please confirm your email address first — check your inbox for the link we sent you.']);
            }

            return $handoff ? redirect()->away($handoff) : redirect()->route('login')->withErrors(['email' => 'Please sign in again.']);
        }

        $user = User::on($landlord)->findOrFail($match['user_id']);

        if (! $user->hasVerifiedEmail()) {
            return back()->withInput($request->only('email'))
                ->with('unverified_email', $user->email)
                ->withErrors(['email' => 'Please confirm your email address first — check your inbox for the link we sent you.']);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        if ($user->company_id) {
            $code = Company::on($landlord)->whereKey($user->company_id)->value('company_code');
            $code && Cookie::queue('zp_company_code', $code, 60 * 24 * 365);
        }

        return redirect()->intended(route($user->homeRoute()))
            ->with('success', 'Welcome back, ' . strtok($user->name, ' ') . '!');
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
