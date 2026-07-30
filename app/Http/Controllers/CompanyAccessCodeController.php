<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompanyAccessCodeController extends Controller
{
    public function generate(Request $request)
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $code = $company->generateAccessCode();

        return back()->with('success', "A new company access code has been generated: {$code}");
    }

    public function regenerate(Request $request)
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $code = $company->generateAccessCode();

        return back()->with('success', "The company access code has been regenerated: {$code}");
    }

    public function deactivate(Request $request)
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $company->deactivateAccessCode();

        return back()->with('success', 'The company access code has been deactivated.');
    }
}
