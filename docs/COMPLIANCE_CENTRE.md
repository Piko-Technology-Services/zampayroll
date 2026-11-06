# ZamPayroll Compliance Centre: technical summary

Built 6 November 2026. Laravel 12, PHP 8.2+, MySQL. Integrated into the existing tenancy, roles, portal, audit log and mail setup; no duplicate employee, company, auth or notification systems.

## 1. What is implemented

| Area | Status |
|---|---|
| Compliance Centre in the sidebar and as a module with tabs: Overview, Contracts, Templates, Policies (library, management, acknowledgements), Documents, Approvals, Reports, Settings (letterheads, workflows, defaults) | Done |
| Letterheads: generated from the company profile (name, logo, address, phone, email, website, TPIN, PACRA, NAPSA), or uploaded header and footer (PNG/JPG; PDF converted when `pdftoppm` exists). Margins, header and footer heights, logo position, font, size, accent colour, page numbers. PDF preview before saving; several letterheads per company with a default | Done |
| Contract templates: 6 ZamPayroll masters (permanent, fixed-term, probationary, internship, casual, temporary); read-only masters, company copies with version history, activate/deactivate, create new, import a Word .docx | Done |
| Contract generation: placeholders filled from employee and company records; missing fields highlighted and required before sending; rich-text editing; drafts; letterhead PDF preview; editable Word (.docx) download; PDF download | Done |
| Digital signing in the employee portal ("My documents"): read, review copy download, ask questions, decline with reason, sign by drawing (mouse/touch/stylus) or uploading a photo (background removed) | Done |
| Signature evidence: signed-in account, employee id, contract id, version, SHA-256 of the exact text, method, time, IP, user agent, typed full name, consent statement. The employee must sign the same version and fingerprint they were shown | Done |
| Configurable sequential approval workflows per company: steps to a named person or a role; review, approve or sign for the company; optional explicit delegate per step; per contract type. Contract workflows must end with the company signature | Done |
| Approver inbox: view, review copy, approve, reject, request changes (reason required), comment, sign. Every action audited | Done |
| Returns and amendments: rejection or change request goes back to HR; editing creates a new version; employee signs again; approvals restart. Executed contracts are locked; amendments are new contracts that supersede the old one when executed | Done |
| Execution: final PDF on the letterhead with both signatures, approval record, reference, version and content fingerprint; SHA-256 of the PDF stored and checked on every download; filed in the repository against the employee; emailed with attachment to the employee and the people who acted | Done |
| Policy library: 18 templates with legal status (7 "Required by law" with the provision quoted, the rest "Recommended practice"), purpose and legal basis | Done |
| Policy lifecycle: generate with company details and letterhead, edit, add clauses, Word/PDF, approval (same engine) or direct publish when the company has no policy workflow, publish, revisions with required reason, previous versions kept, optional fresh acknowledgement | Done |
| Portal "Company policies & notices": read, download, acknowledge (version + fingerprint + statement); acknowledgement matrix for HR | Done |
| Document repository: multi-file and ZIP upload (drag and drop, progress), categories and sub-categories (defaults plus custom), full metadata, links to employee, department, branch, requirement; search and filters; list and grid; secure inline preview (PDF and images); versions with replace; archive and restore; access levels; register export (Excel, CSV, PDF) | Done |
| Expiry and review reminders: configurable intervals (default 90, 30, 7, 0 days) for document expiry and review, policy review and fixed-term contract end; sent once per interval; dashboard alerts | Done |
| Compliance overview: live indicators that link to filtered lists; alerts can be hidden per user until their number changes; no "compliant or non-compliant" labels | Done |
| Reports: 19 contract, policy and repository reports, export to Excel, CSV and PDF | Done |
| Email notifications: issued, signed, awaiting approval, final signature needed, changes or rejection, executed and distributed, questions and replies, policy published or updated, policy awaiting approval, reminders. Every attempt logged as sent or failed | Done |

