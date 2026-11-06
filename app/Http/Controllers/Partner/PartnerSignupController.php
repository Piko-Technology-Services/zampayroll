<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\Billing\PlatformSettings;
use App\Services\Partners\PartnerProgramme;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Public side of the partner programme: the /partners page and application form,
 * the /join/{code} sign-up link, and the live code check used on the sign-up form.
 */
class PartnerSignupController extends Controller
{
    public function __construct(private PartnerProgramme $programme)
    {
    }

    public function show(Request $request)
    {
        abort_if(Tenancy::isDedicated(), 404);

        return view('site.partners', [
            'types'   => Partner::TYPES,
            'rate'    => (float) PlatformSettings::get('partner_commission_rate'),
            'months'  => (int) PlatformSettings::get('partner_commission_months'),
            'auto'    => (int) PlatformSettings::get('partner_auto_approve') === 1,
            'freeLimit' => (int) PlatformSettings::get('free_limit'),
            'signedIn' => $request->user(),
        ]);
    }

    public function store(Request $request)
    {
        abort_if(Tenancy::isDedicated(), 404);

        if (filled($request->input('website'))) { // honeypot
            return redirect()->route('partners.show');
        }

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'email'        => ['required', 'email', 'max:180', function ($a, $v, $fail) {
                if (Tenancy::emailTaken($v)) {
                    $fail('This email already has a ZamPayroll account. Use a different email for your partner account, or WhatsApp us and we\'ll help.');
                }
            }],
            'phone'        => ['required', 'string', 'max:50'],
            'partner_type' => ['required', Rule::in(array_keys(Partner::TYPES))],
            'firm_name'    => ['nullable', 'string', 'max:160'],
            'city'         => ['nullable', 'string', 'max:100'],
            'pitch'        => ['nullable', 'string', 'max:1000'],
            'password'     => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms'        => ['accepted'],
        ], [
            'partner_type.required' => 'Tell us which best describes you.',
            'terms.accepted'        => 'Please accept the partner terms.',
        ]);

        $partner = $this->programme->apply($data);

        try {
            $partner->user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        \App\Support\AdminAlert::send(
            'New partner registration: ' . $data['name'],
            'Partner programme',
            $data['name'] . ' wants to become a partner',
            $partner->isActive() ? 'A new partner has joined and was approved automatically.' : 'A new partner application is waiting for your review.',
            [
                'Name'   => $data['name'],
                'Type'   => Partner::TYPES[$data['partner_type']] ?? $data['partner_type'],
                'Firm'   => $data['firm_name'] ?? null,
                'City'   => $data['city'] ?? null,
                'Email'  => $data['email'],
                'Phone'  => $data['phone'],
                'Code'   => $partner->code,
                'Status' => $partner->isActive() ? 'Active' : 'Pending review',
                'Pitch'  => $data['pitch'] ?? null,
            ],
            route('platform.partners.show', $partner),
            'Review partner',
        );

        $request->session()->put('registered_email', $partner->email);

        return redirect()->route('verification.notice')->with('status', $partner->isActive()
            ? 'You\'re in! Your partner code is ' . $partner->code . '. Confirm your email to open your partner dashboard.'
            : 'Application received. Confirm your email, and we\'ll send your partner code once we\'ve reviewed it (usually within one working day).');
    }

    /** /join/ZP-CHANDA25 → remember the partner for a while and open the sign-up form with the code filled in. */
    public function join(Request $request, string $code)
    {
        abort_if(Tenancy::isDedicated(), 404);
        $partner = $this->programme->findActive($code);

        if (! $partner) {
            return redirect()->route('register')->with('error', 'That partner link isn\'t active any more — you can still sign up below.');
        }

        Cookie::queue(PartnerProgramme::COOKIE, $partner->code, 60 * 24 * PartnerProgramme::COOKIE_DAYS);

        return redirect()->route('register', ['partner' => $partner->code]);
    }

    /** Live check for the sign-up form. */
    public function check(Request $request)
    {
        $code = PartnerProgramme::normalise($request->query('code'));
        if ($code === '') {
            return response()->json(['valid' => false, 'message' => '']);
        }

        $partner = $this->programme->findActive($code);

        return response()->json($partner
            ? ['valid' => true, 'code' => $partner->code, 'name' => $partner->publicName(), 'message' => 'Linked to partner: ' . $partner->publicName()]
            : ['valid' => false, 'message' => 'We couldn\'t find an active partner with that code.']);
    }
}
