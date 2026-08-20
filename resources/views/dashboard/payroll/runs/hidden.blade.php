@extends('layouts.app')

@section('content')

<style>
.pr * { box-sizing: border-box; }
.pr {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    color: #0F172A; background: #F8FAFC; padding: 1.5rem 0 2.5rem;
}
.pr-back { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; color: #64748B; text-decoration: none; margin-bottom: .6rem; }
.pr-back:hover { color: #00742D; }
.pr-header { margin-bottom: 1.5rem; }
.pr-header h1 { font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; margin: 0; color: #0F172A; display: flex; align-items: center; gap: .5rem; }
.pr-header .subtitle { font-size: .82rem; color: #94A3B8; margin-top: .15rem; }

.pr-alert-success { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; border-radius: 10px; padding: .7rem 1rem; font-size: .84rem; margin-bottom: 1.2rem; }

.pr-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
@media (max-width: 1100px) { .pr-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .pr-grid { grid-template-columns: 1fr; } }

.pr-card { background: #fff; border: 1px solid #E9EDF2; border-radius: 16px; overflow: hidden; }
.pr-card-head { padding: 1.25rem 1.35rem 0; display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
.pr-alias { display: flex; align-items: center; gap: .45rem; font-size: 1rem; font-weight: 700; color: #0F172A; margin: 0 0 .2rem; }
.pr-alias i { color: #D97706; font-size: .95rem; }
.pr-period { font-size: .78rem; color: #94A3B8; }
.pr-id { font-size: .7rem; color: #B4BECC; margin-top: .1rem; }

.pr-hidden-note { padding: .8rem 1.35rem 0; font-size: .74rem; color: #B45309; display: flex; align-items: center; gap: .4rem; }

.pr-net { margin: 1rem 1.35rem 0; background: #F8FAFC; border-radius: 10px; padding: .75rem .9rem; }
.pr-net-label { font-size: .68rem; color: #94A3B8; text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }
.pr-net-value { font-size: 1.1rem; font-weight: 800; color: #0F172A; letter-spacing: -.02em; margin-top: .1rem; }

.pr-actions { margin-top: 1.1rem; padding: .9rem 1.1rem; border-top: 1px solid #F1F5F9; display: flex; gap: .5rem; }
.pr-actions form { flex: 1; margin: 0; }
.pr-actions button {
    width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
    font-size: .78rem; font-weight: 600; padding: .55rem; border-radius: 8px; border: 1px solid #E5E9F0;
    background: #fff; color: #475569; cursor: pointer; transition: border-color .15s, color .15s;
}
.pr-actions button:hover { border-color: #A7F3D0; color: #00742D; }
.pr-actions a.view-link {
    display: inline-flex; align-items: center; justify-content: center;
    width: 38px; border-radius: 8px; border: 1px solid #E5E9F0; color: #94A3B8; text-decoration: none;
}
.pr-actions a.view-link:hover { border-color: #CBD5E1; color: #0F172A; }

.pr-empty { background: #fff; border: 1px dashed #D7DEE8; border-radius: 16px; text-align: center; padding: 3rem 1.5rem; color: #94A3B8; }
.pr-empty i { font-size: 2rem; display: block; margin-bottom: .75rem; color: #CBD5E1; }
</style>

<div class="pr">

    <a href="{{ route('payroll.runs.index') }}" class="pr-back"><i class="bi bi-arrow-left"></i> Payroll Runs</a>

    <div class="pr-header">
        <h1><i class="bi bi-eye-slash"></i> Hidden Payroll Runs</h1>
        <div class="subtitle">Archived out of the main view — fully intact, not deleted</div>
    </div>

    @if(session('success'))
        <div class="pr-alert-success"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    @if($runs->isEmpty())
        <div class="pr-empty">
            <i class="bi bi-eye-slash"></i>
            Nothing hidden right now.
        </div>
    @else
        <div class="pr-grid">
            @foreach($runs as $run)
                <div class="pr-card">

                    <div class="pr-card-head">
                        <div>
                            <h5 class="pr-alias"><i class="bi bi-calendar3"></i>{{ $run->alias }}</h5>
                            <div class="pr-period">{{ $run->period }}</div>
                            <div class="pr-id">ID #{{ $run->id }}</div>
                        </div>
                    </div>

                    <div class="pr-hidden-note">
                        <i class="bi bi-clock-history"></i> Hidden {{ $run->hidden_at?->diffForHumans() }}
                    </div>

                    <div class="pr-net">
                        <div class="pr-net-label">Net Pay</div>
                        <div class="pr-net-value">K{{ number_format($run->net_pay, 2) }}</div>
                    </div>

                    <div class="pr-actions">
                        <a href="{{ route('payroll.runs.show', $run->id) }}" class="view-link" title="Open">
                            <i class="bi bi-folder2-open"></i>
                        </a>

                        <form method="POST" action="{{ route('payroll.runs.unhide', $run->id) }}">
                            @csrf
                            <button type="submit"><i class="bi bi-eye"></i> Unhide</button>
                        </form>

                        <form method="POST" action="{{ route('payroll.runs.trash.store', $run->id) }}"
                              onsubmit="return confirm('Move this payroll run to trash?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"><i class="bi bi-trash3"></i> Trash</button>
                        </form>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

</div>

@endsection
