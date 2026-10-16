<?php

namespace App\Http\Controllers;

use App\Models\CompanyRole;
use App\Models\PayrollApprovalStep;
use App\Models\User;
use App\Services\PayrollApprovals;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Settings → Payroll approvals: the admin decides who signs off a payroll,
 * in what order, before it is locked.
 */
class PayrollApprovalSettingsController extends Controller
{
    public function index(PayrollApprovals $approvals)
    {
        $configured = PayrollApprovalStep::query()->orderBy('position')->get();

        return view('dashboard.settings.payroll-approvals', [
            'steps'      => $configured->isEmpty() ? collect(PayrollApprovals::DEFAULT_CHAIN) : $configured->map(fn ($s) => $s->only(['position', 'name', 'role_key', 'user_id'])),
            'isDefault'  => $configured->isEmpty(),
            'roles'      => CompanyRole::query()->orderBy('id')->get(['key', 'name', 'permissions']),
            'people'     => User::where('company_id', Tenancy::companyId())->get()->filter->isStaff()->sortBy('name')->values(),
            'selfApproval' => (bool) (Tenancy::company()?->payroll_allow_self_approval ?? true),
            'approvals'  => $approvals,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'steps'          => 'required|array|min:1|max:6',
            'steps.*.name'   => 'required|string|max:80',
            'steps.*.who'    => ['required', 'string', 'regex:/^(role:[a-z0-9_]+|user:\d+)$/'],
            'self_approval'  => 'nullable|boolean',
        ], [
            'steps.required'      => 'Add at least one approval step.',
            'steps.*.name.required' => 'Give every step a name, e.g. "Finance review".',
            'steps.*.who.required'  => 'Choose who approves each step.',
        ]);

        $companyUsers = User::where('company_id', Tenancy::companyId())->pluck('id')->all();
        $roleKeys = CompanyRole::query()->pluck('key')->all();

        DB::transaction(function () use ($data, $companyUsers, $roleKeys) {
            PayrollApprovalStep::query()->delete();
            foreach (array_values($data['steps']) as $i => $step) {
                [$type, $value] = explode(':', $step['who'], 2);
                if (($type === 'user' && ! in_array((int) $value, $companyUsers, true)) || ($type === 'role' && ! in_array($value, $roleKeys, true))) {
                    abort(422, 'Unknown approver.');
                }
                PayrollApprovalStep::create([
                    'position' => $i + 1,
                    'name'     => trim($step['name']),
                    'role_key' => $type === 'role' ? $value : null,
                    'user_id'  => $type === 'user' ? (int) $value : null,
                ]);
            }
        });

        Tenancy::company()?->forceFill(['payroll_allow_self_approval' => $request->boolean('self_approval')])->save();

        return redirect()->route('payroll-approvals.index')->with('success', 'Approval chain saved. New payroll submissions will follow it.');
    }
}
