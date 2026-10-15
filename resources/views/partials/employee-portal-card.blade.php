{{-- Self-service portal status + actions for one employee (HR view). Requires zp-brand.css --}}
@php $portalUser = $employee->user; @endphp
<div class="zp-portal-card {{ $portalUser ? '' : 'off' }}">
    <span class="ic"><i class="bi {{ $portalUser ? 'bi-phone-vibrate' : 'bi-phone' }}"></i></span>
    <div class="tx">
        @if($portalUser)
            <b>Self-service portal is active</b>
            <span>
                Signs in as {{ $portalUser->email }}
                — {{ $portalUser->last_login_at ? 'last seen ' . $portalUser->last_login_at->diffForHumans() : 'hasn\'t signed in yet' }}
                @if($portalUser->must_change_password) (still using the temporary password) @endif
            </span>
        @else
            <b>No self-service portal yet</b>
            <span>
                @if($employee->portal_email)
                    Create one and we'll email login details and instructions to {{ $employee->portal_email }}.
                @else
                    Add a company or personal email to this employee first.
                @endif
            </span>
        @endif
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($portalUser)
            <form method="POST" action="{{ route('employees.portal.resend', $employee) }}">@csrf
                <button class="zp-btn zp-btn-ghost" style="padding:.55rem .9rem"><i class="bi bi-envelope-arrow-up"></i> Re-send login details</button>
            </form>
            <form method="POST" action="{{ route('employees.portal.destroy', $employee) }}" onsubmit="return confirm('Remove portal access for {{ addslashes($employee->full_name) }}? They will no longer be able to sign in.')">
                @csrf @method('DELETE')
                <button class="zp-btn zp-btn-ghost" style="padding:.55rem .9rem;color:var(--zp-red)"><i class="bi bi-x-circle"></i> Remove access</button>
            </form>
        @else
            <form method="POST" action="{{ route('employees.portal.store', $employee) }}">@csrf
                <button class="zp-btn zp-btn-primary" style="padding:.6rem 1rem" @disabled(! $employee->portal_email)><i class="bi bi-person-plus"></i> Create portal access</button>
            </form>
        @endif
    </div>
</div>
