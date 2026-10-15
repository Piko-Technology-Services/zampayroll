{{-- Shown on the HR dashboard until the company profile is complete ("complete later" from sign-up). --}}
@php
    $setupCompany = auth()->user()?->company;
@endphp
@if($setupCompany && auth()->user()->isCompanyAdmin() && $setupCompany->profileCompletion() < 100)
    @php
        $missing = $setupCompany->missingProfileFields();
        $pct = $setupCompany->profileCompletion();
    @endphp
    <div class="zp-setup-banner">
        <div style="flex:1;min-width:240px">
            <h5>Finish setting up {{ $setupCompany->name }}</h5>
            <p>Still missing: {{ implode(', ', array_slice(array_values($missing), 0, 4)) }}{{ count($missing) > 4 ? ' and ' . (count($missing) - 4) . ' more' : '' }}. These appear on payslips and statutory returns.</p>
        </div>
        <div>
            <div class="small mb-1" style="color:#fff;font-weight:700">{{ $pct }}% complete</div>
            <div class="zp-setup-meter"><span style="width:{{ $pct }}%"></span></div>
        </div>
        <a class="zp-btn zp-btn-copper" href="{{ route('company.settings') }}"><i class="bi bi-building-gear"></i> Complete profile</a>
    </div>
@endif
