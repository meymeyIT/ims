# Inventory Management System (IMS)

A web-based inventory management system for retail shops, supermarkets, warehouses,
coffee shops, restaurants and small/medium businesses. It manages products, suppliers,
purchase orders, stock in / stock out, low-stock alerts and business reports, with a
complete, tamper-proof history of every stock change.

Built with **Laravel 12**, **MySQL 8 / MariaDB 10.4+** and **Bootstrap 5**.

---

## Contents

1. [Features](#features)
2. [Technology stack](#technology-stack)
3. [System requirements](#system-requirements)
4. [Installation (local development)](#installation-local-development)
5. [Database configuration](#database-configuration)
6. [Migrations and seeders](#migrations-and-seeders)
7. [Development commands](#development-commands)
8. [Default roles](#default-roles)
9. [Modules](#modules)
10. [How stock is kept correct](#how-stock-is-kept-correct)
11. [Testing](#testing)
12. [Deployment](#deployment)
13. [Troubleshooting](#troubleshooting)
14. [Project structure](#project-structure)

Further documentation: [`docs/DATABASE.md`](docs/DATABASE.md) (ERD and schema rules) ·
[`docs/SECURITY.md`](docs/SECURITY.md) (security checklist).

---

## Features

- **Authentication**: login with username or email, logout, forgot / reset password, profile, change password, rate-limited sign-in.
- **Users & roles**: 4 roles (Admin, Manager, Staff, Viewer) with 32 editable permissions; create, edit, deactivate and delete users.
- **Dashboard**: 10 live statistic cards, 5 charts (stock in/out, categories, purchase trend, top products, low stock), recent movements, purchases, activities and low-stock alerts.
- **Catalogue**: categories, units, products (image upload, SKU, barcode, prices, minimum stock, search, filters, sorting, archive/restore) and suppliers (with purchase history).
- **Purchases**: multi-product purchase orders with discount and tax, draft → pending → approved → (partially) received / cancelled, printable purchase order.
- **Receiving**: full or partial deliveries; receiving more than ordered or receiving twice is impossible.
- **Stock In / Stock Out**: manual stock in (opening stock, restock, customer returns), stock out (sold, damaged, expired, internal use, returned to supplier, other).
- **Stock history**: complete ledger with filters, detail page, CSV export and print; inventory adjustments (physical count) and reversals (compensating movements).
- **Low-stock notifications**: automatic, duplicate-free alerts, notification bell, alert history, restock suggestions and one-click purchase orders.
- **Reports**: 10 reports with date / product / category / supplier / status filters, pagination, totals, **Excel**, **PDF** and **print**.
- **Inventory valuation**: weighted average cost.
- **Activity logs**: who did what, when and from which IP address (including failed sign-ins).
- **System settings**: business name, logo, address, phone, email, currency (USD / KHR), exchange rate, date format, notification options, document number prefixes.

## Technology stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+ (8.3 recommended), Laravel 12, Eloquent ORM, Form Requests, Policies, Middleware |
| Frontend | Blade, Bootstrap 5.3, Bootstrap Icons, vanilla JavaScript, Chart.js 4, SweetAlert2, Vite |
| Database | MySQL 8 (production target) or MariaDB 10.4+ (XAMPP) |
| Exports | Laravel Excel (`maatwebsite/excel`), DomPDF (`barryvdh/laravel-dompdf`) |
| Tests | PHPUnit 11 (Laravel feature tests), 130+ tests |

## System requirements

- **PHP 8.2 or newer** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `curl`, `zip`, **`gd`**, **`intl`**, `bcmath`, `dom`, `iconv`
- **Composer 2**
- **Node.js 20.19+ or 22 LTS** and npm
- **MySQL 8** or **MariaDB 10.4+**
- Git

> **XAMPP users:** `gd` and `intl` are disabled by default. Open `C:\xampp\php\php.ini`,
> remove the `;` in front of `extension=gd` and `extension=intl`, then restart Apache and
> open a new terminal. Check with `php -m | findstr /i "gd intl"`.

## Installation (local development)

```bash
# 1. Get the code
git clone <your-repository-url> ims
cd ims

# 2. PHP and JavaScript dependencies
composer install
npm install

# 3. Environment file
cp .env.example .env            # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate

# 4. Edit .env: database, admin account, timezone (see below)

# 5. Database tables + roles, units, settings and the first admin
php artisan migrate --seed

# 6. Public link for product images and the logo
php artisan storage:link

# 7. Build the frontend and start the server
npm run build
php artisan serve
```

Open <http://127.0.0.1:8000> and sign in with the admin account (see the next sections).

### Timezone

Dates and document numbers (e.g. `PO-20261116-0001`) use the application timezone.
Open `config/app.php` and make sure the timezone line reads:

```php
'timezone' => env('APP_TIMEZONE', 'UTC'),
```

then set `APP_TIMEZONE=Asia/Phnom_Penh` in `.env`.
Check it with `php artisan about` (look for **Timezone**).

## Database configuration

1. Create two databases (the second one is only for automated tests):

   ```sql
   CREATE DATABASE ims_db      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE ims_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Set the connection in `.env`:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ims_db
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   Passwords with special characters must be quoted: `DB_PASSWORD="my#pass"`.
   In production use a dedicated database user, not `root` (see [Deployment](#deployment)).

3. The test database connection is configured in `phpunit.xml` (`ims_db_test`, user `root`, empty password). Change it there if your MySQL user is different.

## Migrations and seeders

| Command | What it does |
|---|---|
| `php artisan migrate` | Creates / updates all tables |
| `php artisan migrate --seed` | Same + seeds roles, permissions, units, settings and the first admin |
| `php artisan db:seed` | Seeds only (safe to run again: nothing is duplicated) |
| `php artisan db:seed --class=DemoUserSeeder` | **Local testing only**: creates `manager`, `staff`, `viewer` accounts (refuses to run in production) |
| `php artisan migrate:fresh --seed` | ⚠️ Deletes ALL data and rebuilds the database |

**First admin account** comes from `.env`:

```env
ADMIN_NAME="System Administrator"
ADMIN_USERNAME=admin
ADMIN_EMAIL=admin@ims.local
ADMIN_PASSWORD=          # leave empty: a strong random password is generated and shown ONCE
```

Change the password after the first sign-in (top-right menu → **My Profile**).
If you lose it:

```bash
php artisan tinker --execute "App\Models\User::where('username', 'admin')->first()->update(['password' => 'NewPassword123']);"
```

## Development commands

| Command | Purpose |
|---|---|
| `php artisan serve` | Development web server on <http://127.0.0.1:8000> |
| `npm run dev` | Vite with hot reload (use during frontend work) |
| `npm run build` | Production build of CSS/JS (run after pulling changes) |
| `php artisan test` | Run all automated tests |
| `php artisan schedule:work` | Run scheduled tasks locally (daily low-stock check) |
| `php artisan ims:check-low-stock` | Run the low-stock check now |
| `php artisan optimize:clear` | Clear all caches after changing `.env` or config |
| `php artisan about` | Show Laravel version, environment, drivers, timezone |

## Default roles

Permissions can be changed by an Admin in **System → Roles** (except the Admin role, which always has everything).

| Role | Can do |
|---|---|
| **Admin** | Everything: users, roles & permissions, settings, activity logs, all modules and reports |
| **Manager** | Products, categories, units, suppliers; create / approve / receive / cancel purchases; stock in, stock out, adjustments, reversals; low-stock alerts; all reports with export |
| **Staff** | Dashboard, view products, stock out, stock history, low-stock alerts. **Stock in only when an Admin grants the `stock.in` permission** |
| **Viewer** | Read only: dashboard, products and stock balances, product / stock / valuation reports (no export, no purchase reports) |

Hiding a button is never the protection: every page and action checks the permission on the server.

## Modules

| # | Module | Main pages |
|---|---|---|
| 1 | Authentication & user management | Login, forgot/reset password, profile, Users, Roles |
| 2 | Dashboard | Dashboard |
| 3 | Category management | Categories |
| 4 | Unit management | Units |
| 5 | Product management | Products (list, add, edit, details) |
| 6 | Supplier management | Suppliers (list, add, edit, details + purchase history) |
| 7 | Purchase management | All Purchases, Create Purchase, Purchase details, Receive, Print |
| 8 | Stock in | Stock In (history + new) |
| 9 | Stock out | Stock Out (history + new) |
| 10 | Stock movement history | Stock History, movement details, adjustments, reversals, export, print |
| 11 | Low-stock notifications | Notification bell, Low Stock Alerts |
| 12 | Reports & analytics | 10 reports with Excel / PDF / print |
| 13 | Activity logs | Activity Logs |
| 14 | System settings | Settings |

## How stock is kept correct

- **One gateway**: `App\Services\StockService` is the only code that changes `products.current_stock`. The product form cannot set stock.
- **Ledger**: every change writes one `stock_movements` row (previous stock, new stock, signed quantity, cost, user, reference). Rows can never be edited or deleted; mistakes are fixed with a **reversal** (a compensating movement). The sum of a product's movements always equals its current stock.
- **Transactions + row locks**: each change runs in a database transaction and locks the product row (`SELECT … FOR UPDATE`), so two users changing the same product are processed one after the other. A failure saves nothing.
- **Database guarantees**: CHECK constraints (no negative stock, no over-receiving, `new = previous + quantity`) and unique indexes (one movement per receipt line, one reversal per movement).
- **No double submissions**: receive, stock-in, stock-out and adjustment forms carry a one-time token.
- **Weighted average cost**: recalculated on every stock in:
  `new average = (stock × average + quantity in × unit cost) ÷ (stock + quantity in)`.
  Stock out does not change it. Inventory value = stock × average cost.

## Testing

Tests run against the separate `ims_db_test` database, never your real data.

```bash
php artisan test                         # everything
php artisan test --filter=ReportsTest    # one test class
php artisan test > test-results.txt      # save the output to a file
```

| Test class | Covers |
|---|---|
| `AuthAndUserManagementTest` | Spec #1, #2, #6: login, invalid login, rate limiting, roles, user management |
| `CatalogTest` | Spec #3, #4, #5: products, duplicate SKU, categories, units, suppliers, uploads |
| `PurchaseAndStockInTest` | Spec #7, #10, #11, #12: stock in, receiving, partial and duplicate receiving, rollback |
| `StockOutAndHistoryTest` | Spec #8, #9, #15: stock out, overselling, reversals, adjustments, history export |
| `LowStockAndDashboardTest` | Spec #13, #14: low-stock alerts, no duplicates, dashboard figures |
| `ReportsTest` | Spec #14, #15: report values, valuation, exports, permissions |
| `SettingsActivityAndSecurityTest` | Settings, activity logs, security headers, route protection, password hashing |
| `StockConcurrencyTest` | Concurrency (row locking with a second DB connection) and rollback |

All 15 test cases listed in the specification are covered.

## Deployment

Example for a Linux server (Ubuntu) with Nginx, PHP-FPM 8.3 and MySQL 8.
For Windows servers the same steps apply with IIS/Apache and Windows Task Scheduler.

### 1. Server software

```bash
sudo apt install nginx mysql-server php8.3-fpm php8.3-{mysql,mbstring,xml,curl,zip,gd,intl,bcmath} composer git
# Node.js 22 LTS for building the frontend (or build locally and upload public/build)
```

### 2. Database and a dedicated user

```sql
CREATE DATABASE ims_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ims_app'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON ims_db.* TO 'ims_app'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Application

```bash
cd /var/www
git clone <your-repository-url> ims && cd ims
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
# edit .env (see below)
php artisan migrate --seed --force
php artisan storage:link
php artisan optimize            # caches config, routes, views and events
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

Production `.env` essentials:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://inventory.example.com
APP_TIMEZONE=Asia/Phnom_Penh
DB_USERNAME=ims_app
DB_PASSWORD=a-long-random-password
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
LOG_LEVEL=warning
MAIL_MAILER=smtp               # plus MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD
```

### 4. Web server

Point the web root to the **`public`** folder (never to the project root). Minimal Nginx site:

```nginx
server {
    listen 80;
    server_name inventory.example.com;
    root /var/www/ims/public;
    index index.php;
    client_max_body_size 5M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```

Enable HTTPS (e.g. `sudo certbot --nginx`). The app then also sends the HSTS header.

### 5. Scheduler (daily low-stock check)

```bash
# crontab -e   (as www-data)
* * * * * cd /var/www/ims && php artisan schedule:run >> /dev/null 2>&1
```

Windows: create a Task Scheduler task that runs `php D:\path\to\ims\artisan schedule:run` every minute.

### 6. Backups

Back up the database daily (`mysqldump ims_db > ims-$(date +%F).sql`) and the folder
`storage/app/public` (product images and logo). Test restoring a backup regularly.

### Updating

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

## Troubleshooting

| Problem | Solution |
|---|---|
| `Access denied for user ... (1045)` | Wrong DB password in `.env`; run `php artisan config:clear` after changing it |
| `Access denied ... to database (1044)` | The DB user has no rights on the database: `GRANT ALL PRIVILEGES ON ims_db.* ...` |
| `composer require pkg:^3.1` installs the wrong version on Windows | Windows removes `^`. Use `composer require pkg` (no version) or quote it: `"pkg:^3.1"` |
| Composer: `requires ext-gd` | Enable `extension=gd` in `php.ini` (see requirements) |
| `This password does not use the Bcrypt algorithm` | A password was typed directly into the database. Reset it with the tinker command above |
| Product images / logo not shown | Run `php artisan storage:link` |
| `Vite manifest not found` | Run `npm run build` |
| Low-stock alerts never arrive by email | Set `MAIL_*` in `.env` and enable "email alerts" in Settings |
| Pages are slow with `php artisan serve` | Normal: it handles one request at a time. Use Apache/Nginx in production |
| Khmer text looks wrong in PDF exports | DomPDF cannot shape Khmer script; use Excel or Print for Khmer data |

## Project structure

```
app/
  Console/Commands/      ims:check-low-stock
  Enums/                 PurchaseStatus, MovementType, StockOutReason
  Exports/               ReportExport (Excel)
  Http/Controllers/      one controller per module (+ Auth/)
  Http/Middleware/       EnsureUserIsActive, SecurityHeaders
  Http/Requests/         Form Request validation classes
  Models/                Eloquent models
  Notifications/         LowStockNotification
  Policies/              UserPolicy
  Reports/               Report base class + 10 report definitions
  Rules/                 PhoneNumber
  Services/              StockService, PurchaseService, LowStockMonitor, DashboardStats,
                         DocumentNumber, ActivityLogger
  Support/               Currency, Quantity, Decimal, Branding
database/
  migrations/  seeders/  factories/
docs/                    DATABASE.md (ERD), SECURITY.md
resources/
  views/                 Blade pages per module, layouts, partials, components
  js/                    app.js, purchase-form.js, dashboard.js
  css/                   app.css (Bootstrap theme)
routes/                  web.php, auth.php, console.php (scheduler)
tests/Feature/           automated tests
```
