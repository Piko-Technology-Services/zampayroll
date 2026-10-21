@extends('layouts.app')

@section('content')
@php $me = auth()->user(); @endphp

{{-- Company code --}}
<section class="tm-code">
    <div>
        <div class="tm-code-label">Your company code</div>
        <div class="tm-code-value" translate="no" id="tmCode">{{ $company->company_code }}</div>
        <p>Your team signs in with just their email and password. This code is only needed by people whose email belongs to more than one company.</p>
    </div>
    <div class="tm-code-actions">
        <button type="button" class="zp-btn zp-btn-copper" onclick="navigator.clipboard.writeText('{{ $company->company_code }}');this.innerHTML='<i class=\'bi bi-check2\'></i> Copied'"><i class="bi bi-clipboard"></i> Copy code</button>
        <a class="zp-btn zp-btn-ghost" style="color:#fff;border-color:rgba(255,255,255,.3)" target="_blank" rel="noopener"
           href="https://wa.me/?text={{ urlencode("Sign in to {$company->name} on ZamPayroll with your email and password: " . route('login')) }}"><i class="bi bi-whatsapp"></i> Share on WhatsApp</a>
    </div>
</section>

<div class="row g-4">
    {{-- Members --}}
    <div class="col-xl-7">
        <div class="tm-card">
            <div class="tm-card-head">
                <h2>People with access <span>{{ $members->count() }}</span></h2>
            </div>
            <div class="table-responsive">
                <table class="tm-table">
                    <thead><tr><th>Name</th><th>Role</th><th>Last seen</th><th></th></tr></thead>
                    <tbody>
                    @foreach($members as $m)
                        <tr>
                            <td>
                                <b>{{ $m->name }}</b> @if($m->id === $me->id)<span class="zp-portal-pill">You</span>@endif
                                <div class="tm-muted">{{ $m->email }}@if($m->position) · {{ $m->position }}@endif</div>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('team.members.update', $m) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="position" value="{{ $m->position }}">
                                    <select name="role" class="zp-select tm-role-select" onchange="this.form.submit()" aria-label="Role for {{ $m->name }}">
                                        @foreach($roles as $r)
                                            <option value="{{ $r->key }}" @selected($m->roleKey() === $r->key)>{{ $r->name }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="tm-muted">{{ $m->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="text-end">
                                @if($m->id !== $me->id)
                                    <form method="POST" action="{{ route('team.members.destroy', $m) }}" onsubmit="return confirm('Remove {{ addslashes($m->name) }}\'s access?')">
                                        @csrf @method('DELETE')
                                        <button class="tm-icon" title="Remove access" aria-label="Remove access"><i class="bi bi-person-x"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="tm-muted mt-2 mb-0"><i class="bi bi-info-circle"></i> Employees with a self-service portal are managed from their employee file, not here.</p>
        </div>
    </div>

    {{-- Invite --}}
    <div class="col-xl-5">
        <div class="tm-card">
            <div class="tm-card-head"><h2>Invite someone</h2></div>
            <form method="POST" action="{{ route('invitations.store') }}">
                @csrf
                <div class="zp-field">
                    <label class="zp-label" for="inv_email">Email</label>
                    <input class="zp-input" type="email" id="inv_email" name="email" required placeholder="colleague@company.co.zm">
                </div>
                <div class="zp-field">
                    <span class="zp-label">Role</span>
                    <div class="tm-role-pick">
                        @foreach($roles as $r)
                            <label class="tm-role-opt">
                                <input type="radio" name="role" value="{{ $r->key }}" @checked($loop->index === 1) required>
                                <span><b>{{ $r->name }}</b><small>{{ $r->description ?: count($r->permissions ?? []) . ' permissions' }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="zp-field">
                    <label class="zp-label" for="inv_pos">Job title <small>(optional)</small></label>
                    <input class="zp-input" id="inv_pos" name="position" placeholder="e.g. Payroll Officer">
                </div>
                <button class="zp-btn zp-btn-primary zp-btn-block"><i class="bi bi-send"></i> Send invitation</button>
            </form>

            @if($invitations->isNotEmpty())
                <div class="tm-muted mt-3 mb-1 fw-semibold">Waiting to accept</div>
                @foreach($invitations as $inv)
                    <div class="d-flex justify-content-between align-items-center py-1 small">
                        <span>{{ $inv->email }} <span class="tm-muted">· {{ $roles->firstWhere('key', $inv->role)?->name ?? $inv->role }}</span></span>
                        <form method="POST" action="{{ route('invitations.revoke', $inv) }}">@csrf @method('DELETE')
                            <button class="zp-btn-link" style="border:0;background:none;color:var(--zp-red);font-size:.8rem">Revoke</button>
                        </form>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

{{-- Roles & permissions --}}
<div class="tm-card mt-4" id="roles">
    <div class="tm-card-head">
        <div>
            <h2>Roles &amp; permissions</h2>
            <p class="tm-muted mb-0">Tick what each role can do. Administrators always have full access.</p>
        </div>
        <button type="button" class="zp-btn zp-btn-ghost" onclick="document.getElementById('newRole').hidden=false;document.getElementById('nr_name').focus()"><i class="bi bi-plus-lg"></i> New role</button>
    </div>

    <form id="newRole" hidden method="POST" action="{{ route('team.roles.store') }}" class="tm-newrole">
        @csrf
        <input class="zp-input" id="nr_name" name="name" placeholder="Role name, e.g. Branch Supervisor" required maxlength="80">
        <select class="zp-select" name="copy_from" aria-label="Start from">
            <option value="">Start empty</option>
            @foreach($roles->reject->isAdmin() as $r)<option value="{{ $r->key }}">Copy {{ $r->name }}</option>@endforeach
        </select>
        <button class="zp-btn zp-btn-primary">Create</button>
    </form>

    <form method="POST" action="{{ route('team.roles.update') }}">
        @csrf @method('PUT')
        <div class="tm-matrix-wrap">
            <table class="tm-matrix">
                <thead>
                    <tr>
                        <th>Permission</th>
                        @foreach($roles as $r)
                            <th>
                                {{ $r->name }}
                                @php $rc = (int) ($roleCounts[$r->key] ?? 0); @endphp<small>{{ $rc }} {{ $rc === 1 ? 'person' : 'people' }}</small>
                                @unless($r->is_system)
                                    <button type="submit" form="del-{{ $r->id }}" class="tm-del" title="Delete role" aria-label="Delete {{ $r->name }}"><i class="bi bi-trash3"></i></button>
                                @endunless
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $group => $perms)
                        <tr class="tm-group"><td colspan="{{ $roles->count() + 1 }}">{{ $group }}</td></tr>
                        @foreach($perms as $perm => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                @foreach($roles as $r)
                                    <td class="tm-cell">
                                        @if($r->isAdmin())
                                            <i class="bi bi-check-circle-fill" style="color:var(--zp-green)" title="Always"></i>
                                        @else
                                            <input type="checkbox" name="permissions[{{ $r->key }}][]" value="{{ $perm }}" @checked($r->allows($perm)) aria-label="{{ $r->name }}: {{ $label }}">
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-end mt-3"><button class="zp-btn zp-btn-primary"><i class="bi bi-check2"></i> Save permissions</button></div>
    </form>
    @foreach($roles->where('is_system', false) as $r)
        <form id="del-{{ $r->id }}" method="POST" action="{{ route('team.roles.destroy', $r) }}" onsubmit="return confirm('Delete the {{ addslashes($r->name) }} role?')">@csrf @method('DELETE')</form>
    @endforeach
</div>

@push('styles')
<style>
.tm-code { display:flex; justify-content:space-between; gap:1.5rem; align-items:center; flex-wrap:wrap; background:var(--zp-forest); color:#DCEDE2; border-radius:20px; padding:1.4rem 1.6rem; margin-bottom:1.5rem; position:relative; overflow:hidden; }
.tm-code::after { content:""; position:absolute; left:0; right:0; bottom:0; height:4px; background:linear-gradient(90deg,#00742D 0 70%,#C42926 70% 80%,#101512 80% 90%,#C8742B 90%); }
.tm-code-label { font-size:.85rem; color:#A9C8B5; font-weight:600; }
.tm-code-value { font-family:var(--zp-font-display); font-size:2.4rem; font-weight:700; color:#fff; letter-spacing:.06em; margin:.1rem 0 .3rem; }
.tm-code p { margin:0; max-width:60ch; font-size:.9rem; color:#B9D3C3; }
.tm-code-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
.tm-card { background:#fff; border:1px solid var(--zp-line); border-radius:16px; padding:1.2rem 1.3rem; height:100%; }
.tm-card-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1rem; }
.tm-card-head h2 { font-size:1.1rem; font-weight:700; margin:0; }
.tm-card-head h2 span { font-size:.8rem; color:var(--zp-ink-3); font-weight:600; margin-left:.3rem; }
.tm-muted { color:var(--zp-ink-3); font-size:.82rem; }
.tm-table { width:100%; border-collapse:collapse; font-size:.9rem; }
.tm-table th { font-size:.75rem; color:var(--zp-ink-3); font-weight:600; padding:.4rem .5rem; border-bottom:1px solid var(--zp-line); }
.tm-table td { padding:.65rem .5rem; border-bottom:1px solid #EEF2EF; vertical-align:middle; }
.tm-role-select { padding:.4rem .6rem; font-size:.85rem; width:auto; min-width:160px; }
.tm-icon { border:0; background:none; color:var(--zp-ink-3); padding:.3rem .45rem; border-radius:8px; }
.tm-icon:hover { color:var(--zp-red); background:#FBEAEA; }
.tm-role-pick { display:flex; flex-direction:column; gap:.4rem; }
.tm-role-opt { display:flex; gap:.6rem; align-items:flex-start; border:1.5px solid var(--zp-line); border-radius:12px; padding:.55rem .7rem; cursor:pointer; }
.tm-role-opt:has(input:checked) { border-color:var(--zp-green); background:var(--zp-green-50); }
.tm-role-opt input { margin-top:.25rem; accent-color:var(--zp-green); }
.tm-role-opt b { display:block; font-size:.88rem; } .tm-role-opt small { color:var(--zp-ink-3); font-size:.78rem; }
.tm-newrole { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem; padding:.8rem; background:var(--zp-mist); border-radius:12px; }
.tm-newrole .zp-input { flex:1; min-width:220px; } .tm-newrole .zp-select { width:auto; }
.tm-matrix-wrap { overflow-x:auto; border:1px solid var(--zp-line); border-radius:12px; }
.tm-matrix { width:100%; border-collapse:collapse; font-size:.86rem; }
.tm-matrix th { background:#FAFCFA; padding:.7rem .6rem; text-align:center; font-weight:700; border-bottom:1px solid var(--zp-line); white-space:nowrap; position:relative; }
.tm-matrix th:first-child, .tm-matrix td:first-child { text-align:left; position:sticky; left:0; background:#fff; min-width:260px; }
.tm-matrix th:first-child { background:#FAFCFA; }
.tm-matrix th small { display:block; font-weight:500; color:var(--zp-ink-3); font-size:.72rem; }
.tm-matrix td { padding:.5rem .6rem; border-bottom:1px solid #EEF2EF; }
.tm-cell { text-align:center; }
.tm-cell input { width:18px; height:18px; accent-color:var(--zp-green); cursor:pointer; }
.tm-group td { background:var(--zp-mist) !important; font-weight:700; font-size:.78rem; color:var(--zp-ink-2); padding:.45rem .6rem; }
.tm-del { border:0; background:none; color:var(--zp-ink-3); font-size:.8rem; padding:0 .2rem; }
.tm-del:hover { color:var(--zp-red); }
</style>
@endpush
@endsection
