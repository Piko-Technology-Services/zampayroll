@extends('layouts.app')

@section('content')

{{-- =====================================================================
     EMPLOYEES — Personnel Registry
     Structural layout: left "registry rail" holds the primary action,
     filters, view switch and overview counts. Right column is pure
     content — a search bar sitting directly above the ledger/index
     cards. Same dossier visual language, different anatomy.
====================================================================== --}}

<div class="emp">

    <div class="emp-shell">

        {{-- ===== LEFT RAIL ===== --}}
        <aside class="emp-rail">

            <a href="{{ route('employees.create') }}" class="emp-rail-cta">
                <i class="bi bi-plus-lg"></i> New Employee
            </a>

            <button class="emp-rail-secondary"
                    data-bs-toggle="modal"
                    data-bs-target="#importEmployeesModal">
                <i class="bi bi-upload"></i> Import Employees
            </button>

            <div class="emp-rail-divider"></div>

            <div class="emp-rail-section">
                <div class="emp-rail-label">Department</div>
                <select id="empDeptFilter" class="emp-select" onchange="empFilter()">
                    <option value="">All Departments</option>
                    @foreach($employees->pluck('department')->filter()->unique()->sort() as $dept)
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            <div class="emp-rail-section">
                <div class="emp-rail-label">Status</div>
                <select id="empStatusFilter" class="emp-select" onchange="empFilter()">
                    <option value="">All Statuses</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div class="emp-rail-divider"></div>

            <div class="emp-rail-section">
                <div class="emp-rail-label">View</div>
                <div class="emp-view-stack">
                    <button id="btnTable" class="emp-view-btn active" onclick="empShowView('table')">
                        <i class="bi bi-list-ul"></i> Table
                    </button>
                    <button id="btnCard" class="emp-view-btn" onclick="empShowView('card')">
                        <i class="bi bi-grid"></i> Cards
                    </button>
                </div>
            </div>

            <div class="emp-rail-divider"></div>

            <div class="emp-rail-section">
                <div class="emp-rail-label">Overview</div>

                <div class="emp-rail-stats">
                    <div class="emp-rail-stat-row">
                        <span class="emp-rail-stat-dot"></span>
                        <span class="emp-rail-stat-label">Total Employees</span>
                        <span class="emp-rail-stat-value">{{ $employees->count() }}</span>
                    </div>
                    <div class="emp-rail-stat-row">
                        <span class="emp-rail-stat-dot emp-dot-green"></span>
                        <span class="emp-rail-stat-label">Active</span>
                        <span class="emp-rail-stat-value emp-stat-green">
                            {{ $employees->where('employment_status', 'Active')->count() }}
                        </span>
                    </div>
                    <div class="emp-rail-stat-row">
                        <span class="emp-rail-stat-dot"></span>
                        <span class="emp-rail-stat-label">Departments</span>
                        <span class="emp-rail-stat-value">
                            {{ $employees->pluck('department')->filter()->unique()->count() }}
                        </span>
                    </div>
                    <div class="emp-rail-stat-row">
                        <span class="emp-rail-stat-dot emp-dot-red"></span>
                        <span class="emp-rail-stat-label">Contracts Expiring</span>
                        <span class="emp-rail-stat-value emp-stat-red">
                            {{ $employees->filter(fn($e) => $e->contract_end && \Carbon\Carbon::parse($e->contract_end)->diffInDays(now()) <= 30 && \Carbon\Carbon::parse($e->contract_end)->isFuture())->count() }}
                        </span>
                    </div>
                </div>
            </div>

        </aside>

        {{-- ===== MAIN CONTENT ===== --}}
        <div class="emp-main">

            <div class="emp-main-head">
                <div>
                    <div class="emp-eyebrow">Personnel Registry</div>
                    <h1 class="emp-title">Employees</h1>
                </div>
            </div>

            <div class="emp-toolbar">
                <div class="emp-search-wrap">
                    <i class="bi bi-search emp-search-icon"></i>
                    <input type="text"
                           id="empSearch"
                           class="emp-search-input"
                           placeholder="Search name, ID, position…"
                           oninput="empFilter()">
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
                                && \Carbon\Carbon::parse($employee->contract_end)->diffInDays(now()) <= 60;

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
                                        {{ $employee->contract_start->format('Y-m-d') }}
                                        &rarr;
                                        {{ $employee->contract_end->format('Y-m-d') }}
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
                                    Contract ending soon ({{ $employee->contract_end->format('Y-m-d') }})
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

        </div>{{-- end main --}}

    </div>{{-- end shell --}}

