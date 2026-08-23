# ZamPayroll HR Management System Documentation

## 1. Overview

ZamPayroll is a web-based Human Resources and Payroll Management System built with Laravel. It is designed to help a company manage its people, workforce records, attendance, leave, payroll processing, and statutory reporting from a single platform.

The system supports:

- Employee master data management
- Attendance tracking by day and month
- Leave applications and approvals
- Public overtime and loan request workflows
- Payroll run creation and employee pay calculations
- Payslip generation and PDF exports
- Company-level access management and team invitations
- Reports for payroll, attendance, and statutory submissions

This platform is structured for business use, with a company data model that supports multiple companies, users, and employee records while keeping each company’s data isolated.

## 2. What the system does

At a high level, the system helps HR and payroll teams do the following:

- Keep a central record of all employees
- Manage contract, salary, branch, department, and bank information
- Track employee work attendance and working days
- Handle annual, sick, and other leave requests
- Process overtime and loan applications
- Build payroll runs for a period such as a month
- Calculate earnings, deductions, and net pay
- Finalize payroll, generate payslips, and export payroll reports
- Produce reports for internal management or statutory bodies such as NAPSA, NHIMA, ZRA, and WCF/CB

The application is therefore not just an employee directory. It is a complete HR operations and payroll control system designed for daily HR administration and monthly payroll control.

## 3. Core users and roles

The system defines user roles and access boundaries around company ownership.

### 3.1 Super Admin

- Platform-level administrator
- Can manage the broader system
- Not assigned via invitation flow

### 3.2 Company Admin

- Creates or manages a company profile
- Can generate or deactivate a company access code
- Invites staff to the company
- Controls company team membership and role assignment
- Has high-level access to HR and payroll workflows

### 3.3 Manager / HR user

- Can review employee-related requests
- Can approve or reject leave, overtime, and loan requests
- Usually interacts with payroll, attendance, and reports

### 3.4 Employee

- May apply for leave, overtime, and loans
- Can access their own profile information
- Can receive payroll or attendance-related information relevant to them

## 4. System modules

The system is divided into several modules aligned with HR and payroll operations.

### 4.1 Authentication and user management

This module covers:

- User registration
- Login and logout
- Company-linked user accounts
- Invitation-based onboarding
- Role assignment such as company_admin, manager, and employee

Typical flow:

1. A company admin generates an access code for the company.
2. A user registers or joins via invite.
3. The invitation token is accepted and the user is linked to the correct company.
4. The person is assigned a role and profile details.

Relevant files and logic:

- app/Http/Controllers/AuthController.php
- app/Http/Controllers/InvitationController.php
- app/Models/User.php
- app/Models/Company.php

### 4.2 Company and team management

This module manages the organization profile and the people connected to it.

Included functionality:

- Company profile details: name, email, phone, address, TIN
- Company access code generation and deactivation
- Team invites and revocation
- Multi-company isolation using company_id

The company entity owns many employees and users, which prevents data from one organization being seen by another.

### 4.3 Employee master data

This is the employee information system. It stores personal, contractual, and payroll data such as:

- Employee ID
- Names and personal details
- Contact information
- Department, branch, and position
- Employment status
- Hiring dates and contract dates
- Next of kin and emergency contact
- Bank account information
- Salary, leave balances, and statutory IDs

The employee record is the core subject of HR and payroll workflows.

Important model:

- app/Models/Employee.php

Typical employee tasks:

- Add a new employee
- Update salary or contract details
- Upload supporting documents
- Review leave balance and payroll status

### 4.4 Attendance management

The attendance system allows HR teams to maintain daily employee attendance records.

Features include:

- Calendar view by month
- Mark attendance per date
- Statuses such as present, absent, sick, leave, holiday, other, and non-working day
- Time-in and time-out entry
- Hours worked calculations
- CSV import for bulk attendance updates
- Working days and holiday configuration
- Attendance reporting exports

Status examples:

- Present
- Absent
- Sick
- Leave
- Holiday
- Other
- Non-working day

