{{-- In resources/views/dashboard/payroll/reports/index.blade.php, replace the existing
     "Bank bulk payments" row inside the Banking & Payments section with this — it now
     routes to the pre-export match check instead of downloading directly. --}}

<div class="rp-export-row">
    <div class="rp-export-info">
        <span class="rp-export-badge rp-badge-csv">CSV</span>
        <div>
            <div class="rp-export-title">Bank bulk payments</div>
            <div class="rp-export-desc">Universal template — works with any bank, auto-fills SWIFT/BIC per employee</div>
        </div>
    </div>
    <a href="{{ route('payroll.reports.bank_payments.preview', $run->id) }}" class="btn-rp-export">Review &amp; Download</a>
</div>
