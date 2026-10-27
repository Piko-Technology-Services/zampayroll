<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\Payslips\PayslipDelivery;
use Illuminate\Http\Request;

/** Payroll → Payslips → Share: portal, work email, personal email — any mix, for everyone or some people. */
class PayslipShareController extends Controller
{
    public function __construct(private PayslipDelivery $delivery)
    {
    }

    public function show(Request $request, PayrollRun $run)
    {
        $run->load(['payrolls.employee.user']);
        $only = collect((array) $request->query('only'))->map(fn ($v) => (int) $v)->filter();

        return view('dashboard.payroll.payslips.share', [
            'run'      => $run,
            'payrolls' => $run->payrolls->filter(fn ($p) => $p->employee)->sortBy(fn ($p) => mb_strtolower($p->employee->first_name . ' ' . $p->employee->last_name))->values(),
            'settings' => $this->delivery->settings(),
            'only'     => $only,
        ]);
    }

    public function store(Request $request, PayrollRun $run)
    {
        $data = $request->validate([
            'payrolls'   => ['required', 'array', 'min:1'],
            'payrolls.*' => ['integer'],
            'portal'     => ['nullable', 'boolean'],
            'work'       => ['nullable', 'boolean'],
            'personal'   => ['nullable', 'boolean'],
            'remember'   => ['nullable', 'boolean'],
            'action'     => ['nullable', 'in:share,unpublish'],
        ], ['payrolls.required' => 'Tick at least one person.']);

        $payrolls = $run->payrolls()->with('employee')->whereIn('id', $data['payrolls'])->get();

        if (($data['action'] ?? 'share') === 'unpublish') {
            $n = $this->delivery->unpublish($run, $payrolls);

            return back()->with('success', "{$n} payslip(s) removed from the portal. Employees can't see them until you share them again.");
        }

        $channels = ['portal' => $request->boolean('portal'), 'work' => $request->boolean('work'), 'personal' => $request->boolean('personal')];
        if (! array_filter($channels)) {
            return back()->withErrors(['portal' => 'Choose at least one way to share: the portal, work email or personal email.'])->withInput();
        }

        if ($request->boolean('remember')) {
            $this->delivery->saveSettings(\App\Support\Tenancy::company(), $channels);
        }

        try {
            $r = $this->delivery->share($run, $payrolls, $channels, $request->user());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $parts = [];
        if ($channels['portal']) {
            $parts[] = "{$r['published']} on the portal";
        }
        if ($channels['work'] || $channels['personal']) {
            $parts[] = "{$r['emailed']} emailed";
        }
        $msg = 'Done — ' . implode(', ', $parts) . '.';
        if ($r['no_email']) {
            $msg .= ' No email address for: ' . implode(', ', $r['no_email']) . ($channels['portal'] ? ' (they can still see it on the portal if they have an account).' : '.');
        }

        return redirect()->route('payroll.runs.payslips.share', $run)->with('success', $msg);
    }
}
