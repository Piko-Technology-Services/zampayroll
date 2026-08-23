<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * Update company info. Route is protected by the `company_admin` middleware,
     * but we double-check here as defense in depth.
     */
    // public function update(UpdateCompanyRequest $request)
    // {
    //     $user = $request->user();
    //     $company = $user->company;

    //     abort_unless($company, 404);
    //     abort_unless($user->isCompanyAdmin(), 403);

    //     $company->update($request->only(['name', 'email', 'phone', 'address', 'work_days', 'tpin']));

    //     return back()->with('success', 'Company information has been updated successfully.');
    // }



    /*
    |--------------------------------------------------------------------------
    | EDIT — company settings form
    |--------------------------------------------------------------------------
    |
    | Assumes the logged-in user belongs to a company via User::company()
    | (belongsTo Company, company_id on users). Swap auth()->user()->company
    | for however company scoping actually works in your app if different.
    |
    */
    public function edit()
    {
        $company = auth()->user()->company;

        return view('dashboard.company.settings', compact('company'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE — company info + statutory numbers
    |--------------------------------------------------------------------------
    |
    | Deliberately does NOT touch work_days / working_days_per_month — those
    | stay owned by the Attendance → Working Days settings page so there's
    | only one form writing to them.
    |
    */
    public function update(Request $request)
    {
        $company = auth()->user()->company;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',

            'tpin' => 'nullable|string|max:50',
            'napsa_employer_number' => 'nullable|string|max:50',
            'nhima_employer_number' => 'nullable|string|max:50',
            'pacra_registration_number' => 'nullable|string|max:50',
            'workers_comp_number' => 'nullable|string|max:50',
            'vat_number' => 'nullable|string|max:50',

            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company/logos', 'public');
        }
        unset($data['logo']);

        $company->update($data);

        return back()->with('success', 'Company information updated.');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESS CODE — regenerate / deactivate
    |--------------------------------------------------------------------------
    */
    public function regenerateAccessCode()
    {
        $company = auth()->user()->company;
        $code = $company->generateAccessCode();

        return back()->with('success', "New access code generated: {$code}");
    }

    public function deactivateAccessCode()
    {
        $company = auth()->user()->company;
        $company->deactivateAccessCode();

        return back()->with('success', 'Access code deactivated. Existing invite links using it will stop working.');
    }


}
