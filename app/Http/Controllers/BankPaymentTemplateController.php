<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankPaymentTemplate;
use Illuminate\Http\Request;

class BankPaymentTemplateController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — list this company's templates, banks directory, and the mapping form
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $company = auth()->user()->company;

        $templates = BankPaymentTemplate::where('company_id', $company->id)->latest()->get();
        $banks = Bank::active()->orderBy('name')->get();

        return view('dashboard.company.bank-templates', [
            'templates' => $templates,
            'banks' => $banks,
            'fields' => BankPaymentTemplate::FIELDS,
            'genericColumns' => BankPaymentTemplate::genericColumns(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE FROM PRESET — company picks a bank and starts from the generic
    | universal column layout, which they can still tweak (headers/prefixes)
    | before saving.
    |--------------------------------------------------------------------------
    */
    public function storePreset(Request $request)
    {
        $company = auth()->user()->company;

        $data = $request->validate([
            'bank_id' => 'nullable|exists:banks,id',
            'name' => 'required|string|max:255',
            'delimiter' => 'nullable|string|max:5',
            'include_header' => 'nullable|boolean',
            'columns' => 'required|array|min:1',
            'columns.*.header' => 'required|string|max:255',
            'columns.*.field' => 'required|string|in:' . implode(',', array_keys(BankPaymentTemplate::FIELDS)),
            'columns.*.prefix' => 'nullable|string|max:20',
            'columns.*.suffix' => 'nullable|string|max:20',
            'columns.*.static_value' => 'nullable|string|max:255',
        ]);

        $template = BankPaymentTemplate::create([
            'company_id' => $company->id,
            'bank_id' => $data['bank_id'] ?? null,
            'name' => $data['name'],
            'source' => 'preset',
            'delimiter' => $data['delimiter'] ?? ',',
            'include_header' => $request->boolean('include_header', true),
            'columns' => $data['columns'],
            'created_by' => auth()->id(),
        ]);

        $this->makeDefaultIfFirst($company->id, $template);

        return back()->with('success', 'Bank payment template saved.');
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD CUSTOM CSV — parse the header row and return a best-guess mapping
    | for the company to review/adjust before saving (does not save yet).
    |--------------------------------------------------------------------------
    */
    public function parseCustomUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $headerRow = fgetcsv($handle) ?: [];
        fclose($handle);

        $suggested = array_map(function ($header) {
            return [
                'header' => $header,
                'field' => $this->guessField($header),
                'prefix' => '',
                'suffix' => '',
                'static_value' => null,
            ];
        }, $headerRow);

        return response()->json([
            'success' => true,
            'columns' => $suggested,
        ]);
    }

    /**
     * Best-effort guess at which internal field a bank's own column header
     * corresponds to, purely to save re-typing — the company still reviews
     * and confirms every mapping before it's saved.
     */
    protected function guessField(string $header): string
    {
        $h = strtolower(trim($header, " \t\n\r\0\x0B#"));

        $map = [
            'account_number' => ['account', 'acc no', 'acc number', 'account no'],
            'amount' => ['amount', 'value', 'net pay', 'net amount'],
            'account_name' => ['beneficiary name', 'name', 'account name', 'full name'],
            'swift_code' => ['swift', 'bic'],
            'sort_code' => ['sort code', 'branch code', 'routing'],
            'bank_name' => ['bank name', 'bank'],
            'bank_branch' => ['branch'],
            'reference' => ['my reference', 'reference', 'narration', 'description'],
            'beneficiary_reference' => ['beneficiary reference'],
            'employee_id' => ['employee id', 'staff number', 'staff no', 'emp id'],
            'nrc_no' => ['nrc'],
            'email' => ['email'],
            'phone' => ['phone', 'mobile', 'contact'],
            'address' => ['address'],
        ];

        foreach ($map as $field => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($h, $needle)) {
                    return $field;
                }
            }
        }

        return 'static';
    }

    /*
    |--------------------------------------------------------------------------
    | STORE CUSTOM — save the reviewed/confirmed mapping for an uploaded template
    |--------------------------------------------------------------------------
    */
    public function storeCustom(Request $request)
    {
        $company = auth()->user()->company;

        $data = $request->validate([
            'bank_id' => 'nullable|exists:banks,id',
            'name' => 'required|string|max:255',
            'delimiter' => 'nullable|string|max:5',
            'include_header' => 'nullable|boolean',
            'columns' => 'required|array|min:1',
            'columns.*.header' => 'required|string|max:255',
            'columns.*.field' => 'required|string|in:' . implode(',', array_keys(BankPaymentTemplate::FIELDS)),
            'columns.*.prefix' => 'nullable|string|max:20',
            'columns.*.suffix' => 'nullable|string|max:20',
            'columns.*.static_value' => 'nullable|string|max:255',
        ]);

        $template = BankPaymentTemplate::create([
            'company_id' => $company->id,
            'bank_id' => $data['bank_id'] ?? null,
            'name' => $data['name'],
            'source' => 'custom',
            'delimiter' => $data['delimiter'] ?? ',',
            'include_header' => $request->boolean('include_header', true),
            'columns' => $data['columns'],
            'created_by' => auth()->id(),
        ]);

        $this->makeDefaultIfFirst($company->id, $template);

        return back()->with('success', 'Custom bank template saved and ready to use.');
    }

    /*
    |--------------------------------------------------------------------------
    | SET DEFAULT — the template used whenever "Bank Bulk Payments" is exported
    |--------------------------------------------------------------------------
    */
    public function setDefault(BankPaymentTemplate $template)
    {
        $this->authorizeCompany($template);

        BankPaymentTemplate::where('company_id', $template->company_id)->update(['is_default' => false]);
        $template->update(['is_default' => true]);

        return back()->with('success', "\"{$template->name}\" is now the default bank payment template.");
    }

    public function destroy(BankPaymentTemplate $template)
    {
        $this->authorizeCompany($template);

        $wasDefault = $template->is_default;
        $template->delete();

        if ($wasDefault) {
            $next = BankPaymentTemplate::where('company_id', $template->company_id)->first();
            $next?->update(['is_default' => true]);
        }

        return back()->with('success', 'Template deleted.');
    }

    protected function makeDefaultIfFirst(int $companyId, BankPaymentTemplate $template): void
    {
        if (BankPaymentTemplate::where('company_id', $companyId)->count() === 1) {
            $template->update(['is_default' => true]);
        }
    }

    protected function authorizeCompany(BankPaymentTemplate $template): void
    {
        abort_unless($template->company_id === auth()->user()->company_id, 403);
    }
}
