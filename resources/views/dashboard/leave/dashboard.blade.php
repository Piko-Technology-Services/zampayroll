@extends('layouts.app')

@section('content')

<div class="ldb p-5">

    <div class="ldb-header">
        <div>
            <a href="{{ route('leave.years') }}" class="ldb-back"><i class="bi bi-arrow-left"></i> Leave Sheets</a>
            <div class="ldb-eyebrow">Leave Management</div>
            <h1 class="ldb-title">Leave Requests</h1>
        </div>
    </div>

    @if(session('success'))
        <div class="ldb-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    <div class="ldb-tabs">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
            <a href="{{ route('leave.dashboard', ['status' => $key]) }}"
               class="ldb-tab {{ $status === $key ? 'ldb-tab-active' : '' }}">
                {{ $label }}
                @if(isset($counts[$key]))
                    <span class="ldb-tab-count">{{ $counts[$key] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="ldb-card">
        <table class="ldb-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Dates</th>
                    <th class="num">Days</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Reviewed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    <tr>
                        <td>
                            <div class="ldb-emp-name">{{ $req->employee->first_name }} {{ $req->employee->last_name }}</div>
                            <div class="ldb-emp-id"><i class="bi bi-hash"></i>{{ $req->employee->employee_id }}</div>
                        </td>
                        <td class="ldb-mono">{{ $req->start_date->format('d M Y') }} → {{ $req->end_date->format('d M Y') }}</td>
                        <td class="num ldb-mono">{{ $req->days }}</td>
                        <td class="ldb-reason">{{ $req->reason ?: '—' }}</td>
                        <td><span class="ldb-status ldb-status-{{ $req->status }}">{{ ucfirst($req->status) }}</span></td>
                        <td class="ldb-td-muted">
                            @if($req->reviewed_by)
                                {{ $req->reviewedBy->name }}<br>{{ $req->reviewed_at->format('d M Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="ldb-actions">
                            @if($req->status === 'pending')
                                <form method="POST" action="{{ route('leave.approve', $req) }}" class="ldb-decision-form">
                                    @csrf
                                    <input type="text" name="comment" class="ldb-comment" placeholder="Comment (optional)">
                                    <button class="ldb-btn ldb-btn-approve" title="Approve"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <form method="POST" action="{{ route('leave.reject', $req) }}" class="ldb-decision-form">
                                    @csrf
                                    <button class="ldb-btn ldb-btn-reject" title="Reject" onclick="return confirm('Reject this leave request?');"><i class="bi bi-x-lg"></i></button>
                                </form>
                            @elseif($req->hr_comment)
                                <span class="ldb-td-muted" title="{{ $req->hr_comment }}"><i class="bi bi-chat-left-text"></i></span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ldb-empty">No leave requests here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');
.ldb * { box-sizing: border-box; }
.ldb {
    --paper: #ffffff;
    --ink: #00742E;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #00742E;
    --brass-soft: #EAF7ED;
    --line: #D9E9DE;
    --accent-orange: #E97B00;
    --accent-red: #D50115;
    --active: #00742E;
    --active-bg: #EAF7ED;
    --warn: #D50115;
    --warn-bg: #FDECEF;
    font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
}
.ldb-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.ldb-back:hover { color: var(--brass); }
.ldb-eyebrow { font-family: 'IBM Plex Sans', sans-serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.ldb-title { font-family: 'IBM Plex Sans', sans-serif; font-size: 1.4rem; font-weight: 700; margin: 0; }
.ldb-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin: 1rem 0; background: var(--active-bg); color: var(--active); border: 1px solid #CFE6D5; }

.ldb-tabs { display: flex; gap: .4rem; margin: 1rem 0; }
.ldb-tab { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .5rem .9rem; border-radius: 6px; text-decoration: none; color: var(--ink-soft); border: 1px solid var(--line); background: #fff; }
.ldb-tab-active { background: var(--ink); color: #fff; border-color: var(--ink); }
.ldb-tab-count { font-size: .68rem; background: rgba(0,116,46,.08); padding: .05rem .4rem; border-radius: 10px; }
.ldb-tab-active .ldb-tab-count { background: rgba(255,255,255,.2); }

.ldb-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; overflow-x: auto; }
.ldb-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 900px; }
.ldb-table thead th { font-family: 'IBM Plex Sans', sans-serif; font-size: .64rem; font-weight: 600; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .06em; padding: .8rem 1rem; border-bottom: 2px solid var(--ink); text-align: left; background: #F9FCFA; }
.ldb-table thead th.num, .ldb-table td.num { text-align: right; }
.ldb-table td { padding: .6rem 1rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
.ldb-mono { font-family: 'IBM Plex Sans', sans-serif; }
.ldb-emp-name { font-weight: 600; font-size: .82rem; }
.ldb-emp-id { font-size: .66rem; color: var(--ink-faint); }
.ldb-reason { color: var(--ink-soft); max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ldb-td-muted { color: var(--ink-faint); font-size: .76rem; }
.ldb-empty { text-align: center; color: var(--ink-faint); padding: 1.5rem 0 !important; }

.ldb-status { display: inline-block; font-size: .68rem; font-weight: 700; padding: .2rem .6rem; border-radius: 4px; text-transform: uppercase; }
.ldb-status-pending { background: #FFF0E1; color: var(--accent-orange); }
.ldb-status-approved { background: var(--active-bg); color: var(--active); }
.ldb-status-rejected { background: var(--warn-bg); color: var(--warn); }

.ldb-actions { display: flex; gap: .4rem; align-items: center; }
.ldb-decision-form { display: flex; align-items: center; gap: .3rem; }
.ldb-comment { font-size: .74rem; padding: .3rem .5rem; border: 1px solid var(--line); border-radius: 4px; width: 120px; }
.ldb-btn { width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 5px; border: 1px solid var(--line); background: #fff; cursor: pointer; }
.ldb-btn-approve { color: var(--active); }
.ldb-btn-approve:hover { background: var(--active-bg); border-color: #CFE6D5; }
.ldb-btn-reject { color: var(--warn); }
.ldb-btn-reject:hover { background: var(--warn-bg); border-color: #F4C6CC; }
</style>

@endsection
