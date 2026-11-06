@extends('layouts.app')
@section('title', 'Policy acknowledgements · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Employee acknowledgements</h1><p>Who has confirmed receiving each active policy. An acknowledgement confirms receipt; it does not take away an employee's rights.</p></div>
        <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ route('compliance.reports.export', ['report' => 'ack_status', 'format' => 'xlsx']) }}"><i class="bi bi-file-earmark-excel"></i> Export</a></div></div>
    <section class="pr-card">
        @if($policies->isEmpty())
            <div class="pr-empty"><i class="bi bi-journal-check"></i>No active policies need acknowledgement yet.</div>
        @else
        <div class="cc-wrap"><table class="cc-table cc-matrix">
            <thead><tr><th>Employee</th>@foreach($policies as $p)<th title="{{ $p->title }}">{{ \Illuminate\Support\Str::limit($p->title, 22) }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($employees as $e)
                <tr><td><b>{{ $e->full_name }}</b><br><small class="pp-muted">{{ $e->department }}</small></td>
                @foreach($policies as $p)
                    @php $at = $matrix[$p->id][$e->id] ?? null; @endphp
                    <td class="{{ $at ? 'ok' : 'no' }}">{!! $at ? '<i class="bi bi-check2-circle"></i> ' . e(\Illuminate\Support\Carbon::parse($at)->format('j M')) : '<i class="bi bi-dash-circle"></i> Outstanding' !!}</td>
                @endforeach</tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
    </section>
</div>
@endsection
