<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\ApprovalWorkflow;
use App\Models\Compliance\ComplianceSetting;
use App\Models\Compliance\Letterhead;
use App\Support\Audit;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('compliance.settings', [
            'settings' => ComplianceSetting::current(),
            'letterheads' => Letterhead::orderByDesc('is_default')->orderBy('name')->get(),
            'workflows' => ApprovalWorkflow::with('steps.approver', 'steps.delegate')->orderBy('applies_to')->orderBy('name')->get(),
            'company' => \App\Support\Tenancy::company(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'disclaimer' => 'nullable|string|max:2000',
            'reminder_days' => 'nullable|string|max:60',
            'retention_years' => 'required|integer|min:5|max:100',
            'working_hours' => 'nullable|string|max:255',
            'notice_period' => 'nullable|string|max:120',
            'probation_months' => 'required|integer|min:1|max:3',
            'allowances' => 'nullable|string|max:255',
            'signatory_name' => 'nullable|string|max:120',
            'signatory_title' => 'nullable|string|max:120',
        ], [
            'retention_years.min' => 'The Employment Code Act requires written contracts to be kept for five years after they end, so keep records for at least 5 years.',
            'probation_months.max' => 'Probation may not exceed three months under the Employment Code Act (s.27).',
        ]);
        $days = collect(preg_split('/[\s,]+/', (string) ($data['reminder_days'] ?? '')))->filter(fn ($d) => $d !== '' && ctype_digit($d))
            ->map(fn ($d) => min(365, (int) $d))->unique()->sortDesc()->values()->all();
        $data['reminder_days'] = $days ?: [90, 30, 7, 0];

        $s = ComplianceSetting::current();
        $s->update($data);
        Audit::record('compliance.settings_updated', $s, 'Compliance settings updated');

        return back()->with('success', 'Settings saved.');
    }
}
