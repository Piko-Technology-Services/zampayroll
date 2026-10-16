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
use App\Http\Controllers\BankPaymentTemplateController;
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
use App\Http\Controllers\LoanLedgerController;

use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\WorkspaceHandoffController;
use App\Http\Controllers\EmployeePortalAccessController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalRequestController;

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

// Public website pages (SEO)
Route::get('/features', [\App\Http\Controllers\SiteController::class, 'features'])->name('site.features');
Route::get('/ai-assistant', [\App\Http\Controllers\SiteController::class, 'ai'])->name('site.ai');
Route::get('/pricing', [\App\Http\Controllers\SiteController::class, 'pricing'])->name('site.pricing');
Route::get('/terms', [\App\Http\Controllers\SiteController::class, 'terms'])->name('site.terms');
Route::get('/privacy', [\App\Http\Controllers\SiteController::class, 'privacy'])->name('site.privacy');
Route::get('/sitemap.xml', [\App\Http\Controllers\SiteController::class, 'sitemap'])->name('site.sitemap');
Route::post('/demo-request', [DemoRequestController::class, 'store'])->name('demo.request.store');

Route::middleware('guest')->group(function () {

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/company-code', [AuthController::class, 'showCodeReminder'])->name('company-code.request');
    Route::post('/company-code', [AuthController::class, 'sendCodeReminder'])->name('company-code.send')->middleware('throttle:5,1');

    // Interactive multi-step registration wizard
    Route::get('/register', [RegistrationController::class, 'show'])->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/register/check-email', [RegistrationController::class, 'checkEmail'])->name('register.check-email')->middleware('throttle:60,1');
    Route::get('/register/check-subdomain', [RegistrationController::class, 'checkSubdomain'])->name('register.check-subdomain')->middleware('throttle:60,1');

    // Forgot / reset password (email link)
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email')->middleware('throttle:6,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.store');
});

// Email confirmation — reachable without being signed in (the link is signed).
Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:12,1'])->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:4,1')->name('verification.send');

// One-time signed hop from the main domain into a dedicated workspace.
Route::get('/auth/handoff/{user}', WorkspaceHandoffController::class)
    ->middleware(['signed'])->name('workspace.handoff');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');


/*
|--------------------------------------------------------------------------
| HR / ADMIN AREA — signed-in staff only (employees use /portal)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff'])->group(function () {

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
    
    Route::get('/{run}/bank-payments/preview', [PayrollReportController::class, 'bankPaymentsPreview'])->name('bank_payments.preview');

 
});

Route::post('/payroll/{payroll}/email', [PayslipMailController::class, 'sendSingle'])
    ->name('payroll.email.single');
 
Route::post('/payroll/runs/{run}/email-all', [PayslipMailController::class, 'sendBulk'])
    ->name('payroll.email.bulk');

Route::get('/settings/payroll-approvals', [\App\Http\Controllers\PayrollApprovalSettingsController::class, 'index'])->name('payroll-approvals.index');
Route::put('/settings/payroll-approvals', [\App\Http\Controllers\PayrollApprovalSettingsController::class, 'update'])->name('payroll-approvals.update');

Route::prefix('payroll/runs')->name('payroll.runs.')->group(function () {
 
    // ... your existing routes (index, create, store, show, generate,
    //     finalize, adjustments, etc.) stay exactly as they are ...
 
    Route::get('/hidden', [PayrollRunController::class, 'hiddenIndex'])->name('hidden');
    Route::get('/trash', [PayrollRunController::class, 'trashIndex'])->name('trash');
 
    Route::post('/{run}/hide', [PayrollRunController::class, 'hide'])->name('hide');
    Route::post('/{run}/unhide', [PayrollRunController::class, 'unhide'])->name('unhide');
 
    // "Delete" on a run card = move to trash (soft delete), not permanent.
    Route::delete('/{run}/trash', [PayrollRunController::class, 'trash'])->name('trash.store');
 
    // Trashed rows are excluded from default route-model binding, so these
    // two use a plain {id} + onlyTrashed()->findOrFail() in the controller.
    Route::post('/{id}/restore', [PayrollRunController::class, 'restore'])->name('restore');
    Route::delete('/{id}/force-delete', [PayrollRunController::class, 'forceDelete'])->name('force-delete');
 
});



/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (LOGGED IN USERS ONLY)
|--------------------------------------------------------------------------
*/
Route::group([], function () { // (inside staff area)

    // ✅ STATIC ROUTES FIRST
    Route::get('/employees/sample-csv', [EmployeeController::class, 'downloadSampleCsv'])
        ->name('employees.sample-csv');

    Route::post('/employees/import', [EmployeeController::class, 'import'])
        ->name('employees.import');

    // "Bring your team" bulk importer
    Route::get('/employees/import', [\App\Http\Controllers\EmployeeImportController::class, 'index'])->name('employees.import.wizard');
    Route::get('/employees/import/template', [\App\Http\Controllers\EmployeeImportController::class, 'template'])->name('employees.import.template');
    Route::post('/employees/import/parse', [\App\Http\Controllers\EmployeeImportController::class, 'parse'])->name('employees.import.parse');
    Route::post('/employees/import/preview', [\App\Http\Controllers\EmployeeImportController::class, 'preview'])->name('employees.import.preview');
    Route::post('/employees/import/commit', [\App\Http\Controllers\EmployeeImportController::class, 'commit'])->name('employees.import.commit');

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

});



