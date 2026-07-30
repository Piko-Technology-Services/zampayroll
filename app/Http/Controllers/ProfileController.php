<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{


    public function index(){
        $user = auth()->user();
        $company = $user->company;

        $teamMembers = collect();
        $pendingInvitations = collect();

        if ($company && $user->isCompanyAdmin()) {
            $teamMembers = User::where('company_id', $company->id)
                ->orderBy('name')
                ->get();

            $pendingInvitations = Invitation::where('company_id', $company->id)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get();
        }

        return view('dashboard.profile', [
            'user' => $user,
            'company' => $company,
            'teamMembers' => $teamMembers,
            'pendingInvitations' => $pendingInvitations,
            'invitableRoles' => User::INVITABLE_ROLES,
        ]);
    }

    /**
     * Show the profile & company settings page.
     */
    public function edit()
    {
        $user = auth()->user();
        $company = $user->company;

        // Company data isolation: only ever load team members / invitations for the user's own company.
        $teamMembers = collect();
        $pendingInvitations = collect();

        if ($company && $user->isCompanyAdmin()) {
            $teamMembers = User::where('company_id', $company->id)
                ->orderBy('name')
                ->get();

            $pendingInvitations = Invitation::where('company_id', $company->id)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get();
        }

        return view('dashboard.profile', [
            'user' => $user,
            'company' => $company,
            'teamMembers' => $teamMembers,
            'pendingInvitations' => $pendingInvitations,
            'invitableRoles' => User::INVITABLE_ROLES,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();

        // Note: role is intentionally NOT editable here. Role changes for a user
        // are a company-admin managed action (via Team Members), never self-service,
        // to prevent privilege escalation.
        $user->update($request->only(['name', 'email', 'position']));

        return back()->with('success', 'Your profile has been updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $request->user()->update([
            'password' => Hash::make($request->input('new_password')),
        ]);

        return back()->with('success', 'Your password has been updated successfully.');
    }
}