Key files:

- app/Http/Controllers/AttendanceController.php
- app/Http/Controllers/AttendanceSettingsController.php
- app/Http/Controllers/AttendanceReportController.php
- app/Models/Attendance.php

Typical HR usage:

1. Configure the company’s working days and public holidays.
2. Open the attendance calendar for a month.
3. Mark each employee’s status for each workday.
4. Import a CSV file when bulk attendance is available.
5. Generate a report for management or payroll review.

### 4.5 Leave management

The leave module supports employee leave requests and HR review.

Features include:

- Public leave application form
- Leave type options such as annual, sick, maternity, paternity, study, unpaid, and other
- Start date, end date, and return date tracking
- Days calculation
- Approval and rejection workflow
- Leave records per employee and month/year
- Annual leave sheet editing
- Leave balance and amount payable calculation

The system can maintain leave records by year and month and recalculate balances automatically from entries.

Relevant models and files:

- app/Models/LeaveRequest.php
- app/Models/LeaveRecord.php
- app/Http/Controllers/LeaveApplicationController.php
- app/Http/Controllers/LeaveController.php
- app/Http/Controllers/LeaveDashboardController.php

Typical HR process:

1. Employee submits a leave request.
2. HR reviews the request in dashboard.
3. HR approves or rejects with comments.
4. If approved, the leave is recorded and the employee balance is reduced.
5. Payroll and annual leave sheets stay in sync.

### 4.6 Overtime management

This module allows employees to request overtime work and provides an approval workflow.

Features:

- Public overtime request form
- Employee request submission
- Manager / HR review and decision
- Approved overtime can be considered in payroll calculations or reviewed with HR records

Relevant modules:

- app/Http/Controllers/OvertimeApplicationController.php
- app/Http/Controllers/OvertimeDashboardController.php
- app/Models/OvertimeRequest.php

### 4.7 Loan management

The platform includes employee loan request processing and tracking.

Features:

- Public loan application form
- Loan request classification by payment plan
- HR approval or rejection
- Loan ledger and payment tracking
- Deduction schedule calculations linked to payroll

Relevant modules:

- app/Http/Controllers/LoanApplicationController.php
- app/Http/Controllers/LoanDashboardController.php
- app/Http/Controllers/LoanLedgerController.php
- app/Models/LoanRequest.php
- app/Models/Loan.php
- app/Models/LoanPayment.php
- app/Services/LoanRepaymentService.php

How it works:

- An employee applies for a loan.
- HR reviews the application.
- If approved, the loan is recorded in the ledger.
- The repayment schedule is used during payroll generation so deductions are automatically created and posted.

### 4.8 Payroll management

This is the main financial component of the HR system.

Functions include:

- Payroll creation for one employee or as a payroll run for many employees
- Payroll run periods and aliases
- Employee payroll generation for active employees
- Earnings and deduction itemization
- Payroll rule configuration
- Adjustments and imported payroll adjustments
- Gross earnings, deductions, and net pay calculations
- Payslip generation and PDF export
- Finalization of payroll runs

Important components:

- app/Models/PayrollRun.php
- app/Models/Payroll.php
- app/Models/PayrollItem.php
- app/Models/PayrollRule.php
- app/Models/PayrollRunAdjustment.php
- app/Services/PayrollEngine.php
- app/Services/LoanRepaymentService.php

Payroll workflow:

1. HR creates a payroll run for a specific period.
2. The payroll engine prepares employee payroll records.
3. Earnings and deductions are calculated using rules and adjustments.
4. Loan repayments and statutory deductions can be included.
5. Payroll is reviewed and finalized.
6. Payslips are generated and distributed.

### 4.9 Payroll rules and adjustments

Payroll rules define how salary items are calculated.

Examples of logic that can be configured include:

- Basic salary
- Allowances
- Deductions
- Statutory rules
- Tax-related calculations
- Loan repayment deductions

The system supports payroll rule seed defaults and ad hoc adjustments per run.

### 4.10 Reporting and statutory export

