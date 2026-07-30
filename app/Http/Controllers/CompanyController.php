<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCompanyRequest;

class CompanyController extends Controller
{
    /**
     * Update company info. Route is protected by the `company_admin` middleware,
     * but we double-check here as defense in depth.
     */
    public function update(UpdateCompanyRequest $request)
    {
        $user = $request->user();
        $company = $user->company;

        abort_unless($company, 404);
        abort_unless($user->isCompanyAdmin(), 403);

        $company->update($request->only(['name', 'email', 'phone', 'address', 'tpin']));

        return back()->with('success', 'Company information has been updated successfully.');
    }
}
