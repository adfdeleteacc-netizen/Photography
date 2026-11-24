#!/usr/bin/env bash
# Run from an empty directory. Needs PHP 8.3+, Composer, Node 20+, MySQL, Redis.
set -euo pipefail
OVERLAY="$(cd "$(dirname "$0")" && pwd)"

composer create-project laravel/laravel marketplace
cd marketplace

composer require laravel/sanctum spatie/laravel-permission league/flysystem-aws-s3-v3 "^3.0"
composer require laravel/breeze --dev
php artisan breeze:install blade          # Tailwind + Alpine + auth views (login/register/forgot/verify)

# Overlay the marketplace code on top (overwrites bootstrap/app.php, routes/*, User model, etc.)
cp -R "$OVERLAY"/app "$OVERLAY"/bootstrap "$OVERLAY"/config "$OVERLAY"/database "$OVERLAY"/routes "$OVERLAY"/tests .
mkdir -p resources/views && cp -R "$OVERLAY"/resources/views/. resources/views/
cp "$OVERLAY"/resources/js/app.js resources/js/app.js
cp "$OVERLAY"/resources/css/app.css resources/css/app.css
npm install alpinejs axios

cat "$OVERLAY"/.env.marketplace.example >> .env
php artisan key:generate
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan storage:link

echo
echo "Now edit .env (DB, Redis, S3, Razorpay, ADMIN_EMAIL/ADMIN_PASSWORD), then run:"
echo "  php artisan migrate --seed && npm run build && php artisan test"
