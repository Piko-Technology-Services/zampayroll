<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\DefaultPayrollRules;
use App\Services\TenantProvisioner;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Registration is only complete once the email address is confirmed.
 *
 *  shared workspace     -> activate, seed payroll rules, sign in, go to dashboard
 *  dedicated workspace  -> create the company's database, move the admin into it
 *                          and hand the browser over to {subdomain}.zampayroll.com
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        $email = $request->session()->get('registered_email') ?? $request->query('email');

        return view('auth.verify-email', ['email' => $email]);
    }

    public function verify(Request $request, int $id, string $hash, TenantProvisioner $provisioner)
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1(Str::lower($user->getEmailForVerification())), $hash)) {
            return view('auth.verify-result', [
                'state' => 'invalid',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $company = $user->company;

        // ---------------------------------------------------------- shared
        if (! $company || $company->isShared()) {
            if ($company && $company->status !== Company::STATUS_ACTIVE) {
                $company->forceFill(['status' => Company::STATUS_ACTIVE])->save();
            }

            if ($company) {
                DefaultPayrollRules::ensureFor((int) $company->id);
            }

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->forget('registered_email');
            $user->forceFill(['last_login_at' => now()])->saveQuietly();

            return redirect()->route($user->homeRoute())
                ->with('celebrate', true)
                ->with('success', '🎉 Email confirmed — your ZamPayroll workspace is ready!');
        }

        // ------------------------------------------------------- dedicated
        if (! config('tenancy.auto_provision')) {
            $company->forceFill(['status' => Company::STATUS_AWAITING_DATABASE])->save();
            Log::notice("Tenancy: {$company->name} ({$company->subdomain}) is verified and waiting for a database. Run: php artisan tenancy:provision {$company->id}");

            return view('auth.verify-result', ['state' => 'awaiting', 'company' => $company]);
        }

        try {
            $company = $provisioner->provision($company);
        } catch (\Throwable $e) {
            report($e);

            return view('auth.verify-result', ['state' => 'failed', 'company' => $company]);
        }

        $handoff = Tenancy::runInDedicated($company, fn () => URL::temporarySignedRoute(
            'workspace.handoff', now()->addMinutes(10), ['user' => $user->id, 'welcome' => 1]
        ));

        return view('auth.verify-result', [
            'state'   => 'dedicated_ready',
            'company' => $company,
            'handoff' => $handoff,
        ]);
    }

    /**
     * POST email -> resend the confirmation link (always answers the same way,
     * so it can't be used to discover which emails have accounts).
     */
    public function resend(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', Str::lower($request->input('email')))->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $request->session()->put('registered_email', $request->input('email'));

        return redirect()->route('verification.notice')
            ->with('status', 'If that address is waiting for confirmation, a fresh link is on its way. 📬');
    }
}
