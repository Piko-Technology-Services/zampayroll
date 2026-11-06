@extends('layouts.app')
@section('title', 'Company documents · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php
    $icon = fn ($ext) => match (true) { $ext === 'pdf' => ['pdf', 'bi-file-earmark-pdf'], in_array($ext, ['doc', 'docx']) => ['word', 'bi-file-earmark-word'],
        in_array($ext, ['xls', 'xlsx']) => ['excel', 'bi-file-earmark-excel'], default => ['image', 'bi-file-earmark-image'] };
    $q = request()->except('page');
@endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Company documents</h1><p>One secure place for contracts, policies, certificates and HR records, ready when you need them. Storing a document here does not mean it has been legally checked.</p></div>
        <div class="pr-head-actions">
            <div class="dropdown">
                <button class="pp-btn pp-btn-ghost dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download"></i> Document register</button>
                <ul class="dropdown-menu">@foreach(['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'] as $f => $l)<li><a class="dropdown-item" href="{{ route('compliance.documents.register', ['format' => $f]) }}">{{ $l }}</a></li>@endforeach</ul>
            </div>
            @if($canManage)<a class="pp-btn pp-btn-primary" href="{{ route('compliance.documents.create') }}"><i class="bi bi-cloud-upload"></i> Upload</a>@endif
        </div></div>

    <div class="cc-kpis">
        <a class="cc-kpi" href="{{ route('compliance.documents.index') }}"><b>{{ $summary['total'] }}</b><span>Documents</span></a>
        <a class="cc-kpi" href="{{ route('compliance.documents.index', ['sort' => 'recent']) }}"><b>{{ $summary['recent'] }}</b><span>Added this week</span></a>
        <a class="cc-kpi {{ $summary['review'] ? 'warn' : '' }}" href="{{ route('compliance.documents.index', ['expiry' => 'review']) }}"><b>{{ $summary['review'] }}</b><span>Due for review</span></a>
        <a class="cc-kpi {{ $summary['expiring'] ? 'warn' : '' }}" href="{{ route('compliance.documents.index', ['expiry' => 'soon']) }}"><b>{{ $summary['expiring'] }}</b><span>Expiring in 30 days</span></a>
        <a class="cc-kpi {{ $summary['expired'] ? 'danger' : '' }}" href="{{ route('compliance.documents.index', ['expiry' => 'expired']) }}"><b>{{ $summary['expired'] }}</b><span>Expired</span></a>
    </div>

    <div class="pr-layout" style="grid-template-columns:260px minmax(0,1fr)">
        <aside class="pr-side" style="position:static">
            <section class="pr-card">
                <h2 style="font-size:1rem;margin-bottom:.5rem">Categories</h2>
                <a href="{{ route('compliance.documents.index', array_diff_key($q, ['category' => 1])) }}" style="display:block;font-weight:{{ request('category') ? 500 : 800 }};margin-bottom:.3rem">All documents</a>
                @foreach($categories as $c)
                    @php $n = ($byCategory[$c->id] ?? 0) + $c->children->sum(fn ($ch) => $byCategory[$ch->id] ?? 0); @endphp
                    <details @if(request('category') == $c->id || $c->children->contains('id', (int) request('category'))) open @endif style="margin-bottom:.25rem">
                        <summary style="cursor:pointer;font-size:.88rem"><a href="{{ route('compliance.documents.index', array_merge($q, ['category' => $c->id])) }}" style="font-weight:{{ request('category') == $c->id ? 800 : 600 }}">{{ $c->name }}</a> <small class="pp-muted">{{ $n }}</small></summary>
                        @foreach($c->children as $ch)
                            <a href="{{ route('compliance.documents.index', array_merge($q, ['category' => $ch->id])) }}" style="display:block;padding-left:1rem;font-size:.84rem;font-weight:{{ request('category') == $ch->id ? 800 : 500 }}">{{ $ch->name }} <small class="pp-muted">{{ $byCategory[$ch->id] ?? 0 }}</small></a>
                        @endforeach
                    </details>
                @endforeach
                @if($canManage)
                <form method="POST" action="{{ route('compliance.categories.store') }}" style="display:grid;gap:.35rem;margin-top:.8rem">@csrf
                    <input class="pp-input" name="name" required maxlength="120" placeholder="New category">
                    <select class="pp-select" name="parent_id"><option value="">Top level</option>@foreach($categories as $c)<option value="{{ $c->id }}">Inside {{ $c->name }}</option>@endforeach</select>
                    <button class="pp-btn pp-btn-ghost pp-btn-sm">Add category</button></form>
                @endif
            </section>
        </aside>
        <section class="pr-card">
            <form class="cc-filters" method="GET">
                <input type="hidden" name="category" value="{{ request('category') }}"><input type="hidden" name="view" value="{{ $view }}">
                <div class="pp-field" style="flex:1;min-width:200px"><label>Search</label><input class="pp-input" name="q" value="{{ request('q') }}" placeholder="Title, reference, tag or description"></div>
                <div class="pp-field"><label>Employee</label><select class="pp-select" name="employee"><option value="">Anyone</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected(request('employee') == $e->id)>{{ $e->first_name }} {{ $e->last_name }}</option>@endforeach</select></div>
                <div class="pp-field"><label>Department</label><select class="pp-select" name="department"><option value="">All</option>@foreach($departments as $d)<option @selected(request('department') === $d)>{{ $d }}</option>@endforeach</select></div>
                <div class="pp-field"><label>Type</label><select class="pp-select" name="type"><option value="">All</option>@foreach(['pdf' => 'PDF', 'word' => 'Word', 'excel' => 'Excel', 'image' => 'Image'] as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="pp-field"><label>Expiry</label><select class="pp-select" name="expiry"><option value="">Any</option>@foreach(['soon' => 'Expiring in 30 days', 'expired' => 'Expired', 'review' => 'Review due'] as $k => $l)<option value="{{ $k }}" @selected(request('expiry') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="pp-field"><label>Status</label><select class="pp-select" name="status"><option value="">Active</option><option value="archived" @selected(request('status') === 'archived')>Archived</option></select></div>
                <details style="width:100%"><summary style="font-size:.84rem;font-weight:700;cursor:pointer">More filters</summary>
                    <div class="cc-filters" style="margin:.5rem 0 0">
                        <div class="pp-field"><label>Tag</label><input class="pp-input" name="tag" value="{{ request('tag') }}"></div>
                        <div class="pp-field"><label>Uploaded from</label><input type="date" class="pp-input" name="uploaded_from" value="{{ request('uploaded_from') }}"></div>
                        <div class="pp-field"><label>Uploaded to</label><input type="date" class="pp-input" name="uploaded_to" value="{{ request('uploaded_to') }}"></div>
                        <div class="pp-field"><label>Issued from</label><input type="date" class="pp-input" name="issued_from" value="{{ request('issued_from') }}"></div>
                        <div class="pp-field"><label>Issued to</label><input type="date" class="pp-input" name="issued_to" value="{{ request('issued_to') }}"></div>
                        <div class="pp-field"><label>Expires from</label><input type="date" class="pp-input" name="expires_from" value="{{ request('expires_from') }}"></div>
                        <div class="pp-field"><label>Expires to</label><input type="date" class="pp-input" name="expires_to" value="{{ request('expires_to') }}"></div>
                        <div class="pp-field"><label>Sort</label><select class="pp-select" name="sort">@foreach(['recent' => 'Newest', 'updated' => 'Recently changed', 'title' => 'Title', 'expiry' => 'Expiry date'] as $k => $l)<option value="{{ $k }}" @selected(request('sort', 'recent') === $k)>{{ $l }}</option>@endforeach</select></div>
                    </div></details>
                <button class="pp-btn pp-btn-ghost">Filter</button>
                <div style="margin-left:auto;display:flex;gap:.3rem">
                    <a class="pp-btn pp-btn-sm {{ $view === 'list' ? 'pp-btn-primary' : 'pp-btn-ghost' }}" href="{{ route('compliance.documents.index', array_merge($q, ['view' => 'list'])) }}" aria-label="List view"><i class="bi bi-list"></i></a>
                    <a class="pp-btn pp-btn-sm {{ $view === 'grid' ? 'pp-btn-primary' : 'pp-btn-ghost' }}" href="{{ route('compliance.documents.index', array_merge($q, ['view' => 'grid'])) }}" aria-label="Grid view"><i class="bi bi-grid"></i></a>
                </div>
            </form>

            @if($documents->isEmpty())
                <div class="pr-empty"><i class="bi bi-folder2-open"></i>No documents match. @if($canManage)<a href="{{ route('compliance.documents.create') }}">Upload your first documents</a>.@endif</div>
            @elseif($view === 'grid')
                <div class="cc-docgrid">
                    @foreach($documents as $d)@php [$cls, $ic] = $icon($d->latestVersion?->extension); $st = $d->expiryState(); @endphp
                        <a class="cc-doc" href="{{ route('compliance.documents.show', $d) }}">
                            <span class="ic {{ $cls }}"><i class="bi {{ $ic }}"></i></span>
                            <b>{{ $d->title }}</b>
                            <small>{{ $d->category?->name }}{{ $d->employee ? ' · ' . $d->employee->full_name : '' }}</small>
                            <div style="margin-top:auto;display:flex;gap:.3rem;flex-wrap:wrap">
                                @if($st === 'expired')<span class="cc-pill red">Expired</span>@elseif($st === 'expiring')<span class="cc-pill amber">Expires {{ $d->expiry_date->format('j M') }}</span>@endif
                                @if($d->reviewDue())<span class="cc-pill amber">Review due</span>@endif
                                <span class="cc-pill">v{{ $d->current_version }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="cc-wrap"><table class="cc-table">
                    <thead><tr><th>Document</th><th>Category</th><th>Employee / dept</th><th>Access</th><th>Expiry</th><th>Updated</th></tr></thead>
                    <tbody>
                    @foreach($documents as $d)@php [$cls, $ic] = $icon($d->latestVersion?->extension); $st = $d->expiryState(); @endphp
                        <tr>
                            <td><a class="row-link" href="{{ route('compliance.documents.show', $d) }}"><i class="bi {{ $ic }}"></i> {{ $d->title }}</a>@if($d->reference)<br><small class="pp-muted">{{ $d->reference }}</small>@endif</td>
                            <td><small>{{ $d->category?->fullName() }}</small></td>
                            <td><small>{{ $d->employee?->full_name ?? $d->department }}</small></td>
                            <td><small>{{ \Illuminate\Support\Str::before(\App\Models\Compliance\CompanyDocument::ACCESS[$d->access_level] ?? '', ' (') }}</small></td>
                            <td>@if($st === 'expired')<span class="cc-pill red">Expired {{ $d->expiry_date->format('j M Y') }}</span>@elseif($st === 'expiring')<span class="cc-pill amber">{{ $d->expiry_date->format('j M Y') }}</span>@elseif($d->expiry_date)<small>{{ $d->expiry_date->format('j M Y') }}</small>@else<small class="pp-muted">—</small>@endif</td>
                            <td><small>{{ $d->updated_at->format('j M Y') }}</small></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
            {{ $documents->links() }}
        </section>
    </div>
</div>
@endsection
