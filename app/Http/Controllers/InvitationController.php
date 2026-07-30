<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteInvitationRequest;
use App\Http\Requests\InviteUserRequest;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class InvitationController extends Controller
{
    /**
     * Company admin invites a new team member by email.
     */
    public function store(InviteUserRequest $request)
    {
        Log::info('InvitationController@store executed');

        $inviter = $request->user();
        $company = $inviter->company;

        Log::info('InvitationController@store executed', [
            'inviter_id' => $inviter->id,
            'company_id' => optional($company)->id,
            'email' => $request->input('email'),
        ]);

        abort_unless($company, 404);

        [$invitation, $rawToken] = DB::transaction(function () use ($company, $inviter, $request) {
            return Invitation::issue(
                $company,
                $inviter,
                $request->input('email'),
                $request->input('role'),
                $request->input('position')
            );
        });

        Mail::to($invitation->email)->send(new InvitationMail($invitation, $rawToken, $company));

        return back()->with('success', "An invitation has been sent to {$invitation->email}.");
    }

    /**
     * Company admin revokes a pending invitation.
     */
    public function revoke(Request $request, Invitation $invitation)
    {
        $user = $request->user();

        // Company data isolation: an admin may only revoke invitations belonging to their own company.
        abort_unless($user->belongsToCompany($invitation->company_id), 403);

        $invitation->update(['status' => 'revoked']);

        return back()->with('success', 'The invitation has been revoked.');
    }

    /**
     * GET /invitations/{token}/accept
     * Public entry point clicked from the invitation email. The token is a
     * random, single-use, expiring secret — no company id or invitation id is exposed.
     */
    public function accept(string $token)
    {
        $invitation = Invitation::findValidByRawToken($token);

        if (! $invitation) {
            return view('invitations.accept', [
                'invalid' => true,
                'token' => $token,
            ]);
        }

        $existingUser = User::where('email', $invitation->email)->first();

        // If the visitor is already logged in under a different account, don't silently
        // merge company access onto whoever happens to be signed in on this browser.
        if (Auth::check() && Auth::user()->email !== $invitation->email) {
            return view('invitations.accept', [
                'invalid' => false,
                'wrongAccount' => true,
                'invitation' => $invitation,
                'token' => $token,
            ]);
        }

        return view('invitations.accept', [
            'invalid' => false,
            'wrongAccount' => false,
            'invitation' => $invitation,
            'existingUser' => $existingUser,
            'isNewUser' => $existingUser === null,
            'token' => $token,
            'invitableRoles' => User::INVITABLE_ROLES,
        ]);
    }

    /**
     * POST /invitations/{token}/complete
     * Finalizes the invitation: either links an existing account to the company,
     * or creates a brand new account, always assigning the SAME company_id as the inviter.
     */
    public function complete(CompleteInvitationRequest $request, string $token)
    {
        $invitation = Invitation::findValidByRawToken($token);

        if (! $invitation) {
            return redirect()->route('invitations.accept', $token)
                ->withErrors(['token' => 'This invitation link is invalid or has expired.']);
        }

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser && Auth::check() && Auth::id() !== $existingUser->id) {
            abort(403, 'This invitation was sent to a different email address.');
        }

        $user = DB::transaction(function () use ($request, $invitation, $existingUser) {
            if ($existingUser) {
                // Existing account: link it to the inviting company with the confirmed role/position.
                $existingUser->update([
                    'company_id' => $invitation->company_id,
                    'role'       => $request->input('role'),
                    'position'   => $request->input('position'),
                ]);
                $user = $existingUser;
            } else {
                // No account yet: create one now, linked to the SAME company_id as the inviter.
                $user = User::create([
                    'company_id' => $invitation->company_id,
                    'name'       => $request->input('name'),
                    'email'      => $invitation->email,
                    'password'   => Hash::make($request->input('password')),
                    'role'       => $request->input('role'),
                    'position'   => $request->input('position'),
                ]);
            }

            $invitation->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            return $user;
        });

        Auth::login($user);

        return redirect()->route('profile.edit')
            ->with('success', 'Welcome! Your account is now linked to the company.');
    }
}
