# RentDesk

Equipment rental management built with Laravel: inventory with serial-tracked units and QR labels, availability and booking without double-booking, quotes, contracts and invoices (PDF), check-out and check-in with inspections and signatures, deposits, late fees, maintenance, stock transfers, kits, reports, a public booking-request form, a web installer and English and Bengali translations.

## Requirements

- PHP 8.3 or newer with the extensions `openssl`, `pdo`, `pdo_mysql` (or `pdo_sqlite`), `mbstring`, `tokenizer`, `xml`, `dom`, `ctype`, `json` and `fileinfo`; `gd` is recommended for images in PDFs. The installer checks all of them.
- MySQL 8 / MariaDB 10.6+ (recommended for production) or SQLite (evaluation only, see below)
- Write access to `storage/`, `bootstrap/cache/` and the project folder (the installer writes `.env`)
- A cron entry for the scheduler

The CodeCanyon download already contains `vendor/` and `public/build/`, so Composer and Node.js are only needed to work on the code.

## Installation

The full buyer's guide is `documentation/index.html` in the download. In short:

1. Create an empty MySQL/MariaDB database.
2. Upload the files. Either point the domain's document root at `public/`, or, on shared hosting, upload everything into `public_html`: the `.htaccess` in the project folder passes every request to `public/`.
3. Open the site. Until the app is installed, every page redirects to `/install`, where you check the server requirements, enter the database settings (the connection is tested before anything is saved, then the tables are created) and create the administrator account, business name and currency, optionally with sample data.

The installer writes `.env` (with `APP_DEBUG=false`) and creates `storage/app/installed.lock`; after that `/install` returns 404. Run `php artisan storage:link` if the installer could not.

From a git checkout instead of the download:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build          # only after changing CSS/JS; public/build is committed
```

### Scheduler

Late fees, overdue reminders and maintenance tasks run from the scheduler. Add one cron entry:

```
* * * * * cd /path/to/rentdesk && php artisan schedule:run >> /dev/null 2>&1
```

| Time (UTC, the app timezone) | Command | Purpose |
| --- | --- | --- |
| 01:00 | `maintenance:generate-tasks` | Creates tasks from maintenance plans |
| 06:00 | `rentals:calculate-late-fees` | Updates late fees of overdue rentals |
| 08:00 | `rentals:send-overdue-reminders` | Emails customers with overdue rentals |

No queue worker is needed; nothing is queued.

### Mail

Set `MAIL_*` in `.env` to send invoices and overdue reminders. The default `MAIL_MAILER=log` only writes mail to the log.

## Configuration

The administrator changes these under **Settings** (company name, currency, tax rate, invoice due days, default language, date and time format, late fees, public booking). The `.env` values below are only the starting defaults; once saved in Settings, the stored value wins. A new currency applies to new rentals only.

| Variable | Default | Meaning |
| --- | --- | --- |
| `RENTDESK_COMPANY_NAME` | `RentDesk` | Shown on documents |
| `RENTDESK_CURRENCY` | `USD` | Three-letter code |
| `RENTDESK_TAX_RATE` | `0` | Percent, applied after discounts |
| `RENTDESK_INVOICE_DUE_DAYS` | `7` | Days until an invoice is due |
| `RENTDESK_LATE_FEE_PERCENT` | `100` | Per started day late, percent of the average daily charge |
| `RENTDESK_LATE_FEE_GRACE_MINUTES` | `60` | Minutes after the end time before a rental is late |
| `RENTDESK_PUBLIC_BOOKING` | `false` | Enables the public request form at `/book` |

All dates are stored in UTC and money in cents.

## Users and permissions

Administrators add and manage team accounts under **Administration → Users**. Three roles: **admin** (everything), **manager** (everything except deleting records and managing settings, languages and users) and **staff** (view access to everything except reports, plus create and edit for rentals, customers, invoices and booking requests). Permissions are managed with spatie/laravel-permission; after upgrading, run `php artisan db:seed --class=RoleSeeder` to pick up new permissions.

## Languages

English and Bengali are included. Users switch language from the top bar or the login page; the administrator chooses the default under **Settings**.

Administrators manage languages under **Languages**: add any language with its code (`fr`, `ar`, `pt-BR`), its name and its text direction, switch it on or off, and delete it. To translate, download the language's JSON file (every string, with English where there is no translation yet), translate the text on the right of each line and upload the file. Uploaded files are checked (known strings only, placeholders such as `:name` must be kept) and saved in `storage/app/lang`, so that folder must be writable. Untranslated strings are shown in English. The base language (English) and the current default language cannot be removed.

The layouts use direction-neutral classes, so right-to-left languages mirror correctly, and a test rejects `left`/`right` utilities in views. `TranslationTest` fails when a string in the code has no Bengali translation, so keep `lang/bn.json` complete when you add text.

PDFs (contracts, invoices) are always English because the PDF library cannot shape Bengali or right-to-left scripts.

## Demo data

`php artisan migrate:fresh --seed` loads a demo business with accounts `admin@gmail.com`, `manager@rentdesk.test` and `staff@rentdesk.test` (password `111111`). **Use this only on a demo or development database**; never seed a live installation. The installer's "sample data" option adds the business data without those accounts.

## Production notes

- Use MySQL or MariaDB. The booking checks rely on row locks and `READ COMMITTED` isolation (set in `config/database.php`); SQLite serialises writes and is fine for trying the app, but it has not been load tested.
- Set `APP_DEBUG=false` (the installer does).
- Private files (signatures, inspection photos, customer documents) are on the `local` disk and served only through authorised routes.
- Public booking requests are protected by a honeypot field, a minimum fill time and rate limits (2 per minute, 6 per hour per IP).

## Testing

```bash
php artisan test
```

Tests run on SQLite in memory. To run them on MySQL, create an empty database and run `DB_CONNECTION=mysql DB_DATABASE=rentdesk_test php artisan test`.

## Known limitations

- No payments table: invoices can be marked paid but individual part-payments are not recorded.
- Times are always UTC; there is no time zone setting.
- The late fee is not added to the invoice automatically.
- The public booking form does not send confirmation emails.
- PDFs are English only.
- Framework validation messages fall back to English in Bengali.

## Building the CodeCanyon package

```bash
php build/package.php                # runs npm run build, then packages
php build/package.php --skip-assets  # uses the current public/build
```

This writes `codecanyon/1-main-file/RentDesk-v<version>.zip` (version from the first heading in `CHANGELOG.md`; the previous zip there is replaced) with two folders: `rentdesk/` (the application with production `vendor/` and `public/build/`, without `.env`, `.git`, `node_modules`, logs, uploads, the install lock, SQLite files or editor/AI notes) and `documentation/`. The script checks the result and stops if something that must not ship is found. `build/` and `codecanyon/` are not part of the package. `codecanyon/` also holds the thumbnail, inline preview, screenshots and item description for the upload form; see `codecanyon/README.txt`.

## Third-party packages

Laravel, spatie/laravel-permission, barryvdh/laravel-dompdf, simplesoftwareio/simple-qrcode, FakerPHP (sample data), Alpine.js, Tailwind CSS, FullCalendar, Flatpickr, SweetAlert2 and html5-qrcode. Each is distributed under its own licence.
