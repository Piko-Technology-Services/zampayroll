<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Forgot password -> emailed reset link -> choose a new password.
 *
 * Tenant aware: on a dedicated sub-domain the broker works against that
 * company's database. On the main domain, if the email belongs to a dedicated
 * workspace we quietly send the link from that workspace instead.
 */
class PasswordResetController extends Controller
{
    public function request(Request $request)
    {
        return view('auth.forgot-password', [
            'workspace' => Tenancy::dedicatedCompany(),
            'prefillEmail' => $request->query('email'),
        ]);
    }

    public function email(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $email = Str::lower($request->input('email'));

        $send = fn () => Password::sendResetLink(['email' => $email]);

        $status = $send();

        if ($status === Password::INVALID_USER && ! Tenancy::isDedicated()
            && ($company = Tenancy::directoryCompanyFor($email)) && $company->isActive() && $company->database_name) {
            // Fresh broker so the reset token is stored in the workspace's own database.
            $status = Tenancy::runInDedicated($company, fn () => (new \Illuminate\Auth\Passwords\PasswordBrokerManager(app()))
                ->broker()->sendResetLink(['email' => $email]));
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors([
                'email' => 'We just sent you a link — please wait a minute before asking for another one.',
            ]);
        }

        // Same answer whether or not the account exists (prevents email enumeration).
        return back()->with('status', 'If an account exists for ' . e($email) . ', a reset link is on its way. It expires in 60 minutes. 📬');
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
            'workspace' => Tenancy::dedicatedCompany(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            [
                'email'                 => Str::lower($request->input('email')),
                'password'              => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token'                 => $request->input('token'),
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password'             => Hash::make($password),
                    'remember_token'       => Str::random(60),
                    'must_change_password' => false,
                    // Following the link proves the person controls the mailbox.
                    'email_verified_at'    => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login', ['email' => $request->input('email')])
                ->with('success', '🔐 Password updated! Sign in with your new password.');
        }

        return back()->withInput($request->only('email'))->withErrors([
            'email' => $status === Password::INVALID_TOKEN
                ? 'This reset link is invalid or has expired. Request a new one below.'
                : 'We couldn\'t reset the password for that email address.',
        ]);
    }
}
