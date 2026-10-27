{{--
    Payslip document: the same markup for the PDF (mode = pdf) and on screen (mode = screen).
    Expects: $slips (Collection of arrays from Payslips::data), $mode, optional $toolbar (array).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $slips->count() === 1 ? 'Payslip — ' . trim($slips->first()['employee']->first_name . ' ' . $slips->first()['employee']->last_name) . ' — ' . $slips->first()['periodLabel'] : 'Payslips — ' . ($slips->first()['periodLabel'] ?? '') }}</title>
<meta name="robots" content="noindex, nofollow">
@include('payslips._styles')
<style>
    @if($mode === 'pdf')
        @page { size: A4 portrait; margin: 12mm 13mm; }
        body { margin: 0; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
    @else
        * { box-sizing: border-box; }
        body { margin: 0; background: #e7e9e7; font-family: 'Inter', 'Segoe UI', Arial, sans-serif; }
        .bar { position: sticky; top: 0; z-index: 10; background: rgba(22,22,22,.94); color: #eee; display: flex; align-items: center; gap: 12px; padding: 10px 18px; flex-wrap: wrap; }
        .bar .t { font-weight: 600; font-size: 14px; flex: 1; min-width: 200px; }
        .bar .t small { display: block; color: #aaa; font-weight: 500; font-size: 12px; }
        .bar a, .bar button { appearance: none; border: 1px solid #555; background: #2a2a2a; color: #f2f2f2; padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; display: inline-flex; gap: 6px; align-items: center; }
        .bar a.primary, .bar button.primary { background: #f2f2f2; color: #111; border-color: #f2f2f2; }
        .bar .search { background: #2a2a2a; border: 1px solid #555; color: #eee; border-radius: 10px; padding: 8px 12px; font-size: 13px; min-width: 220px; }
        .wrap { padding: 28px 16px 60px; }
        .sheet { width: 210mm; max-width: 100%; min-height: 297mm; margin: 0 auto 28px; background: #fff; padding: 12mm 13mm; box-shadow: 0 30px 60px -30px rgba(0,0,0,.35), 0 0 0 1px rgba(0,0,0,.05); }
        @media (max-width: 820px) { .sheet { min-height: 0; padding: 18px 14px; } .ps-head td.title { width: auto; } .ps-who td.net { width: auto; } }
        @media print {
            @page { size: A4 portrait; margin: 12mm 13mm; }
            body { background: #fff; }
            .bar { display: none; }
            .wrap { padding: 0; }
            .sheet { box-shadow: none; margin: 0; padding: 0; width: auto; min-height: 0; page-break-after: always; }
            .sheet:last-child { page-break-after: auto; }
            .ps-strip td, .ps-sum td.net, .ps-who td.bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    @endif
</style>
</head>
<body>
@if($mode !== 'pdf' && ! empty($toolbar))
    <div class="bar">
        @if(! empty($toolbar['back']))<a href="{{ $toolbar['back'] }}">&larr; Back</a>@endif
        <div class="t">{{ $toolbar['title'] ?? 'Payslips' }}<small>{{ $toolbar['subtitle'] ?? '' }}</small></div>
        @if($slips->count() > 3)<input class="search" type="search" placeholder="Find an employee…" oninput="var q=this.value.toLowerCase();document.querySelectorAll('.sheet').forEach(function(s){s.style.display=s.dataset.name.indexOf(q)>-1?'':'none'})">@endif
        @foreach($toolbar['actions'] ?? [] as $a)
            @if(($a['type'] ?? 'link') === 'form')
                <form method="POST" action="{{ $a['href'] }}" style="margin:0" @if(! empty($a['confirm'])) onsubmit="return confirm(@js($a['confirm']))" @endif>@csrf<button class="{{ $a['class'] ?? '' }}">{{ $a['label'] }}</button></form>
            @else
                <a href="{{ $a['href'] }}" class="{{ $a['class'] ?? '' }}">{{ $a['label'] }}</a>
            @endif
        @endforeach
        <button type="button" class="primary" onclick="window.print()">Print</button>
    </div>
@endif
<div class="{{ $mode === 'pdf' ? '' : 'wrap' }}">
    @foreach($slips as $s)
        <div class="sheet" data-name="{{ mb_strtolower(trim($s['employee']->first_name . ' ' . $s['employee']->last_name . ' ' . $s['employee']->employee_id)) }}">
            @include('payslips._slip', ['s' => $s])
        </div>
    @endforeach
    @if($slips->isEmpty())
        <div class="sheet" style="min-height:0;text-align:center;color:#666">No payslips in this pay run yet.</div>
    @endif
</div>
</body>
</html>
