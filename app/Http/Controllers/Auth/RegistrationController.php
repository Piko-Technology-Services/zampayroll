<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Multi-step company sign-up.
 *
 * Mandatory (steps 1-2): who you are, your company, its size — and a
 * sub-domain when the company has more than 10 employees. Everything else
 * (statutory numbers, address, payroll preferences) is optional and can be
 * completed later from Company Settings.
 */
class RegistrationController extends Controller
{
    public function show()
    {
        abort_if(Tenancy::isDedicated(), 404); // sign-ups happen on the main domain

        return view('auth.register', [
            'ranges'      => config('tenancy.employee_ranges'),
            'limit'       => config('tenancy.shared_employee_limit'),
            'industries'  => Company::INDUSTRIES,
            'provinces'   => Company::PROVINCES,
            'frequencies' => Company::PAY_FREQUENCIES,
            'modules'     => Company::MODULES,
            'centralDomain' => Tenancy::centralDomain(),
        ]);
    }

    public function store(Request $request)
    {
        abort_if(Tenancy::isDedicated(), 404);

        // Every company starts on the shared workspace. Larger companies can ask for
        // a private sub-domain later (Settings → Billing), which ZamPayroll approves.
        $dedicated = false;

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'subdomain' => Str::lower(trim((string) $request->input('subdomain'))),
        ]);

        $validated = $request->validate([
            // Step 1 — you
            'name'      => ['required', 'string', 'max:255'],
            'position'  => ['nullable', 'string', 'max:255'],
            'phone'     => ['required', 'string', 'max:50'],
            'email'     => ['required', 'email', 'max:255', function ($attr, $value, $fail) {
                if (Tenancy::emailTaken($value)) {
                    $fail('An account with this email already exists. Try signing in or resetting your password.');
                }
            }],
            'password'  => ['required', 'confirmed', Password::min(8)->letters()->numbers()],

            // Step 2 — company
            'company_name'         => ['required', 'string', 'max:255'],
            'industry'             => ['required', Rule::in(array_keys(Company::INDUSTRIES))],
            'employee_count_range' => ['required', Rule::in(array_keys(config('tenancy.employee_ranges')))],
            'subdomain'            => $dedicated
                ? ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', function ($attr, $value, $fail) {
                    if ($reason = $this->subdomainProblem($value)) {
                        $fail($reason);
                    }
                }]
                : ['nullable'],

            // Step 3 — company details (optional)
            'company_email'             => ['nullable', 'email', 'max:255'],
            'company_phone'             => ['nullable', 'string', 'max:50'],
            'website'                   => ['nullable', 'string', 'max:255'],
            'company_address'           => ['nullable', 'string', 'max:1000'],
            'city'                      => ['nullable', 'string', 'max:100'],
            'province'                  => ['nullable', Rule::in(Company::PROVINCES)],
            'company_tpin'              => ['nullable', 'string', 'max:50'],
            'pacra_registration_number' => ['nullable', 'string', 'max:50'],
            'napsa_employer_number'     => ['nullable', 'string', 'max:50'],
            'nhima_employer_number'     => ['nullable', 'string', 'max:50'],
            'workers_comp_number'       => ['nullable', 'string', 'max:50'],
            'vat_number'                => ['nullable', 'string', 'max:50'],

            // Step 4 — payroll preferences (optional)
            'pay_frequency'          => ['nullable', Rule::in(array_keys(Company::PAY_FREQUENCIES))],
            'pay_day'                => ['nullable', 'integer', 'between:1,31'],
            'work_days'              => ['nullable', 'array'],
            'work_days.*'            => ['integer', 'between:1,7'],
            'working_days_per_month' => ['nullable', 'integer', 'between:1,31'],
            'interested_modules'     => ['nullable', 'array'],
            'interested_modules.*'   => [Rule::in(array_keys(Company::MODULES))],
            'heard_about_us'         => ['nullable', 'string', 'max:100'],

            'terms' => ['accepted'],
        ], [
            'terms.accepted'     => 'Please accept the terms to create your workspace.',
            'subdomain.required' => 'Pick a web address for your dedicated workspace.',
            'subdomain.regex'    => 'Use lowercase letters, numbers and dashes only (no spaces).',
            'industry.required'  => 'Tell us which industry you are in.',
            'employee_count_range.required' => 'How many people work at your company?',
        ]);

        $finishLater = $request->boolean('finish_later');

        $user = DB::transaction(function () use ($validated, $dedicated, $finishLater) {
            $company = Company::create([
                'name'                      => $validated['company_name'],
                'company_code'              => Company::makeCompanyCode($validated['company_name']),
                'tenancy_mode'              => $dedicated ? Company::MODE_DEDICATED : Company::MODE_SHARED,
                'subdomain'                 => $dedicated ? $validated['subdomain'] : null,
                'status'                    => Company::STATUS_PENDING_VERIFICATION,
                'industry'                  => $validated['industry'],
                'employee_count_range'      => $validated['employee_count_range'],

                'email'                     => $validated['company_email'] ?? null,
                'phone'                     => $validated['company_phone'] ?? null,
                'website'                   => $validated['website'] ?? null,
                'address'                   => $validated['company_address'] ?? null,
                'city'                      => $validated['city'] ?? null,
                'province'                  => $validated['province'] ?? null,
                'tpin'                      => $validated['company_tpin'] ?? null,
                'pacra_registration_number' => $validated['pacra_registration_number'] ?? null,
                'napsa_employer_number'     => $validated['napsa_employer_number'] ?? null,
                'nhima_employer_number'     => $validated['nhima_employer_number'] ?? null,
                'workers_comp_number'       => $validated['workers_comp_number'] ?? null,
                'vat_number'                => $validated['vat_number'] ?? null,

                'pay_frequency'             => $validated['pay_frequency'] ?? 'monthly',
                'pay_day'                   => $validated['pay_day'] ?? null,
                'work_days'                 => array_values(array_map('intval', $validated['work_days'] ?? [1, 2, 3, 4, 5])),
                'working_days_per_month'    => $validated['working_days_per_month'] ?? 26,
                'interested_modules'        => $validated['interested_modules'] ?? null,
                'heard_about_us'            => $validated['heard_about_us'] ?? null,
                'onboarding_completed_at'   => $finishLater ? null : now(),
            ]);

            \App\Support\Permissions::seedDefaultRoles((int) $company->id);

            return User::create([
                'company_id' => $company->id,
                'name'       => $validated['name'],
                'position'   => $validated['position'] ?? null,
                'phone'      => $validated['phone'],
                'email'      => $validated['email'],
                'password'   => Hash::make($validated['password']),
                'role'       => 'company_admin',
            ]);
        });

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        $request->session()->put('registered_email', $user->email);

        return redirect()->route('verification.notice');
    }

    /* ------------------------------------------------------------------ */
    /*  Live checks used by the wizard                                      */
    /* ------------------------------------------------------------------ */

    public function checkEmail(Request $request)
    {
        $email = Str::lower(trim((string) $request->query('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['available' => false, 'message' => 'That doesn\'t look like a valid email yet.']);
        }

        $taken = Tenancy::emailTaken($email);

        return response()->json([
            'available' => ! $taken,
            'message'   => $taken ? 'This email already has an account — try signing in instead.' : 'Looks good!',
        ]);
    }

    public function checkSubdomain(Request $request)
    {
        $value = Str::lower(trim((string) $request->query('subdomain')));
        $problem = $this->subdomainProblem($value);

        return response()->json([
            'available'  => $problem === null,
            'message'    => $problem ?? 'Great choice — it\'s all yours!',
            'host'       => $value . '.' . Tenancy::centralDomain(),
            'suggestion' => $problem ? $this->suggestSubdomain($value ?: (string) $request->query('company')) : null,
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function isDedicatedRange(?string $range): bool
    {
        $ranges = config('tenancy.employee_ranges');

        return isset($ranges[$range]) && $ranges[$range]['max'] > config('tenancy.shared_employee_limit');
    }

    private function subdomainProblem(string $value): ?string
    {
        if ($value === '') {
            return 'Pick a web address for your workspace.';
        }
        if (strlen($value) < 3 || strlen($value) > 32) {
            return 'Use between 3 and 32 characters.';
        }
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $value)) {
            return 'Use lowercase letters, numbers and dashes only (no spaces).';
        }
        if (in_array($value, config('tenancy.reserved_subdomains'), true)) {
            return 'That address is reserved — try another one.';
        }
        if (Company::on(Tenancy::landlordConnection())->where('subdomain', $value)->exists()) {
            return 'Someone already took that one — try a variation.';
        }

        return null;
    }

    private function suggestSubdomain(string $seed): ?string
    {
        $base = Str::of($seed)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->limit(24, '')->value();
        $base = trim($base, '-');

        if (strlen($base) < 3) {
            $base = 'team-' . Str::lower(Str::random(4));
        }

        foreach (['', '-zm', '-hr', '-ltd', '-' . random_int(10, 99)] as $suffix) {
            $candidate = $base . $suffix;
            if ($this->subdomainProblem($candidate) === null) {
                return $candidate;
            }
        }

        return null;
    }
}