The system includes reporting features for payroll and compliance.

Examples:

- Management summary reports by run
- Total earnings, deductions, and net pay reports
- Department or branch-based reporting
- Bank payment listings
- Export to CSV/Excel/PDF
- Statutory reporting for NAPSA, NHIMA, ZRA, and WCF/CB

This makes the platform useful not only for HR administration but also for statutory and payroll compliance.

Relevant files:

- app/Http/Controllers/PayrollReportController.php
- app/Exports/
- app/Imports/

## 5. Main data entities

The system relies on a set of core database entities.

- Company: organization or business entity
- User: login account tied to a company
- Employee: worker record tied to a company
- Attendance: daily attendance status and hours
- Holiday: company or statutory holiday settings
- LeaveRequest: leave application submitted by an employee
- LeaveRecord: aggregated leave data per employee/month/year
- OvertimeRequest: overtime application
- LoanRequest: loan application
- Loan: approved loan account
- LoanPayment: payment against a loan
- PayrollRun: payroll batch for a month or period
- Payroll: employee-level pay record within a payroll run
- PayrollItem: each payroll line item such as basic pay, allowance, or deduction
- PayrollRule: calculation rules
- PayrollRunAdjustment: ad hoc modifications made to a payroll run

## 6. Typical business workflow

A common company workflow looks like this:

1. Company admin creates company and team access.
2. Admin invites people and assigns roles.
3. HR enters employee details into the employee master record.
4. Attendance is marked daily or imported from CSV.
5. Employees submit leave, overtime, or loan requests.
6. HR reviews and decides each request.
7. A payroll run is created for a period.
8. The payroll engine calculates salaries and deductions.
9. Adjustments are reviewed and applied if needed.
10. Payroll is finalized.
11. Payslips are generated and reports are exported.

This makes the application a complete HR and payroll cycle from onboarding to payroll approval and reporting.

## 7. User experience and navigation

The application exposes a dashboard and functional pages for HR and payroll work. The route structure includes pages such as:

- /login and /register
- /dashboard
- /employees
- /attendance
- /leave
- /overtime/apply
- /loan/apply
- /payroll/runs
- /payroll/rules
- /reports
- /profile
- /settings

Public forms are also available for employee self-service requests:

- /leave/apply
- /overtime/apply
- /loan/apply

These public forms allow employees to submit requests without requiring a full internal HR screen workflow.

## 8. Access control and data isolation

The application is designed to isolate each company’s data.

Key rules:

- Each user belongs to a company through company_id
- Each employee belongs to a company
- Many payroll and HR actions are performed within the company context
- Some actions are restricted to company admins
- Role-based checks are used to limit functionality and data access

This reduces the chance of one company seeing another company’s employee, attendance, payroll, or leave records.

## 9. Example HR usage scenario

A typical HR team might do the following in a monthly cycle:

- Review employee records and update any changes
- Record attendance for the month
- Import attendance CSV from a biometric system
- Review employee leave submissions
- Approve or reject overtime and loan applications
- Create a payroll run for the current month
- Generate payroll calculations for all active employees
- Check statutory contributions and deductions
- Finalize payroll and send payslips
- Export reports for finance and management review

This is the practical day-to-day value of the system.

## 10. Technical structure

The project is a Laravel application built using a standard MVC structure.

Major areas:

- app/Models: business data models
- app/Http/Controllers: request handling and business actions
- app/Services: payroll and loan logic
- database/migrations: schema for each feature
- routes/web.php: application navigation and endpoints
- resources/views: front-end pages and dashboards
- public: static assets and generated files

This architecture keeps the application modular and suitable for HR and payroll operations.

## 11. Installation and local usage

To run the application locally in a standard Laravel environment:

1. Install dependencies:
   - composer install
   - npm install
2. Create and configure the environment file:
   - cp .env.example .env
3. Generate the application key:
   - php artisan key:generate
4. Configure database settings in .env
5. Run migrations:
   - php artisan migrate
6. Optionally seed the system if available
7. Start the application:
   - php artisan serve