Route::group([], function () { // (inside staff area)

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

        Route::get('/settings/company', [CompanyController::class, 'edit'])->name('company.settings');
        Route::put('/settings/company', [CompanyController::class, 'update'])->name('company.settings.update');
        Route::post('/settings/company/access-code/generate', [CompanyController::class, 'regenerateAccessCode'])->name('company.settings.access-code.generate');
        Route::post('/settings/company/access-code/deactivate', [CompanyController::class, 'deactivateAccessCode'])->name('company.settings.access-code.deactivate');

        // Bank payment template management (Company Settings)
        Route::get('/settings/bank-templates', [BankPaymentTemplateController::class, 'index'])->name('bank-templates.index');
        Route::post('/settings/bank-templates/preset', [BankPaymentTemplateController::class, 'storePreset'])->name('bank-templates.preset');
        Route::post('/settings/bank-templates/custom/parse', [BankPaymentTemplateController::class, 'parseCustomUpload'])->name('bank-templates.custom.parse');
        Route::post('/settings/bank-templates/custom', [BankPaymentTemplateController::class, 'storeCustom'])->name('bank-templates.custom');
        Route::post('/settings/bank-templates/{template}/default', [BankPaymentTemplateController::class, 'setDefault'])->name('bank-templates.default');
        Route::delete('/settings/bank-templates/{template}', [BankPaymentTemplateController::class, 'destroy'])->name('bank-templates.destroy');


        Route::post('/team/invite', [InvitationController::class, 'store'])->name('invitations.store');
        Route::delete('/team/invite/{invitation}', [InvitationController::class, 'revoke'])->name('invitations.revoke');
    });
});


Route::middleware(['auth', 'staff'])->prefix('attendance')->name('attendance.')->group(function () {
 
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


}); // end staff area (part 1)

