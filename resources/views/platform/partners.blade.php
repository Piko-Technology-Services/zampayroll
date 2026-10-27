@extends('platform.layout')
@section('title', 'Partners · ZamPayroll admin')
@php $k = fn ($n) => 'K ' . number_format((float) $n, 2); @endphp
@section('content')
<div class="pr-head"><div><h1>Partners</h1><p>{{ $partners->total() }} {{ \Illuminate\Support\Str::plural('partner', $partners->total()) }} · {{ $waiting }} waiting for review · {{ $k($owed) }} commission to pay out</p></div>
    <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ route('partners.show') }}" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Public partner page</a></div></div>
<form class="pl-filter" method="GET">
    <div class="pr-search" style="max-width:320px"><i class="bi bi-search"></i><input type="search" name="q" value="{{ $q }}" placeholder="Name, code, email or firm"></div>
    @foreach(['' => 'All', 'pending' => 'Waiting for review', 'active' => 'Active', 'suspended' => 'Suspended'] as $v => $l)
        <a href="{{ route('platform.partners', array_filter(['q' => $q, 'status' => $v])) }}" class="{{ (string) $status === $v ? 'on' : '' }}">{{ $l }}</a>
    @endforeach
</form>
<section class="pr-card">
    @if($partners->isEmpty())
        <p class="pp-muted" style="margin:0">No partners yet. Share {{ route('partners.show') }} with HR consultants, practitioners, students and freelancers.</p>
    @else
    <div class="pr-tablewrap">
        <table class="pr-table pr-hist">
            <thead><tr><th>Partner</th><th>Code</th><th>Type</th><th>Status</th><th style="text-align:right">Clients</th><th style="text-align:right">To pay out</th><th>Applied</th></tr></thead>
            <tbody>
            @foreach($partners as $p)
                <tr onclick="location.href='{{ route('platform.partners.show', $p) }}'">
                    <td><b>{{ $p->name }}</b><br><small class="pp-muted">{{ $p->firm_name ? $p->firm_name . ' · ' : '' }}{{ $p->email }}</small></td>
                    <td><b>{{ $p->code }}</b></td>
                    <td>{{ $p->typeLabel() }}<br><small class="pp-muted">{{ $p->city }}</small></td>
                    <td><span class="pl-tag {{ $p->status === 'active' ? 'active' : ($p->status === 'pending' ? 'pending' : 'suspended') }}">{{ $p->status === 'pending' ? 'waiting for review' : $p->status }}</span></td>
                    <td class="num">{{ $clients[$p->id] ?? 0 }}</td>
                    <td class="num">{{ isset($pending[$p->id]) ? $k($pending[$p->id]) : '—' }}</td>
                    <td>{{ $p->created_at->format('j M Y') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $partners->links('pagination::bootstrap-5') }}
    @endif
</section>
@endsection
