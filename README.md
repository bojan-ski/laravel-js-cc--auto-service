# 🔧 Auto Service

**Auto Service** is an internal garage management application for a single auto repair shop.
It runs the whole working day without paper: booking appointments, receiving cars, recording labor and parts, tracking stock, and invoicing the customer. It is built as dense, fast, high-contrast shop equipment for staff working at a counter.

Every user is a staff member. Accounts are created by an admin, and there is no public registration. Customers are records, not users: they never log in, and they are contacted only by email. The core rule is that **once a job is invoiced it is locked**, and only an admin voiding the invoice can reopen it.

---

## ✨ Features

### 👥 Every Staff Member
- 🔐 Authentication: Sign in, Forgot/Reset password
- 🌍 Use the app in **English, Serbian (Latin), Romanian, or German**. The choice is saved on your account, and the sign-in page has its own language switcher
- 🌗 **Light, dark, or system** theme, saved on your account. Printing is always light

### 🔩 Mechanic
- 📋 Jobs board with status tabs, search by plate or customer, sorting, and pagination
- 🔄 Start work, pause for parts, resume, and complete jobs (Received → In progress ⇄ Awaiting parts → Completed)
- 🛠️ Record their own labor lines and the parts they take off the shelf (stock falls immediately)
- 📸 Upload job photos (optimized into two WebP sizes with **Intervention Image**) and PDF documents
- 🚗 View customers, vehicles, and full vehicle service history (read-only)
- 🗓️ View appointments (read-only)
- 📦 View the parts catalogue and stock levels
- 🙈 **Never sees money**: prices, totals, and invoices are withheld at the query level, not just hidden in the page

### 🧾 Receptionist
- ✅ Sees everything a mechanic sees, with prices visible. Starting and resuming work is left to the mechanics; receptionists pause, complete, reopen, and cancel jobs
- 👥 Create and edit customers and vehicles, with plate and VIN uniqueness. Search customers by name, phone, plate (dashes and spaces ignored), make and model, or VIN
- 🗓️ Book appointments from tomorrow onward, within the shop's appointment hours (with a non-blocking overlap warning), and mark cars as arrived or no-show
- 🚘 Book or walk in a **new customer and vehicle from one form**: find an existing customer by name, phone, or plate, or enter new ones on the spot
- 🧰 Open jobs from an arrival or as a walk-in, and manage every job line regardless of who recorded it
- 📦 Create and edit parts, record OE, catalogue, barcode, and supplier numbers, restock, and adjust stock. Every change is written to an audit trail
- 📥 Import parts from a CSV file (all-or-nothing) using a generated template
- 💶 Issue invoices from completed jobs (VAT stamped at issue time), mark them paid, and print them
- 📧 Customers are emailed automatically when their car is ready and when an invoice is issued, always in the shop's language. The screen confirms the address being emailed, or that none is on file

### 🛡️ Admin
- ✅ Everything a receptionist can do, plus starting and resuming work and seeing part cost prices
- 👤 Manage staff accounts (create, edit, offboard), with safeguards so the last admin cannot be removed
- ❌ Void invoices. This is the only way to unlock an invoiced job
- ⏰ Set the shop's appointment hours
- 🏷️ Manage part brands and their quality tiers
- 🗑️ Delete and restore customers, vehicles, and parts. Nothing is ever purged, so every past job keeps its history

---

