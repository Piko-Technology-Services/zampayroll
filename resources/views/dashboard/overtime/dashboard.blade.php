@extends('layouts.app')

@section('content')

<div class="otd p-5">

    <div class="otd-header">
        <div>
            <div class="otd-eyebrow">Overtime Management</div>
            <h1 class="otd-title">Overtime Requests</h1>
        </div>
    </div>

    @if(session('success'))
        <div class="otd-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    <div class="otd-tabs">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
            <a href="{{ route('overtime.dashboard', ['status' => $key]) }}" class="otd-tab {{ $status === $key ? 'otd-tab-active' : '' }}">
                {{ $label }} @if(isset($counts[$key]))<span class="otd-tab-count">{{ $counts[$key] }}</span>@endif
            </a>
        @endforeach
    </div>

    <div class="otd-card">
        <table class="otd-table">
            <thead>
                <tr>
                    <th>Employee</th><th>Date</th><th>Time</th><th class="num">Hours</th>
                    <th>Type</th><th class="num">Amount</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    <tr>
                        <td>
                            <div class="otd-emp-name">{{ $req->employee->first_name }} {{ $req->employee->last_name }}</div>
                            <div class="otd-emp-id"><i class="bi bi-hash"></i>{{ $req->employee->employee_id }}</div>
                        </td>
                        <td class="otd-mono">{{ $req->date->format('d M Y') }}</td>
                        <td class="otd-mono">{{ \Carbon\Carbon::parse($req->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($req->end_time)->format('H:i') }}</td>
                        <td class="num otd-mono">{{ $req->hours }}</td>
                        <td>
                            <span class="otd-type otd-type-{{ $req->type }}">{{ \App\Models\OvertimeRequest::TYPES[$req->type]['label'] ?? $req->type }}</span>
                        </td>
                        <td class="num otd-mono">K {{ number_format($req->amount, 2) }}</td>
                        <td><span class="otd-status otd-status-{{ $req->status }}">{{ ucfirst($req->status) }}</span></td>
                        <td class="otd-actions">
                            @if($req->status === 'pending')
                                <form method="POST" action="{{ route('overtime.approve', $req) }}" class="otd-decision-form">
                                    @csrf
                                    <input type="text" name="comment" class="otd-comment" placeholder="Comment (optional)">
                                    <button class="otd-btn otd-btn-approve" title="Approve"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <form method="POST" action="{{ route('overtime.reject', $req) }}" class="otd-decision-form">
                                    @csrf
                                    <button class="otd-btn otd-btn-reject" title="Reject" onclick="return confirm('Reject this overtime request?');"><i class="bi bi-x-lg"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="otd-empty">No overtime requests here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
.otd * { box-sizing: border-box; }
.otd {
    --paper: #ffffff; --ink: #00742E; --ink-soft: #58687D; --ink-faint: #93A0B2;
    --brass: #00742E; --brass-soft: #EAF7ED; --line: #D9E9DE;
    --active: #00742E; --active-bg: #EAF7ED; --warn: #D50115; --warn-bg: #FFE5E8;
    --accent-orange: #E97B00; --accent-red: #D50115;
    font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
}
.otd-header { margin-bottom: 1rem; }
.otd-eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.otd-title { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; font-weight: 700; margin: 0; }
.otd-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin-bottom: 1rem; background: var(--active-bg); color: var(--active); border: 1px solid var(--line); }

.otd-tabs { display: flex; gap: .4rem; margin-bottom: 1rem; }
.otd-tab { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .5rem .9rem; border-radius: 6px; text-decoration: none; color: var(--ink-soft); border: 1px solid var(--line); background: #fff; }
.otd-tab-active { background: var(--ink); color: #fff; border-color: var(--ink); }
.otd-tab-count { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; background: rgba(0,0,0,.08); padding: .05rem .4rem; border-radius: 10px; }
.otd-tab-active .otd-tab-count { background: rgba(255,255,255,.2); }

.otd-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; overflow-x: auto; }
.otd-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 980px; }
.otd-table thead th { font-family: 'IBM Plex Sans', sans-serif; font-size: .64rem; font-weight: 600; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .06em; padding: .8rem 1rem; border-bottom: 2px solid var(--ink); text-align: left; background: var(--brass-soft); }
.otd-table thead th.num, .otd-table td.num { text-align: right; }
.otd-table td { padding: .6rem 1rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
.otd-mono { font-family: 'IBM Plex Sans', sans-serif; }
.otd-emp-name { font-weight: 600; font-size: .82rem; }
.otd-emp-id { font-family: 'IBM Plex Sans', sans-serif; font-size: .66rem; color: var(--ink-faint); }
.otd-empty { text-align: center; color: var(--ink-faint); padding: 1.5rem 0 !important; }

.otd-type { font-size: .68rem; font-weight: 700; padding: .18rem .5rem; border-radius: 4px; }
.otd-type-normal { background: var(--brass-soft); color: var(--ink); }
.otd-type-double { background: #FFF4E6; color: var(--accent-orange); }

.otd-status { display: inline-block; font-size: .68rem; font-weight: 700; padding: .2rem .6rem; border-radius: 4px; text-transform: uppercase; }
.otd-status-pending { background: #FFF4E6; color: var(--accent-orange); }
.otd-status-approved { background: var(--active-bg); color: var(--active); }
.otd-status-rejected { background: #FFE5E8; color: var(--warn); }

.otd-actions { display: flex; gap: .4rem; align-items: center; }
.otd-decision-form { display: flex; align-items: center; gap: .3rem; }
.otd-comment { font-size: .74rem; padding: .3rem .5rem; border: 1px solid var(--line); border-radius: 4px; width: 110px; }
.otd-btn { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 5px; border: 1px solid var(--line); background: #fff; cursor: pointer; }
.otd-btn-approve { color: var(--active); }
.otd-btn-approve:hover { background: var(--active-bg); border-color: var(--line); }
.otd-btn-reject { color: var(--warn); }
.otd-btn-reject:hover { background: #FFE5E8; border-color: var(--line); }
</style>

@endsection
