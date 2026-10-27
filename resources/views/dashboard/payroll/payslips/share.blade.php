@extends('layouts.app')

@section('title', 'Share payslips · ' . $run->displayName())

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<style>
.sh-ways { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .8rem; margin-bottom: 1.1rem; }
.sh-way { position: relative; display: block; cursor: pointer; }
.sh-way input { position: absolute; opacity: 0; }
.sh-way .box { display: flex; gap: .8rem; align-items: flex-start; padding: 1rem 1.1rem; border: 1.5px solid var(--zp-line); border-radius: 18px; background: #fff; transition: border-color .15s, box-shadow .15s, background .15s; height: 100%; }
.sh-way .ic { width: 42px; height: 42px; border-radius: 13px; background: var(--zp-mist); color: var(--zp-ink-2); display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.sh-way b { display: block; font-size: .95rem; }
.sh-way small { color: var(--zp-ink-3); font-size: .8rem; display: block; margin-top: .15rem; }
.sh-way .tick { margin-left: auto; width: 22px; height: 22px; border-radius: 50%; border: 1.5px solid var(--zp-line); flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .8rem; }
.sh-way input:checked + .box { border-color: var(--zp-green); background: var(--zp-green-50); box-shadow: 0 12px 26px -20px rgba(0,116,45,.8); }
.sh-way input:checked + .box .ic { background: var(--zp-green); color: #fff; }
.sh-way input:checked + .box .tick { background: var(--zp-green); border-color: var(--zp-green); }
.sh-way input:focus-visible + .box { outline: 3px solid var(--zp-green-100); }
.sh-table td, .sh-table th { vertical-align: middle; }
.sh-pill { font-size: .72rem; font-weight: 700; padding: .18rem .5rem; border-radius: 99px; background: var(--zp-mist); color: var(--zp-ink-2); white-space: nowrap; }
.sh-pill.on { background: var(--zp-green-50); color: var(--zp-green); }
.sh-pill.warn { background: var(--zp-copper-50); color: #9a5a17; }
.sh-mail { font-size: .8rem; color: var(--zp-ink-2); display: block; }
.sh-mail.none { color: var(--zp-ink-3); font-style: italic; }
.sh-bar { position: sticky; bottom: 0; background: rgba(255,255,255,.95); backdrop-filter: blur(8px); border: 1px solid var(--zp-line); border-radius: 18px; padding: .8rem 1rem; display: flex; gap: .8rem; align-items: center; flex-wrap: wrap; margin-top: 1rem; box-shadow: 0 -10px 30px -24px rgba(0,0,0,.4); }
.sh-bar .grow { flex: 1; min-width: 220px; font-size: .88rem; color: var(--zp-ink-2); }
@media (max-width: 760px) { .sh-ways { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="pr pp">
    <a class="pr-crumb" href="{{ route('payroll.runs.payslips', $run) }}"><i class="bi bi-arrow-left"></i> Payslips</a>
    <div class="pr-head">
        <div><h1>Share payslips</h1><p>{{ $run->displayName() }} · {{ $payrolls->count() }} {{ \Illuminate\Support\Str::plural('payslip', $payrolls->count()) }} · {{ $run->statusLabel() }}</p></div>
        <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ route('payroll.runs.payslips', $run) }}"><i class="bi bi-eye"></i> Preview payslips</a></div>
    </div>

    @unless($run->isLocked())
        <div class="pp-callout" style="margin:0 0 1rem"><i class="bi bi-lock"></i><div><b>Not approved yet.</b> Payslips can be shared once this pay run is approved, so employees never see figures that might still change.</div></div>
    @endunless

    <form method="POST" action="{{ route('payroll.runs.payslips.share.store', $run) }}" id="shForm">
        @csrf
        <h2 style="font:700 1.05rem var(--zp-font);margin:0 0 .6rem">How should employees get them? <small class="pp-muted" style="font-weight:500">Pick one or more</small></h2>
        <div class="sh-ways">
            <label class="sh-way"><input type="checkbox" name="portal" value="1" @checked(old('portal', $settings['portal']))><span class="box"><span class="ic"><i class="bi bi-phone"></i></span><span><b>Self-service portal</b><small>Employees sign in and view or download it. Nothing is emailed.</small></span><span class="tick"><i class="bi bi-check2"></i></span></span></label>
            <label class="sh-way"><input type="checkbox" name="work" value="1" @checked(old('work', $settings['work']))><span class="box"><span class="ic"><i class="bi bi-building"></i></span><span><b>Work email</b><small>Sent as a PDF to their company email address.</small></span><span class="tick"><i class="bi bi-check2"></i></span></span></label>
            <label class="sh-way"><input type="checkbox" name="personal" value="1" @checked(old('personal', $settings['personal']))><span class="box"><span class="ic"><i class="bi bi-envelope-heart"></i></span><span><b>Personal email</b><small>Sent as a PDF to their personal email address.</small></span><span class="tick"><i class="bi bi-check2"></i></span></span></label>
        </div>
        @error('portal')<div class="pl-alert bad" style="color:var(--zp-red);font-size:.85rem;margin-bottom:.8rem">{{ $message }}</div>@enderror

        <section class="pr-card">
            <div class="pr-card-head"><div><h2>Who</h2><div class="sub">If the email you chose is missing for someone, we use their other address.</div></div>
                <label style="font-size:.85rem;font-weight:600;display:flex;gap:.4rem;align-items:center"><input type="checkbox" id="shAll" checked style="accent-color:var(--zp-green)"> Everyone</label></div>
            <div class="pr-tablewrap">
                <table class="pr-table sh-table">
                    <thead><tr><th style="width:36px"></th><th>Employee</th><th>Work email</th><th>Personal email</th><th>Portal</th><th>Last shared</th></tr></thead>
                    <tbody>
                    @foreach($payrolls as $p)
                        @php $e = $p->employee; $hasPortal = (bool) $e->user; @endphp
                        <tr>
                            <td><input type="checkbox" class="sh-one" name="payrolls[]" value="{{ $p->id }}" @checked($only->isEmpty() || $only->contains($p->id)) style="accent-color:var(--zp-green);width:16px;height:16px"></td>
                            <td><b>{{ $e->first_name }} {{ $e->last_name }}</b><br><small class="pp-muted">{{ $e->employee_id }} · K {{ number_format((float) $p->net_pay, 2) }}</small></td>
                            <td><span class="sh-mail {{ $e->company_email ? '' : 'none' }}">{{ $e->company_email ?: 'none' }}</span></td>
                            <td><span class="sh-mail {{ $e->personal_email ? '' : 'none' }}">{{ $e->personal_email ?: 'none' }}</span></td>
                            <td>
                                @if($p->published_at)<span class="sh-pill on">On portal</span>@else<span class="sh-pill">Not shared</span>@endif
                                @unless($hasPortal)<br><span class="sh-pill warn" title="They need a portal account to see it" style="margin-top:.25rem;display:inline-block">No account</span>@endunless
                            </td>
                            <td><small class="pp-muted">{{ $p->emailed_at ? 'Emailed ' . $p->emailed_at->diffForHumans() : '—' }}@if($p->emailed_to)<br>{{ $p->emailed_to }}@endif</small></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="sh-bar">
            <div class="grow"><b id="shCount">{{ $payrolls->count() }}</b> selected · <span id="shHow"></span>
                <label style="display:block;margin-top:.3rem;font-size:.8rem"><input type="checkbox" name="remember" value="1" style="accent-color:var(--zp-green)"> Use these choices by default for future pay runs</label></div>
            <button class="pp-btn pp-btn-ghost" name="action" value="unpublish" type="submit" onclick="return confirm('Remove the selected payslips from the portal?')"><i class="bi bi-eye-slash"></i> Remove from portal</button>
            <button class="pp-btn pp-btn-primary" name="action" value="share" type="submit" @disabled(! $run->isLocked())><i class="bi bi-send"></i> Share payslips</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const all = document.getElementById('shAll'), ones = [...document.querySelectorAll('.sh-one')];
    const how = () => {
        const n = ones.filter(o => o.checked).length;
        document.getElementById('shCount').textContent = n;
        all.checked = n === ones.length; all.indeterminate = n > 0 && n < ones.length;
        const ways = [['portal', 'portal'], ['work', 'work email'], ['personal', 'personal email']].filter(([k]) => document.querySelector(`[name=${k}]`).checked).map(w => w[1]);
        document.getElementById('shHow').textContent = ways.length ? 'via ' + ways.join(' + ') : 'choose how to share above';
    };
    all.addEventListener('change', () => { ones.forEach(o => o.checked = all.checked); how(); });
    ones.forEach(o => o.addEventListener('change', how));
    document.querySelectorAll('[name=portal],[name=work],[name=personal]').forEach(i => i.addEventListener('change', how));
    how();
})();
</script>
@endpush
