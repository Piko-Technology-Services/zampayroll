@extends('layouts.app')

@section('content')

{{-- =====================================================================
     EMPLOYEES — ZamPayroll
     Redesign: light cards, thin borders, hover-reactive rows/cards,
     single green accent — consistent with the dashboard & payroll runs.
====================================================================== --}}

<div class="emp">

    {{-- ===== TOP BAR ===== --}}
    <div class="emp-top-bar">
        <div>
            <h1 class="emp-title">Employees</h1>
            <div class="emp-subtitle">Workforce directory &amp; records</div>
        </div>

        <div class="emp-top-right">

            <div class="emp-search-wrap">
                <i class="bi bi-search emp-search-icon"></i>
                <input type="text"
                       id="empSearch"
                       class="emp-search-input"
                       placeholder="Search name, ID, position…"
                       oninput="empFilter()">
            </div>

            <button class="emp-btn emp-btn-outline"
                    data-bs-toggle="modal"
                    data-bs-target="#importEmployeesModal">
                <i class="bi bi-upload"></i> Import
            </button>

            <a href="{{ route('employees.create') }}" class="emp-btn emp-btn-primary">
                <i class="bi bi-plus-lg"></i> Add Employee
            </a>

        </div>
    </div>

    {{-- ===== STATS ===== --}}
    <div class="emp-stats">

        <div class="emp-stat-card">
            <div class="emp-stat-label">Total Employees</div>
            <div class="emp-stat-value">{{ $employees->count() }}</div>
        </div>

        <div class="emp-stat-card">
            <div class="emp-stat-label">Active</div>
            <div class="emp-stat-value emp-stat-green">
                {{ $employees->where('employment_status', 'Active')->count() }}
            </div>
        </div>

        <div class="emp-stat-card">
            <div class="emp-stat-label">Departments</div>
            <div class="emp-stat-value">
                {{ $employees->pluck('department')->filter()->unique()->count() }}
            </div>
        </div>

        <div class="emp-stat-card">
            <div class="emp-stat-label">Contracts Expiring</div>
            <div class="emp-stat-value emp-stat-red">
                {{ $employees->filter(fn($e) => $e->contract_end && \Carbon\Carbon::parse($e->contract_end)->diffInDays(now()) <= 30 && \Carbon\Carbon::parse($e->contract_end)->isFuture())->count() }}
            </div>
        </div>

    </div>

    {{-- ===== FILTER BAR ===== --}}
    <div class="emp-card emp-filter-bar">

        <span class="emp-filter-label">Filter:</span>

        <select id="empDeptFilter" class="emp-select" onchange="empFilter()">
            <option value="">All Departments</option>
            @foreach($employees->pluck('department')->filter()->unique()->sort() as $dept)
                <option value="{{ $dept }}">{{ $dept }}</option>
            @endforeach
        </select>

        <select id="empStatusFilter" class="emp-select" onchange="empFilter()">
            <option value="">All Statuses</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
        </select>

        <div class="emp-view-toggle ms-auto">
            <button id="btnTable" class="emp-view-btn active" onclick="empShowView('table')">
                <i class="bi bi-list-ul"></i> Table
            </button>
            <button id="btnCard" class="emp-view-btn" onclick="empShowView('card')">
                <i class="bi bi-grid"></i> Cards
            </button>
        </div>

    </div>

    {{-- ===== TABLE VIEW ===== --}}
    <div class="emp-card emp-table-card" id="empTableView">

        <table class="emp-table" id="empTable">

            <thead>
                <tr>
                    <th>EIN</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Branch</th>
                    <th>Phone</th>
                    <th>Contract</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @foreach($employees as $employee)

                @php
                    $contractEndingSoon = $employee->contract_end
                        && \Carbon\Carbon::parse($employee->contract_end)->isFuture()
                        && \Carbon\Carbon::parse($employee->contract_end)->diffInDays(now()) <= 30;
                @endphp

                <tr class="emp-row"
                    data-dept="{{ $employee->department }}"
                    data-status="{{ $employee->employment_status }}"
                    data-search="{{ strtolower($employee->first_name . ' ' . $employee->last_name . ' ' . $employee->employee_id . ' ' . $employee->position . ' ' . $employee->department . ' ' . $employee->branch) }}"
                    onclick="window.location='{{ route('employees.show', $employee->id) }}'">

                    <td class="emp-td-id">
                        <i class="bi bi-hash"></i>{{ $employee->employee_id }}
                    </td>

                    <td>
                        <div class="emp-name-cell">
                            <div class="emp-avatar">
                                <img src="{{ $employee->passport_photo
                                    ? asset('storage/' . $employee->passport_photo)
                                    : asset('assets/images/avatar/avatar.jpg') }}">
                            </div>
                            <div>
                                <div class="emp-name">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                                <div class="emp-pos">
                                    <i class="bi bi-briefcase"></i> {{ $employee->position }}
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="emp-dept-badge emp-dept-{{ Str::slug($employee->department ?? 'default') }}">
                            {{ $employee->department ?? '—' }}
                        </span>
                    </td>

                    <td>
                        <span class="emp-branch">{{ $employee->branch ?? '—' }}</span>
                    </td>

                    <td class="emp-td-muted">
                        <i class="bi bi-telephone"></i> {{ $employee->primary_phone ?? '—' }}
                    </td>

                    <td>
                        @if($employee->contract_start && $employee->contract_end)
                            <div class="emp-contract {{ $contractEndingSoon ? 'emp-contract-warn' : '' }}">
                                @if($contractEndingSoon)
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                @else
                                    <i class="bi bi-calendar-event"></i>
                                @endif
                                {{ $employee->contract_start->format('M d, Y') }}
                                &rarr;
                                {{ $employee->contract_end->format('M d, Y') }}
                                @if($contractEndingSoon)
                                    <span class="emp-soon-label">ending soon</span>
                                @endif
                            </div>
                        @else
                            <span class="emp-td-muted">—</span>
                        @endif
                    </td>

                    <td>
                        <span class="emp-status-badge emp-status-{{ strtolower($employee->employment_status) }}">
                            {{ $employee->employment_status }}
                        </span>
                    </td>

                    <td onclick="event.stopPropagation()" class="emp-actions-cell">
                        <div class="emp-row-actions">
                            <a href="{{ route('employees.show', $employee->id) }}"
                               class="emp-action-btn" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('employees.edit', $employee->id) }}"
                               class="emp-action-btn" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                    </td>

                </tr>

                @endforeach
            </tbody>

        </table>

        <div id="empTableEmpty" class="emp-empty-state" style="display:none;">
            <i class="bi bi-search"></i>
            <div>No employees match your search or filters.</div>
        </div>

    </div>

    {{-- ===== CARD VIEW ===== --}}
    <div id="empCardView" style="display:none;">

        @foreach($employees->groupBy('department') as $department => $group)

        <div class="emp-dept-group" data-dept-group="{{ $department }}">

            <div class="emp-dept-group-label">
                {{ $department ?? 'Unassigned' }}
                <span class="emp-dept-count">{{ $group->count() }}</span>
            </div>

            <div class="emp-card-grid" id="cardGroup-{{ Str::slug($department ?? 'unassigned') }}">

                @foreach($group as $employee)

                @php
                    $contractEndingSoon = $employee->contract_end
                        && \Carbon\Carbon::parse($employee->contract_end)->isFuture()
                        && \Carbon\Carbon::parse($employee->contract_end)->diffInDays(now()) <= 30;
                @endphp

                <div class="emp-card-col"
                     data-dept="{{ $employee->department }}"
                     data-status="{{ $employee->employment_status }}"
                     data-search="{{ strtolower($employee->first_name . ' ' . $employee->last_name . ' ' . $employee->employee_id . ' ' . $employee->position . ' ' . $employee->department) }}">

                    <div class="emp-person-card" onclick="window.location='{{ route('employees.show', $employee->id) }}'">

                        <div class="emp-card-top">

                            <div class="emp-avatar emp-avatar-lg">
                                @if($employee->passport_photo)
                                    <img src="{{ asset('storage/' . $employee->passport_photo) }}"
                                         alt="{{ $employee->first_name }}">
                                @else
                                    <span>{{ strtoupper(substr($employee->first_name, 0, 1)) }}{{ strtoupper(substr($employee->last_name, 0, 1)) }}</span>
                                @endif
                            </div>

                            <div class="flex-grow-1 min-width-0">
                                <div class="emp-card-name">
                                    {{ $employee->first_name }} {{ $employee->last_name }}
                                </div>
                                <div class="emp-card-pos">{{ $employee->position }}</div>
                                <div class="emp-card-id">
                                    <i class="bi bi-hash"></i>{{ $employee->employee_id }}
                                </div>
                            </div>

                            <span class="emp-status-badge emp-status-{{ strtolower($employee->employment_status) }}">
                                {{ $employee->employment_status }}
                            </span>

                        </div>

                        @if($employee->primary_phone)
                        <div class="emp-card-detail">
                            <i class="bi bi-telephone"></i> {{ $employee->primary_phone }}
                        </div>
                        @endif

                        @if($contractEndingSoon)
                        <div class="emp-card-warn">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Contract ending soon ({{ $employee->contract_end->format('M d, Y') }})
                        </div>
                        @endif

                        <div class="emp-card-footer">
                            <span class="emp-dept-badge emp-dept-{{ Str::slug($employee->department ?? 'default') }}">
                                {{ $employee->department ?? 'Unassigned' }}
                            </span>
                            <div class="emp-card-actions" onclick="event.stopPropagation()">
                                <a href="{{ route('employees.show', $employee->id) }}" class="emp-action-btn" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('employees.edit', $employee->id) }}" class="emp-action-btn" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </div>

                    </div>

                </div>

                @endforeach

            </div>

        </div>

        @endforeach

        <div id="empCardEmpty" class="emp-empty-state" style="display:none;">
            <i class="bi bi-search"></i>
            <div>No employees match your search or filters.</div>
        </div>

    </div>