## 2. Database (migration `2026_11_06_000001_create_compliance_centre_tables`)

All tenant tables carry `company_id` and use the existing `BelongsToCompany` scope. Master templates have `company_id = NULL`.

- `compliance_settings` (one row per company: disclaimer, reminder days, retention years, document defaults)
- `company_letterheads` (soft deletes)
- `document_templates` (contracts and policies; masters and company copies; soft deletes) and `document_template_versions`
- `employment_contracts` (soft deletes), `employment_contract_versions`, `contract_signatures`, `compliance_comments`
- `approval_workflows` (soft deletes), `approval_workflow_steps`, `approval_requests` (shared by contracts and policies)
- `company_policies` (soft deletes), `company_policy_versions`, `policy_acknowledgements` (unique per policy, version and employee)
- `document_categories`, `company_documents` (soft deletes), `company_document_versions`
- `compliance_reminder_logs` (unique per subject, kind, date and interval), `compliance_mail_logs`, `compliance_alert_dismissals`
- Audit trail reuses the existing `audit_logs` (actions `compliance.*`).
- The migration also grants the new permissions to the standard HR, Payroll, Manager and Viewer roles and loads the master templates.

## 3. Routes and controllers

Staff (`/compliance`, `compliance.*`): `OverviewController`, `SettingsController`, `LetterheadController`, `WorkflowController`, `TemplateController`, `ContractController`, `ApprovalController`, `PolicyController`, `DocumentController`, `ReportController` (all in `app/Http/Controllers/Compliance`).

Portal (`/portal/documents`, `portal.documents.*`): `Portal\PortalDocumentController`.

## 4. Models and services

Models in `app/Models/Compliance`. Services in `app/Services/Compliance`:
`TemplateLibrary` (master texts and versioning), `Placeholders`, `HtmlSanitizer`, `Vault` (private storage), `Letterheads`, `DocumentPdf`, `DocxWriter`, `DocxImport`, `SignatureImage`, `Approvals` (workflow engine), `Contracts`, `Policies`, `DocumentAccess`, `Overview`, `ComplianceMailer`, `TableExport`.

## 5. Permissions (Settings › Team & roles, group "Compliance Centre")

| Permission | Covers |
|---|---|
| `compliance.view` | Overview, read contracts and policies, reports and exports, acknowledgements |
| `compliance.contracts` | Create, edit, send, cancel and amend contracts; contract templates |
| `compliance.policies` | Create, edit, submit, revise and archive policies; policy templates |
| `compliance.documents.view` | Open and download repository documents the user's access allows |
| `compliance.documents` | Upload, replace, archive, restore, set access, categories |
| `compliance.settings` | Letterheads, approval workflows, defaults |

Approvals are decided by each workflow (assigned person, role or configured delegate), not by a permission. Employees act only in the portal, on their own documents.

## 6. Email templates

`resources/views/emails/compliance.blade.php` (branded layout), sent by `App\Mail\ComplianceMail`.

## 7. File storage

Private disk `local` (`storage/app/private`) under `compliance/{company_id}/{contracts|signatures|letterheads|documents}` with random file names. Files are served only through controllers that check the company and the user's access. Each serve also checks the SHA-256, MIME type and content type, and sends `nosniff`, a sandbox CSP and `no-store`. Uploads are validated by extension and by actual content (PDF, DOC, DOCX, XLS, XLSX, JPG, PNG; ZIP expanded in memory, names reduced to basenames, unsupported entries skipped, size limits). Limit: 20 MB per file.

## 8. Packages

None added. Word files are written and read with PHP's ZipArchive. PDFs use the existing `barryvdh/laravel-dompdf`. Excel uses the existing PhpSpreadsheet. PHP extensions used: `zip`, `gd`, `fileinfo`, `dom`.

## 9. Scheduled jobs and queues

