<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\Statutory\StatutoryReturns;
use Illuminate\Http\Request;

/** Review, correct and export NAPSA / NHIMA / ZRA returns. */
class StatutoryReturnController extends Controller
{
    public function __construct(private StatutoryReturns $returns)
    {
    }

    private function kind(string $kind): string
    {
        abort_unless(isset(StatutoryReturns::KINDS[$kind]), 404);

        return $kind;
    }

    private function scope(Request $request, PayrollRun $run): string
    {
        $s = $request->input('scope');
        if (in_array($s, ['run', 'month'], true)) {
            return $s;
        }

        return $this->returns->runsFor($run, 'month')->count() > 1 ? 'month' : 'run';
    }

    public function preview(Request $request, PayrollRun $run, string $kind)
    {
        $kind = $this->kind($kind);
        $scope = $this->scope($request, $run);

        return view('dashboard.payroll.reports.return', $this->returns->current($kind, $run, $scope) + [
            'run'        => $run,
            'scope'      => $scope,
            'monthRuns'  => $this->returns->runsFor($run, 'month'),
            'otherKinds' => array_diff_key(StatutoryReturns::KINDS, [$kind => 1]),
        ]);
    }

    public function save(Request $request, PayrollRun $run, string $kind)
    {
        $kind = $this->kind($kind);
        $data = $request->validate([
            'rows'           => ['present', 'array', 'max:5000'],
            'rows.*.key'     => ['nullable', 'string', 'max:40'],
            'rows.*.label'   => ['nullable', 'string', 'max:120'],
            'rows.*.cells'   => ['required', 'array'],
            'rows.*.employee_id' => ['nullable', 'integer'],
            'removed'        => ['nullable', 'array'],
            'removed.*'      => ['string', 'max:40'],
            'scope'          => ['required', 'in:run,month'],
        ]);

        $this->returns->save($kind, $run, $data['scope'], $data['rows'], $data['removed'] ?? [], $request->user()?->id);

        return response()->json(['ok' => true, 'message' => 'Saved. Downloads now use your corrected figures.']);
    }

    public function reset(Request $request, PayrollRun $run, string $kind)
    {
        $kind = $this->kind($kind);
        $scope = $this->scope($request, $run);
        $this->returns->reset($kind, $run, $scope);

        return redirect()->route('payroll.reports.returns.preview', [$run, $kind, 'scope' => $scope])->with('success', 'Back to the payroll figures — your edits were discarded.');
    }

    public function download(Request $request, PayrollRun $run, string $kind)
    {
        $kind = $this->kind($kind);

        return $this->returns->download($kind, $run, $this->scope($request, $run), $request->has('with_header') ? $request->boolean('with_header') : null);
    }
}
