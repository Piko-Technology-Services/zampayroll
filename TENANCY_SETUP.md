# Multi-tenancy, onboarding & self-service — setup notes

## 1. After pulling these files
```bash
php artisan migrate          # adds company_id everywhere + back-fills existing data, tenancy columns, portal fields
php artisan optimize:clear
php artisan tenancy:list     # see every company, its mode and database
```
Existing users are marked "email confirmed" by the migration, so nobody is locked out.
All existing data is assigned to its company (via the employee it belongs to); data with no
owner goes to the company with the most employees. Other existing companies get the default
Zambian payroll rules.

## 2. .env keys (already appended to your .env)
```
TENANCY_CENTRAL_DOMAIN=localhost      # zampayroll.com in production
TENANCY_SHARED_EMPLOYEE_LIMIT=10
TENANCY_DB_PREFIX=zp_
TENANCY_AUTO_PROVISION=true           # false on hosting where MySQL users can't CREATE DATABASE
```
Email confirmation and reset links need real mail: set `MAIL_MAILER=smtp` (it is `log` now,
so links land in storage/logs/laravel.log while testing locally).

## 3. How tenancy works
| Team size at sign-up | Where it lives | URL |
|---|---|---|
| 1 – 10 | main database, rows isolated by `company_id` | zampayroll.com |
| 11 + | its own database `zp_{subdomain}` | {subdomain}.zampayroll.com |

* `App\Models\Concerns\BelongsToCompany` filters every query and fills `company_id` on create.
  Route-model binding therefore 404s on another company's records.
* `IdentifyTenant` middleware reads the sub-domain and switches the DB connection before the
  session starts (sessions, cache, reset tokens are per workspace).
* People can always sign in / reset passwords on the main domain — they are handed over to their
  workspace automatically (`tenant_user_directory` table).
* Queued jobs remember their tenant. Run one worker for everyone: `php artisan queue:work`.

## 4. Sub-domains
* **Local (XAMPP / artisan serve):** `acme.localhost` resolves to 127.0.0.1 in Chrome. Safari/others:
  add `127.0.0.1 acme.localhost` to /etc/hosts. Apache needs `ServerAlias *.localhost` on the vhost.
* **Production:** wildcard DNS `*.zampayroll.com` → server, wildcard sub-domain pointing at the same
  `public/` folder, and a wildcard SSL certificate.

## 5. Dedicated workspace commands
```bash
php artisan tenancy:provision acme                   # create/migrate DB, activate (manual mode)
php artisan tenancy:provision acme --database=u2152_acme   # use a DB you created in hPanel
php artisan tenancy:migrate                          # run NEW migrations on every dedicated DB — do this after every deploy
```

## 6. Employee self-service portal
HR: Employees → open a file → **Create portal access** (or **Create portals (n)** on the list for
everyone with an email). The employee gets a branded email with a temporary password and how-to
guide; they must choose their own password on first sign-in, then land on `/portal`.
Requests made there appear in the existing Leave / Overtime / Loan request dashboards.

## 7. AI mode (floating assistant)
AI mode runs in **basic mode** by default: built-in rules on your own server, no external API calls and no cost.
To switch to Claude later (paid per message), add to `.env`:
```
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=your-key
```
Nothing the assistant proposes (adding employees, approving requests, preparing payroll, creating portals) happens until the user presses **Confirm** on the card.

## 8. Company codes, roles, languages and the website (Oct 2026)
- `php artisan migrate` creates company codes for every existing company and the default roles
  (Administrator, HR Officer, Payroll Officer, Manager, Viewer). See the code in **Settings → Team & roles**
  and share it with your staff — sign-in on the main domain is now *company code + email + password*.
  Dedicated sub-domains don't need the code.
- Administrators edit what each role can do (and create custom roles) on the same page.
- Language switcher (Google Translate) is on every page. Text is only sent to Google after someone picks a language other than English.
- Public website: `/`, `/features`, `/ai-assistant`, `/pricing`, `/terms`, `/privacy`, `/sitemap.xml`.
  Submit `https://zampayroll.com/sitemap.xml` in Google Search Console.
- Optional `.env` for the website:
```
SITE_URL=https://zampayroll.com
SOCIAL_FACEBOOK=https://www.facebook.com/yourpage
SOCIAL_INSTAGRAM=https://www.instagram.com/yourhandle
SOCIAL_TIKTOK=https://www.tiktok.com/@yourhandle
SOCIAL_LINKEDIN=https://www.linkedin.com/company/yourpage
SITE_WHATSAPP=260776136965
SITE_DEMO_VIDEO=https://www.youtube.com/embed/VIDEO_ID
```
