<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerCommission;
use App\Services\Partners\PartnerProgramme;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin console → Partners: applications, codes, clients and commission payouts. */
class PartnerAdminController extends Controller
{
    public function __construct(private PartnerProgramme $programme)
    {
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('status');

        $partners = Partner::query()
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")->orWhere('firm_name', 'like', "%{$q}%")))
            ->when($status, fn ($w) => $w->where('status', $status))
            ->orderByRaw("status = 'pending' desc")->latest('id')->paginate(30)->withQueryString();

        $ids = $partners->pluck('id');
        $landlord = \App\Support\Tenancy::landlordConnection();

        return view('platform.partners', [
            'partners' => $partners,
            'q'        => $q,
            'status'   => $status,
            'clients'  => DB::connection($landlord)->table('companies')->whereIn('partner_id', $ids)->selectRaw('partner_id, COUNT(*) n')->groupBy('partner_id')->pluck('n', 'partner_id'),
            'pending'  => PartnerCommission::query()->whereIn('partner_id', $ids)->where('status', 'pending')->selectRaw('partner_id, SUM(amount) s')->groupBy('partner_id')->pluck('s', 'partner_id'),
            'owed'     => (float) PartnerCommission::query()->where('status', 'pending')->sum('amount'),
            'waiting'  => Partner::query()->where('status', 'pending')->count(),
        ]);
    }

    public function show(Partner $partner)
    {
        $clients = $this->programme->clients($partner);

        return view('platform.partner', [
            'partner'     => $partner,
            'clients'     => $clients,
            'stats'       => $this->programme->stats($partner, $clients),
            'commissions' => PartnerCommission::query()->with('company')->where('partner_id', $partner->id)->latest('id')->get(),
            'defaultRate' => (float) \App\Services\Billing\PlatformSettings::get('partner_commission_rate'),
        ]);
    }

    public function action(Request $request, Partner $partner)
    {
        $action = (string) $request->input('action');

        switch ($action) {
            case 'approve':
                $this->programme->approve($partner);
                $msg = "{$partner->name} approved — code {$partner->code} is live and emailed to them.";
                break;

            case 'suspend':
                $partner->forceFill(['status' => 'suspended'])->save();
                $msg = "{$partner->code} suspended: the code stops working for new sign-ups and no new commission is recorded.";
                break;

            case 'reactivate':
                $partner->forceFill(['status' => 'active', 'approved_at' => $partner->approved_at ?? now()])->save();
                $msg = "{$partner->code} is active again.";
                break;

            case 'rate':
                $rate = $request->validate(['commission_rate' => 'nullable|numeric|min:0|max:100'])['commission_rate'];
                $partner->forceFill(['commission_rate' => $rate === null || $rate === '' ? null : $rate])->save();
                $msg = $partner->commission_rate === null ? 'Uses the standard commission rate.' : "Commission rate set to {$partner->commission_rate}% for future invoices.";
                break;

            case 'notes':
                $partner->forceFill(['admin_notes' => $request->validate(['admin_notes' => 'nullable|string|max:5000'])['admin_notes']])->save();
                $msg = 'Notes saved.';
                break;

            case 'mark_paid':
                $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'reference' => 'required|string|max:120'],
                    ['ids.required' => 'Tick the commission you paid.']);
                $rows = PartnerCommission::query()->where('partner_id', $partner->id)->where('status', 'pending')->whereIn('id', $data['ids'])->get();
                abort_if($rows->isEmpty(), 422, 'Nothing pending was selected.');
                $total = (float) $rows->sum('amount');
                PartnerCommission::query()->whereIn('id', $rows->pluck('id'))->update(['status' => 'paid', 'paid_at' => now(), 'payout_reference' => $data['reference']]);
                $this->programme->mail($partner, 'paid', ['amount' => $total, 'reference' => $data['reference']]);
                $msg = 'Marked K ' . number_format($total, 2) . ' as paid (' . $rows->count() . ' ' . \Illuminate\Support\Str::plural('invoice', $rows->count()) . ').';
                break;

            case 'void':
                $id = (int) $request->validate(['commission_id' => 'required|integer'])['commission_id'];
                $row = PartnerCommission::query()->where('partner_id', $partner->id)->where('status', 'pending')->findOrFail($id);
                $row->update(['status' => 'void']);
                $msg = "Commission on {$row->invoice_number} voided.";
                break;

            default:
                abort(422);
        }

        Audit::record('platform.partner_' . $action, $partner, $msg, $request->except(['_token', 'action']), null);

        return back()->with('success', $msg);
    }
}
