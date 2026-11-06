@extends('layouts.app')
@section('title', $title . ' · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Compliance reports</h1><p>Live from your records. Export any report to Excel, CSV or PDF.</p></div></div>
    <div class="pr-layout" style="grid-template-columns:260px minmax(0,1fr)">
        <aside class="pr-side" style="position:static">
            <section class="pr-card">
                @foreach($reports as $group => $items)
                    <h2 style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;color:var(--zp-ink-3);margin:.6rem 0 .3rem">{{ $group }}</h2>
                    @foreach($items as $k => $l)<a href="{{ route('compliance.reports.index', ['report' => $k]) }}" style="display:block;padding:.3rem .5rem;border-radius:8px;font-size:.88rem;{{ $k === $key ? 'background:var(--zp-green-50);font-weight:800' : '' }}">{{ $l }}</a>@endforeach
                @endforeach
            </section>
        </aside>
        <section class="pr-card">
            <div class="pr-card-head"><div><h2>{{ $title }}</h2><div class="sub">{{ count($rows) }} {{ \Illuminate\Support\Str::plural('row', count($rows)) }}</div></div>
                <div style="display:flex;gap:.35rem">@foreach(['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF'] as $f => $l)<a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('compliance.reports.export', ['report' => $key, 'format' => $f]) }}"><i class="bi bi-download"></i> {{ $l }}</a>@endforeach</div></div>
            <div class="cc-wrap"><table class="cc-table">
                <thead><tr>@foreach($columns as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse($rows as $r)<tr>@foreach($r as $v)<td>{{ $v ?? '—' }}</td>@endforeach</tr>
                @empty<tr><td colspan="{{ count($columns) }}"><div class="pr-empty"><i class="bi bi-check2-circle"></i>Nothing to show.</div></td></tr>@endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</div>
@endsection
