# Photography Marketplace (RORI)

A Laravel-based marketplace that connects customers with photographers and photography studios. The application supports discovery, booking requests, creator workspaces, subscriptions, payments, portfolio media, customer reviews, messaging, and marketplace administration.

> **Security notice:** Never commit `.env`, `.env.backup`, credentials, API keys, private uploads, `vendor/`, or `node_modules/` to a public repository. If credentials have ever been committed, rotate them immediately. Removing a file from the latest commit does not erase it from Git history.

## Contents

- [Features](#features)
- [Technology stack](#technology-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Environment configuration](#environment-configuration)
- [Database and demo data](#database-and-demo-data)
- [Run the application](#run-the-application)
- [Queues, mail, and media](#queues-mail-and-media)
- [Payments](#payments)
- [Roles and demo accounts](#roles-and-demo-accounts)
- [Testing](#testing)
- [Useful commands](#useful-commands)
- [Project structure](#project-structure)
- [API overview](#api-overview)
- [Production checklist](#production-checklist)
- [License](#license)

## Features

### Customer
- Browse and search photographer profiles and studios.
- View creator portfolios, packages, categories, and reviews.
- Submit booking requests and view booking status.
- Pay for bookings through Razorpay Checkout.
- Message the creator associated with a booking.
- Cancel bookings subject to the configured cancellation policy.
- Submit reviews for eligible bookings.
- View loyalty/rewards information.

### Photographer and studio
- Creator dashboard and booking inbox.
- Accept or reject booking requests.
- Manage weekly availability and blocked dates.
- Manage portfolio entries and media.
- Manage subscription plans and invoices.
- View earnings and settlements.
- Studio team management endpoints.

### Platform administration
- Dashboard and analytics.
- Configure marketplace commission and subscription plans.
- Review KYC documents.
- Generate and approve settlements.
- Manage coupons and users.
- Review fraud flags.
- View GST reports and export GSTR-8 CSV data.

### Marketplace rules
- Currency is INR.
- Monetary amounts are stored as integer paise.
- Booking availability is checked against creator working hours and existing reservations.
- Creator earning features require an active or grace-period subscription.
- Role and permission checks protect customer, creator, studio, and admin routes.

## Technology stack

- PHP 8.3+
- Laravel 13
- MySQL or another Laravel-supported database (SQLite is configured for automated tests)
- Laravel Sanctum for authenticated API/session workflows
- Spatie Laravel Permission for roles and permissions
- Blade, Alpine.js, Axios, Tailwind CSS, and Vite
- Razorpay REST API for payment orders and checkout verification
- Laravel queues for background jobs

## Requirements

For local development, install:

- PHP 8.3 or newer with the extensions required by Laravel and the project (including PDO for your database, Mbstring, OpenSSL, XML, Ctype, JSON, Tokenizer, Fileinfo, and cURL).
- Composer 2.
- Node.js and npm.
- MySQL 8+ (recommended for local marketplace development) or another supported database.
- Redis is optional unless enabled in your environment.

Ubuntu example (package names may differ by PHP repository/version):

```bash
sudo apt update
sudo apt install -y \
  php8.3-cli php8.3-common php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
  php8.3-gd php8.3-intl php8.3-sqlite3 \
  unzip git curl
```

Install Composer and Node.js using their official installation instructions if they are not already installed.

## Installation

Clone the repository and enter the project directory:

```bash
git clone https://github.com/adfdeleteacc-netizen/Photography.git
cd Photography
```

Install PHP dependencies:

```bash
composer install
```

Create your local environment file and generate an application key:

```bash
cp .env.example .env
php artisan key:generate
```

Install frontend dependencies and build assets:

```bash
npm install
npm run build
```

Create a database and database user in MySQL, then configure the connection in `.env`. Example:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=photography_marketplace
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Do not use the example values as real credentials. Use a database account with access only to this application's database.

Prepare Laravel storage and run migrations:

```bash
php artisan storage:link
php artisan migrate --seed
```

The seeder creates roles/permissions and marketplace catalog data. In the local environment it also adds demo users and creator data. See [Roles and demo accounts](#roles-and-demo-accounts).

## Environment configuration

Configure `.env` for your own machine. At minimum, review:

| Variable | Purpose |
|---|---|
| `APP_NAME` | Application name |
| `APP_ENV` | Use `local` for development and `production` for deployment |
| `APP_DEBUG` | `true` only in local development; set `false` in production |
| `APP_URL` | Base URL of the application |
| `APP_KEY` | Laravel encryption key; generate with `php artisan key:generate` |
| `DB_*` | Database connection |
| `CACHE_STORE` | Cache backend |
| `SESSION_DRIVER` | Session backend |
| `QUEUE_CONNECTION` | Queue backend |
| `MAIL_MAILER` | Mail transport |
| `FILESYSTEM_DISK` | Default Laravel filesystem |
| `MEDIA_DISK` | Disk used for creator media |
| `RAZORPAY_KEY_ID` | Razorpay API key ID |
| `RAZORPAY_KEY_SECRET` | Razorpay API key secret |
| `RAZORPAY_WEBHOOK_SECRET` | Secret configured for the Razorpay webhook |
| `RAZORPAYX_ACCOUNT_NUMBER` | RazorpayX account number, if payout features are configured |
| `ADMIN_EMAIL` | Email used to create the initial admin during seeding |
| `ADMIN_PASSWORD` | Password used for the initial admin during seeding |

Add the Razorpay variables to your local `.env` if they are not present in `.env.example`:

```dotenv
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
RAZORPAYX_ACCOUNT_NUMBER=
```

Keep these values empty until you have credentials. Use Razorpay **Test Mode** credentials for development. Never publish secrets or paste them into issue reports.

For local mail testing, `MAIL_MAILER=log` writes mail content to the Laravel log instead of sending real email. Configure a real mail provider for production.

After changing environment values, clear cached configuration:

```bash
php artisan config:clear
php artisan optimize:clear
```

## Database and demo data

Run migrations and seeders:

```bash
php artisan migrate --seed
```

The `DatabaseSeeder` runs role/permission and catalog seeders. When `APP_ENV=local`, it also runs `DemoSeeder`.

The initial admin is created only when both `ADMIN_EMAIL` and `ADMIN_PASSWORD` are configured before running the seeder. There is intentionally no default admin password.

If you add or change these values after the first seed, rerun:

```bash
php artisan db:seed
```

The seeder uses `firstOrCreate`; it does not reset an existing user's password.

## Run the application

Start the Laravel development server:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

For access from another device on your local network, bind to `0.0.0.0` and open the machine's LAN IP address:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Only expose the development server on a trusted network. Do not use `artisan serve` as a production web server.

## Queues, mail, and media

The project uses queued/background work for some notifications and media operations. Start a queue worker during development:

```bash
php artisan queue:work --queue=default,media
```

Keep the worker running in a separate terminal. In production, use a process manager such as Supervisor or systemd to keep workers alive.

For local media storage, configure a suitable local/public disk and set `MEDIA_DISK` accordingly. For production, configure a private S3-compatible bucket if required by your deployment, including access credentials, region, bucket name, and appropriate access policies.

Do not make KYC documents or private customer media publicly accessible. Use the application's authorized private-media routes and short-lived URLs.

## Payments

The application uses Razorpay to create payment orders and verify checkout callbacks.

1. Create a Razorpay account and enable Test Mode.
2. Generate Test API keys in the Razorpay dashboard.
3. Add the key ID and key secret to your local `.env`.
4. Configure the webhook URL as `https://your-domain.example/api/webhooks/razorpay` when testing through a reachable HTTPS endpoint.
5. Set the webhook secret in `.env` to match the secret configured in Razorpay.
6. Test the complete booking checkout and payment verification flow before enabling live payments.

The booking acceptance step creates a payment order. Without valid Razorpay credentials, the gateway order request will fail and the customer will not be able to complete payment.

RazorpayX payout configuration is separate from standard Razorpay Checkout and requires an appropriately enabled account and valid fund-account setup.

## Roles and demo accounts

Local demo data includes:

| Role | Email | Password | Demo profile |
|---|---|---|---|
| Customer | `customer@example.test` | `Password123` | Demo customer |
| Photographer | `photographer0@example.test` | `Password123` | Lens Studio 0 — Mumbai |
| Photographer | `photographer1@example.test` | `Password123` | Lens Studio 1 — Delhi |
| Photographer | `photographer2@example.test` | `Password123` | Lens Studio 2 — Bengaluru |

These accounts are intended **only for local development**. Do not use the demo password in production. The demo photographers have sample availability Monday through Saturday, 09:00–20:00, and a sample 600-minute package; choose a booking time that fits the full package duration.

An admin account is created from `ADMIN_EMAIL` and `ADMIN_PASSWORD` when the database seeder runs.

## Testing

Run the automated test suite:

```bash
php artisan test
```

The PHPUnit configuration uses an in-memory SQLite database and array/sync drivers for isolated tests. Ensure the PHP SQLite extension is installed.

Build frontend assets:

```bash
npm run build
```

## Useful commands

```bash
# List registered routes
php artisan route:list

# Clear application caches
php artisan optimize:clear

# Rebuild production caches (after configuration is correct)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate

# Run seeders
php artisan db:seed

# Run tests
php artisan test

# Build frontend
npm run build

# Run Vite in development mode
npm run dev

# Start a queue worker
php artisan queue:work --queue=default,media

# Inspect recent application logs
tail -n 100 storage/logs/laravel.log
```

## Project structure

```text
app/
  Exceptions/             Application exceptions
  Http/Controllers/       Web and API controllers
  Models/                 Eloquent models
  Policies/               Authorization policies
  Services/                Marketplace business logic
bootstrap/                 Laravel application bootstrap
config/                    Application configuration
database/
  migrations/              Database schema
  seeders/                 Roles, catalog, admin and local demo data
resources/
  css/                     Stylesheets
  js/                      Alpine/Axios frontend code
  views/                   Blade pages and dashboards
routes/
  web.php                  Web pages and role dashboards
  api.php                  Public/authenticated API routes
  admin.php                Admin API routes
  customer.php              Customer API routes
  photographer.php          Photographer API routes
  studio.php               Studio team routes
tests/
  Feature/                 Feature tests
  Unit/                    Unit tests
```

## API overview

The API routes are registered by the application bootstrap with route-specific middleware. Authentication uses Laravel Sanctum, and creator/admin access is restricted by roles, permissions, subscription checks, and policies.

| Area | Representative endpoints |
|---|---|
| Public discovery | `GET /api/photographers`, `GET /api/studios`, `GET /api/search` |
| Authentication | `POST /api/register`, `POST /api/login`, `POST /api/logout` |
| Current user | `GET /api/me` |
| Customer bookings | `POST /api/bookings`, `GET /api/my-bookings` |
| Booking actions | `GET /api/bookings/{booking}`, `POST /api/bookings/{booking}/cancel` |
| Booking messages | `GET/POST /api/bookings/{booking}/messages` |
| Customer payments | `POST /api/payments`, `POST /api/payments/verify` |
| Creator bookings | `GET /api/creator/bookings`, `POST /api/bookings/{booking}/accept` |
| Creator availability | `GET/POST /api/availability` |
| Creator portfolio | `GET/POST /api/portfolio` |
| Subscriptions | `GET /api/subscription/plans`, `POST /api/subscription/checkout`, `POST /api/subscription/verify` |
| Admin | `/api/admin/...` |
| Razorpay webhook | `POST /api/webhooks/razorpay` |

Use `php artisan route:list` for the authoritative route list in your current checkout.

## Production checklist

Before deploying:

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Use a strong, unique `APP_KEY`; keep it private and backed up securely.
- Rotate any credentials that were ever committed to Git.
- Remove `.env`, `.env.backup`, local archives, dependency folders, caches, logs, and generated artifacts from repository tracking; rewrite Git history if secrets were committed and follow GitHub's secret-removal guidance.
- Use HTTPS and configure `APP_URL` correctly.
- Configure production database, mail, cache, session, queue, and filesystem services.
- Use valid live Razorpay credentials only after successful test-mode verification.
- Configure and verify Razorpay webhook signatures.
- Run migrations as part of a controlled deployment.
- Build frontend assets with `npm run build`.
- Run queue workers under a process manager.
- Restrict KYC and private media access.
- Set up backups, monitoring, log rotation, and error reporting.
- Review role permissions, rate limits, upload validation, and payment/settlement workflows.
- Do not use demo accounts or demo passwords in production.

## License

The repository's Composer metadata declares the MIT License. If this project is distributed, include the appropriate MIT license text and confirm that all third-party assets and dependencies are compatible.
