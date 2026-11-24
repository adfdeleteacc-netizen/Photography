# Photography Marketplace SaaS (Laravel)

Implementation of `instruction.md`: multi-vendor marketplace with subscriptions, commission bookings,
Razorpay payments, ledger, settlements and GST/TCS compliance.
This package is an **overlay** on a fresh Laravel 11/12 install (see `setup.sh`).

## Setup
```bash
mkdir work && cd work && cp -R /path/to/this/package ./pkg && bash pkg/setup.sh
# edit .env (DB, Redis, S3, Razorpay, ADMIN_EMAIL/ADMIN_PASSWORD)
php artisan migrate --seed && npm run build && php artisan test
php artisan queue:work --queue=default,media   # and: php artisan schedule:work
```
Razorpay webhook URL: `POST /api/webhooks/razorpay` (events: payment.captured, order.paid, payment.failed).

## Money model (verified by `tools_verify_money.py` and `tests/Unit/PricingServiceTest.php`)
All amounts are integer **paise**. For ₹10,000 at 20%: commission ₹2,000; GST 18% on commission ₹360;
TCS 0.5% of booking value ₹50; creator share ₹8,000; **net payout ₹7,590** (GST and TCS are deducted from the creator).
Coupon/loyalty discounts are platform-funded (creator payout unaffected). Commission % is admin-configurable and
snapshotted per booking. Every event writes balanced double-entry `ledgers` rows.

## Flow
inquiry → creator accepts (slot re-checked under row lock, coupon/points reserved, 30-min payment hold) →
Razorpay order → verified capture (callback or webhook, idempotent) → confirmed (dates locked, ledger, tax invoice) →
completed → settlement (pending_approval → approved → paid via RazorpayX).
Paid-but-unfulfillable payments (hold expired / slot lost) are auto-refunded.

## Module map
| Spec module | Where |
|---|---|
| Auth, KYC, referrals | `Services/Auth/AccountService`, `AuthController`, Breeze views |
| Subscriptions, grace, limits | `Services/Subscription`, `EnsureActiveSubscription` |
| Availability, booking | `Services/Booking/{Availability,Booking,Search}Service` |
| Payments, refunds, ledger | `Services/Payment/*` |
| Settlements | `Services/Settlement/SettlementService` |
| GST/TCS, GSTR-8 CSV | `Services/Tax/TaxService` |
| Coupons, loyalty | `Services/Coupon/*` |
| Portfolio, media safety | `Services/Portfolio/PortfolioService`, `Jobs/ProcessPortfolioMedia` |
| Chat, reviews | `MessageController`, `ReviewController` |
| Admin, fraud | `AdminController`, `marketplace:detect-fraud` |

## Honest status — read before going live
- **Not executed.** The build sandbox had no PHP/Composer/network. Code was structurally linted and the money
  arithmetic fuzz-tested in Python, but migrations, routes and tests have **not been run**. Expect small fixes
  on first `php artisan test`.
- **Compliance needs a CA review:** GSTR-8 CSV is a supplier-wise summary, not the official offline-tool template;
  TCS rate/base and who bears GST are configurable assumptions.
- **Bank verification** is tied to PAN approval (no penny-drop); `razorpay_fund_account_id` must be created via
  RazorpayX before payouts work.
- Not built: Echo/Pusher broadcasting (hook is commented), social login, disputes UI, video transcoding,
  campaigns/promotions CRUD (tables exist), multi-day bookings, refunds after a settlement was already paid
  (needs clawback against the next settlement).
- Cancellation refund tiers (>=7d 100%, 2–7d 50%, <2d 0%) in `BookingService::refundAmountFor` are placeholders.