For frontend assets:

- npm run dev

## 12. Strengths of the system

The current implementation is useful because it combines several HR workflows together into one application:

- Employee management
- Time and attendance
- Leave administration
- Overtime and loan handling
- Payroll computation
- Payslip delivery
- Agency/statutory reporting
- Company-based access control

This makes it suitable for small to medium organizations that want a centralized people-management solution without needing multiple disconnected tools.

## 13. Limitations and future considerations

While the application is quite complete for a first-generation HR system, a production-grade version would usually benefit from:

- More robust role permissions and audit logs
- Notification and email templates for all events
- Bulk employee import validation and history tracking
- More advanced payroll tax rules and jurisdiction support
- Better integrations with biometric attendance devices, bank APIs, and government portals
- More detailed reporting and dashboard analytics

## 14. Step-by-step workflows for common HR tasks

Below are the most practical step-by-step tasks for using the system from a user or HR perspective.

### 14.1 How to add and manage employee information

For HR or an admin user:

1. Log in to the system.
2. Go to the Employees section.
3. Click Add Employee or create a new employee record.
4. Enter the required employee profile information:
   - Employee ID
   - Names and personal details
   - Contact details
   - Department, branch, and position
   - Employment status
   - Contract dates and salary details
   - Bank information and statutory IDs
5. Save the employee record.
6. If needed, upload supporting documents such as ID, contract, or payslip documents.
7. To update employee information later, open the employee profile and edit the relevant fields.
8. To change employment status, update the record to Active, Inactive, or other HR status used by the business.
9. To review payroll and leave details, open the employee profile and verify salary, balances, and statutory fields.

Typical goal: keep a complete employee master record that payroll and HR processes use.

### 14.2 How to onboard a new user and link them to a company

For a Company Admin:

1. Open the company settings or team section.
2. Generate or regenerate the company access code if needed.
3. Invite a person by email.
4. Enter the user role, for example Company Admin, Manager, or Employee.
5. Send the invitation.
6. The invited person receives an invitation link.
7. They accept the invitation and complete the registration or account linking flow.
8. The account is attached to the company through company_id.
9. The user can then log in and access only the features allowed for their company and role.

Typical goal: create a compliant company team and grant role-based access.

### 14.3 Attendance workflow

For HR or a manager:

1. Go to Attendance.
2. Set the company working days and public holidays if they are not yet configured.
3. Open the attendance calendar for a selected month.
4. Click a date to view the day detail screen.
5. Mark each employee as present, absent, sick, leave, holiday, other, or non-working day.
6. Add time-in and time-out if required.
7. Save the day’s attendance.
8. If many records must be entered, use the CSV import option.
9. Import a CSV file containing employee_id, date, status, time_in, and time_out.
10. Review any skipped or invalid rows and correct them.
11. Generate attendance reports for review.
12. Use the report for payroll validation, staffing review, and compliance support.

Typical goal: produce an accurate record of who was present or absent on each workday.

### 14.4 Leave application and approval flow

For an employee:

1. Open the public leave application form.
2. Select the leave type.
3. Enter the start date and end date.
4. Provide the reason and required details.
5. Submit the request.
6. The request appears as pending in the HR leave dashboard.

For HR:

1. Go to the Leave dashboard.
2. View pending leave requests.
3. Open a request to review the date range, type, and employee information.
4. Approve or reject the request.
5. Add a comment if needed.
6. If approved, the system records the leave and updates the employee’s leave balance.
7. The employee receives notification by email if configured.
8. The annual leave sheet can be reviewed and adjusted to reflect actual days taken.

Typical goal: manage employee leave requests, keep balances current, and ensure payroll and HR records match.

### 14.5 Overtime workflow

For an employee:

1. Go to the public Overtime form.
2. Enter the overtime date, hours, and reason.
3. Submit the request.

For HR or a manager:

1. Open the Overtime dashboard.
2. Review all pending overtime requests.
3. Approve or reject each request.
4. Use the decision for payroll review or overtime compliance updates.