- `compliance:reminders` runs daily at 07:10 (`routes/console.php`). The server needs the Laravel scheduler (`* * * * * php artisan schedule:run`).
- `compliance:sync-templates` adds or updates master templates after a ZamPayroll release (never touches company copies).
- Emails are sent synchronously with logging, like the rest of the app. To queue them later, set `QUEUE_CONNECTION` and make `ComplianceMail` `ShouldQueue`.

## 10. Environment and server

- No new variables. Real email needs `MAIL_*` configured (today `MAIL_MAILER=log` in local).
- Optional: install poppler (`pdftoppm`) to accept PDF letterheads. Without it, users are asked for PNG or JPG.

## 11. Deployment

```
php artisan migrate
php artisan tenancy:migrate      # dedicated workspaces
php artisan view:clear && php artisan route:clear && php artisan config:clear
```

## 12. Tests

`tests/Feature/ComplianceCentreTest.php`, four end-to-end tests with 271 assertions:

- **Contract workflow:**
  - letterhead created and previewed
  - workflow rules enforced
  - probationary contract generated from records
  - missing fields block sending
  - script tags stripped
  - Word file valid and editable
  - sent, viewed (not treated as acceptance), refused to other employees
  - wrong fingerprint, wrong name and missing consent rejected
  - drawn signature
  - out-of-order and wrong-role approvals blocked
  - changes requested, then a new version with version 1 preserved
  - re-signed with an uploaded photo (background removed)
  - CEO signature required, then executed
  - PDF fingerprint stored and verified, tampering detected (HTTP 410)
  - emails with attachment and delivery log
  - filed to the employee
  - full audit history
  - locked against edits; amendment created
  - another company gets 404
- **Policy workflow:**
  - legal labels
  - generate, edit, Word and PDF
  - approval, then employees cannot see it until it is published
  - publish, notifications, acknowledgement with fingerprint
  - HR matrix
  - revision with required reason; old version stays active until approval
  - fresh acknowledgement requested; old acknowledgement kept
- **Repository:**
  - uploads with metadata
  - search, tag, employee and expiry filters
  - inline preview with security headers
  - Word download-only
  - replace keeps version 1
  - renamed and executable files rejected
  - ZIP path traversal neutralised and PHP skipped
  - reminders sent once
  - manager, employee and other-tenant access checks
  - archive keeps the file
  - register export to CSV and Excel
- **Templates, overview, reports, roles:**
  - masters read-only; copies versioned and private
  - Word import
  - master-update notice
  - overview counts and alert hiding
  - all 19 reports render, with CSV and PDF exports
  - role restrictions
  - legal minimums enforced in settings

Full suite: 76 tests, 1,414 assertions, all passing.

## 13. Known limitations and next steps

- **Legal review:** templates are marked "Not yet legally reviewed". "Required by law" is used only for Employment Code Act s.95(1) (code of conduct, grievance procedure, harassment, performance management, HIV and AIDS, health and wellness) and the Occupational Health and Safety Act No. 16 of 2025 s.14 (health and safety policy). The 2025 OHS Act was read from a law-firm summary, not the gazetted text. Have a Zambian employment lawyer review every template, then set `review_status = reviewed` and `reviewed_on`.
- **Electronic signatures:** the evidence captured is designed with the Electronic Communications and Transactions Act in mind, but the process has not been certified. It is not an advanced or qualified electronic signature with a certificate authority.
- **Malware scanning:** not included. Hook a scanner such as ClamAV into `DocumentController::validateBytes` if the server allows.
- **Previews:** Word and Excel files are download-only.
- **Uploaded letterheads:** a PDF letterhead is used as an image of its first page.
- **Delegation:** a fixed delegate per step only. No date-ranged "out of office" delegation.
- **In-app notifications:** shown as dashboard counts and badges (sidebar Compliance Centre badge, portal "Waiting for you"). There is no separate notification feed.
- **Existing employee uploads:** these remain on the public disk as before. Consider moving them to the private vault.
- **Retention:** `retention_years` is stored and enforced as a minimum of 5 (Employment Code Act s.23(3)), but no automatic purge runs. Nothing is ever deleted automatically.
