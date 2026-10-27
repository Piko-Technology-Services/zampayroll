{{--
    One payslip. Shared by the PDF, the admin screens, the portal and the email attachment.
    Greyscale and print-first on purpose: it should feel like a confidential document.
    Expects: $s (array from App\Services\Payslips\Payslips::data)
--}}
@php
    $e = $s['employee']; $c = $s['company'];
    $k = fn ($n) => number_format((float) $n, 2);
    $rows = max($s['earnings']->count(), $s['deductions']->count(), 4);
    $addr = collect([$c?->address, $c?->city])->filter()->implode(', ');
@endphp
<div class="ps">
    <div class="ps-wm">{{ $s['final'] ? 'CONFIDENTIAL' : 'DRAFT' }}</div>

    <table class="ps-strip"><tr>
        <td>STRICTLY PRIVATE &amp; CONFIDENTIAL</td>
        <td class="r">{{ $s['final'] ? 'PAYSLIP' : 'DRAFT PAYSLIP — NOT FINAL' }} &nbsp;·&nbsp; {{ strtoupper($s['periodLabel']) }}</td>
    </tr></table>

    <table class="ps-head"><tr>
        <td class="logo">@if($s['logo']['src'])<img src="{{ $s['logo']['src'] }}" alt="{{ $s['logo']['own'] ? $s['companyName'] : 'ZamPayroll' }} logo">@endif</td>
        <td class="co">
            <div class="co-name">{{ $s['companyName'] }}</div>
            <div class="co-meta">
                @if($addr){{ $addr }}<br>@endif
                @if($c?->tpin)TPIN {{ $c->tpin }}@endif @if($c?->napsa_employer_number) &nbsp;·&nbsp; NAPSA {{ $c->napsa_employer_number }}@endif
            </div>
        </td>
        <td class="title">
            <div class="t">Payslip</div>
            <div class="ref">{{ $s['periodRange'] ?? $s['periodLabel'] }}</div>
            <div class="ref mono">REF {{ $s['reference'] }}</div>
        </td>
    </tr></table>

    <table class="ps-who"><tr>
        <td class="bar"></td>
        <td class="who">
            <div class="lbl">Prepared for — eyes only</div>
            <div class="name">{{ trim($e->first_name . ' ' . $e->middle_name . ' ' . $e->last_name) }}</div>
            <div class="sub">{{ collect([$e->position, $e->department, 'No. ' . $e->employee_id])->filter()->implode('  ·  ') }}</div>
        </td>
        <td class="net">
            <div class="lbl">Net pay</div>
            <div class="amt">K {{ $k($s['net']) }}</div>
        </td>
    </tr></table>

    <table class="ps-facts">
        <tr>
            <td><span>NRC</span>{{ $e->nrc_no ?: '—' }}</td>
            <td><span>TPIN</span>{{ $e->tpin ?: '—' }}</td>
            <td><span>NAPSA SSN</span>{{ $e->ssn ?: '—' }}</td>
            <td><span>NHIMA No.</span>{{ $e->nhima_no ?: '—' }}</td>
        </tr>
        <tr>
            <td><span>Date engaged</span>{{ $e->contract_start ? \Carbon\Carbon::parse($e->contract_start)->format('d M Y') : '—' }}</td>
            <td><span>Branch</span>{{ $e->branch ?: ($s['payroll']->branch ?: '—') }}</td>
            <td><span>Paid into</span>{{ $s['bank'] ?: 'Cash / not set' }}</td>
            <td><span>Leave balance</span>{{ $s['leaveBalance'] !== null ? rtrim(rtrim(number_format((float) $s['leaveBalance'], 2), '0'), '.') . ' days' : '—' }}</td>
        </tr>
    </table>

    <table class="ps-lines">
        <thead><tr>
            <th class="d">Earnings</th><th class="a">Amount (K)</th>
            <th class="gap"></th>
            <th class="d">Deductions</th><th class="a">Amount (K)</th>
        </tr></thead>
        <tbody>
        @for($i = 0; $i < $rows; $i++)
            @php $er = $s['earnings']->get($i); $dr = $s['deductions']->get($i); @endphp
            <tr>
                <td class="d">@if($er)<span class="code">{{ $er->code }}</span>{{ $er->description }}@endif</td>
                <td class="a">{{ $er ? $k($er->amount) : '' }}</td>
                <td class="gap"></td>
                <td class="d">@if($dr)<span class="code">{{ $dr->code }}</span>{{ $dr->description }}@endif</td>
                <td class="a">{{ $dr ? $k($dr->amount) : '' }}</td>
            </tr>
        @endfor
        </tbody>
        <tfoot><tr>
            <td class="d">Total earnings</td><td class="a">{{ $k($s['gross']) }}</td>
            <td class="gap"></td>
            <td class="d">Total deductions</td><td class="a">{{ $k($s['totalDeductions']) }}</td>
        </tr></tfoot>
    </table>

    <table class="ps-sum"><tr>
        <td><span>Gross pay</span>K {{ $k($s['gross']) }}</td>
        <td><span>Less deductions</span>K {{ $k($s['totalDeductions']) }}</td>
        <td class="net"><span>Net pay</span>K {{ $k($s['net']) }}</td>
    </tr></table>
    <div class="ps-words">{{ $s['netWords'] }}</div>

    <table class="ps-ytd">
        <tr><th colspan="5">Year to date · {{ $s['period']?->year }}</th></tr>
        <tr>
            <td><span>Gross</span>{{ $k($s['ytd']['gross']) }}</td>
            <td><span>PAYE</span>{{ $k($s['ytd']['paye']) }}</td>
            <td><span>NAPSA</span>{{ $k($s['ytd']['napsa']) }}</td>
            <td><span>NHIMA</span>{{ $k($s['ytd']['nhima']) }}</td>
            <td><span>Net</span>{{ $k($s['ytd']['net']) }}</td>
        </tr>
    </table>

    <div class="ps-note">
        Employer contributions this month (not deducted from your pay): NAPSA K {{ $k($s['employerNapsa']) }} · NHIMA K {{ $k($s['employerNhima']) }}.
    </div>

    <div class="ps-foot">
        <div class="seal">CONFIDENTIAL</div>
        <p>This payslip contains personal pay information for <b>{{ trim($e->first_name . ' ' . $e->last_name) }}</b> only. Do not copy, forward or share it. If you received it by mistake, return it to {{ $s['companyName'] }} HR and delete any copies.</p>
        <p class="mono small">Computer-generated · no signature required · issued {{ $s['generatedAt']->format('d M Y H:i') }} · ZamPayroll</p>
    </div>
</div>
