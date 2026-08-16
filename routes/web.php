<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;

use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveDashboardController;

use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayrollRuleController;
use App\Http\Controllers\PayrollReportController;
use App\Http\Controllers\PayslipMailController;
use App\Http\Controllers\CompanyAccessCodeController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DemoRequestController;
use App\Http\Controllers\PaymentController;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceSettingsController;
use App\Http\Controllers\AttendanceReportController;

use App\Http\Controllers\OvertimeApplicationController;
use App\Http\Controllers\OvertimeDashboardController;

use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\LoanDashboardController;

/*
|--------------------------------------------------------------------------
| GUEST ROUTES (NOT LOGGED IN)
|--------------------------------------------------------------------------
*/


// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');

Route::get('/', [DemoRequestController::class, 'index'])->name('home');
Route::post('/demo-request', [DemoRequestController::class, 'store'])->name('demo.request.store');

Route::middleware('guest')->group(function () {

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});


Route::prefix('payroll/reports')->name('payroll.reports.')->group(function () {
 
    // Main dashboard — pick a run, see summary, jump to any export
    Route::get('/', [PayrollReportController::class, 'index'])->name('index');
 
    // Management reports — PDF export
    // {report} = total_earnings | total_deductions | net_payable |
    //            statutory_summary | comprehensive | department | branch
    Route::get('/{run}/management/{report}/pdf',
        [PayrollReportController::class, 'exportManagementPdf'])
        ->name('management.pdf');
 
    // Statutory submissions
    Route::get('/{run}/napsa/csv',
        [PayrollReportController::class, 'exportNapsaCsv'])
        ->name('napsa.csv');
 
    Route::get('/{run}/zra/excel',
        [PayrollReportController::class, 'exportZraExcel'])
        ->name('zra.excel');
 
    Route::get('/{run}/nhima/csv',
        [PayrollReportController::class, 'exportNhimaCsv'])
        ->name('nhima.csv');
 
    Route::get('/{run}/wcfcb/csv',
        [PayrollReportController::class, 'exportWcfcbCsv'])
        ->name('wcfcb.csv');
 
    // Banking & payments
    Route::get('/{run}/bank-payments/csv',
        [PayrollReportController::class, 'exportBankPaymentsCsv'])
        ->name('bank_payments.csv');
 
});

Route::post('/payroll/{payroll}/email', [PayslipMailController::class, 'sendSingle'])
    ->name('payroll.email.single');
 
Route::post('/payroll/runs/{run}/email-all', [PayslipMailController::class, 'sendBulk'])
    ->name('payroll.email.bulk');