Typical goal: capture overtime work and secure approval before payroll processing.

### 14.6 Loan request workflow

For an employee:

1. Open the public Loan application form.
2. Enter the requested amount, payment plan, and reason.
3. Upload supporting documents if required.
4. Submit the loan request.

For HR:

1. Open the Loan dashboard.
2. Review the request and supporting information.
3. Approve or reject the application.
4. If approved, the loan enters the loan ledger.
5. Review payment schedule and repayment plan.
6. During payroll generation, the system can deduct the scheduled repayment automatically.

Typical goal: manage employee borrowing and ensure payroll deductions are correctly applied.

### 14.7 Payroll creation and processing workflow

For HR or payroll staff:

1. Go to Payroll.
2. Create a payroll run for a selected period.
3. Enter the period name or alias for the payroll cycle.
4. Save the run.
5. Open the payroll run details page.
6. Generate payroll for all active employees.
7. Review employee-level payroll entries.
8. Check earnings and deduction items produced by the payroll engine.
9. If needed, add manual adjustments or import adjustment templates.
10. Review totals such as gross income, deductions, and net pay.
11. Finalize the payroll run when acceptable.
12. Generate payslips.
13. Download PDF payslips or email them as needed.

Typical goal: produce the monthly salary output for all active employees correctly and consistently.

### 14.8 Payroll adjustment and rule management

For payroll administrators:

1. Open Payroll Rules.
2. Review or create payroll calculation rules.
3. Update allowances, deductions, and payroll logic as needed.
4. Seed default rules if the system supports that action.
5. Open a payroll run.
6. Add run-specific adjustments for manual overtime, bonus, loan deductions, or special allowances.
7. Import adjustments from a spreadsheet if available.
8. Validate the run totals before finalizing.

Typical goal: enforce standardized payroll formulas while allowing controlled exceptions.

### 14.9 Reports and export workflow

For HR, finance, or payroll staff:

1. Open the Reports section.
2. Choose a payroll run or attendance report.
3. Review summary data such as total earnings, deductions, and net pay.
4. Export PDF, CSV, or Excel output depending on the report type.
5. Use bank payment reports for salary transfers.
6. Use statutory reports for NAPSA, NHIMA, ZRA, and WCF/CB compliance.
7. Store or share exported files with finance and management.

Typical goal: produce management and statutory reporting from the system without manual spreadsheet work.

### 14.10 Settings and company configuration

For a Company Admin:

1. Open Settings.
2. Update company profile information such as name, address, email, phone, and TIN.
3. Configure work days and holiday schedules.
4. Review attendance settings.
5. Generate or regenerate the company access code.
6. Deactivate the access code when no longer needed.
7. Invite new team members and manage existing invitations.
8. Review profile and security settings for the company account.

Typical goal: maintain the company’s operational configuration and access rules.

### 14.11 End-to-end payroll cycle example

A complete monthly workflow could be:

1. Add or update employee records.
2. Invite relevant HR and payroll team members.
3. Configure attendance and holiday settings.
4. Record daily attendance.
5. Receive leave, overtime, and loan applications.
6. Approve or reject each case.
7. Create a payroll run for the period.
8. Generate salaries for active employees.
9. Review payroll rules and adjustments.
10. Finalize the payroll run.
11. Print or email payslips.
12. Export payroll and statutory reports.
13. Keep archived or hidden payroll runs for reference when required.

This is the standard business lifecycle the system is designed to support.

## 15. Summary

ZamPayroll is a Laravel-based HR and payroll management system for companies that need to manage employees, attendance, leave, overtime, loans, and payroll from one platform. It combines HR administration and payroll processing with multi-company access control, user onboarding, and reporting features.

In practical business terms, the system allows an organization to:

- manage employees,
- monitor attendance,
- approve leave and special requests,
- calculate payroll accurately,
- generate payslips,
- produce statutory and management reports,
- and keep HR and payroll operations organized in one place.

This documentation is intended to help a user understand what the system does, which modules exist, and how the platform is meant to be used as an HR Management System.
