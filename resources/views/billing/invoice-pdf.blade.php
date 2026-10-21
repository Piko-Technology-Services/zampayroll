@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $paid = $invoice->status === 'paid';
@endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 11px; color: #15201A; margin: 0; }
    .flag { height: 8px; }
    .flag td { height: 8px; }
    .wrap { padding: 28px 34px; }
    h1 { font-family: DejaVu Serif, serif; font-size: 26px; margin: 0; color: #0A3B22; }
    .muted { color: #6b776f; }
    .grid { width: 100%; border-collapse: collapse; }
    .grid td { vertical-align: top; }
    table.lines { width: 100%; border-collapse: collapse; margin-top: 18px; }
    table.lines th { text-align: left; background: #F3F6F2; padding: 8px; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #45524A; }
    table.lines td { padding: 9px 8px; border-bottom: 1px solid #E3EAE4; }
    .r { text-align: right; }
    .tot td { padding: 5px 8px; }
    .grand td { font-size: 14px; font-weight: bold; border-top: 2px solid #0A3B22; padding-top: 8px; }
    .stamp { display: inline-block; padding: 6px 14px; border: 2px solid #00742D; color: #00742D; font-weight: bold; font-size: 16px; border-radius: 6px; letter-spacing: .1em; }
    .due { display: inline-block; padding: 6px 14px; background: #FBF0E5; color: #8a4c12; font-weight: bold; border-radius: 6px; }
    .box { background: #F3F6F2; padding: 12px 14px; border-radius: 6px; }
</style></head>
<body>
<table class="flag" width="100%" cellspacing="0" cellpadding="0"><tr>
    <td style="background:#00742D;width:70%"></td><td style="background:#C42926;width:10%"></td><td style="background:#101512;width:10%"></td><td style="background:#C8742B;width:10%"></td>
</tr></table>
<div class="wrap">
    <table class="grid"><tr>
        <td>
            <h1>{{ config('billing.company_name') }}</h1>
            <div class="muted">{{ config('billing.company_address') }}@if(config('billing.company_tpin')) · TPIN {{ config('billing.company_tpin') }}@endif<br>{{ config('billing.support_email') }} · zampayroll.com</div>
        </td>
        <td class="r">
            <div style="font-size:20px;font-weight:bold;color:#0A3B22">{{ $paid ? 'RECEIPT' : 'INVOICE' }}</div>
            <div style="font-size:13px;margin-top:2px">{{ $invoice->number }}</div>
            <div class="muted" style="margin-top:4px">Issued {{ $invoice->created_at->format('j M Y') }}</div>
            <div style="margin-top:10px">@if($paid)<span class="stamp">PAID {{ optional($invoice->paid_at)->format('j M Y') }}</span>@else<span class="due">Due {{ optional($invoice->due_at)->format('j M Y') }}</span>@endif</div>
        </td>
    </tr></table>

    <table class="grid" style="margin-top:22px"><tr>
        <td style="width:55%">
            <div class="muted" style="font-size:9px;text-transform:uppercase;letter-spacing:.08em">Bill to</div>
            <div style="font-size:13px;font-weight:bold;margin-top:3px">{{ $company->name }}</div>
            <div class="muted">
                @if($company->address){{ $company->address }}<br>@endif
                @if($company->tpin)TPIN {{ $company->tpin }}<br>@endif
                {{ $company->billing_email ?: $company->email }}
            </div>
        </td>
        <td class="r">
            @if($invoice->period_start)
                <div class="muted" style="font-size:9px;text-transform:uppercase;letter-spacing:.08em">Service period</div>
                <div style="margin-top:3px">{{ $invoice->period_start->format('j M Y') }} – {{ optional($invoice->period_end)->format('j M Y') }}</div>
            @endif
            <div class="muted" style="margin-top:6px">Account {{ $company->company_code }}</div>
        </td>
    </tr></table>

    <table class="lines">
        <thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Unit</th><th class="r">Amount</th></tr></thead>
        <tbody>
        @foreach($invoice->lines as $l)
            <tr><td>{{ $l['description'] }}</td><td class="r">{{ $l['quantity'] }}</td><td class="r">{{ $k($l['unit']) }}</td><td class="r">{{ $k($l['amount']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <table class="grid" style="margin-top:8px"><tr><td style="width:55%"></td><td>
        <table width="100%" class="tot">
            <tr><td class="muted">Subtotal</td><td class="r">{{ $k($invoice->subtotal) }}</td></tr>
            @if((float) $invoice->vat > 0)<tr><td class="muted">VAT ({{ rtrim(rtrim($invoice->vat_rate, '0'), '.') }}%)</td><td class="r">{{ $k($invoice->vat) }}</td></tr>@endif
            <tr class="grand"><td>{{ $paid ? 'Paid' : 'Total due' }}</td><td class="r">{{ $k($invoice->total) }}</td></tr>
        </table>
    </td></tr></table>

    <div class="box" style="margin-top:24px">
        @if($paid)
            Thank you! This invoice was paid {{ optional($invoice->paid_at)->format('j M Y, H:i') }}
            @php $p = $invoice->payments()->withoutGlobalScopes()->where('status', 'paid')->latest('paid_at')->first(); @endphp
            @if($p) by {{ str_replace('_', ' ', (string) $p->method) }}{{ $p->trans_ref ? ' (ref ' . $p->trans_ref . ')' : ($p->reference ? ' (ref ' . $p->reference . ')' : '') }}@endif.
        @else
            <b>How to pay:</b> sign in to ZamPayroll → Settings → Billing → <b>Pay now</b>. Pay by Airtel Money, MTN MoMo, Zamtel Kwacha or card.
            Questions? {{ config('billing.support_email') }}
        @endif
    </div>
</div>
</body></html>