/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (LOGGED IN USERS ONLY)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // ✅ STATIC ROUTES FIRST
    Route::get('/employees/sample-csv', [EmployeeController::class, 'downloadSampleCsv'])
        ->name('employees.sample-csv');

    Route::post('/employees/import', [EmployeeController::class, 'import'])
        ->name('employees.import');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Employees
    Route::resource('employees', EmployeeController::class);

    // Others
    Route::get('/departments', [DashboardController::class, 'departments'])->name('departments.index');
    // Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    
    Route::get('/payroll/runs/all', [DashboardController::class, 'payroll'])->name('payroll.index');
    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports.index');

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::view('/settings', 'dashboard.settings')->name('settings.index');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware('auth')->group(function () {

    // My Profile / Password & Security (any authenticated user)
    Route::get('/profile-edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Company Info / Access Code / Team invites (company_admin only)
    Route::middleware('company_admin')->group(function () {
        Route::put('/company', [CompanyController::class, 'update'])->name('company.update');

        Route::post('/company/access-code/generate', [CompanyAccessCodeController::class, 'generate'])->name('company.access-code.generate');
        Route::post('/company/access-code/regenerate', [CompanyAccessCodeController::class, 'regenerate'])->name('company.access-code.regenerate');
        Route::post('/company/access-code/deactivate', [CompanyAccessCodeController::class, 'deactivate'])->name('company.access-code.deactivate');

        Route::post('/team/invite', [InvitationController::class, 'store'])->name('invitations.store');
        Route::delete('/team/invite/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.revoke');
    });
});

// Public invitation acceptance flow (token-protected, not auth-gated —
// a not-yet-registered invitee has no account to authenticate with).
Route::get('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
Route::post('/invitations/{token}/complete', [InvitationController::class, 'complete'])->name('invitations.complete');

Route::middleware(['auth'])->prefix('attendance')->name('attendance.')->group(function () {
 
    Route::get('/', [AttendanceController::class, 'calendar'])->name('calendar');
 
    // CSV import (kept above the {date} routes so 'import' isn't swallowed by the date wildcard)
    Route::get('/import/sample-csv', [AttendanceController::class, 'downloadSampleCsv'])->name('import.sample');
    Route::post('/import', [AttendanceController::class, 'import'])->name('import');
 
    // Working days + holidays
    Route::get('/settings', [AttendanceSettingsController::class, 'index'])->name('settings');
    Route::put('/settings/work-days', [AttendanceSettingsController::class, 'updateWorkDays'])->name('settings.workdays');
    Route::post('/settings/holidays', [AttendanceSettingsController::class, 'storeHoliday'])->name('settings.holidays.store');
    Route::delete('/settings/holidays/{holiday}', [AttendanceSettingsController::class, 'destroyHoliday'])->name('settings.holidays.destroy');
 
    // Day drill-down — date constrained to YYYY-MM-DD so it never matches 'import' or 'settings' above
    Route::get('/{date}', [AttendanceController::class, 'day'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('day');
 
    Route::post('/{date}', [AttendanceController::class, 'store'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('store');

    Route::get('/report', [AttendanceReportController::class, 'index'])->name('report');
    Route::get('/report/export', [AttendanceReportController::class, 'export'])->name('report.export');
    
 
});


/*
|--------------------------------------------------------------------------
| Public Leave Form
|--------------------------------------------------------------------------
*/

Route::get('/leave/apply', [LeaveApplicationController::class, 'form'])->name('leave.apply.form');
Route::get('/leave/apply/work-days', [LeaveApplicationController::class, 'workDaysLookup'])->name('leave.apply.workdays');
Route::post('/leave/apply', [LeaveApplicationController::class, 'store'])->name('leave.apply.store');
/*
|--------------------------------------------------------------------------
| HR Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('leave')->name('leave.')->group(function () {
 
    Route::get('/', [LeaveController::class, 'years'])->name('years');
 
    Route::get('/dashboard/requests', [LeaveDashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/requests/{leaveRequest}/approve', [LeaveDashboardController::class, 'approve'])->name('approve');
    Route::post('/dashboard/requests/{leaveRequest}/reject', [LeaveDashboardController::class, 'reject'])->name('reject');
 
    Route::get('/{year}', [LeaveController::class, 'sheet'])
        ->where('year', '\d{4}')
        ->name('sheet');
 
    Route::put('/{year}', [LeaveController::class, 'sheetUpdate'])
        ->where('year', '\d{4}')
        ->name('sheet.update');
    
    Route::post('/{year}/cell', [LeaveController::class, 'updateCell'])
        ->where('year', '\d{4}')
        ->name('cell.update');
 
});

// PUBLIC — outside your auth group, alongside leave.apply.*
Route::get('/overtime/apply', [OvertimeApplicationController::class, 'form'])->name('overtime.apply.form');
Route::post('/overtime/apply', [OvertimeApplicationController::class, 'store'])->name('overtime.apply.store');
 
// AUTHENTICATED — inside your auth group, alongside leave.* / attendance.*
Route::middleware(['auth'])->prefix('overtime')->name('overtime.')->group(function () {
    Route::get('/dashboard/requests', [OvertimeDashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/requests/{overtimeRequest}/approve', [OvertimeDashboardController::class, 'approve'])->name('approve');
    Route::post('/dashboard/requests/{overtimeRequest}/reject', [OvertimeDashboardController::class, 'reject'])->name('reject');
});

// PUBLIC — outside your auth group, alongside leave.apply.* / overtime.apply.*
Route::get('/loan/apply', [LoanApplicationController::class, 'form'])->name('loan.apply.form');
Route::post('/loan/apply', [LoanApplicationController::class, 'store'])->name('loan.apply.store');
 
// AUTHENTICATED — inside your auth group
Route::middleware(['auth'])->prefix('loan')->name('loan.')->group(function () {
    Route::get('/dashboard/requests', [LoanDashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/requests/{loanRequest}/approve', [LoanDashboardController::class, 'approve'])->name('approve');
    Route::post('/dashboard/requests/{loanRequest}/reject', [LoanDashboardController::class, 'reject'])->name('reject');
});


Route::get('/payroll/rules', function () {
    return "RULES WORKING";
});

Route::middleware('auth')->prefix('payroll')->name('payroll.')->group(function () {

    // -------------------------
    // PAYROLL (EMPLOYEE LEVEL)
    // -------------------------
    Route::get('/', [PayrollController::class, 'index'])->name('index');
    Route::get('/create', [PayrollController::class, 'create'])->name('create');
    Route::post('/store', [PayrollController::class, 'store'])->name('store');

    Route::get('/{payroll}', [PayrollController::class, 'show'])->name('show');
    Route::get('/{payroll}/edit', [PayrollController::class, 'edit'])->name('edit');
    Route::put('/{payroll}', [PayrollController::class, 'update'])->name('update');
    Route::delete('/{payroll}', [PayrollController::class, 'destroy'])->name('destroy');

    Route::post('/{payroll}/process', [PayrollController::class, 'process'])->name('process');
    // Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])->name('show');
    Route::get('/payroll/{payroll}/print', [PayrollController::class, 'print'])->name('print');
    Route::get('/payroll/{payroll}/pdf', [PayrollController::class, 'downloadPdf'])->name('pdf');


    // -------------------------
    // PAYROLL RUNS (BATCH LEVEL)
    // -------------------------
    Route::get('/runs/all', [PayrollRunController::class, 'index'])->name('runs.index');
    Route::get('/runs/create', [PayrollRunController::class, 'create'])->name('runs.create');
    Route::post('/runs', [PayrollRunController::class, 'store'])->name('runs.store');

    Route::get('/runs/{run}', [PayrollRunController::class, 'show'])->name('runs.show');

    Route::post('/runs/{run}/generate', [PayrollRunController::class, 'generate'])->name('runs.generate');

    Route::post('/runs/{run}/finalize', [PayrollRunController::class, 'finalize'])->name('runs.finalize');

    Route::get('/runs/{run}/payslips', [PayrollRunController::class, 'payslips'])->name('runs.payslips');

    
    Route::get('/rules',              [PayrollRuleController::class, 'index'])->name('rules.index');
    Route::post('/rules',             [PayrollRuleController::class, 'store'])->name('rules.store');
    Route::patch('/rules/{rule}',     [PayrollRuleController::class, 'update'])->name('rules.update');
    Route::delete('/rules/{rule}',    [PayrollRuleController::class, 'destroy'])->name('rules.destroy');
    Route::post('/rules/seed',        [PayrollRuleController::class, 'seedDefaults'])->name('rules.seedDefaults');

    
    Route::post('/runs/{run}/adjustments',          [PayrollRunController::class, 'storeAdjustment'])->name('runs.adjustments.store');
    Route::delete('/runs/adjustments/{adjustment}', [PayrollRunController::class, 'destroyAdjustment'])->name('runs.adjustments.destroy');
    Route::get(
        '/runs/{run}/adjustments/template',
        [PayrollRunController::class, 'downloadAdjustmentTemplate']
    )->name('runs.adjustments.template');

    Route::post(
        '/runs/{run}/adjustments/import',
        [PayrollRunController::class, 'importAdjustmentsExcel']
    )->name('runs.adjustments.import');

    Route::get(
        '/runs/{run}/adjustments/template/{rule}',
        [PayrollRunController::class, 'downloadAdjustmentTemplate']
    )->name('runs.adjustments.template');

    

    Route::get(
        '/runs/{run}/employee/{payroll}/summary',
        [PayrollRunController::class, 'employeeSummary']
    )->name('runs.employee.summary');



    Route::post('/items/{item}/update-field', [PayrollRunController::class, 'updateField']);

    Route::delete('/items/{item}', [PayrollRunController::class, 'deleteItem']);

    Route::post('/{payroll}/items/store', [PayrollRunController::class, 'storeItem']);


});




