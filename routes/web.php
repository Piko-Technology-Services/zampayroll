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

// Partner programme (resellers): public page + application, sign-up links, live code check
Route::get('/partners', [\App\Http\Controllers\Partner\PartnerSignupController::class, 'show'])->name('partners.show');
Route::post('/partners', [\App\Http\Controllers\Partner\PartnerSignupController::class, 'store'])->name('partners.store')->middleware(['guest', 'throttle:6,1']);
Route::get('/join/{code}', [\App\Http\Controllers\Partner\PartnerSignupController::class, 'join'])->name('partners.join');
Route::get('/partner-code/check', [\App\Http\Controllers\Partner\PartnerSignupController::class, 'check'])->name('partners.check')->middleware('throttle:30,1');

// Partner dashboard (partner accounts only)
Route::middleware(['auth', 'partner'])->prefix('partner')->name('partner.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Partner\PartnerDashboardController::class, 'index'])->name('dashboard');
    Route::post('/payout', [\App\Http\Controllers\Partner\PartnerDashboardController::class, 'payout'])->name('payout');
});

Route::middleware('guest')->group(function () {

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/login/choose', [AuthController::class, 'showChooser'])->name('login.choose');
    Route::post('/login/choose', [AuthController::class, 'choose'])->middleware('throttle:10,1');
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

// ZamPayroll admin console (platform administrators only)
Route::middleware(['auth', 'platform'])->prefix('platform')->name('platform.')->group(function () {
    $c = \App\Http\Controllers\Platform\PlatformController::class;
    Route::get('/', [$c, 'dashboard'])->name('dashboard');
    Route::get('/companies', [$c, 'companiesIndex'])->name('companies');
    Route::get('/companies/{company}', [$c, 'companyShow'])->name('companies.show');
    Route::post('/companies/{company}/action', [$c, 'companyAction'])->name('companies.action');
    Route::get('/billing', [$c, 'invoices'])->name('invoices');
    Route::post('/invoices/{invoice}/action', [$c, 'invoiceAction'])->name('invoices.action');
    Route::get('/payment-proofs/{payment}/file', [\App\Http\Controllers\Platform\PaymentProofController::class, 'file'])->name('proofs.file');
    Route::post('/payment-proofs/{payment}/confirm', [\App\Http\Controllers\Platform\PaymentProofController::class, 'confirm'])->name('proofs.confirm');
    Route::post('/payment-proofs/{payment}/decline', [\App\Http\Controllers\Platform\PaymentProofController::class, 'decline'])->name('proofs.decline');
    Route::get('/invoices/{invoice}/pdf', [\App\Http\Controllers\BillingController::class, 'pdf'])->name('invoices.pdf');
    Route::get('/tickets', [$c, 'tickets'])->name('tickets');
    Route::get('/tickets/{ticket}', [$c, 'ticket'])->name('tickets.show');
    Route::post('/tickets/{ticket}', [$c, 'ticketReply'])->name('tickets.reply');
    Route::get('/settings', [$c, 'settings'])->name('settings');
    Route::post('/settings', [$c, 'saveSettings'])->name('settings.save');
    Route::get('/audit', [$c, 'audit'])->name('audit');
    Route::get('/partners', [\App\Http\Controllers\Platform\PartnerAdminController::class, 'index'])->name('partners');
    Route::get('/partners/{partner}', [\App\Http\Controllers\Platform\PartnerAdminController::class, 'show'])->name('partners.show');
    Route::post('/partners/{partner}/action', [\App\Http\Controllers\Platform\PartnerAdminController::class, 'action'])->name('partners.action');
});

// DPO Pay: card popup return + server-to-server notification (verified with DPO, no sign-in needed)
Route::get('/billing/dpo/return', [\App\Http\Controllers\BillingController::class, 'dpoReturn'])->name('billing.dpo.return');
Route::post('/billing/dpo/notify', [\App\Http\Controllers\BillingController::class, 'dpoNotify'])->name('billing.dpo.notify')->middleware('throttle:60,1');


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
 
    // Statutory submissions: review, correct and export (saved edits are used by every download)
    Route::get('/{run}/returns/{kind}', [\App\Http\Controllers\StatutoryReturnController::class, 'preview'])->name('returns.preview');
    Route::post('/{run}/returns/{kind}', [\App\Http\Controllers\StatutoryReturnController::class, 'save'])->name('returns.save');
    Route::post('/{run}/returns/{kind}/reset', [\App\Http\Controllers\StatutoryReturnController::class, 'reset'])->name('returns.reset');
    Route::get('/{run}/returns/{kind}/download', [\App\Http\Controllers\StatutoryReturnController::class, 'download'])->name('returns.download');

    // Statutory submissions (direct downloads)
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

// ZamPayroll subscription & billing (company side)
Route::prefix('settings/billing')->name('billing.')->group(function () {
    Route::get('/', [\App\Http\Controllers\BillingController::class, 'index'])->name('index');
    Route::get('/pay/{plan}', [\App\Http\Controllers\BillingController::class, 'checkoutForm'])->name('checkout.form');
    Route::post('/checkout', [\App\Http\Controllers\BillingController::class, 'checkout'])->name('checkout');
    Route::get('/checkout/confirm/{invoice}', [\App\Http\Controllers\BillingController::class, 'confirmManual'])->name('checkout.confirm');
    Route::post('/invoices/{invoice}/send', [\App\Http\Controllers\BillingController::class, 'sendManual'])->name('invoices.send');
    Route::post('/invoices/{invoice}/proof', [\App\Http\Controllers\BillingController::class, 'submitProof'])->name('invoices.proof')->middleware('throttle:10,1');
    Route::post('/postpaid', [\App\Http\Controllers\BillingController::class, 'postpaid'])->name('postpaid');
    Route::post('/details', [\App\Http\Controllers\BillingController::class, 'details'])->name('details');
    Route::post('/private-workspace', [\App\Http\Controllers\BillingController::class, 'requestPrivate'])->name('private');
    Route::get('/invoices/{invoice}/pdf', [\App\Http\Controllers\BillingController::class, 'pdf'])->name('invoices.pdf');
    Route::get('/payments/{payment}', [\App\Http\Controllers\BillingController::class, 'status'])->name('payments.status');
});

// Help & support tickets (company side)
Route::prefix('help')->name('support.')->group(function () {
    Route::get('/', [\App\Http\Controllers\SupportController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\SupportController::class, 'store'])->name('store');
    Route::get('/{ticket}', [\App\Http\Controllers\SupportController::class, 'show'])->name('show');
    Route::post('/{ticket}/reply', [\App\Http\Controllers\SupportController::class, 'reply'])->name('reply');
    Route::post('/{ticket}/close', [\App\Http\Controllers\SupportController::class, 'close'])->name('close');
});

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
    // Changes employees asked for in the portal (bank details) — HR checks before applying.
    Route::get('/employees/changes', [\App\Http\Controllers\EmployeeChangeController::class, 'index'])->name('employees.changes.index');
    Route::post('/employees/changes/{change}/approve', [\App\Http\Controllers\EmployeeChangeController::class, 'approve'])->name('employees.changes.approve');
    Route::post('/employees/changes/{change}/reject', [\App\Http\Controllers\EmployeeChangeController::class, 'reject'])->name('employees.changes.reject');
    Route::get('/employees/changes/{change}/document', [\App\Http\Controllers\EmployeeChangeController::class, 'document'])->name('employees.changes.document');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Employees
    Route::resource('employees', EmployeeController::class);

    // Others
    Route::get('/departments', [\App\Http\Controllers\DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/departments', [\App\Http\Controllers\DepartmentController::class, 'store'])->name('departments.store');
    Route::put('/departments/{department}', [\App\Http\Controllers\DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/departments/{department}', [\App\Http\Controllers\DepartmentController::class, 'destroy'])->name('departments.destroy');
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
 
    // Shift rota (weekly schedule) and the company's shifts
    Route::get('/rota', [\App\Http\Controllers\ShiftRotaController::class, 'index'])->name('rota');
    Route::post('/rota', [\App\Http\Controllers\ShiftRotaController::class, 'save'])->name('rota.save');
    Route::post('/rota/copy', [\App\Http\Controllers\ShiftRotaController::class, 'copy'])->name('rota.copy');
    Route::get('/shifts', [\App\Http\Controllers\ShiftRotaController::class, 'shifts'])->name('shifts');
    Route::post('/shifts', [\App\Http\Controllers\ShiftRotaController::class, 'storeShift'])->name('shifts.store');
    Route::put('/shifts/{shift}', [\App\Http\Controllers\ShiftRotaController::class, 'updateShift'])->name('shifts.update');
    Route::delete('/shifts/{shift}', [\App\Http\Controllers\ShiftRotaController::class, 'destroyShift'])->name('shifts.destroy');

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
    Route::post('/dashboard/requests/{leaveRequest}/cancel', [LeaveDashboardController::class, 'cancel'])->name('cancel');
    Route::post('/dashboard/record', [LeaveDashboardController::class, 'record'])->name('record');
    Route::get('/dashboard/check', [LeaveDashboardController::class, 'check'])->name('check');

    // Leave types & policy (HR)
    Route::get('/types', [\App\Http\Controllers\LeaveTypeController::class, 'index'])->name('types.index');
    Route::post('/types', [\App\Http\Controllers\LeaveTypeController::class, 'store'])->name('types.store');
    Route::put('/types/{leaveType}', [\App\Http\Controllers\LeaveTypeController::class, 'update'])->name('types.update');
    Route::post('/types/policy', [\App\Http\Controllers\LeaveTypeController::class, 'policy'])->name('types.policy');
 
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
    Route::post('/dashboard/requests/{overtimeRequest}/cancel', [OvertimeDashboardController::class, 'cancel'])->name('cancel');
    Route::post('/dashboard/record', [OvertimeDashboardController::class, 'record'])->name('record');
    Route::get('/dashboard/check', [OvertimeDashboardController::class, 'check'])->name('check');
    Route::get('/report', [OvertimeDashboardController::class, 'report'])->name('report');
    Route::get('/settings', [OvertimeDashboardController::class, 'settings'])->name('settings');
    Route::post('/settings', [OvertimeDashboardController::class, 'saveSettings'])->name('settings.save');
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
    Route::post('/dashboard/requests/{loanRequest}/cancel', [LoanDashboardController::class, 'cancel'])->name('cancel');
    Route::post('/loans/{loan}/disburse', [LoanDashboardController::class, 'disburse'])->name('disburse');
    Route::post('/loans/{loan}/cancel', [LoanDashboardController::class, 'cancelLoan'])->name('loans.cancel');
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
    Route::post('/runs/{run}/unlock', [\App\Http\Controllers\PayrollFlowController::class, 'unlock'])->name('runs.unlock');

    Route::get('/runs/{run}', [\App\Http\Controllers\PayrollFlowController::class, 'show'])->name('runs.show');

    Route::post('/runs/{run}/generate', [PayrollRunController::class, 'generate'])->name('runs.generate');

    Route::post('/runs/{run}/finalize', [PayrollRunController::class, 'finalize'])->name('runs.finalize');

    Route::get('/runs/{run}/payslips', [PayrollRunController::class, 'payslips'])->name('runs.payslips');
    Route::get('/runs/{run}/payslips/pdf', [PayrollRunController::class, 'payslipsPdf'])->name('runs.payslips.pdf');
    Route::get('/runs/{run}/payslips/share', [\App\Http\Controllers\PayslipShareController::class, 'show'])->name('runs.payslips.share');
    Route::post('/runs/{run}/payslips/share', [\App\Http\Controllers\PayslipShareController::class, 'store'])->name('runs.payslips.share.store');

    
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
| COMPLIANCE CENTRE — contracts, policies, documents (permissions: Permissions::ROUTE_MAP)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff'])->prefix('compliance')->name('compliance.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Compliance\OverviewController::class, 'index'])->name('index');
    Route::post('/alerts/{key}/dismiss', [\App\Http\Controllers\Compliance\OverviewController::class, 'dismiss'])->name('alerts.dismiss');

    // Settings: general, letterheads, approval workflows
    Route::get('/settings', [\App\Http\Controllers\Compliance\SettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [\App\Http\Controllers\Compliance\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/letterheads', [\App\Http\Controllers\Compliance\LetterheadController::class, 'store'])->name('letterheads.store');
    Route::post('/letterheads/preview', [\App\Http\Controllers\Compliance\LetterheadController::class, 'preview'])->name('letterheads.preview');
    Route::get('/letterheads/{letterhead}/edit', [\App\Http\Controllers\Compliance\LetterheadController::class, 'edit'])->name('letterheads.edit');
    Route::put('/letterheads/{letterhead}', [\App\Http\Controllers\Compliance\LetterheadController::class, 'update'])->name('letterheads.update');
    Route::post('/letterheads/{letterhead}/default', [\App\Http\Controllers\Compliance\LetterheadController::class, 'makeDefault'])->name('letterheads.default');
    Route::get('/letterheads/{letterhead}/sample', [\App\Http\Controllers\Compliance\LetterheadController::class, 'sample'])->name('letterheads.sample');
    Route::delete('/letterheads/{letterhead}', [\App\Http\Controllers\Compliance\LetterheadController::class, 'destroy'])->name('letterheads.destroy');
    Route::get('/workflows/create', [\App\Http\Controllers\Compliance\WorkflowController::class, 'create'])->name('workflows.create');
    Route::post('/workflows', [\App\Http\Controllers\Compliance\WorkflowController::class, 'store'])->name('workflows.store');
    Route::get('/workflows/{workflow}/edit', [\App\Http\Controllers\Compliance\WorkflowController::class, 'edit'])->name('workflows.edit');
    Route::put('/workflows/{workflow}', [\App\Http\Controllers\Compliance\WorkflowController::class, 'update'])->name('workflows.update');
    Route::delete('/workflows/{workflow}', [\App\Http\Controllers\Compliance\WorkflowController::class, 'destroy'])->name('workflows.destroy');

    // Templates (contract templates need compliance.contracts, policy templates compliance.policies; checked in the controller)
    Route::get('/templates', [\App\Http\Controllers\Compliance\TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/create', [\App\Http\Controllers\Compliance\TemplateController::class, 'create'])->name('templates.create');
    Route::post('/templates', [\App\Http\Controllers\Compliance\TemplateController::class, 'store'])->name('templates.store');
    Route::get('/templates/{template}', [\App\Http\Controllers\Compliance\TemplateController::class, 'show'])->name('templates.show');
    Route::get('/templates/{template}/preview', [\App\Http\Controllers\Compliance\TemplateController::class, 'preview'])->name('templates.preview');
    Route::post('/templates/{template}/duplicate', [\App\Http\Controllers\Compliance\TemplateController::class, 'duplicate'])->name('templates.duplicate');
    Route::get('/templates/{template}/edit', [\App\Http\Controllers\Compliance\TemplateController::class, 'edit'])->name('templates.edit');
    Route::put('/templates/{template}', [\App\Http\Controllers\Compliance\TemplateController::class, 'update'])->name('templates.update');
    Route::post('/templates/{template}/toggle', [\App\Http\Controllers\Compliance\TemplateController::class, 'toggle'])->name('templates.toggle');

    // Employment contracts
    Route::get('/contracts', [\App\Http\Controllers\Compliance\ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [\App\Http\Controllers\Compliance\ContractController::class, 'create'])->name('contracts.create');
    Route::post('/contracts', [\App\Http\Controllers\Compliance\ContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{contract}', [\App\Http\Controllers\Compliance\ContractController::class, 'show'])->name('contracts.show');
    Route::get('/contracts/{contract}/edit', [\App\Http\Controllers\Compliance\ContractController::class, 'edit'])->name('contracts.edit');
    Route::put('/contracts/{contract}', [\App\Http\Controllers\Compliance\ContractController::class, 'update'])->name('contracts.update');
    Route::get('/contracts/{contract}/preview', [\App\Http\Controllers\Compliance\ContractController::class, 'preview'])->name('contracts.preview');
    Route::get('/contracts/{contract}/pdf', [\App\Http\Controllers\Compliance\ContractController::class, 'pdf'])->name('contracts.pdf');
    Route::get('/contracts/{contract}/docx', [\App\Http\Controllers\Compliance\ContractController::class, 'docx'])->name('contracts.docx');
    Route::post('/contracts/{contract}/send', [\App\Http\Controllers\Compliance\ContractController::class, 'send'])->name('contracts.send');
    Route::post('/contracts/{contract}/comment', [\App\Http\Controllers\Compliance\ContractController::class, 'comment'])->name('contracts.comment');
    Route::post('/contracts/{contract}/cancel', [\App\Http\Controllers\Compliance\ContractController::class, 'cancel'])->name('contracts.cancel');
    Route::post('/contracts/{contract}/amend', [\App\Http\Controllers\Compliance\ContractController::class, 'amend'])->name('contracts.amend');

    // Approvals inbox (who may act is decided by each workflow)
    Route::get('/approvals', [\App\Http\Controllers\Compliance\ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('/approvals/{approval}', [\App\Http\Controllers\Compliance\ApprovalController::class, 'show'])->name('approvals.show');
    Route::get('/approvals/{approval}/document', [\App\Http\Controllers\Compliance\ApprovalController::class, 'document'])->name('approvals.document');
    Route::post('/approvals/{approval}', [\App\Http\Controllers\Compliance\ApprovalController::class, 'decide'])->name('approvals.decide');

    // Policies
    Route::get('/policies', [\App\Http\Controllers\Compliance\PolicyController::class, 'index'])->name('policies.index');
    Route::get('/policies/library', [\App\Http\Controllers\Compliance\PolicyController::class, 'library'])->name('policies.library');
    Route::post('/policies', [\App\Http\Controllers\Compliance\PolicyController::class, 'store'])->name('policies.store');
    Route::get('/policies/{policy}', [\App\Http\Controllers\Compliance\PolicyController::class, 'show'])->name('policies.show');
    Route::get('/policies/{policy}/edit', [\App\Http\Controllers\Compliance\PolicyController::class, 'edit'])->name('policies.edit');
    Route::put('/policies/{policy}', [\App\Http\Controllers\Compliance\PolicyController::class, 'update'])->name('policies.update');
    Route::get('/policies/{policy}/preview', [\App\Http\Controllers\Compliance\PolicyController::class, 'preview'])->name('policies.preview');
    Route::get('/policies/{policy}/pdf', [\App\Http\Controllers\Compliance\PolicyController::class, 'pdf'])->name('policies.pdf');
    Route::get('/policies/{policy}/docx', [\App\Http\Controllers\Compliance\PolicyController::class, 'docx'])->name('policies.docx');
    Route::post('/policies/{policy}/submit', [\App\Http\Controllers\Compliance\PolicyController::class, 'submit'])->name('policies.submit');
    Route::post('/policies/{policy}/revise', [\App\Http\Controllers\Compliance\PolicyController::class, 'revise'])->name('policies.revise');
    Route::post('/policies/{policy}/archive', [\App\Http\Controllers\Compliance\PolicyController::class, 'archive'])->name('policies.archive');
    Route::get('/acknowledgements', [\App\Http\Controllers\Compliance\PolicyController::class, 'acknowledgements'])->name('acknowledgements');

    // Document repository
    Route::get('/documents', [\App\Http\Controllers\Compliance\DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/upload', [\App\Http\Controllers\Compliance\DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [\App\Http\Controllers\Compliance\DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/register', [\App\Http\Controllers\Compliance\DocumentController::class, 'register'])->name('documents.register');
    Route::get('/documents/{document}', [\App\Http\Controllers\Compliance\DocumentController::class, 'show'])->name('documents.show');
    Route::put('/documents/{document}', [\App\Http\Controllers\Compliance\DocumentController::class, 'update'])->name('documents.update');
    Route::post('/documents/{document}/replace', [\App\Http\Controllers\Compliance\DocumentController::class, 'replace'])->name('documents.replace');
    Route::get('/documents/{document}/download/{version?}', [\App\Http\Controllers\Compliance\DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview/{version?}', [\App\Http\Controllers\Compliance\DocumentController::class, 'preview'])->name('documents.preview');
    Route::post('/documents/{document}/archive', [\App\Http\Controllers\Compliance\DocumentController::class, 'archive'])->name('documents.archive');
    Route::post('/documents/{document}/restore', [\App\Http\Controllers\Compliance\DocumentController::class, 'restore'])->name('documents.restore');
    Route::post('/categories', [\App\Http\Controllers\Compliance\DocumentController::class, 'storeCategory'])->name('categories.store');
    Route::delete('/categories/{category}', [\App\Http\Controllers\Compliance\DocumentController::class, 'destroyCategory'])->name('categories.destroy');

    // Reports
    Route::get('/reports', [\App\Http\Controllers\Compliance\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [\App\Http\Controllers\Compliance\ReportController::class, 'export'])->name('reports.export');
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
    Route::post('/profile/bank', [PortalController::class, 'requestBankChange'])->middleware('throttle:5,1')->name('profile.bank');
    Route::delete('/profile/changes/{change}', [PortalController::class, 'cancelChange'])->name('profile.changes.cancel');

    // Attendance
    Route::get('/attendance', [PortalController::class, 'attendance'])->name('attendance');

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
    // My employment documents & company policies
    Route::get('/documents', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/contracts/{contract}', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'contract'])->name('documents.contract');
    Route::get('/documents/contracts/{contract}/pdf', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'contractPdf'])->name('documents.contract.pdf');
    Route::post('/documents/contracts/{contract}/sign', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'sign'])->middleware('throttle:10,1')->name('documents.contract.sign');
    Route::post('/documents/contracts/{contract}/decline', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'decline'])->name('documents.contract.decline');
    Route::post('/documents/contracts/{contract}/comment', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'comment'])->middleware('throttle:10,1')->name('documents.contract.comment');
    Route::get('/documents/policies/{policy}', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'policy'])->name('documents.policy');
    Route::get('/documents/policies/{policy}/pdf', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'policyPdf'])->name('documents.policy.pdf');
    Route::post('/documents/policies/{policy}/acknowledge', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'acknowledge'])->name('documents.policy.acknowledge');
    Route::get('/documents/files/{document}', [\App\Http\Controllers\Portal\PortalDocumentController::class, 'file'])->name('documents.file');
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
    Route::post('/employee-access', [\App\Http\Controllers\TeamController::class, 'grantEmployee'])->name('employee-access.store');
    Route::post('/roles', [\App\Http\Controllers\TeamController::class, 'storeRole'])->name('roles.store');
    Route::put('/roles', [\App\Http\Controllers\TeamController::class, 'updateRoles'])->name('roles.update');
    Route::delete('/roles/{role}', [\App\Http\Controllers\TeamController::class, 'destroyRole'])->name('roles.destroy');
});