## 🛠️ Tech Stack
- **Framework**: [Laravel 13](https://laravel.com/) on PHP 8.4
- **Database**: [MySQL](https://www.mysql.com/)
- **Templating**: [Blade Templates](https://laravel.com/docs/blade) + vanilla JavaScript modules (progressive enhancement, no frontend framework)
- **Styling**: [Tailwind CSS 4](https://tailwindcss.com/), built with [Vite 8](https://vite.dev/)
- **Image Handling**: [Intervention Image 4](https://image.intervention.io/) (WebP thumbnail and full-size variants, GD driver)
- **Queue**: [Laravel Queues](https://laravel.com/docs/queues) (database driver) for all customer email
- **Email**: [Laravel Mail](https://laravel.com/docs/mail) (Mailables), with [Mailtrap](https://mailtrap.io/) for development and staging
- **Localization**: [Laravel localization](https://laravel.com/docs/localization) (`lang/en`, `lang/sr`, `lang/ro`, `lang/de`) with [Carbon](https://carbon.nesbot.com/) for translated dates
- **Testing**: [PHPUnit](https://phpunit.de/), run in parallel with [ParaTest](https://github.com/paratestphp/paratest)

---

## 🚀 Getting Started

### 1. Clone the Repository
```bash
git clone https://github.com/bojan-ski/laravel-js-cc--auto-service
cd laravel-js-cc--auto-service
```

### 2. Install Dependencies
```bash
composer install
npm install
```

### 3. Create the Databases
Create two MySQL databases: `auto_service` for the app and `auto_service_testing` for the test suite (configured in `phpunit.xml`).

### 4. Create Accounts
[Mailtrap](https://mailtrap.io/) catches every outgoing email in a sandbox inbox, so no real customer is ever emailed during development or on staging.

### 5. Environment Setup - .env
```bash
cp .env.example .env
php artisan key:generate
```

```env
# Application
APP_NAME="Auto Service"
APP_URL=http://localhost:8000

# Shop language: en, sr (Serbian, Latin script), ro or de. Every customer
# email is written in it, and it is the default for staff who have not
# picked a language of their own.
APP_LOCALE=en

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auto_service
DB_USERNAME=
DB_PASSWORD=

# Queue (all customer email is queued)
QUEUE_CONNECTION=database

# Mail (Mailtrap sandbox)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="Auto Service"

# First admin account (created by the seeder)
ADMIN_EMAIL=
ADMIN_PASSWORD=

# Shop settings (VAT is stamped onto each invoice at issue time)
SHOP_LABOR_RATE=3000
SHOP_VAT_RATE=20
SHOP_IDENTITY_NAME=
SHOP_IDENTITY_ADDRESS=
SHOP_IDENTITY_PHONE=
SHOP_IDENTITY_TAX_NUMBER=
```

### 6. Run Migrations & Seed Database
```bash
php artisan migrate --seed
```
The seeder creates the first admin account from `ADMIN_EMAIL` and `ADMIN_PASSWORD`. It refuses to run without a password, and it is safe to run again. Sign in as that admin to create the other staff accounts.

**Demo data.** Outside production, the seeder also loads about 60 days of shop history: staff, a parts catalogue, customers and vehicles, and jobs, appointments, and invoices in every state. Demo staff sign in with the password `password`, for example `milica@auto-service.test` (admin), `jelena@auto-service.test` (receptionist), and `dragan@auto-service.test` (mechanic). The demo seeder skips a database that already holds customers or parts, and it never runs when `APP_ENV=production`.

> A staging site reachable from the internet should not keep the demo accounts: anyone who reads this README knows their password.

> Do **not** run `php artisan storage:link`. Job photos and documents are staff-only files. They live on the private disk and are served only through an authorized route.

### 7. Start Servers
```bash
php artisan serve
npm run dev
php artisan queue:work
```
Without the queue worker, customer emails wait unsent in the `queue_jobs` table.

### 8. Run the Tests
```bash
php artisan test --parallel --processes=8 --compact
```
The full suite runs in parallel. Each process uses its own `auto_service_testing_test_N` database, created on first use. To run a single file or test while you work, run it without `--parallel`, for example `php artisan test --compact tests/Feature/Quality/LocaleParityTest.php`.

> Adding UI text? Add the key to all four `lang/` folders in the same change. `LocaleParityTest` fails when a language is missing a key or drops a `:placeholder`.

---

## 🌐 Deployment
- The web server's document root must be `public/`
- Set `APP_ENV=production`, which also keeps the demo data out
- Build the frontend with `npm run build`, run `php artisan migrate --force`, then `php artisan view:cache`. Rebuilding the compiled views matters: views compiled by an older release can point at components that have since changed
- Keep a queue worker running. On shared hosting without Supervisor, run a cron job every minute: `php artisan queue:work --stop-when-empty --max-time=50`
- Raise PHP's `upload_max_filesize` to at least 24M and `post_max_size` to at least 32M. With lower limits a large photo or PDF upload fails as a "419 page expired" error
- The application is deliberately kept out of search engines on every host: `robots.txt` disallows everything, and every response carries `X-Robots-Tag: noindex`

---

## 👨‍💻 Author
Developed with ❤️ by BPdevelopment (bojan-ski)
