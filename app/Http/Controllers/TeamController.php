<?php

namespace App\Http\Controllers;

use App\Models\CompanyRole;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Settings → Team & roles: who has access, with which role, and what each role may do.
 */
class TeamController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->company;
        CompanyRole::options(); // make sure default roles exist

        return view('dashboard.team.index', [
            'company'     => $company,
            'roles'       => CompanyRole::orderByDesc('is_system')->orderBy('id')->get(),
            'roleCounts'  => User::where('company_id', $company->id)->whereNull('employee_id')
                                ->selectRaw('role, COUNT(*) c')->groupBy('role')->pluck('c', 'role'),
            'members'     => User::where('company_id', $company->id)->whereNull('employee_id')->orderBy('name')->get(),
            'invitations' => Invitation::where('status', 'pending')->where('expires_at', '>', now())->latest()->get(),
            'groups'      => Permissions::GROUPS,
        ]);
    }

    public function updateMember(Request $request, User $user)
    {
        $this->sameCompany($request, $user);

        $data = $request->validate([
            'role' => ['required', Rule::in(CompanyRole::pluck('key')->all())],
            'position' => ['nullable', 'string', 'max:255'],
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== 'company_admin') {
            return back()->with('error', 'You can\'t remove your own administrator role. Ask another administrator to do it.');
        }

        if ($user->isCompanyAdmin() && $data['role'] !== 'company_admin' && $this->adminCount($user->company_id) <= 1) {
            return back()->with('error', 'Every company needs at least one administrator.');
        }

        $user->update($data);

        return back()->with('success', "{$user->name} is now " . CompanyRole::where('key', $data['role'])->value('name') . '.');
    }

    public function removeMember(Request $request, User $user)
    {
        $this->sameCompany($request, $user);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You can\'t remove yourself.');
        }
        if ($user->isCompanyAdmin() && $this->adminCount($user->company_id) <= 1) {
            return back()->with('error', 'Every company needs at least one administrator.');
        }

        $user->delete();

        return back()->with('success', "{$user->name} no longer has access.");
    }

    public function storeRole(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'copy_from' => ['nullable', 'string'],
        ]);

        $key = Str::slug($data['name'], '_');
        $base = $key;
        for ($i = 2; CompanyRole::where('key', $key)->exists() || isset(Permissions::DEFAULT_ROLES[$key]); $i++) {
            $key = $base . '_' . $i;
        }

        $template = $data['copy_from'] ? CompanyRole::where('key', $data['copy_from'])->first() : null;

        CompanyRole::create([
            'key' => $key,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $template && ! $template->isAdmin() ? $template->permissions : ['employees.view'],
            'is_system' => false,
        ]);

        return redirect()->route('team.index', ['#roles'])->with('success', "Role \"{$data['name']}\" created — tick what it can do below.");
    }

    /**
     * Save the whole permission matrix at once: permissions[role_key][] = permission.
     */
    public function updateRoles(Request $request)
    {
        $request->validate(['permissions' => ['nullable', 'array']]);
        $valid = Permissions::all();

        foreach (CompanyRole::get() as $role) {
            if ($role->isAdmin()) {
                continue; // administrators always keep everything
            }
            $chosen = array_values(array_intersect($valid, (array) $request->input("permissions.{$role->key}", [])));
            $role->update(['permissions' => $chosen]);
        }

        return back()->with('success', 'Role permissions saved. Changes apply on each person\'s next page load.');
    }

    public function destroyRole(CompanyRole $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'Built-in roles can\'t be deleted — untick their permissions instead.');
        }
        if (User::where('role', $role->key)->exists()) {
            return back()->with('error', 'Move the people in this role to another role first.');
        }

        $role->delete();

        return back()->with('success', "Role \"{$role->name}\" deleted.");
    }

    private function sameCompany(Request $request, User $user): void
    {
        abort_unless($user->belongsToCompany($request->user()->company_id) && ! $user->isEmployee(), 404);
    }

    private function adminCount(int $companyId): int
    {
        return User::where('company_id', $companyId)->where('role', 'company_admin')->count();
    }
}
