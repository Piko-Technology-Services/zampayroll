<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Company;

class AuthController extends Controller
{
    /**
     * Show login page
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->remember)) {
            $request->session()->regenerate();

            return redirect()->route('dashboard')
                ->with('success', 'Welcome back!');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials provided.'
        ])->withInput();
    }

    /**
     * Show register page
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle register request
     */
    // public function register(Request $request)
    // {
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'company_name' => 'nullable|string|max:255',
    //         'email' => 'required|email|unique:users,email',
    //         'password' => 'required|min:6|confirmed',
    //         'role' => 'required|string|max:255'
    //     ]);

    //     $user = User::create([
    //         'name' => $request->name,
    //         'company_name' => $request->company_name,
    //         'email' => $request->email,
    //         'password' => Hash::make($request->password),
    //         'role' => $request->role
    //     ]);

    //     Auth::login($user);

    //     return redirect()->route('dashboard')
    //         ->with('success', 'Account created successfully!');
    // }

  public function register(Request $request)
{
$validated = $request->validate([
/*
|--------------------------------------------------------------------------
| Company Information
|--------------------------------------------------------------------------
*/

    'company_name' => [
        'required',
        'string',
        'max:255',
    ],

    'company_email' => [
        'nullable',
        'email',
        'max:255',
    ],

    'company_phone' => [
        'nullable',
        'string',
        'max:50',
    ],

    'company_address' => [
        'nullable',
        'string',
        'max:1000',
    ],

    'company_tpin' => [
        'nullable',
        'string',
        'max:100',
    ],

    /*
    |--------------------------------------------------------------------------
    | Main Administrator Information
    |--------------------------------------------------------------------------
    */

    'name' => [
        'required',
        'string',
        'max:255',
    ],

    'position' => [
        'nullable',
        'string',
        'max:255',
    ],

    'email' => [
        'required',
        'email',
        'max:255',
        'unique:users,email',
    ],

    'password' => [
        'required',
        'string',
        'min:8',
        'confirmed',
    ],

    'terms' => [
        'accepted',
    ],
]);

/*
|--------------------------------------------------------------------------
| Create Company and Administrator
|--------------------------------------------------------------------------
|
| The transaction ensures that both records are created together.
| If one operation fails, Laravel rolls back the other operation.
|
*/

$user = DB::transaction(
    function () use ($validated) {

        /*
        |--------------------------------------------------------------------------
        | Create the Company
        |--------------------------------------------------------------------------
        */

        $company = Company::create([
            'name' => $validated[
                'company_name'
            ],

            'email' => $validated[
                'company_email'
            ] ?? null,

            'phone' => $validated[
                'company_phone'
            ] ?? null,

            'address' => $validated[
                'company_address'
            ] ?? null,

            'tpin' => $validated[
                'company_tpin'
            ] ?? null,
        ]);


        /*
        |--------------------------------------------------------------------------
        | Create the First Company User
        |--------------------------------------------------------------------------
        |
        | The first user is automatically the company administrator.
        |
        */

        $user = User::create([
            'company_id' => $company->id,

            'name' => $validated[
                'name'
            ],

            'position' => $validated[
                'position'
            ] ?? null,

            'email' => $validated[
                'email'
            ],

            'password' => Hash::make(
                $validated['password']
            ),

            'role' => 'company_admin',
        ]);

        return $user;
    }
);


/*
|--------------------------------------------------------------------------
| Log in the New Administrator
|--------------------------------------------------------------------------
*/

Auth::login($user);

$request->session()->regenerate();


/*
|--------------------------------------------------------------------------
| Redirect to Dashboard
|--------------------------------------------------------------------------
*/

return redirect()
    ->route('dashboard')
    ->with(
        'success',
        'Your company account has been created successfully.'
    );


}
  

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}