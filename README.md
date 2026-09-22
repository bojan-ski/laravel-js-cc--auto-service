# 🔧 Auto Service

**Auto Service** is an internal garage management application for a single auto repair shop.
It runs the whole working day without paper: booking appointments, receiving cars, recording labor and parts, tracking stock, and invoicing the customer. It is built as dense, fast, high-contrast shop equipment for staff working at a counter.

Every user is a staff member. Accounts are created by an admin, and there is no public registration. Customers are records, not users: they never log in, and they are contacted only by email. The core rule is that **once a job is invoiced it is locked**, and only an admin voiding the invoice can reopen it.

---

## ✨ Features

### 🔩 Mechanic
- 🔐 Authentication: Sign in, Forgot/Reset password
- 📋 Jobs board with status tabs, search by plate or customer, sorting, and pagination
- 🔄 Move jobs through their lifecycle (Received → In progress ⇄ Awaiting parts → Completed)
- 🛠️ Record their own labor lines and the parts they take off the shelf (stock falls immediately)
- 📸 Upload job photos (optimized into two WebP sizes with **Intervention Image**) and PDF documents
- 🚗 View customers, vehicles, and full vehicle service history (read-only)
- 🗓️ View appointments and mark cars as arrived or no-show
- 📦 View the parts catalogue and stock levels
- 🙈 **Never sees money**: prices, totals, and invoices are withheld at the query level, not just hidden in the page

### 🧾 Receptionist
- ✅ Everything a mechanic can do, with prices visible
- 👥 Create and edit customers and vehicles, with plate and VIN uniqueness
- 🗓️ Book appointments (with a non-blocking overlap warning) and create jobs from an arrival or as a walk-in
- 🛠️ Manage every job line regardless of who recorded it; cancel and reopen jobs
- 📦 Create and edit parts, record OE, catalogue, barcode, and supplier numbers, restock, and adjust stock. Every change is written to an audit trail
- 📥 Import parts from a CSV file (all-or-nothing) using a generated template
- 💶 Issue invoices from completed jobs (VAT stamped at issue time), mark them paid, and print them
- 📧 Customers are emailed automatically when their car is ready and when an invoice is issued

### 🛡️ Admin
- ✅ Everything a receptionist can do, plus part cost prices
- 👤 Manage staff accounts (create, edit, offboard), with safeguards so the last admin cannot be removed
- ❌ Void invoices. This is the only way to unlock an invoiced job
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
- **Testing**: [PHPUnit](https://phpunit.de/)

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
The seeder creates the first admin account from `ADMIN_EMAIL` and `ADMIN_PASSWORD`. It refuses to run without a password, and it is safe to run again. Sign in as that admin to create the other staff accounts. There is no demo data.

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
php artisan test --compact
```

---

## 🌐 Deployment
- The web server's document root must be `public/`
- Build the frontend with `npm run build`
- Keep a queue worker running. On shared hosting without Supervisor, run a cron job every minute: `php artisan queue:work --stop-when-empty --max-time=50`
- Raise PHP's `upload_max_filesize` to at least 24M and `post_max_size` to at least 32M. With lower limits a large photo or PDF upload fails as a "419 page expired" error
- The application is deliberately kept out of search engines on every host: `robots.txt` disallows everything, and every response carries `X-Robots-Tag: noindex`

---

## 👨‍💻 Author
Developed with ❤️ by BPdevelopment (bojan-ski)
