<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lands on a dedicated workspace's sub-domain with a short-lived signed link
 * (generated after the password was checked on the main domain, or right after
 * the email was confirmed) and signs the user in there.
 */
class WorkspaceHandoffController extends Controller
{
    public function __invoke(Request $request, int $user)
    {
        abort_unless(Tenancy::isDedicated(), 404);

        $account = User::findOrFail($user);

        Auth::login($account, $request->boolean('remember'));
        $request->session()->regenerate();
        $account->forceFill(['last_login_at' => now()])->saveQuietly();

        $message = $request->boolean('welcome')
            ? '🎉 Your dedicated workspace is ready. Welcome to ZamPayroll!'
            : 'Welcome back, ' . strtok($account->name, ' ') . '!';

        return redirect()->route($account->homeRoute())->with('success', $message);
    }
}
