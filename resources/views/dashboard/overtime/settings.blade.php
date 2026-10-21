@extends('layouts.app')

@section('title', 'Overtime rates · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-requests.css') }}">
@endpush

@section('content')
<div class="pr pp rq">
    <div class="pr-head">
        <div>
            <h1>Overtime rates &amp; rules</h1>
            <p>Set the rates from your contracts or collective agreement. New claims use them; past claims keep the rate they were made with.</p>
        </div>
        <div class="pr-head-actions"><a href="{{ route('overtime.dashboard') }}" class="pp-btn pp-btn-ghost"><i class="bi bi-arrow-left"></i> Overtime</a></div>
    </div>

    <form method="POST" action="{{ route('overtime.settings.save') }}">
        @csrf
        <section class="pr-card">
            <div class="pr-card-head"><h2>Rates</h2><button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" id="addRate"><i class="bi bi-plus-lg"></i> Add a rate</button></div>
            <div class="rq-types" id="rates">
                @foreach($s['rates'] as $key => $r)
                    <div class="rq-typeform" style="border:1px solid var(--zp-line);border-radius:14px">
                        <input type="hidden" name="rates[{{ $loop->index }}][key]" value="{{ $key }}">
                        <div class="pp-field" style="margin:0"><label>Name</label><input class="pp-input" name="rates[{{ $loop->index }}][label]" value="{{ $r['label'] }}" required maxlength="60"></div>
                        <div class="pp-field" style="margin:0"><label>When it applies</label><input class="pp-input" name="rates[{{ $loop->index }}][hint]" value="{{ $r['hint'] ?? '' }}" maxlength="120"></div>
                        <div class="pp-field" style="margin:0"><label>Times the hourly rate</label><input class="pp-input" type="number" step="0.05" min="1" max="5" name="rates[{{ $loop->index }}][multiplier]" value="{{ (float) $r['multiplier'] }}" required></div>
                    </div>
                @endforeach
            </div>
            <p style="font-size:.78rem;color:var(--zp-ink-3);margin:.7rem 0 0">To remove a rate, clear its name. Claims already made with it keep their amounts.</p>
        </section>

        <section class="pr-card">
            <div class="pr-card-head"><h2>How the hourly rate is worked out</h2></div>
            <div class="rq-typeform" style="border:0;padding:0">
                <div class="pp-field" style="margin:0"><label>Based on</label>
                    <select class="pp-select" name="base">
                        <option value="basic" @selected($s['base'] === 'basic')>Basic salary</option>
                        <option value="gross" @selected($s['base'] === 'gross')>Basic + regular allowances</option>
                    </select>
                </div>
                <div class="pp-field" style="margin:0"><label>Hours in a working day</label><input class="pp-input" type="number" step="0.5" min="1" max="24" name="hours_per_day" value="{{ (float) $s['hours_per_day'] }}" required></div>
                <div class="pp-field" style="margin:0"><label>Most hours in one claim</label><input class="pp-input" type="number" step="0.5" min="1" max="24" name="max_hours_per_day" value="{{ (float) $s['max_hours_per_day'] }}" required></div>
                <div class="pp-field" style="margin:0"><label>Employees can log up to <small>(days back)</small></label><input class="pp-input" type="number" min="1" max="365" name="days_back" value="{{ (int) $s['days_back'] }}" required></div>
                <label class="rq-toggle" style="align-self:end"><input type="checkbox" name="require_reason" value="1" @checked($s['require_reason'])> A reason is required</label>
            </div>
            <p style="font-size:.8rem;color:var(--zp-ink-3);margin:.8rem 0 0">Hourly rate = monthly {{ $s['base'] === 'gross' ? 'basic + allowances' : 'basic salary' }} ÷ working days in the month (Settings → Company) ÷ hours in a working day.</p>
        </section>

        <div style="display:flex;justify-content:flex-end;margin-top:1rem"><button class="pp-btn pp-btn-primary"><i class="bi bi-check-lg"></i> Save</button></div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const box = document.getElementById('rates');
    document.getElementById('addRate').addEventListener('click', () => {
        const i = box.children.length;
        const d = document.createElement('div');
        d.className = 'rq-typeform'; d.style.cssText = 'border:1px solid var(--zp-line);border-radius:14px';
        d.innerHTML = `<input type="hidden" name="rates[${i}][key]" value="">
            <div class="pp-field" style="margin:0"><label>Name</label><input class="pp-input" name="rates[${i}][label]" maxlength="60" placeholder="e.g. Night shift"></div>
            <div class="pp-field" style="margin:0"><label>When it applies</label><input class="pp-input" name="rates[${i}][hint]" maxlength="120"></div>
            <div class="pp-field" style="margin:0"><label>Times the hourly rate</label><input class="pp-input" type="number" step="0.05" min="1" max="5" name="rates[${i}][multiplier]" value="1.5"></div>`;
        box.appendChild(d);
    });
    // Rows with an empty name are removed before sending.
    box.closest('form').addEventListener('submit', () => {
        box.querySelectorAll('.rq-typeform').forEach(row => { const n = row.querySelector('input[name$="[label]"]'); if (n && !n.value.trim()) row.querySelectorAll('input').forEach(x => x.disabled = true); });
    });
});
</script>
@endpush
