<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerCommission;
use App\Services\Partners\PartnerProgramme;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * The partner's own dashboard: code and link, clients (name, size and plan only —
 * never payroll or employee details), commission and payout details.
 */
class PartnerDashboardController extends Controller
{
    public function __construct(private PartnerProgramme $programme)
    {
    }

    public function index(Request $request)
    {
        $partner = $request->user()->partner;
        $clients = $this->programme->clients($partner);

        return view('partner.dashboard', [
            'partner'     => $partner,
            'clients'     => $clients,
            'stats'       => $this->programme->stats($partner, $clients),
            'commissions' => PartnerCommission::query()->with('company')->where('partner_id', $partner->id)->latest('id')->take(50)->get(),
            'shareText'   => 'Hi! I use ZamPayroll for payroll in Zambia: PAYE, NAPSA & NHIMA done for you, payslips on every phone, free for up to '
                . \App\Services\Billing\PlatformSettings::get('free_limit') . ' employees. Sign up with my code ' . $partner->code . ': ' . $partner->joinUrl(),
        ]);
    }

    public function payout(Request $request)
    {
        $partner = $request->user()->partner;
        $data = $request->validate([
            'payout_method'  => ['required', 'in:mobile_money,bank'],
            'payout_details' => ['required', 'string', 'max:500'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'firm_name'      => ['nullable', 'string', 'max:160'],
        ], ['payout_details.required' => 'Add the number or account we should pay your commission into.']);

        $partner->forceFill($data + ['phone' => $partner->phone])->save();
        Audit::record('partner.payout_details', $partner, "{$partner->code} updated payout details", [], null);

        return back()->with('success', 'Saved. We\'ll pay your commission to these details.');
    }
}