</div>


{{-- ===== STYLES ===== --}}
<style>
@import url('https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');

.emp * { box-sizing: border-box; }

.emp {
    --paper: #ffffff;
    --ink: #00742E;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #029508;
    --brass-soft: #cdffcf;
    --line: #f3f3f3;
    --active: #2F6F4E;
    --active-bg: #EAF3EC;
    --inactive: #029508;
    --inactive-bg: #F0EEE7;
    --warn: #cf1c00;
    --warn-bg: #F7E7E2;

    font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ink);
    background: var(--paper);
    padding: 1.5rem 0 2.5rem;
}

/* ---- Shell: rail + main ---- */
.emp-shell {
    display: flex;
    align-items: flex-start;
    gap: 1.25rem;
}

/* ---- Rail ---- */
.emp-rail {
    width: 236px;
    flex-shrink: 0;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 6px;
    padding: 1.25rem 1.1rem;
    position: sticky;
    top: 1.25rem;
}

.emp-rail-cta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    width: 100%;
    font-size: .82rem;
    font-weight: 700;
    padding: .7rem .9rem;
    border-radius: 6px;
    background: var(--ink);
    color: #fff;
    text-decoration: none;
    border: 1px solid var(--ink);
    transition: background .18s;
}
.emp-rail-cta:hover { background: #029508; color: #fff; }

.emp-rail-secondary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .45rem;
    width: 100%;
    font-size: .78rem;
    font-weight: 600;
    padding: .6rem .9rem;
    border-radius: 6px;
    background: #fff;
    color: var(--ink-soft);
    border: 1px dashed #f0f0f0;
    cursor: pointer;
    margin-top: .55rem;
    transition: border-color .15s, color .15s;
}
.emp-rail-secondary:hover { border-color: var(--brass); color: var(--brass); }

.emp-rail-divider { border-top: 1px solid var(--line); margin: 1.1rem 0; }

.emp-rail-label {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;

    font-size: .64rem;
    font-weight: 600;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: var(--brass);
    margin-bottom: .5rem;
}
.emp-rail-section + .emp-rail-section { margin-top: 1rem; }

.emp-select {
    width: 100%;
    font-size: .8rem;
    padding: .5rem .65rem;
    border: 1px solid var(--line);
    border-radius: 5px;
    background: #fafafa;
    color: var(--ink-soft);
    outline: none;
    cursor: pointer;
}
.emp-select:focus { border-color: var(--brass); }

/* ---- Vertical view stack ---- */
.emp-view-stack {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--line);
    border-radius: 6px;
    overflow: hidden;
}
.emp-view-btn {
    display: flex;
    align-items: center;
    gap: .45rem;
    padding: .55rem .8rem;
    border: none;
    border-bottom: 1px solid var(--line);
    background: #fff;
    cursor: pointer;
    font-size: .78rem;
    font-weight: 600;
    color: var(--ink-faint);
    transition: background .15s, color .15s;
}
.emp-view-btn:last-child { border-bottom: none; }
.emp-view-btn.active { background: var(--brass-soft); color: var(--ink); }

/* ---- Rail stats ---- */
.emp-rail-stats { display: flex; flex-direction: column; gap: .55rem; }
.emp-rail-stat-row {
    display: flex;
    align-items: center;
    gap: .5rem;
}
.emp-rail-stat-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--ink-faint);
    flex-shrink: 0;
}
.emp-dot-green { background: var(--active); }
.emp-dot-red { background: var(--warn); }
.emp-rail-stat-label {
    font-size: .74rem;
    color: var(--ink-soft);
    flex: 1;
}
.emp-rail-stat-value {
    font-family: 'IBM Plex Mono', monospace;
    font-size: .84rem;
    font-weight: 600;
    color: var(--ink);
}
.emp-stat-green { color: var(--active); }
.emp-stat-red   { color: var(--warn); }

