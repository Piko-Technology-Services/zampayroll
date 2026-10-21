# ZamPayroll: deploying the October 2026 update

This update adds subscriptions and billing (DPO Pay), the ZamPayroll admin console, support tickets,
email-only sign-in, the "paste anything" employee import, and new leave, overtime, loan and
self-service workflows. Every change is in this repository; nothing needs to be installed from Composer or npm.

---

## 1. Before you start

1. **Back up the database** (and every dedicated tenant database):
   `mysqldump -u root -p zampayrolldb > backup-$(date +%F).sql`
2. Put the site in maintenance mode on production: `php artisan down`

## 2. Database

```bash
php artisan migrate            # main (shared) database
php artisan tenancy:migrate    # every private-workspace (dedicated) database
php artisan optimize:clear
```

New migrations, in order:

| Migration | What it does |
|---|---|
| `2026_10_21_000001_create_audit_logs_table` | Audit trail for billing, leave, overtime, loans, bank changes, settings |
| `2026_10_21_000002_create_billing_and_support_tables` | Plans, trial dates, invoices, DPO payments, support tickets, platform settings. **Existing companies get a fresh 7-day trial.** |
| `2026_10_22_000001_leave_types_and_workflow` | Company leave types, manager → HR approval, cancellation kept on record, exact balance given back on cancel. Leave already approved is marked as having taken its days, so cancelling it returns them. |
| `2026_10_22_000002_overtime_breaks_rates_and_payroll_link` | Breaks, overnight shifts, company rates, link to the payroll that paid each claim |
| `2026_10_22_000003_loan_approval_and_disbursement` | Separate approval and payout steps. Loans already on the ledger are treated as paid out on their start date (they keep deducting). The Payroll Officer role gets "Record loan payouts". |
| `2026_10_22_000004_create_employee_change_requests_table` | Bank-detail changes from the portal that HR approves |

All migrations are additive. Nothing is dropped, and finalised payrolls are not touched.
`php artisan migrate:rollback --step=6` undoes them if needed (data in the new columns and tables is lost).

## 3. `.env`

Add these yourself. **Never paste the live keys into chat, email or a ticket.**

```dotenv
# DPO Pay (live)
DPO_COMPANY_TOKEN=
DPO_SERVICE_TYPE=
DPO_MODE=live
# DPO_FAKE must be absent or false in production (it is ignored when APP_ENV=production anyway)

# What appears on invoices and billing emails
BILLING_COMPANY_NAME="ZamPayroll"
BILLING_COMPANY_ADDRESS=
BILLING_TPIN=
BILLING_SUPPORT_EMAIL=
```

Optional DPO mobile-money network codes, only if DPO gives you different ones:
`DPO_MNO_AIRTEL`, `DPO_MNO_MTN`, `DPO_MNO_ZAMTEL`.

Mail must be configured (`MAIL_*`). Invoices, receipts, reminders, trial and leave/overtime/loan emails all use it.

## 4. Your ZamPayroll admin account

```bash
php artisan zampayroll:admin you@yourdomain.com --name="Your Name"
```

It asks for a password (or generates one). Sign in on the main domain and you land on **/platform**:
companies, subscriptions, invoices, payments, tickets, the audit log and settings.

## 5. Scheduler (billing, reminders, trial countdown)

Add one cron entry on the server:

```cron
* * * * * cd /path/to/zampayroll && php artisan schedule:run >> /dev/null 2>&1
```

`billing:run` runs daily at 06:30. It issues Business (post-paid) invoices and renewal invoices,
sends trial/overdue reminders, moves unpaid accounts to read-only, and re-checks pending DPO payments.
You can run it by hand: `php artisan billing:run`.

## 6. DPO dashboard

1. Copy the **notify URL** shown on /platform → Settings (it is `https://<your-domain>/billing/dpo/notify`)
   into your DPO merchant settings.
2. Make sure the site is served over HTTPS. DPO's return page (`/billing/dpo/return`) opens in the card popup.

## 7. Prices and plans

Open /platform → Settings and set the real prices. The current values are placeholders:
Starter K350/month or K3,500/year (up to 10 employees, pre-paid); Business K35 per active employee
per month with a K500 minimum (post-paid); VAT 0%; invoices due in 7 days; 7 days' grace.

## 8. After deploying: quick checks

- [ ] Sign in with email and password only (no company code).
- [ ] Settings → Billing shows the trial countdown. Pay a small test invoice with mobile money.
- [ ] /platform loads for your admin account and shows the company.
- [ ] People → Import a list: paste a WhatsApp-style list and check it is understood.
- [ ] Leave → Leave types & rules: set your day limits and approval setting.
- [ ] Payroll → Overtime → Rates & rules: set your rates.
- [ ] Settings → Team & roles: decide who may **Record loan payouts** (Payroll Officer has it by default).
- [ ] `php artisan up`

## 9. Tests

The feature tests (41 tests covering billing, tenancy, the import wizard, leave, overtime, loans, the portal
and payroll) were run against a MariaDB test database with two demo companies. They use MySQL features,
so the default `phpunit.xml` (SQLite in memory) can't run them. To run them yourself, point a copy of
`phpunit.xml` at an **empty test database** (never production), migrate it, add the demo data, then:

```bash
php vendor/bin/phpunit -c phpunit.mysql.xml
```

---

## What changed for users (summary)

- **Sign-in:** email and password only. If the same email belongs to several workspaces, a picker appears.
- **Billing:** 7-day free trial with a live countdown. Starter is pre-paid; Business (over 10 employees)
  is post-paid per active employee. Customers pay by mobile money (an in-app prompt to the phone) or
  card (DPO popup). PDF invoices and receipts are emailed. Unpaid accounts become read-only, never deleted.
- **Private workspace:** Business customers can ask for their own sub-domain. You approve it in /platform
  and the data is moved and verified automatically.
- **Import:** messy pasted text or Excel of any layout.
- **Leave:** company leave types and rules, balance checks, manager → HR approval, cancellation that returns
  balance, leave sheet and attendance.
- **Overtime:** breaks, overnight shifts, company rates, corrected-hours approval, paid once in payroll,
  late approvals roll into the next month, monthly report.
- **Loans:** approve → pay out → deduct. No deductions before payout, one repayment per month.
- **Portal:** my attendance, contact updates (audited), bank changes that HR approves (the employee gets a
  security email).

## Known limitations / next steps

- Unpaid leave is recorded and shown on attendance but is **not yet deducted from pay automatically**.
  Add an unpaid-leave deduction in the payroll adjustments for now.
- ZRA Smart Invoice (VSDC) is not connected. ZamPayroll's own invoices are plain tax invoices until it is.
- Statutory day counts for leave are deliberately not built in. Each company sets its own on the
  Leave types page.
- DPO card payments open DPO's secure popup (card details never touch ZamPayroll). Mobile money stays in-app.
