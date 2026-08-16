@extends('layouts.app')

@section('content')

<div class="lsh p-5">

    <div class="lsh-header">
        <div>
            <a href="{{ route('leave.years') }}" class="lsh-back"><i class="bi bi-arrow-left"></i> Years</a>
            <div class="lsh-eyebrow">Leave Management</div>
            <h1 class="lsh-title">Master Leave Sheet — {{ $year }}</h1>
            <div class="lsh-subtitle">Days taken per month. Edit any cell directly and save.</div>
        </div>

        <form method="GET" action="{{ route('leave.sheet', $year) }}">
            <select name="department" class="lsh-select" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if(session('success'))
        <div class="lsh-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('leave.sheet.update', $year) }}">
        @csrf
        @method('PUT')

        <div class="lsh-card">
            <table class="lsh-table">
                <thead>
                    <tr>
                        <th class="lsh-sticky">Employee</th>
                        <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>May</th><th>Jun</th>
                        <th>Jul</th><th>Aug</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dec</th>
                        <th class="lsh-total-col">Total Taken</th>
                        <th>Entitled</th>
                        <th>Balance</th>
                        <th>Amount Payable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        <tr data-employee-row="{{ $r['employee']->id }}">
                            <td class="lsh-sticky">
                                <div class="lsh-emp-name">{{ $r['employee']->first_name }} {{ $r['employee']->last_name }}</div>
                                <div class="lsh-emp-id"><i class="bi bi-hash"></i>{{ $r['employee']->employee_id }}</div>
                            </td>
                            @for($m = 1; $m <= 12; $m++)
                                <td>
                                    <input type="number" step="0.5" min="0" max="31"
                                        class="lsh-cell"
                                        data-employee-id="{{ $r['employee']->id }}"
                                        data-month="{{ $m }}"
                                        name="records[{{ $r['employee']->id }}][{{ $m }}]"
                                        value="{{ $r['monthly'][$m] ?: '' }}">
                                </td>
                            @endfor
                            <td class="lsh-total-col lsh-mono" data-field="total">{{ $r['total'] }}</td>
                            <td class="lsh-mono" data-field="entitled">{{ $r['entitled'] }}</td>
                            <td class="lsh-mono {{ $r['balance'] < 0 ? 'lsh-negative' : '' }}" data-field="balance">{{ $r['balance'] }}</td>
                            <td class="lsh-mono" data-field="amount">K {{ number_format($r['amountPayable'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="17" class="lsh-empty">No employees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="lsh-save-bar">
            <button class="lsh-btn lsh-btn-primary"><i class="bi bi-save"></i> Save Sheet</button>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const year = {{ $year }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const cellUpdateUrl = "{{ route('leave.cell.update', $year) }}";

    document.querySelectorAll('.lsh-cell').forEach(function (input) {
        input.addEventListener('change', handleCellChange);
        input.addEventListener('blur', handleCellChange);
    });

    let pending = new Set(); // avoid duplicate in-flight requests per employee+month

    function handleCellChange(e) {
        const input = e.target;
        const key = input.dataset.employeeId + '-' + input.dataset.month;

        if (pending.has(key)) return;
        pending.add(key);

        const row = document.querySelector('tr[data-employee-row="' + input.dataset.employeeId + '"]');
        row?.classList.add('lsh-row-saving');

        fetch(cellUpdateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                employee_id: input.dataset.employeeId,
                month: input.dataset.month,
                days_taken: input.value === '' ? 0 : input.value,
            }),
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Save failed');
            return res.json();
        })
        .then(function (data) {
            if (!row) return;

            row.querySelector('[data-field="total"]').textContent = data.total;
            row.querySelector('[data-field="entitled"]').textContent = data.entitled;

            const balanceEl = row.querySelector('[data-field="balance"]');
            balanceEl.textContent = data.balance;
            balanceEl.classList.toggle('lsh-negative', data.balance < 0);

            row.querySelector('[data-field="amount"]').textContent =
                'K ' + Number(data.amountPayable).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            row.classList.remove('lsh-row-saving');
            row.classList.add('lsh-row-saved');
            setTimeout(() => row.classList.remove('lsh-row-saved'), 700);
        })
        .catch(function () {
            row?.classList.remove('lsh-row-saving');
            row?.classList.add('lsh-row-error');
            setTimeout(() => row?.classList.remove('lsh-row-error'), 1500);
        })
        .finally(function () {
            pending.delete(key);
        });
    }
});
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');
.lsh * { box-sizing: border-box; }
.lsh {
    --paper: #ffffff;
    --ink: #00742E;
    --ink-soft: #58687D;
    --ink-faint: #93A0B2;
    --brass: #00742E;
    --brass-soft: #EAF7ED;
    --line: #D9E9DE;
    --active: #00742E;
    --active-bg: #EAF7ED;
    --warn: #D50115;
    --accent-orange: #E97B00;
    --accent-red: #D50115;
    font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); background: var(--paper); padding: 1.5rem 0 2.5rem;
}
.lsh-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.lsh-back:hover { color: var(--brass); }
.lsh-header { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; }
.lsh-eyebrow { font-family: 'Roboto Slab', serif; font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.lsh-title { font-family: 'Roboto Slab', serif; font-size: 1.3rem; font-weight: 700; margin: 0; }
.lsh-subtitle { font-size: .8rem; color: var(--ink-faint); margin-top: .2rem; }
.lsh-select { font-size: .8rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 5px; background: #fff; color: var(--ink-soft); }
.lsh-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin-bottom: 1rem; background: var(--active-bg); color: var(--active); border: 1px solid #C7E0CD; }

.lsh-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; overflow-x: auto; }
.lsh-table { border-collapse: collapse; font-size: .78rem; min-width: 1400px; width: 100%; }
.lsh-table thead th {
    font-family: 'Roboto Slab', serif; font-size: .62rem; font-weight: 600; color: var(--ink-faint);
    text-transform: uppercase; letter-spacing: .05em; padding: .7rem .5rem; border-bottom: 2px solid var(--ink);
    text-align: center; background: #FCFBF8; white-space: nowrap;
}
.lsh-table td { padding: .4rem .5rem; border-bottom: 1px solid var(--line); text-align: center; }
.lsh-sticky { position: sticky; left: 0; background: #fff; text-align: left !important; z-index: 1; min-width: 170px; }
thead .lsh-sticky { background: #FCFBF8; z-index: 2; }
.lsh-emp-name { font-weight: 600; font-size: .8rem; }
.lsh-emp-id { font-family: 'IBM Plex Mono', monospace; font-size: .66rem; color: var(--ink-faint); }
.lsh-total-col { background: #FCFBF8; font-weight: 700; }
.lsh-mono { font-family: 'IBM Plex Mono', monospace; }
.lsh-negative { color: var(--warn); font-weight: 700; }
.lsh-empty { text-align: center; color: var(--ink-faint); padding: 1.5rem 0 !important; }

.lsh-cell {
    width: 48px; text-align: center; font-family: 'IBM Plex Mono', monospace; font-size: .76rem;
    padding: .3rem; border: 1px solid var(--line); border-radius: 4px; outline: none; color: var(--ink);
}
.lsh-cell:focus { border-color: var(--brass); background: var(--brass-soft); }

.lsh-save-bar { display: flex; justify-content: flex-end; margin-top: 1rem; position: sticky; bottom: 1rem; }
.lsh-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .6rem 1.1rem; border-radius: 6px; border: none; cursor: pointer; }
.lsh-btn-primary { background: var(--ink); color: #fff; }
.lsh-btn-primary:hover { background: #23374F; }

.lsh-row-saving { background: #FCFBF8; }
.lsh-row-saved { background: var(--brass-soft); transition: background .4s ease; }
.lsh-row-error { background: #FBE9E7; transition: background .4s ease; }
</style>

@endsection
