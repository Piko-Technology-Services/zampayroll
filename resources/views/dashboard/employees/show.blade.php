@extends('layouts.app')

@section('title', $employee->full_name . ' · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
@endpush

@php
    $u = auth()->user();
    $canManage = $u->hasPermission('employees.manage');
    $canPay = $canManage || $u->hasPermission('payroll.view');
    $canLoans = $canPay || $u->hasPermission('loans.view');
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $status = $employee->employment_status ?: 'Active';
    $statusTone = match (strtolower($status)) { 'active' => 'green', 'suspended' => 'copper', default => 'red' };
    $nature = \App\Http\Controllers\EmployeeController::NATURES[$employee->employment_nature] ?? null;
    $tenure = $employee->contract_start ? $employee->contract_start->diffForHumans(now(), ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : null;
    $contractSoon = $employee->contract_end && $employee->contract_end->isFuture() && now()->diffInDays($employee->contract_end) <= 60;
    $probationOn = $employee->probation_end && $employee->probation_end->isFuture();
    $docs = collect($employee->uploads ?? []);
    $fact = function ($label, $value, $mono = false) {
        $empty = $value === null || $value === '';
        $copy = (! $empty && $mono) ? '<button type="button" class="pf-copy" data-copy="' . e($value) . '" title="Copy"><i class="bi bi-copy"></i></button>' : '';
        return '<div class="pf-fact' . ($mono ? ' mono' : '') . '"><span>' . e($label) . '</span><b class="' . ($empty ? 'empty' : '') . '">' . ($empty ? 'Not added' : e($value)) . $copy . '</b></div>';
    };
    $chart = $payslips->take(6)->reverse()->values();
    $chartMax = max(1, (float) $chart->max('net_pay'));
@endphp

@section('content')
<div class="pp">

    {{-- ===== HEADER ===== --}}
    <section class="pf-head">
        <div class="pf-cover"></div>
        <div class="pf-body">
            <div class="pf-avatar">
                @if($employee->photo_url)<img src="{{ $employee->photo_url }}" alt="{{ $employee->full_name }}">@else{{ $employee->initials }}@endif
            </div>
            <div class="pf-id">
                <h1>{{ $employee->full_name }}</h1>
                <div class="pf-role">{{ $employee->position ?: 'No job title yet' }}@if($employee->department) · {{ $employee->department }}@endif @if($employee->branch) · {{ $employee->branch }}@endif</div>
                <div class="pf-tags">
                    <span class="pf-tag {{ $statusTone }}"><span class="dot"></span>{{ $status }}</span>
                    <span class="pf-tag"><i class="bi bi-hash"></i>{{ $employee->employee_id }} <button type="button" class="pf-copy" data-copy="{{ $employee->employee_id }}" title="Copy"><i class="bi bi-copy"></i></button></span>
                    @if($nature)<span class="pf-tag"><i class="bi bi-briefcase"></i>{{ $nature }}</span>@endif
                    @if($tenure)<span class="pf-tag"><i class="bi bi-calendar-heart"></i>{{ $tenure }} with you</span>@endif
                    @if($employee->user)<span class="pf-tag green"><i class="bi bi-phone"></i>Portal active</span>@endif
                    @if($probationOn)<span class="pf-tag copper"><i class="bi bi-hourglass-split"></i>Probation until {{ $employee->probation_end->format('j M Y') }}</span>@endif
                    @if($contractSoon)<span class="pf-tag red"><i class="bi bi-exclamation-triangle"></i>Contract ends {{ $employee->contract_end->format('j M Y') }}</span>@endif
                </div>
            </div>
            <div class="pf-actions">
                <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('employees.index') }}"><i class="bi bi-arrow-left"></i> All</a>
                @if($canManage)
                    <a class="pp-btn pp-btn-primary pp-btn-sm" href="{{ route('employees.edit', $employee) }}"><i class="bi bi-pencil"></i> Edit profile</a>
                @endif
                <button type="button" class="pp-btn pp-btn-dark pp-btn-sm" data-ai-prompt="Tell me about {{ $employee->full_name }} ({{ $employee->employee_id }})"><i class="bi bi-stars"></i> Ask AI</button>
            </div>
        </div>
    </section>

    {{-- ===== STATS ===== --}}
    <div class="pf-stats">
        <div class="pf-stat"><i class="bi bi-wallet2 ic"></i><span>Take-home pay</span>
            <b>{{ $canPay ? ($employee->net_salary ? $k($employee->net_salary) : '—') : '••••' }}</b>
            <small>{{ $canPay ? 'per month' : 'Hidden for your role' }}</small></div>
        <div class="pf-stat"><i class="bi bi-sun ic"></i><span>Leave days left</span>
            <b>{{ $employee->leave_days_balance !== null ? rtrim(rtrim(number_format((float) $employee->leave_days_balance, 1), '0'), '.') : '—' }}</b>
            <small>of {{ (float) ($employee->leave_days_entitled ?? 24) }} a year · {{ rtrim(rtrim(number_format($leaveTaken, 1), '0'), '.') }} taken this year</small></div>
        <div class="pf-stat"><i class="bi bi-piggy-bank ic"></i><span>Loan balance</span>
            <b>{{ $canLoans ? ($activeLoanBalance > 0 ? $k($activeLoanBalance) : 'None') : '••••' }}</b>
            <small>{{ $loans->where('status', 'active')->count() }} active loan(s)</small></div>
        <div class="pf-stat"><i class="bi bi-receipt ic"></i><span>Last payslip</span>
            <b>{{ $lastPayslip && $canPay ? $k($lastPayslip->net_pay) : '—' }}</b>
            <small>{{ $lastPayslip ? ($lastPayslip->run?->period ?? $lastPayslip->pay_period) : 'Not paid through ZamPayroll yet' }}</small></div>
    </div>

    <div class="pf-layout">
        <div class="pp-card" id="pfTabsCard">
            <div class="pf-tabs" role="tablist">
                <button class="pf-tab active" data-tab="overview"><i class="bi bi-person-vcard"></i> Overview</button>
                @if($canPay)<button class="pf-tab" data-tab="pay"><i class="bi bi-cash-stack"></i> Pay &amp; payslips <span class="count">{{ $payslips->count() }}</span></button>@endif
                <button class="pf-tab" data-tab="time"><i class="bi bi-calendar-check"></i> Leave &amp; time <span class="count">{{ $leave->count() + $overtime->count() }}</span></button>
                @if($canLoans)<button class="pf-tab" data-tab="loans"><i class="bi bi-piggy-bank"></i> Loans <span class="count">{{ $loans->count() }}</span></button>@endif
                <button class="pf-tab" data-tab="docs"><i class="bi bi-folder2-open"></i> Documents <span class="count">{{ $docs->count() }}</span></button>
            </div>

            {{-- OVERVIEW --}}
            <div class="pf-pane" data-pane="overview">
                <div class="pf-section">
                    <h3>Personal @if($canManage)<a class="pp-link" href="{{ route('employees.edit', [$employee, 'step' => 'who']) }}">Edit</a>@endif</h3>
                    <div class="pf-facts">
                        {!! $fact('Gender', $employee->gender) !!}
                        {!! $fact('Date of birth', $employee->date_of_birth ? $employee->date_of_birth->format('j M Y') . ' (' . $employee->age . ')' : null) !!}
                        {!! $fact('Nationality', $employee->nationality) !!}
                        {!! $fact('NRC', $employee->nrc_no, true) !!}
                        @if($employee->passport_number || strcasecmp((string) $employee->nationality, 'Zambian') !== 0){!! $fact('Passport', $employee->passport_number, true) !!}@endif
                    </div>
                </div>
                <div class="pf-section">
                    <h3>Job @if($canManage)<a class="pp-link" href="{{ route('employees.edit', [$employee, 'step' => 'job']) }}">Edit</a>@endif</h3>
                    <div class="pf-facts">
                        {!! $fact('Job title', $employee->position) !!}
                        {!! $fact('Department', $employee->department) !!}
                        {!! $fact('Branch', $employee->branch) !!}
                        {!! $fact('Reports to', $employee->supervisor) !!}
                        {!! $fact('Started', optional($employee->contract_start)->format('j M Y')) !!}
                        {!! $fact($employee->contract_end ? 'Contract ends' : 'Probation ends', optional($employee->contract_end ?? $employee->probation_end)->format('j M Y')) !!}
                    </div>
                </div>
                <div class="pf-section">
                    <h3>ZRA · NAPSA · NHIMA @if($canManage)<a class="pp-link" href="{{ route('employees.edit', [$employee, 'step' => 'statutory']) }}">Edit</a>@endif</h3>
                    <div class="pf-facts">
                        {!! $fact('TPIN', $employee->tpin, true) !!}
                        {!! $fact('NAPSA number', $employee->ssn, true) !!}
                        {!! $fact('NHIMA number', $employee->nhima_no, true) !!}
                    </div>
                </div>
                @if($canPay)
                <div class="pf-section">
                    <h3>Bank @if($canManage)<a class="pp-link" href="{{ route('employees.edit', [$employee, 'step' => 'pay']) }}">Edit</a>@endif</h3>
                    <div class="pf-facts">
                        {!! $fact('Bank', $employee->bank_name) !!}
                        {!! $fact('Branch', $employee->bank_branch) !!}
                        {!! $fact('Account number', $employee->bank_account_no, true) !!}
                    </div>
                </div>
                @endif
                <div class="pf-section">
                    <h3>Contact &amp; emergency @if($canManage)<a class="pp-link" href="{{ route('employees.edit', [$employee, 'step' => 'contact']) }}">Edit</a>@endif</h3>
                    <div class="pf-facts">
                        {!! $fact('Mobile', $employee->primary_phone) !!}
                        {!! $fact('Work email', $employee->company_email) !!}
                        {!! $fact('Personal email', $employee->personal_email) !!}
                        {!! $fact('Emergency contact', trim($employee->emergency_name . ($employee->emergency_relationship ? ' (' . $employee->emergency_relationship . ')' : '')) ?: null) !!}
                        {!! $fact('Emergency phone', $employee->emergency_phone) !!}
                        {!! $fact('Next of kin', trim($employee->next_of_kin_name . ($employee->next_of_kin_phone ? ' · ' . $employee->next_of_kin_phone : '')) ?: null) !!}
                    </div>
                </div>
            </div>

            {{-- PAY --}}
            @if($canPay)
            <div class="pf-pane" data-pane="pay" hidden>
                @if($payslips->isEmpty())
                    <div class="pf-empty"><i class="bi bi-receipt"></i>No payslips yet. They'll appear here after {{ $employee->first_name }}'s first payroll run.</div>
                @else
                    <div class="pf-section">
                        <h3>Take-home over the last {{ $chart->count() }} runs</h3>
                        <div class="pf-chart">
                            @foreach($chart as $i => $p)
                                <div title="{{ $k($p->net_pay) }}"><i style="height:{{ max(6, round((float) $p->net_pay / $chartMax * 100)) }}%;animation-delay:{{ $i * 70 }}ms"></i><small>{{ \Illuminate\Support\Str::limit($p->run?->period ?? $p->pay_period, 8, '') }}</small></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="pf-section">
                        <h3>Payslips</h3>
                        <div class="pf-list">
                            @foreach($payslips as $p)
                                <div class="pf-item">
                                    <span class="ic"><i class="bi bi-receipt"></i></span>
                                    <div class="main"><b>{{ $p->run?->alias ?: ($p->run?->period ?? $p->pay_period) }}</b><small>Gross {{ $k($p->total_income) }} · Deductions {{ $k($p->total_deductions) }}</small></div>
                                    <span class="pf-status {{ strtolower($p->run?->status ?? $p->status) }}">{{ $p->run?->status ?? $p->status }}</span>
                                    <span class="amt">{{ $k($p->net_pay) }}</span>
                                    @if($p->run)
                                        <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('payroll.runs.employee.summary', [$p->run, $p]) }}" title="View payslip"><i class="bi bi-eye"></i></a>
                                    @endif
                                    @if($employee->portal_email)
                                        <form method="POST" action="{{ route('payroll.email.single', $p) }}">@csrf
                                            <button class="pp-btn pp-btn-ghost pp-btn-sm" title="Email payslip to {{ $employee->portal_email }}"><i class="bi bi-envelope"></i></button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
            @endif

            {{-- LEAVE & TIME --}}
            <div class="pf-pane" data-pane="time" hidden>
                <div class="pf-section">
                    <h3>Last 30 days</h3>
                    @if($attendance->isEmpty())
                        <div class="pp-muted">No attendance marked in the last 30 days.</div>
                    @else
                        <div class="pf-tags">
                            @foreach(\App\Models\Attendance::STATUSES as $key => $label)
                                @if($attendanceSummary[$key] ?? 0)
                                    <span class="pf-tag {{ $key === 'present' ? 'green' : ($key === 'absent' ? 'red' : ($key === 'sick' || $key === 'leave' ? 'copper' : '')) }}">{{ $label }} · {{ $attendanceSummary[$key] }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="pf-section">
                    <h3>Leave requests</h3>
                    @forelse($leave as $l)
                        <div class="pf-item" style="margin-bottom:.5rem">
                            <span class="ic" style="background:var(--zp-copper-50);color:var(--zp-copper)"><i class="bi bi-sun"></i></span>
                            <div class="main"><b>{{ $l->leave_type ?: 'Leave' }} · {{ rtrim(rtrim(number_format((float) $l->days, 1), '0'), '.') }} day(s)</b>
                                <small>{{ optional($l->start_date)->format('j M') }} – {{ optional($l->end_date)->format('j M Y') }}@if($l->reason) · {{ \Illuminate\Support\Str::limit($l->reason, 50) }}@endif</small></div>
                            <span class="pf-status {{ strtolower($l->status) }}">{{ $l->status }}</span>
                        </div>
                    @empty
                        <div class="pp-muted">No leave requests yet.</div>
                    @endforelse
                </div>
                <div class="pf-section">
                    <h3>Overtime</h3>
                    @forelse($overtime as $o)
                        <div class="pf-item" style="margin-bottom:.5rem">
                            <span class="ic" style="background:#EEF0EF;color:var(--zp-black)"><i class="bi bi-clock-history"></i></span>
                            <div class="main"><b>{{ rtrim(rtrim(number_format((float) $o->hours, 1), '0'), '.') }} hours</b><small>{{ optional($o->date)->format('j M Y') ?? $o->date }} @if($o->type)· {{ $o->type }}@endif</small></div>
                            <span class="pf-status {{ strtolower($o->status) }}">{{ $o->status }}</span>
                            @if($canPay && $o->amount)<span class="amt">{{ $k($o->amount) }}</span>@endif
                        </div>
                    @empty
                        <div class="pp-muted">No overtime claimed.</div>
                    @endforelse
                </div>
            </div>

            {{-- LOANS --}}
            @if($canLoans)
            <div class="pf-pane" data-pane="loans" hidden>
                @forelse($loans as $loan)
                    @php $paid = (float) $loan->principal_amount > 0 ? max(0, min(100, round((1 - (float) $loan->balance / (float) $loan->principal_amount) * 100))) : 0; @endphp
                    <div class="pf-item" style="margin-bottom:.6rem;flex-wrap:wrap">
                        <span class="ic" style="background:#FBEAEA;color:var(--zp-red)"><i class="bi bi-piggy-bank"></i></span>
                        <div class="main"><b>{{ $k($loan->principal_amount) }} loan</b>
                            <small>{{ $k($loan->installment_amount) }} a month @if($loan->next_deduction_date)· next deduction {{ $loan->next_deduction_date->format('M Y') }}@endif</small>
                            <div class="pp-bar" style="margin:.45rem 0 0;max-width:260px"><i style="width:{{ $paid }}%"></i></div>
                            <small>{{ $paid }}% repaid · {{ $k($loan->balance) }} left</small>
                        </div>
                        <span class="pf-status {{ strtolower($loan->status) }}">{{ $loan->status }}</span>
                        @if(\Illuminate\Support\Facades\Route::has('loan.ledger.show'))
                            <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('loan.ledger.show', $loan) }}">Ledger</a>
                        @endif
                    </div>
                @empty
                    <div class="pf-empty"><i class="bi bi-piggy-bank"></i>No loans or salary advances.</div>
                @endforelse
            </div>
            @endif

            {{-- DOCUMENTS --}}
            <div class="pf-pane" data-pane="docs" hidden>
                @if($docs->isEmpty())
                    <div class="pf-empty"><i class="bi bi-folder2-open"></i>No documents yet.
                        @if($canManage)<br><a class="pp-link" href="{{ route('employees.edit', [$employee, 'step' => 'contact']) }}">Upload a contract or NRC copy</a>@endif
                    </div>
                @else
                    <div class="pf-docs">
                        @foreach($docs as $doc)
                            @php $ext = strtolower(pathinfo($doc['name'] ?? $doc['path'] ?? '', PATHINFO_EXTENSION)); @endphp
                            <a class="pf-doc" href="{{ asset('storage/' . ($doc['path'] ?? '')) }}" target="_blank" rel="noopener">
                                <i class="bi {{ in_array($ext, ['jpg','jpeg','png']) ? 'bi-file-earmark-image' : ($ext === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-text') }}"></i>
                                <b>{{ $doc['name'] ?? 'Document' }}</b>
                                <small>{{ isset($doc['uploaded_at']) ? \Carbon\Carbon::parse($doc['uploaded_at'])->format('j M Y') : '' }}</small>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== SIDE ===== --}}
        <aside class="pf-side">
            <div class="pp-card" style="text-align:center">
                <div class="pf-ring" style="--p: {{ $completeness['percent'] }}"><div>{{ $completeness['percent'] }}%</div></div>
                <b>{{ $completeness['percent'] === 100 ? 'Profile complete 🎉' : 'Profile ' . $completeness['percent'] . '% complete' }}</b>
                @if($completeness['missing'])
                    <div class="pp-muted" style="margin-top:.2rem">Still missing:</div>
                    <ul class="pf-missing" style="text-align:left">
                        @foreach($completeness['missing'] as $step => $fields)
                            <li><a href="{{ $canManage ? route('employees.edit', [$employee, 'step' => $step]) : '#' }}">
                                <span>{{ implode(', ', $fields) }}</span><i class="bi bi-plus-circle"></i></a></li>
                        @endforeach
                    </ul>
                @else
                    <div class="pp-muted" style="margin-top:.2rem">Ready for payroll and statutory returns.</div>
                @endif
            </div>

            @if(auth()->user()?->hasPermission('portal.manage'))
                @include('partials.employee-portal-card', ['employee' => $employee])
            @endif

            <div class="pp-card">
                <div class="pp-card-title" style="margin-bottom:.7rem"><h3>Quick actions</h3></div>
                <div class="pf-quick">
                    @if($canManage)<a href="{{ route('employees.edit', [$employee, 'step' => 'pay']) }}"><i class="bi bi-cash-coin"></i> Change pay or bank</a>@endif
                    <button type="button" data-ai-prompt="How many leave days does {{ $employee->full_name }} have left?"><i class="bi bi-sun"></i> Check leave balance</button>
                    @if($canPay)<button type="button" data-ai-prompt="Show {{ $employee->full_name }}'s latest payslip"><i class="bi bi-receipt"></i> Latest payslip</button>@endif
                    @if($employee->primary_phone)<a href="tel:{{ preg_replace('/\s+/', '', $employee->primary_phone) }}"><i class="bi bi-telephone"></i> Call {{ $employee->first_name }}</a>@endif
                    @if($employee->primary_phone)<a href="https://wa.me/{{ preg_replace('/^0/', '260', preg_replace('/\D/', '', $employee->primary_phone)) }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>@endif
                    @if($employee->portal_email)<a href="mailto:{{ $employee->portal_email }}"><i class="bi bi-envelope"></i> Email</a>@endif
                    @if($canManage)
                        <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="event.stopPropagation(); return confirm('Delete {{ addslashes($employee->full_name) }}? Their payroll history will be lost. Consider setting them to Inactive instead.')">
                            @csrf @method('DELETE')
                            <button type="submit" style="color:var(--zp-red)"><i class="bi bi-trash" style="color:var(--zp-red)"></i> Delete employee</button>
                        </form>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/zp-people.js') }}"></script>
<script>
(function () {
    const card = document.getElementById('pfTabsCard');
    const open = (name) => {
        card.querySelectorAll('.pf-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
        card.querySelectorAll('.pf-pane').forEach(p => p.hidden = p.dataset.pane !== name);
        try { history.replaceState(null, '', '#' + name); } catch (e) {}
    };
    card.querySelectorAll('.pf-tab').forEach(t => t.addEventListener('click', () => open(t.dataset.tab)));
    const h = location.hash.slice(1);
    if (h && card.querySelector('[data-pane="' + h + '"]')) open(h);
    @if(session('celebrate')) zpPeople.confetti(); @endif
})();
</script>
@endpush