</div>


{{-- ===== STYLES ===== --}}
<style>
.emp * { box-sizing: border-box; }

.emp {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    color: #0F172A;
    background: #F8FAFC;
    padding: 1.5rem 0 2.5rem;
}

/* ---- Base card ---- */
.emp-card {
    background: #fff;
    border: 1px solid #E9EDF2;
    border-radius: 16px;
    transition: box-shadow .2s ease, border-color .2s ease;
}

/* ---- Top bar ---- */
.emp-top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: .75rem;
}
.emp-title {
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: -.02em;
    margin: 0;
    color: #0F172A;
}
.emp-subtitle {
    font-size: .82rem;
    color: #94A3B8;
    margin-top: .15rem;
}
.emp-top-right {
    display: flex;
    align-items: center;
    gap: .6rem;
    flex-wrap: wrap;
}

/* ---- Buttons ---- */
.emp-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    font-size: .82rem;
    font-weight: 600;
    padding: .55rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    transition: background .18s ease, transform .18s ease, border-color .18s ease;
}
.emp-btn-primary { background: #00742D; color: #fff; }
.emp-btn-primary:hover { background: #00611F; color: #fff; transform: translateY(-1px); }
.emp-btn-outline { background: #fff; color: #475569; border-color: #E5E9F0; }
.emp-btn-outline:hover { border-color: #CBD5E1; color: #0F172A; }

/* ---- Search ---- */
.emp-search-wrap { position: relative; }
.emp-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #B4BECC;
    font-size: .82rem;
    pointer-events: none;
}
.emp-search-input {
    padding: .55rem .85rem .55rem 2.1rem;
    border: 1px solid #E5E9F0;
    border-radius: 10px;
    font-size: .82rem;
    width: 230px;
    outline: none;
    background: #fff;
    transition: border-color .15s;
}
.emp-search-input:focus { border-color: #00742D; }

/* ---- Stats ---- */
.emp-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}
@media (max-width: 900px) { .emp-stats { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 540px) { .emp-stats { grid-template-columns: 1fr; } }

.emp-stat-card {
    background: #fff;
    border: 1px solid #E9EDF2;
    border-radius: 16px;
    padding: 1.1rem 1.25rem;
    transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
}
.emp-stat-card:hover {
    box-shadow: 0 8px 24px rgba(15, 23, 42, .07);
    border-color: #D7DEE8;
    transform: translateY(-2px);
}
.emp-stat-label {
    font-size: .74rem;
    font-weight: 600;
    color: #94A3B8;
    margin-bottom: .5rem;
}
.emp-stat-value {
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -.02em;
    color: #0F172A;
}
.emp-stat-green { color: #10B981; }
.emp-stat-red   { color: #F43F5E; }

/* ---- Filter bar ---- */
.emp-filter-bar {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
    padding: .85rem 1.25rem;
    margin-bottom: 1rem;
}
.emp-filter-label { font-size: .78rem; color: #94A3B8; font-weight: 600; }

.emp-select {
    font-size: .78rem;
    padding: .4rem .7rem;
    border: 1px solid #E5E9F0;
    border-radius: 8px;
    background: #fff;
    color: #334155;
    outline: none;
    cursor: pointer;
}
.emp-select:focus { border-color: #00742D; }

/* ---- View toggle ---- */
.emp-view-toggle {
    display: flex;
    border: 1px solid #E5E9F0;
    border-radius: 9px;
    overflow: hidden;
}
.emp-view-btn {
    padding: .4rem .85rem;
    border: none;
    background: #fff;
    cursor: pointer;
    font-size: .78rem;
    font-weight: 600;
    color: #94A3B8;
    border-right: 1px solid #E5E9F0;
    display: flex;
    align-items: center;
    gap: .35rem;
    transition: background .15s, color .15s;
}
.emp-view-btn:last-child { border-right: none; }
.emp-view-btn.active { background: #00742D; color: #fff; }

/* ---- Table ---- */
.emp-table-card { padding: 0; overflow: hidden; }
.emp-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
.emp-table thead th {
    font-size: .68rem;
    font-weight: 700;
    color: #94A3B8;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: .9rem 1rem;
    border-bottom: 1px solid #EEF1F5;
    text-align: left;
    white-space: nowrap;
    background: #FAFBFC;
}
.emp-row {
    border-bottom: 1px solid #F1F5F9;
    cursor: pointer;
    transition: background .15s;
}
.emp-row:last-child { border-bottom: none; }
.emp-row:hover { background: #F8FAFC; }
.emp-row td { padding: .75rem 1rem; vertical-align: middle; }

.emp-td-id { font-size: .72rem; color: #B4BECC; white-space: nowrap; }
.emp-td-muted { color: #94A3B8; font-size: .78rem; }

/* ---- Avatar ---- */
.emp-avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .74rem;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
    background: #ECFDF5;
    color: #00742D;
}
.emp-avatar img { width: 100%; height: 100%; object-fit: cover; }
.emp-avatar-lg { width: 48px; height: 48px; font-size: .85rem; }

/* Department badge palette */
.emp-dept-it          { background: #EEF2FF; color: #4338CA; }
.emp-dept-finance     { background: #ECFDF5; color: #059669; }
.emp-dept-hr          { background: #FFF1F2; color: #E11D48; }
.emp-dept-operations  { background: #FFFBEB; color: #B45309; }
.emp-dept-default     { background: #F1F5F9; color: #64748B; }

/* ---- Name cell ---- */
.emp-name-cell { display: flex; align-items: center; gap: .7rem; }
.emp-name { font-weight: 600; font-size: .84rem; color: #0F172A; }
.emp-pos { font-size: .72rem; color: #94A3B8; margin-top: .05rem; }

/* ---- Badges ---- */
.emp-dept-badge, .emp-status-badge {
    display: inline-block;
    font-size: .7rem;
    font-weight: 600;
    padding: .18rem .65rem;
    border-radius: 20px;
    white-space: nowrap;
}
.emp-status-active   { background: #ECFDF5; color: #059669; }
.emp-status-inactive { background: #F1F5F9; color: #64748B; }
.emp-branch { font-size: .78rem; color: #64748B; }

/* ---- Contract ---- */
.emp-contract { font-size: .74rem; color: #94A3B8; white-space: nowrap; }
.emp-contract-warn { color: #F43F5E; font-weight: 600; }
.emp-soon-label {
    display: inline-block;
    margin-left: .3rem;
    background: #FFF1F2;
    color: #F43F5E;
    font-size: .64rem;
    font-weight: 700;
    padding: .08rem .45rem;
    border-radius: 20px;
}

/* ---- Row actions ---- */
.emp-actions-cell { text-align: right; }
.emp-row-actions {
    display: flex;
    gap: .3rem;
    justify-content: flex-end;
    opacity: 0;
    transition: opacity .15s;
}
.emp-row:hover .emp-row-actions,
.emp-card-actions { opacity: 1; }

.emp-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px; height: 30px;
    border: 1px solid #E5E9F0;
    border-radius: 8px;
    color: #64748B;
    text-decoration: none;
    background: #fff;
    font-size: .82rem;
    transition: background .15s, color .15s, border-color .15s;
}
.emp-action-btn:hover { background: #ECFDF5; color: #00742D; border-color: #A7F3D0; }

/* ---- Empty state ---- */
.emp-empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
    color: #B4BECC;
    font-size: .85rem;
}
.emp-empty-state i { font-size: 1.8rem; display: block; margin-bottom: .6rem; color: #CBD5E1; }

/* ---- Card view ---- */
.emp-dept-group { margin-bottom: 1.75rem; }
.emp-dept-group-label {
    font-size: .78rem;
    font-weight: 700;
    color: #00742D;
    background: #ECFDF5;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .35rem .85rem;
    border-radius: 8px;
    margin-bottom: .85rem;
}
.emp-dept-count {
    background: #00742D;
    color: #fff;
    font-size: .64rem;
    font-weight: 700;
    padding: .08rem .45rem;
    border-radius: 20px;
}

.emp-card-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 1024px) { .emp-card-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .emp-card-grid { grid-template-columns: 1fr; } }

.emp-person-card {
    border: 1px solid #E9EDF2;
    border-radius: 16px;
    padding: 1.1rem 1.2rem;
    background: #fff;
    cursor: pointer;
    height: 100%;
    transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
}
.emp-person-card:hover {
    box-shadow: 0 10px 26px rgba(15, 23, 42, .08);
    border-color: #D7DEE8;
    transform: translateY(-3px);
}
.emp-card-top { display: flex; align-items: flex-start; gap: .7rem; margin-bottom: .7rem; }
.emp-card-name {
    font-weight: 700;
    font-size: .88rem;
    color: #0F172A;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.emp-card-pos { font-size: .74rem; color: #94A3B8; margin-top: .05rem; }
.emp-card-id { font-size: .68rem; color: #B4BECC; margin-top: .1rem; }
.emp-card-detail { font-size: .78rem; color: #64748B; margin-bottom: .3rem; }
.emp-card-warn { font-size: .72rem; color: #F43F5E; font-weight: 600; margin-bottom: .3rem; }

.emp-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: .8rem;
    padding-top: .8rem;
    border-top: 1px solid #F1F5F9;
}
.emp-card-actions { display: flex; gap: .3rem; }

.min-width-0 { min-width: 0; }

/* ---- Print ---- */
@media print {
    .emp-top-right, .emp-filter-bar, .emp-row-actions, .emp-card-actions { display: none !important; }
}

/* ---- Responsive ---- */
@media (max-width: 768px) {
    .emp-search-input { width: 160px; }
    .emp-table thead th:nth-child(4), .emp-table tbody td:nth-child(4),
    .emp-table thead th:nth-child(5), .emp-table tbody td:nth-child(5) { display: none; }
}
</style>


{{-- ===== SCRIPTS ===== --}}
<script>
function empFilter() {
    const search = document.getElementById('empSearch').value.toLowerCase().trim();
    const dept   = document.getElementById('empDeptFilter').value;
    const status = document.getElementById('empStatusFilter').value;

    const rows = document.querySelectorAll('#empTable tbody .emp-row');
    let tableVisible = 0;

    rows.forEach(row => {
        const matchSearch = !search || row.dataset.search.includes(search);
        const matchDept   = !dept   || row.dataset.dept   === dept;
        const matchStatus = !status || row.dataset.status === status;
        const show = matchSearch && matchDept && matchStatus;
        row.style.display = show ? '' : 'none';
        if (show) tableVisible++;
    });

    document.getElementById('empTableEmpty').style.display = tableVisible === 0 ? 'block' : 'none';

    const cols = document.querySelectorAll('.emp-card-col');
    let cardVisible = 0;

    cols.forEach(col => {
        const matchSearch = !search || col.dataset.search.includes(search);
        const matchDept   = !dept   || col.dataset.dept   === dept;
        const matchStatus = !status || col.dataset.status === status;
        const show = matchSearch && matchDept && matchStatus;
        col.style.display = show ? '' : 'none';
        if (show) cardVisible++;
    });

    document.querySelectorAll('.emp-dept-group').forEach(group => {
        const visibleInGroup = group.querySelectorAll('.emp-card-col:not([style*="display: none"])').length;
        group.style.display = visibleInGroup === 0 ? 'none' : '';
    });

    document.getElementById('empCardEmpty').style.display = cardVisible === 0 ? 'block' : 'none';
}

function empShowView(view) {
    const tableView = document.getElementById('empTableView');
    const cardView  = document.getElementById('empCardView');
    const btnTable  = document.getElementById('btnTable');
    const btnCard   = document.getElementById('btnCard');

    if (view === 'table') {
        tableView.style.display = 'block';
        cardView.style.display  = 'none';
        btnTable.classList.add('active');
        btnCard.classList.remove('active');
    } else {
        tableView.style.display = 'none';
        cardView.style.display  = 'block';
        btnTable.classList.remove('active');
        btnCard.classList.add('active');
    }
}

(function () {
    const saved = sessionStorage.getItem('empView');
    if (saved === 'card') empShowView('card');
})();

document.getElementById('btnTable').addEventListener('click', () => sessionStorage.setItem('empView', 'table'));
document.getElementById('btnCard').addEventListener('click',  () => sessionStorage.setItem('empView', 'card'));
</script>

@endsection