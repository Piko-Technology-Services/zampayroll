@php
    // Five places, one job each. Sub-pages live in tabs at the top of each module.
    $u = auth()->user();
    $can = fn ($p) => $u?->hasPermission($p);
    $pendingLeave = $can('leave.approve') ? \App\Models\LeaveRequest::whereIn('status', \App\Models\LeaveRequest::OPEN)->count() : null;
    $pendingPay = ($can('overtime.approve') ? \App\Models\OvertimeRequest::where('status', 'pending')->count() : 0)
        + ($can('loans.manage') ? \App\Models\LoanRequest::where('status', 'pending')->count() : 0);
    // Pay runs waiting for this person's approval
    if ($can('payroll.view')) {
        $approvalsSvc = app(\App\Services\PayrollApprovals::class);
        $pendingPay += \App\Models\PayrollRun::where('status', \App\Models\PayrollRun::IN_REVIEW)->get()
            ->filter(fn ($r) => $approvalsSvc->canApprove($u, $r))->count();
    }
    $navCompanyName = $u?->company?->name;

    // The Payroll module opens on the first of its tabs this role can see.
    $payRoute = collect(['payroll.view' => 'payroll.runs.index', 'overtime.approve' => 'overtime.dashboard', 'loans.view' => 'loan.dashboard', 'reports.view' => 'payroll.reports.index'])
        ->first(fn ($route, $perm) => $can($perm));

    $nav = array_filter([
        ['dashboard', 'dashboard', 'bi-house-door', 'Home', 'Today at a glance', null],
        $can('employees.view') ? ['employees.index', 'employees.*', 'bi-people', 'People', 'Employees & onboarding', null] : null,
        $can('attendance.view') ? ['attendance.calendar', 'attendance.*', 'bi-clock-history', 'Attendance', 'Who is in today', null] : null,
        $can('leave.view') ? ['leave.dashboard', 'leave.*', 'bi-calendar2-heart', 'Leave', 'Requests & balances', $pendingLeave] : null,
        $payRoute ? ($can('payroll.view') || $can('reports.view')
            ? [$payRoute, ['payroll.*', 'overtime.*', 'loan.*'], 'bi-cash-stack', 'Payroll', 'Pay, overtime, loans, returns', $pendingPay]
            : [$payRoute, ['overtime.*', 'loan.*'], $can('overtime.approve') ? 'bi-alarm' : 'bi-cash-coin', $can('overtime.approve') ? 'Overtime' : 'Loans', $can('overtime.approve') && $can('loans.view') ? 'Overtime & loan requests' : 'Requests to review', $pendingPay]) : null,
    ]);
    $settingsRoute = $can('settings.company') ? 'company.settings' : ($can('team.manage') ? 'team.index' : 'profile.index');
@endphp
<aside class="admin-sidebar zp-sidebar" id="adminSidebar">

    <div class="sidebar-header">
        <a class="brand-mark" href="{{ route('dashboard') }}">
            <img src="{{ asset('assets/images/logo.png') }}" width="36" height="36" alt="ZamPayroll Logo" class="brand-logo">
            <span class="brand-copy">
                <span class="brand-title">ZamPayroll</span>
                <span class="brand-subtitle" title="{{ $navCompanyName }}">{{ \Illuminate\Support\Str::limit($navCompanyName ?? 'Payroll Made Simple', 26) }}</span>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav" aria-label="Main">
        @foreach($nav as [$route, $match, $icon, $label, $hint, $badge])
            @php $active = request()->routeIs(...(array) $match); @endphp
            <a class="nav-link zp-nav {{ $active ? 'active' : '' }}" href="{{ route($route) }}" @if($active) aria-current="page" @endif>
                <i class="bi {{ $icon }} nav-icon"></i>
                <span class="nav-text">
                    {{ $label }}
                    <small class="zp-nav-hint">{{ $hint }}</small>
                </span>
                @if($badge)
                    <span class="zp-nav-badge" title="{{ $badge }} waiting for you">{{ $badge }}</span>
                @endif
            </a>
        @endforeach

        @if($can('ai.use'))
        <button type="button" class="nav-link zp-nav zp-nav-ai" data-ai-prompt="What needs my attention today?">
            <i class="bi bi-stars nav-icon"></i>
            <span class="nav-text">Ask AI<small class="zp-nav-hint">Questions & quick actions</small></span>
        </button>
        @endif
    </nav>

    <div class="zp-sidebar-foot">
        @php
            $helpActive = request()->routeIs('support.*');
            $helpBadge = \App\Models\SupportTicket::query()->where('last_reply_by_staff', true)->whereIn('status', ['open', 'waiting'])->count();
        @endphp
        <a class="nav-link zp-nav {{ $helpActive ? 'active' : '' }}" href="{{ route('support.index') }}">
            <i class="bi bi-life-preserver nav-icon"></i>
            <span class="nav-text">Help<small class="zp-nav-hint">Talk to ZamPayroll</small></span>
            @if($helpBadge)<span class="zp-nav-badge" title="New reply from support">{{ $helpBadge }}</span>@endif
        </a>
        @php $settingsActive = request()->routeIs('company.*', 'bank-templates.*', 'profile.*', 'settings.*', 'team.*', 'billing.*', 'payroll-approvals.*'); @endphp
        <a class="nav-link zp-nav {{ $settingsActive ? 'active' : '' }}" href="{{ route($settingsRoute) }}">
            <i class="bi bi-gear nav-icon"></i>
            <span class="nav-text">Settings<small class="zp-nav-hint">{{ $u?->roleName() }}</small></span>
        </a>
    </div>
</aside>