/* ---- Main ---- */
.emp-main { flex: 1; min-width: 0; }

.emp-main-head { margin-bottom: 1rem; }
.emp-eyebrow {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    font-size: .68rem;
    font-weight: 600;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--brass);
    margin-bottom: .2rem;
}
.emp-title {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: var(--ink);
}

/* ---- Toolbar (search only, full width) ---- */
.emp-toolbar { margin-bottom: 1rem; }
.emp-search-wrap { position: relative; }
.emp-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--ink-faint);
    font-size: .84rem;
    pointer-events: none;
}
.emp-search-input {
    width: 100%;
    padding: .75rem 1rem .75rem 2.4rem;
    border: 1px solid var(--line);
    border-radius: 6px;
    font-size: .88rem;
    outline: none;
    background: #fff;
    color: var(--ink);
    transition: border-color .15s, box-shadow .15s;
}
.emp-search-input:focus { border-color: var(--brass); box-shadow: 0 0 0 3px var(--brass-soft); }

/* ---- Base card ---- */
.emp-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 6px;
}

/* ---- Table (ledger) ---- */
.emp-table-card { padding: 0; overflow: hidden; }
.emp-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
.emp-table thead th {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;

    font-size: .66rem;
    font-weight: 600;
    color: var(--ink-faint);
    text-transform: uppercase;
    letter-spacing: .07em;
    padding: .85rem 1rem;
    border-bottom: 2px solid var(--ink);
    text-align: left;
    white-space: nowrap;
    background: #FCFBF8;
}
.emp-row {
    border-bottom: 1px solid var(--line);
    cursor: pointer;
    transition: background .15s;
}
.emp-row:nth-child(even) { background: #FCFBF8; }
.emp-row:last-child { border-bottom: none; }
.emp-row:hover { background: var(--brass-soft); }
.emp-row td { padding: .7rem 1rem; vertical-align: middle; }

.emp-td-id { font-family: 'IBM Plex Mono', monospace; font-size: .72rem; color: var(--ink-faint); white-space: nowrap; }
.emp-td-muted { color: var(--ink-faint); font-size: .78rem; }

/* ---- Avatar (ID-badge frame) ---- */
.emp-avatar {
    width: 36px; height: 36px;
    border-radius: 6px;
    border: 1px solid var(--ink);
    display: flex; align-items: center; justify-content: center;
    font-size: .72rem;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
    background: var(--brass-soft);
    color: var(--brass);
}
.emp-avatar img { width: 100%; height: 100%; object-fit: cover; }
.emp-avatar-lg { width: 46px; height: 46px; font-size: .82rem; }

/* Department badge palette — ink-toned dossier tags */
.emp-dept-it          { background: #EAEEF4; color: #029508; }
.emp-dept-finance     { background: var(--active-bg); color: var(--active); }
.emp-dept-hr          { background: #F7E7E2; color: #029508; }
.emp-dept-operations  { background: #F5EFDD; color: #029508; }
.emp-dept-default     { background: var(--inactive-bg); color: var(--inactive); }

/* ---- Name cell ---- */
.emp-name-cell { display: flex; align-items: center; gap: .7rem; }
.emp-name { font-weight: 600; font-size: .84rem; color: var(--ink); }
.emp-pos { font-size: .72rem; color: var(--ink-faint); margin-top: .05rem; }

/* ---- Badges ---- */
.emp-dept-badge {
    display: inline-block;
    font-size: .7rem;
    font-weight: 600;
    padding: .18rem .65rem;
    border-radius: 4px;
    white-space: nowrap;
}
.emp-status-badge {
    display: inline-block;
    font-family: 'IBM Plex Mono', monospace;
    font-size: .64rem;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: .2rem .6rem;
    border-radius: 3px;
    white-space: nowrap;
    transform: rotate(-1deg);
}
.emp-status-active   { background: var(--active-bg); color: var(--active); border: 1px solid #C7E0CD; }
.emp-status-inactive { background: var(--inactive-bg); color: var(--inactive); border: 1px solid #DEDACD; }
.emp-branch { font-size: .78rem; color: var(--ink-soft); }

/* ---- Contract ---- */
.emp-contract { font-size: .74rem; color: var(--ink-faint); white-space: nowrap; font-family: 'IBM Plex Mono', monospace; }
.emp-contract-warn { color: var(--warn); font-weight: 600; }
.emp-soon-label {
    display: inline-block;
    margin-left: .3rem;
    background: var(--warn-bg);
    color: var(--warn);
    font-family: 'IBM Plex Sans', sans-serif;
    font-size: .64rem;
    font-weight: 700;
    padding: .08rem .45rem;
    border-radius: 4px;
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
    border: 1px solid var(--line);
    border-radius: 5px;
    color: var(--ink-soft);
    text-decoration: none;
    background: #fff;
    font-size: .8rem;
    transition: background .15s, color .15s, border-color .15s;
}
.emp-action-btn:hover { background: var(--brass-soft); color: var(--ink); border-color: var(--brass); }

/* ---- Empty state ---- */
.emp-empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
    color: var(--ink-faint);
    font-size: .85rem;
}
.emp-empty-state i { font-size: 1.8rem; display: block; margin-bottom: .6rem; color: var(--ink-faint); }

/* ---- Card view (index cards) ---- */
.emp-dept-group { margin-bottom: 1.75rem; }
.emp-dept-group-label {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    font-size: .74rem;
    font-weight: 700;
    color: #fff;
    background: var(--ink);
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .4rem .9rem;
    border-radius: 0 6px 6px 0;
    margin-bottom: .85rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.emp-dept-count {
    background: var(--brass);
    color: #fff;
    font-family: 'IBM Plex Mono', monospace;
    font-size: .64rem;
    font-weight: 600;
    padding: .08rem .45rem;
    border-radius: 20px;
}

.emp-card-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 1200px) { .emp-card-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .emp-card-grid { grid-template-columns: 1fr; } }

.emp-person-card {
    border: 1px solid var(--line);
    border-radius: 6px;
    padding: 1.1rem 1.2rem;
    background: #fff;
    cursor: pointer;
    height: 100%;
    position: relative;
    transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
}
.emp-person-card::before {
    content: "";
    position: absolute;
    top: 0; left: 18px;
    width: 42px; height: 5px;
    background: var(--brass);
    border-radius: 0 0 3px 3px;
}
.emp-person-card:hover {
    box-shadow: 0 12px 26px -16px rgba(23,38,58,.32);
    border-color: var(--brass);
    transform: translateY(-3px);
}
.emp-card-top { display: flex; align-items: flex-start; gap: .7rem; margin-bottom: .7rem; margin-top: .3rem; }
.emp-card-name {
    font-weight: 700;
    font-size: .88rem;
    color: var(--ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.emp-card-pos { font-size: .74rem; color: var(--ink-faint); margin-top: .05rem; }
.emp-card-id { font-family: 'IBM Plex Mono', monospace; font-size: .68rem; color: var(--ink-faint); margin-top: .15rem; }
.emp-card-detail { font-size: .78rem; color: var(--ink-soft); margin-bottom: .3rem; }
.emp-card-warn { font-size: .72rem; color: var(--warn); font-weight: 600; margin-bottom: .3rem; }

.emp-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: .8rem;
    padding-top: .8rem;
    border-top: 1px solid var(--line);
}
.emp-card-actions { display: flex; gap: .3rem; }

.min-width-0 { min-width: 0; }

/* ---- Print ---- */
@media print {
    .emp-rail, .emp-row-actions, .emp-card-actions { display: none !important; }
    .emp-shell { display: block; }
}

/* ---- Responsive ---- */
@media (max-width: 900px) {
    .emp-shell { flex-direction: column; }
    .emp-rail {
        width: 100%;
        position: static;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: .9rem 1.2rem;
        align-items: start;
    }
    .emp-rail-cta, .emp-rail-secondary { grid-column: 1 / -1; }
    .emp-rail-divider { grid-column: 1 / -1; margin: .2rem 0; }
}
@media (max-width: 560px) {
    .emp-rail { grid-template-columns: 1fr; }
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