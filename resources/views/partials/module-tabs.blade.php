{{-- Sub-navigation for the current module (replaces the old nested sidebar menus). --}}
@php
    $u = auth()->user();
    $can = fn ($p) => $u?->hasPermission($p);
    $modules = [
        'people' => [
            'match' => ['employees.*'],
            'title' => 'People',
            'tabs' => [
                ['employees.index', 'All employees', 'bi-people', ['employees.index', 'employees.show', 'employees.edit'], 'employees.view'],
                ['employees.create', 'Add employee', 'bi-person-plus', ['employees.create'], 'employees.manage'],
                ['employees.import.wizard', 'Import a list', 'bi-upload', ['employees.import.wizard'], 'employees.import'],
                ['employees.changes.index', 'Change requests' . (($n = $can('employees.manage') ? \App\Models\EmployeeChangeRequest::where('status', 'pending')->count() : 0) ? " ({$n})" : ''), 'bi-shield-check', ['employees.changes.*'], 'employees.manage'],
            ],
            'actions' => [
                ['ai' => 'I want to add employees. I will paste or attach the list.', 'label' => 'Add with AI', 'icon' => 'bi-stars', 'perm' => 'employees.manage'],
            ],
        ],
        'attendance' => [
            'match' => ['attendance.*'],
            'title' => 'Attendance',
            'tabs' => [
                ['attendance.calendar', 'Calendar', 'bi-calendar3', ['attendance.calendar', 'attendance.day'], 'attendance.view'],
                ['attendance.report', 'Report', 'bi-graph-up', ['attendance.report'], 'attendance.view'],
                ['attendance.settings', 'Working days & holidays', 'bi-sliders', ['attendance.settings'], 'attendance.manage'],
            ],
            'actions' => [['ai' => 'Who is absent or on leave today?', 'label' => 'Who\'s out today?', 'icon' => 'bi-stars']],
        ],
        'leave' => [
            'match' => ['leave.*'],
            'title' => 'Leave',
            'tabs' => [
                ['leave.dashboard', 'Requests', 'bi-inbox', ['leave.dashboard'], 'leave.view'],
                ['leave.years', 'Leave sheet', 'bi-table', ['leave.years', 'leave.sheet'], 'leave.view'],
                ['leave.types.index', 'Leave types & rules', 'bi-sliders', ['leave.types.*'], 'leave.approve'],
            ],
            'actions' => [
                ['ai' => 'Show me pending leave requests so I can approve them.', 'label' => 'Approve with AI', 'icon' => 'bi-stars', 'perm' => 'leave.approve'],
                ['href' => route('leave.apply.form'), 'label' => 'Public form', 'icon' => 'bi-box-arrow-up-right', 'blank' => true],
            ],
        ],
        'payroll' => [
            'match' => ['payroll.*', 'overtime.*', 'loan.*'],
            'title' => 'Payroll',
            'tabs' => [
                ['payroll.runs.index', 'Payroll', 'bi-play-circle', ['payroll.runs.*', 'payroll.show', 'payroll.index'], 'payroll.view'],
                ['overtime.dashboard', 'Overtime', 'bi-alarm', ['overtime.*'], 'overtime.approve'],
                ['loan.dashboard', 'Loans', 'bi-cash-coin', ['loan.dashboard', 'loan.ledger.*'], 'loans.view'],
                ['payroll.reports.index', 'Reports & returns', 'bi-file-earmark-bar-graph', ['payroll.reports.*'], 'reports.view'],
            ],
            'actions' => [
                ['ai' => 'Prepare this month\'s payroll.', 'label' => 'Prepare with AI', 'icon' => 'bi-stars', 'perm' => 'payroll.run'],
            ],
        ],
        'settings' => [
            'match' => ['company.*', 'bank-templates.*', 'profile.*', 'team.*', 'payroll-approvals.*', 'billing.*'],
            'title' => 'Settings',
            'tabs' => array_values(([
                ['company.settings', 'Company', 'bi-building', ['company.settings'], 'settings.company'],
                ['team.index', 'Team & roles', 'bi-shield-lock', ['team.*'], 'team.manage'],
                ['payroll-approvals.index', 'Payroll approvals', 'bi-diagram-3', ['payroll-approvals.*'], 'payroll.finalize'],
                ['billing.index', 'Billing', 'bi-credit-card', ['billing.*'], 'billing.manage'],
                ['bank-templates.index', 'Bank files', 'bi-bank', ['bank-templates.*'], 'settings.company'],
                ['profile.index', 'My profile', 'bi-person-circle', ['profile.*'], null],
            ])),
            'actions' => [],
        ],
    ];

    $current = collect($modules)->first(fn ($m) => request()->routeIs(...$m['match']));

    if ($current) {
        $current['tabs'] = array_filter($current['tabs'], fn ($t) => empty($t[4]) || $can($t[4]));
        $current['actions'] = array_filter($current['actions'], fn ($a) => (empty($a['perm']) || $can($a['perm'])) && (! isset($a['ai']) || $can('ai.use')));
    }
@endphp

@if($current)
<nav class="zp-tabs" aria-label="{{ $current['title'] }}">
    <div class="zp-tabs-list">
        @foreach($current['tabs'] as [$route, $label, $icon, $activeOn])
            @php $on = request()->routeIs(...$activeOn); @endphp
            <a href="{{ route($route) }}" class="zp-tab {{ $on ? 'active' : '' }}" @if($on) aria-current="page" @endif><i class="bi {{ $icon }}"></i>{{ $label }}</a>
        @endforeach
    </div>
    <div class="zp-tabs-actions">
        @foreach($current['actions'] as $a)
            @if(isset($a['ai']))
                <button type="button" class="zp-tab-action ai" data-ai-prompt="{{ $a['ai'] }}"><i class="bi {{ $a['icon'] }}"></i>{{ $a['label'] }}</button>
            @elseif(isset($a['modal']))
                <button type="button" class="zp-tab-action" data-bs-toggle="modal" data-bs-target="{{ $a['modal'] }}"><i class="bi {{ $a['icon'] }}"></i>{{ $a['label'] }}</button>
            @else
                <a class="zp-tab-action" href="{{ $a['href'] }}" @if(!empty($a['blank'])) target="_blank" rel="noopener" @endif><i class="bi {{ $a['icon'] }}"></i>{{ $a['label'] }}</a>
            @endif
        @endforeach
    </div>
</nav>
@endif
