<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Compliance\AlertDismissal;
use App\Services\Compliance\Approvals;
use App\Services\Compliance\Overview;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function index(Request $request)
    {
        $stats = Overview::stats();

        return view('compliance.overview', [
            'stats' => $stats,
            'alerts' => Overview::alerts($stats, $request->user()),
            'myApprovals' => Approvals::pendingCountFor($request->user()),
            'activity' => AuditLog::where('company_id', Tenancy::companyId())->where('action', 'like', 'compliance.%')->latest('id')->limit(12)->get(),
        ]);
    }

    /** Hide an alert until its number changes. Does not change any status. */
    public function dismiss(Request $request, string $key)
    {
        $stats = Overview::stats();
        abort_unless(array_key_exists($key, $stats), 404);
        AlertDismissal::updateOrCreate(['user_id' => $request->user()->id, 'alert_key' => $key], ['count_at_dismissal' => (int) $stats[$key]]);

        return back()->with('success', 'Hidden until the number changes.');
    }
}
