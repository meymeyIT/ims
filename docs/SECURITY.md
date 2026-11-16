# IMS Security Checklist

Status of every security requirement in the project specification, where it is
implemented, and how it is verified. "Test" refers to `tests/Feature/*`.

| Requirement | How it is implemented | Verified by |
|---|---|---|
| **Authentication** | Session login with username or email (`LoginRequest`), logout invalidates the session and regenerates the CSRF token, session ID regenerated on login (prevents session fixation). No public registration. | `AuthAndUserManagementTest` |
| **Brute-force protection / rate limiting** | 5 failed logins per username+IP → lockout. Throttles on password reset (6/min), password change (6/min), stock posts (30/min), exports (10/min), settings (20/min). | `test_login_is_rate_limited_after_five_failed_attempts` |
| **Role-based authorization** | 4 roles × 32 permissions. Every route uses `auth` + `can:<permission>` or a Policy (`UserPolicy`). Admin role always passes. | `test_every_application_route_requires_login`, permission tests in every phase |
| **Never rely on hidden buttons** | Sidebar/buttons are hidden for convenience only; the same permission is checked on the server for each request. | `test_staff_cannot_access_unauthorized_pages`, `test_viewer_cannot_create_users`, … |
| **Deactivated / deleted users** | `EnsureUserIsActive` logs a deactivated user out on the next request; soft-deleted users cannot log in. | `test_deactivated_user_is_logged_out_on_next_request` |
| **CSRF protection** | Laravel `VerifyCsrfToken` on every POST/PUT/PATCH/DELETE; `@csrf` in every form; axios sends the token for AJAX. | Laravel default |
| **Server-side validation** | Form Request classes for every write (users, products, purchases, stock in/out, adjustments, settings, …). Business rules (no negative stock, no over-receiving) are re-checked inside the database transaction. | Validation tests in every phase |
| **Password hashing** | bcrypt via the `hashed` cast; passwords and remember tokens are `$hidden`; strong password rule (8+ chars, upper/lower case, number). | `test_passwords_are_hashed_and_never_exposed` |
| **SQL injection** | Eloquent / query builder with bound parameters everywhere. Raw SQL fragments are fixed strings (no user input); sort columns use a whitelist. | Code review |
| **XSS** | Blade `{{ }}` escapes all output. The only `{!! !!}` uses are `nl2br(e(...))` (escaped first). JSON for charts is embedded with `@json` (escapes `<`, `>`, `&`, quotes). | Code review |
| **Secure file uploads** | Images only (real content checked, not just the extension), size and dimension limits, random file names, stored outside `public/` code paths. SVG refused (can contain scripts). | `test_non_image_upload_is_rejected`, `test_logo_can_be_uploaded_and_svg_is_refused` |
| **Spreadsheet formula injection** | Excel export stores every text cell as text; CSV export prefixes `= + - @`. | `test_excel_export_has_headings_totals_and_never_creates_formulas`, `test_export_downloads_csv_with_filtered_rows` |
| **Mass assignment** | Explicit `$fillable` on every model; `current_stock`, `average_cost`, purchase status etc. are not fillable. | `test_stock_cannot_be_set_through_the_product_form` |
| **Data integrity / concurrency** | Stock changes only through `StockService`: DB transaction + `SELECT … FOR UPDATE` row lock + CHECK constraints + unique indexes (no duplicate receiving, single reversal). | Phase 5–6 tests |
| **Audit trail** | `activity_logs` records logins, failed / locked sign-ins, user/product/purchase/stock/settings changes with user, IP and user agent. Read-only in the app. Passwords are never logged. | `test_failed_sign_in_is_logged_without_the_password` |
| **Security headers** | `SecurityHeaders` middleware: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, and HSTS when served over HTTPS. | `test_security_headers_are_sent` |
| **Secure sessions** | Database sessions, HttpOnly cookies (Laravel default), `SameSite=Lax`. Production settings below. | Configuration |
| **No sensitive data exposure** | Notification links are redirected only to internal paths; users can open only their own notifications. | `test_user_cannot_open_someone_elses_notification` |

## Production `.env` (required before going live)

```env
APP_ENV=production
APP_DEBUG=false                # never show stack traces to users
APP_URL=https://your-domain    # serve over HTTPS
SESSION_SECURE_COOKIE=true     # cookie only sent over HTTPS
SESSION_ENCRYPT=true
LOG_LEVEL=warning
```

Also: use a dedicated database user with rights only on the IMS database (not `root`),
run `php artisan config:cache route:cache view:cache`, and keep `.env` out of Git (already in `.gitignore`).

## Known limitations / recommendations

- A Content-Security-Policy header is not set yet (Vite's dev server and the small inline page scripts would need nonces). Recommended as a later hardening step.
- PDF export cannot shape Khmer script (DomPDF limitation); use Excel or Print for Khmer text.
- Search inputs use `LIKE` with bound parameters; `%` and `_` typed by a user act as wildcards (harmless, but searches may match more).
