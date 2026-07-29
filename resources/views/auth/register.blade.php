@extends('layouts.auth')

@section('title', 'Create Company Account')

@section('subtitle', 'Register your company and create the first administrator account.')

@section('content')

<form method="POST"
      action="{{ route('register') }}"
      class="needs-validation"
      novalidate>

@csrf

{{-- PAGE HEADER --}}
<div class="mb-4">

    <p class="eyebrow mb-1">
        Payroll System Registration
    </p>

    <h1 class="h3 mb-1">
        Create Your Company Account
    </h1>

    <p class="text-muted mb-0">
        Enter your company details and create the main administrator account.
    </p>

</div>


{{-- =========================================================
   COMPANY INFORMATION
========================================================== --}}

<div class="row g-4">

    <div class="col-12 col-lg-6">

        <div class="border rounded-3 p-3 p-md-4 h-100">

            <div class="d-flex align-items-center mb-3">

                <div class="me-2">

                    <i class="bi bi-building fs-4 text-primary"></i>

                </div>

                <div>

                    <h2 class="h6 mb-0">
                        Company Information
                    </h2>

                    <small class="text-muted">
                        Details about your organisation
                    </small>

                </div>

            </div>


            {{-- COMPANY NAME --}}
            <div class="mb-3">

                <label for="company_name"
                       class="form-label small fw-semibold">

                    Company Name
                    <span class="text-danger">*</span>

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-building"></i>

                    </span>

                    <input type="text"
                           id="company_name"
                           name="company_name"
                           value="{{ old('company_name') }}"
                           class="form-control border-start-0
                                  @error('company_name') is-invalid @enderror"
                           placeholder="Chigayo Milling Limited"
                           required>

                </div>

                @error('company_name')

                    <small class="text-danger d-block mt-1">
                        {{ $message }}
                    </small>

                @enderror

            </div>


            {{-- COMPANY EMAIL --}}
            <div class="mb-3">

                <label for="company_email"
                       class="form-label small fw-semibold">

                    Company Email Address

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-envelope"></i>

                    </span>

                    <input type="email"
                           id="company_email"
                           name="company_email"
                           value="{{ old('company_email') }}"
                           class="form-control border-start-0
                                  @error('company_email') is-invalid @enderror"
                           placeholder="info@company.com">

                </div>

                @error('company_email')

                    <small class="text-danger d-block mt-1">
                        {{ $message }}
                    </small>

                @enderror

            </div>


            {{-- COMPANY PHONE --}}
            <div class="mb-3">

                <label for="company_phone"
                       class="form-label small fw-semibold">

                    Company Phone Number

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-telephone"></i>

                    </span>

                    <input type="text"
                           id="company_phone"
                           name="company_phone"
                           value="{{ old('company_phone') }}"
                           class="form-control border-start-0
                                  @error('company_phone') is-invalid @enderror"
                           placeholder="+260 97 000 0000">

                </div>

                @error('company_phone')

                    <small class="text-danger d-block mt-1">
                        {{ $message }}
                    </small>

                @enderror

            </div>


            {{-- COMPANY ADDRESS --}}
            <div class="mb-3">

                <label for="company_address"
                       class="form-label small fw-semibold">

                    Company Address

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-geo-alt"></i>

                    </span>

                    <textarea id="company_address"
                              name="company_address"
                              rows="2"
                              class="form-control border-start-0
                                     @error('company_address') is-invalid @enderror"
                              placeholder="Plot number, street, town or city">{{ old('company_address') }}</textarea>

                </div>

                @error('company_address')

                    <small class="text-danger d-block mt-1">
                        {{ $message }}
                    </small>

                @enderror

            </div>


            {{-- COMPANY TPIN --}}
            <div class="mb-0">

                <label for="company_tpin"
                       class="form-label small fw-semibold">

                    Company TPIN

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-receipt"></i>

                    </span>

                    <input type="text"
                           id="company_tpin"
                           name="company_tpin"
                           value="{{ old('company_tpin') }}"
                           class="form-control border-start-0
                                  @error('company_tpin') is-invalid @enderror"
                           placeholder="Enter company TPIN">

                </div>

                <small class="text-muted">
                    Optional. You can add this later in company settings.
                </small>

                @error('company_tpin')

                    <small class="text-danger d-block mt-1">
                        {{ $message }}
                    </small>

                @enderror

            </div>

        </div>

    </div>


    {{-- =========================================================
       ADMINISTRATOR INFORMATION
    ========================================================== --}}

    <div class="col-12 col-lg-6">

        <div class="border rounded-3 p-3 p-md-4 h-100">

            <div class="d-flex align-items-center mb-3">

                <div class="me-2">

                    <i class="bi bi-person-badge fs-4 text-primary"></i>

                </div>

                <div>

                    <h2 class="h6 mb-0">
                        Main Administrator
                    </h2>

                    <small class="text-muted">
                        This user will manage the company payroll account
                    </small>

                </div>

            </div>


            {{-- FULL NAME --}}
            <div class="mb-3">

                <label for="name"
                       class="form-label small fw-semibold">

                    Full Name
                    <span class="text-danger">*</span>

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-person"></i>

                    </span>

                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ old('name') }}"
                           class="form-control border-start-0
                                  @error('name') is-invalid @enderror"
                           placeholder="John Chimfwembe"
                           required>

                </div>

                @error('name')

                    <small class="text-danger d-block mt-1">
                        {{ $message }}
                    </small>

                @enderror

            </div>


            {{-- POSITION --}}
            <div class="mb-3">

                <label for="position"
                       class="form-label small fw-semibold">

                    Position in Company

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-briefcase"></i>

                    </span>

                    <input type="text"
                           id="position"
                           name="position"
                           value="{{ old('position') }}"
                           class="form-control border-start-0
                                  @error('position') is-invalid @enderror"
                           placeholder="Human Resource Manager">

                </div>

                @error('position')

                    <small class="text-danger d-block mt-1">
                        {{ $message }}
                    </small>

                @enderror

            </div>


            {{-- USER EMAIL --}}
            <div class="mb-3">

                <label for="email"
                       class="form-label small fw-semibold">

                    Administrator Email Address
                    <span class="text-danger">*</span>

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-envelope"></i>

                    </span>

                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           class="form-control border-start-0
                                  @error('email') is-invalid @enderror"
                           placeholder="john@company.com"
                           required>

                </div>

                @error('email')

                    <small class="text-danger d-block mt-1">
                        {{ $message }}
                    </small>

                @enderror

            </div>


            {{-- PASSWORD --}}
            <div class="mb-3">

                <label for="password"
                       class="form-label small fw-semibold">

                    Password
                    <span class="text-danger">*</span>

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-lock"></i>

                    </span>

                    <input type="password"
                           id="password"
                           name="password"
                           class="form-control border-start-0
                                  @error('password') is-invalid @enderror"
                           placeholder="Create a secure password"
                           minlength="8"
                           required>

                </div>

                <small class="text-muted">
                    Use at least 8 characters.
                </small>

                @error('password')

                    <small class="text-danger d-block mt-1">
                        {{ $message }}
                    </small>

                @enderror

            </div>


            {{-- CONFIRM PASSWORD --}}
            <div class="mb-0">

                <label for="password_confirmation"
                       class="form-label small fw-semibold">

                    Confirm Password
                    <span class="text-danger">*</span>

                </label>

                <div class="input-group">

                    <span class="input-group-text bg-light border-end-0">

                        <i class="bi bi-shield-lock"></i>

                    </span>

                    <input type="password"
                           id="password_confirmation"
                           name="password_confirmation"
                           class="form-control border-start-0"
                           placeholder="Re-enter your password"
                           minlength="8"
                           required>

                </div>

            </div>

        </div>

    </div>

</div>


{{-- TERMS --}}
<div class="mb-4">

    <div class="form-check">

        <input class="form-check-input"
               type="checkbox"
               id="terms"
               name="terms"
               value="1"
               {{ old('terms') ? 'checked' : '' }}
               required>

        <label class="form-check-label"
               for="terms">

            I confirm that the company information is correct
            and I agree to the system terms.

        </label>

    </div>

    @error('terms')

        <small class="text-danger d-block mt-1">
            {{ $message }}
        </small>

    @enderror

</div>


{{-- SUBMIT --}}
<button type="submit"
        class="btn btn-primary w-100 py-2">

    <i class="bi bi-building-add me-2"></i>

    Create Company Account

</button>


{{-- LOGIN LINK --}}
<div class="text-center mt-4">

    <small class="text-muted">

        Already have an account?

        <a href="{{ route('login') }}"
           class="text-decoration-none">

            Sign in

        </a>

    </small>

</div>


</form>

@endsection