// Public invitation acceptance flow (token-protected, not auth-gated —
// a not-yet-registered invitee has no account to authenticate with).
Route::get('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
Route::post('/invitations/{token}/complete', [InvitationController::class, 'complete'])->name('invitations.complete');

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

Route::middleware(['auth', 'staff'])->prefix('leave')->name('leave.')->group(function () {
 
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
Route::middleware(['auth', 'staff'])->prefix('overtime')->name('overtime.')->group(function () {
    Route::get('/dashboard/requests', [OvertimeDashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/requests/{overtimeRequest}/approve', [OvertimeDashboardController::class, 'approve'])->name('approve');
    Route::post('/dashboard/requests/{overtimeRequest}/reject', [OvertimeDashboardController::class, 'reject'])->name('reject');
    Route::get(
    '/dashboard/export-approved',
        [OvertimeDashboardController::class, 'exportApprovedOvertime']
    )->name('export.approved');
});

// PUBLIC — outside your auth group, alongside leave.apply.* / overtime.apply.*
Route::get('/loan/apply', [LoanApplicationController::class, 'form'])->name('loan.apply.form');
Route::post('/loan/apply', [LoanApplicationController::class, 'store'])->name('loan.apply.store');
 
// AUTHENTICATED — inside your auth group
Route::middleware(['auth', 'staff'])->prefix('loan')->name('loan.')->group(function () {
    Route::get('/dashboard/requests', [LoanDashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/requests/{loanRequest}/approve', [LoanDashboardController::class, 'approve'])->name('approve');
    Route::post('/dashboard/requests/{loanRequest}/reject', [LoanDashboardController::class, 'reject'])->name('reject');
});
Route::middleware(['auth', 'staff'])->prefix('loan/ledger')->name('loan.ledger.')->group(function () {
    Route::get('/', [LoanLedgerController::class, 'index'])->name('index');
    Route::get('/create', [LoanLedgerController::class, 'create'])->name('create');
    Route::post('/', [LoanLedgerController::class, 'store'])->name('store');
    Route::get('/{loan}', [LoanLedgerController::class, 'show'])->name('show');
    Route::put('/{loan}', [LoanLedgerController::class, 'updatePlan'])->name('update');
    Route::post('/{loan}/payments', [LoanLedgerController::class, 'recordPayment'])->name('payments.store');
});


Route::middleware(['auth', 'staff'])->prefix('payroll')->name('payroll.')->group(function () {

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
    // Guided payroll: start → check → submit → approval chain → locked → paid
    Route::get('/runs/all', [\App\Http\Controllers\PayrollFlowController::class, 'index'])->name('runs.index');
    Route::get('/runs/create', [\App\Http\Controllers\PayrollFlowController::class, 'create'])->name('runs.create');
    Route::post('/runs', [\App\Http\Controllers\PayrollFlowController::class, 'store'])->name('runs.store');
    Route::get('/runs/{run}/editor', [PayrollRunController::class, 'show'])->name('runs.editor');
    Route::post('/runs/{run}/submit', [\App\Http\Controllers\PayrollFlowController::class, 'submit'])->name('runs.submit');
    Route::post('/runs/{run}/approve', [\App\Http\Controllers\PayrollFlowController::class, 'approve'])->name('runs.approve');
    Route::post('/runs/{run}/send-back', [\App\Http\Controllers\PayrollFlowController::class, 'sendBack'])->name('runs.sendBack');
    Route::post('/runs/{run}/recall', [\App\Http\Controllers\PayrollFlowController::class, 'recall'])->name('runs.recall');
    Route::post('/runs/{run}/paid', [\App\Http\Controllers\PayrollFlowController::class, 'markPaid'])->name('runs.paid');
    Route::post('/runs/{run}/overtime', [\App\Http\Controllers\PayrollFlowController::class, 'overtime'])->name('runs.overtime');

    Route::get('/runs/{run}', [\App\Http\Controllers\PayrollFlowController::class, 'show'])->name('runs.show');

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





/*
|--------------------------------------------------------------------------
| EMPLOYEE SELF-SERVICE — HR creates / resends / revokes portal access
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff'])->group(function () {
    Route::post('/employees/portal/bulk', [EmployeePortalAccessController::class, 'bulk'])->name('employees.portal.bulk');
    Route::post('/employees/{employee}/portal', [EmployeePortalAccessController::class, 'store'])->name('employees.portal.store');
    Route::post('/employees/{employee}/portal/resend', [EmployeePortalAccessController::class, 'resend'])->name('employees.portal.resend');
    Route::delete('/employees/{employee}/portal', [EmployeePortalAccessController::class, 'destroy'])->name('employees.portal.destroy');
});

/*
|--------------------------------------------------------------------------
| EMPLOYEE SELF-SERVICE PORTAL
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'employee', 'force.password'])->prefix('portal')->name('portal.')->group(function () {

    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');

    // First-login / any-time password change
    Route::get('/password', [PortalController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [PortalController::class, 'updatePassword'])->name('password.update');

    // Profile
    Route::get('/profile', [PortalController::class, 'profile'])->name('profile');
    Route::put('/profile', [PortalController::class, 'updateProfile'])->name('profile.update');

    // Payslips
    Route::get('/payslips', [PortalController::class, 'payslips'])->name('payslips');
    Route::get('/payslips/{payroll}', [PortalController::class, 'showPayslip'])->name('payslips.show');
    Route::get('/payslips/{payroll}/download', [PortalController::class, 'downloadPayslip'])->name('payslips.download');

    // Leave
    Route::get('/leave', [PortalRequestController::class, 'leave'])->name('leave');
    Route::get('/leave/calculate', [PortalRequestController::class, 'calculateLeave'])->name('leave.calculate');
    Route::post('/leave', [PortalRequestController::class, 'storeLeave'])->name('leave.store');
    Route::delete('/leave/{leaveRequest}', [PortalRequestController::class, 'cancelLeave'])->name('leave.cancel');

    // Overtime
    Route::get('/overtime', [PortalRequestController::class, 'overtime'])->name('overtime');
    Route::post('/overtime', [PortalRequestController::class, 'storeOvertime'])->name('overtime.store');
    Route::delete('/overtime/{overtimeRequest}', [PortalRequestController::class, 'cancelOvertime'])->name('overtime.cancel');

    // Loans
    Route::get('/loans', [PortalRequestController::class, 'loans'])->name('loans');
    Route::post('/loans', [PortalRequestController::class, 'storeLoan'])->name('loans.store');
    Route::delete('/loans/{loanRequest}', [PortalRequestController::class, 'cancelLoan'])->name('loans.cancel');
});

/*
|--------------------------------------------------------------------------
| AI MODE — floating assistant (HR / admin users)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff', 'throttle:40,1'])->prefix('ai')->name('ai.')->group(function () {
    Route::post('/message', [\App\Http\Controllers\AiAssistantController::class, 'message'])->name('message');
    Route::post('/actions/{token}/confirm', [\App\Http\Controllers\AiAssistantController::class, 'confirm'])->name('confirm');
    Route::post('/actions/{token}/cancel', [\App\Http\Controllers\AiAssistantController::class, 'cancel'])->name('cancel');
    Route::post('/reset', [\App\Http\Controllers\AiAssistantController::class, 'reset'])->name('reset');
});

/*
|--------------------------------------------------------------------------
| TEAM & ROLES (Settings) — needs the "Manage team" permission
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff'])->prefix('settings/team')->name('team.')->group(function () {
    Route::get('/', [\App\Http\Controllers\TeamController::class, 'index'])->name('index');
    Route::put('/members/{user}', [\App\Http\Controllers\TeamController::class, 'updateMember'])->name('members.update');
    Route::delete('/members/{user}', [\App\Http\Controllers\TeamController::class, 'removeMember'])->name('members.destroy');
    Route::post('/roles', [\App\Http\Controllers\TeamController::class, 'storeRole'])->name('roles.store');
    Route::put('/roles', [\App\Http\Controllers\TeamController::class, 'updateRoles'])->name('roles.update');
    Route::delete('/roles/{role}', [\App\Http\Controllers\TeamController::class, 'destroyRole'])->name('roles.destroy');
});
