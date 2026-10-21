<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * HR sets up the kinds of leave the company offers and how approval works.
 */
class LeaveTypeController extends Controller
{
    public function __construct(private LeaveService $leave)
    {
    }

    private function guard(Request $request): void
    {
        abort_unless($this->leave->isHr($request->user()), 403, 'Only HR and administrators can change leave settings.');
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('dashboard.leave.types', [
            'types' => $this->leave->types(null, false),
            'company' => $request->user()->company,
        ]);
    }

    public function store(Request $request)
    {
        $this->guard($request);
        $data = $this->validated($request);
        $key = Str::slug($data['name'], '_');
        $base = $key ?: 'leave';
        $i = 1;
        while (LeaveType::query()->where('key', $key)->exists()) {
            $key = $base . '_' . (++$i);
        }
        $type = LeaveType::create($data + ['key' => Str::limit($key, 40, ''), 'sort' => (int) LeaveType::query()->max('sort') + 10]);
        Audit::record('leave.type_created', $type, "Leave type “{$type->name}” added");

        return back()->with('success', "“{$type->name}” added.");
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $this->guard($request);
        $before = $leaveType->only(array_keys($this->rules()));
        $leaveType->update($this->validated($request));
        $changes = collect($leaveType->only(array_keys($this->rules())))->filter(fn ($v, $k) => ($before[$k] ?? null) != $v)
            ->map(fn ($v, $k) => ['from' => $before[$k] ?? null, 'to' => $v])->all();
        Audit::record('leave.type_updated', $leaveType, "Leave type “{$leaveType->name}” changed", $changes);

        return back()->with('success', "“{$leaveType->name}” saved.");
    }

    public function policy(Request $request)
    {
        $this->guard($request);
        $company = $request->user()->company;
        $data = ['leave_hr_confirm' => $request->boolean('leave_hr_confirm'), 'leave_allow_negative' => $request->boolean('leave_allow_negative')];
        $company->forceFill($data)->save();
        Audit::record('leave.policy_updated', $company, 'Leave approval: ' . ($data['leave_hr_confirm'] ? 'manager, then HR confirms' : 'manager or HR approves')
            . ' · balance ' . ($data['leave_allow_negative'] ? 'may go below zero' : 'can\'t go below zero'));

        return back()->with('success', 'Leave approval settings saved.');
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
            'is_paid' => 'boolean',
            'deducts_balance' => 'boolean',
            'requires_document' => 'boolean',
            'document_after_days' => 'nullable|integer|min:0|max:365',
            'days_per_year' => 'nullable|numeric|min:0|max:366',
            'max_days_per_request' => 'nullable|numeric|min:0.5|max:366',
            'employees_can_apply' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    private function validated(Request $request): array
    {
        foreach (['is_paid', 'deducts_balance', 'requires_document', 'employees_can_apply', 'is_active'] as $b) {
            $request->merge([$b => $request->boolean($b)]);
        }

        return $request->validate($this->rules());
    }
}
