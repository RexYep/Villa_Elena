# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Villa Elena is a Laravel 12 / PHP 8.2 / MySQL 8 resort management system for **one single villa property with 6 rooms, rented out exclusively as a whole to one guest group at a time** — not a multi-unit hotel. It has four surfaces: an unauthenticated **Public Portal** (browsing/booking), an **Admin Panel**, a **Staff Portal** (frontdesk/walk-ins), and a **Customer Portal** (guest self-service).

**`project.md` at the repo root is the authoritative, actively-maintained design doc** (schema, pricing model, booking rules, route list, deployment, known-issues-fixed history). It is large and versioned (currently v5.3) — read the relevant section before touching booking, pricing, payments, notifications, or deployment code. Don't try to re-derive that history from git log; it's already written down there. When you make a change that this doc describes (schema, pricing, slots, deployment), update `project.md` too — it's treated as living documentation, not a changelog to leave stale.

## Commands

```bash
# Setup (installs deps, copies .env, generates key, migrates, builds assets)
composer setup

# Local dev — runs php artisan serve + queue:listen + pail (logs) + vite + schedule:work, concurrently
composer dev

# Local dev, in Docker — same Dockerfile/image Render deploys, for parity testing.
# Keep this running in its own terminal (Compose Watch syncs edits in live).
docker compose up --watch

# Run the test suite (defaults to Laravel's skeleton tests — see Testing note below)
composer test
# equivalent to:
php artisan config:clear && php artisan test

# Run a single test
php artisan test --filter=test_method_name
php artisan test tests/Feature/SomeTest.php

# Frontend build only
npm run dev      # vite dev server
npm run build    # production build (multi-entry, see Frontend section)

# Code style
./vendor/bin/pint            # fix
./vendor/bin/pint --test     # check only

# IDE helper regen (after model/facade changes) — repo commits these, keep them in sync
php artisan ide-helper:generate
php artisan ide-helper:models
```

### Database

Local dev uses `villa_elena_db` on local MySQL (`root`, credentials in `.env` — not reproduced here) with `SESSION_DRIVER=file`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=sync`. Production (Render + Aiven) has no shell access, so a **second `aiven` DB connection** exists in `config/database.php` purely for running one-off artisan commands against production from a dev machine:

```bash
php artisan migrate --force --database=aiven
php artisan migrate:fresh --seed --force --database=aiven   # full prod reset + reseed
```

Migrations use sequential sub-second timestamps to control FK creation order — don't rely on filename dates alone when reordering.

### Redis (local caching only)

Local dev caches through Redis (`predis/predis` client, no PHP `redis` extension needed) via a container — not part of the production stack (Render/Aiven still cache via the `database` driver, no free Redis provider wired up there yet):

```bash
docker compose up -d redis      # starts just the redis service, independent of the app service
```

`App\Models\Setting::get()` and `Admin\DashboardController::index()`'s KPI stats both go through `Cache::remember()` — this is now a real cache hit/miss locally instead of the `file` driver it used to be silently backed by.

`REDIS_HOST` in `.env` is `127.0.0.1` (correct for `php artisan serve` reaching Redis's published port) — the Dockerized `app` service below overrides this to `redis` (the Compose service name) since `127.0.0.1` inside that container means the container itself, not the sibling Redis one.

### Full app in Docker (parity testing, NEW)

`docker compose up --watch` runs the app in the **same `Dockerfile`/image Render deploys** (PHP 8.2-fpm-alpine + nginx + supervisord) — use it to check whether a change behaves the same in that stack before pushing, rather than only finding out after a deploy. `php artisan serve` is unaffected and still the default for day-to-day work.

- Reaches local MySQL via `host.docker.internal`, authenticating as a scoped `villa_docker`@`%` MySQL user (not `root` — see `project.md` §15.7 for the one-time `CREATE USER`/`GRANT` setup) — the container doesn't present as `localhost` to MySQL, so `root`'s `localhost`-only grant doesn't match.
- Bind-mounts only `storage/` (needs to persist uploads/sessions/cache across restarts). Everything else you'd actually edit (`app/`, `resources/`, `routes/`, `config/`, `database/`) is synced in via Compose Watch (`develop.watch` in `docker-compose.yml`) rather than live bind-mounted — a plain bind mount of the whole repo works but is multiple seconds slower per request on Windows (every `vendor/` file read crosses the Windows↔WSL2 boundary). `composer.json`/`package.json`/`Dockerfile`/`docker/` changes trigger an automatic rebuild instead of a sync.
- **Never bind-mount or sync `bootstrap/cache/`** into this container. It holds Laravel's compiled package-manifest cache; the host's copy reflects a full `composer install` (dev packages included), and mounting it over the image's clean `--no-dev` build throws `Class "...ServiceProvider" not found` at container start. Same underlying reason `.dockerignore` (excludes `vendor/`, `node_modules/`, `.env`) has to exist at all — without it, a local `docker build` (unlike Render's, which always builds from a fresh clone) copies local dev-only artifacts straight into the image and produces a build that doesn't actually match what Render runs.

## Architecture

### Routing & roles

Four route files, each gated by role middleware (`RoleMiddleware`, aliased as `role` in `bootstrap/app.php`):

| File | Prefix | Middleware | Purpose |
|---|---|---|---|
| `routes/web.php` | `/` | none | Public portal, auth, PayMongo payment routes |
| `routes/admin.php` | `/admin/` | `auth, role:admin` | Admin panel |
| `routes/staff.php` | `/staff/` | `auth, role:staff,admin` | Staff portal |
| `routes/customer.php` | `/my/` | `auth, role:customer` | Customer portal |

Controllers mirror this under `app/Http/Controllers/{Admin,Staff,Customer,Portal}/`, plus `app/Http/Controllers/Auth/AuthController.php` for shared auth. Post-login redirect is role-based: admin → `/admin/dashboard`, staff → `/staff/frontdesk`, customer → `/my/`.

### The single-villa model

`properties.type` is an enum of just `villa` / `room`. There is exactly **one** `type=villa` row (the only bookable listing — "Villa Elena (Whole Villa)"); the 6 `type=room` rows are informational only (status badges, images, housekeeping) and are never independently bookable or shown in customer-facing listings. Don't reintroduce per-room booking.

### Booking slots & pricing (current model, v5.1/v4.0)

- Every booking is one of exactly two fixed slots, defined in `App\Models\Booking::SLOTS` — `day` (8:00 AM–5:00 PM, 9 hrs) or `night` (7:00 PM–6:00 AM next day, 11 hrs). There is **no free-choice time input anywhere** in booking creation.
- `Booking::slotDateTimes($slot, $checkInDate)` is the single source of truth converting a slot + date into check-in/check-out `Carbon`s — every booking-creating controller (public portal, customer reschedule, staff walk-in, admin create) must go through it rather than parsing raw time fields. `Booking::slotKey()` does the reverse for pre-filling forms.
- `Booking::hasConflict()` does a plain check-in/out **datetime** overlap against the single villa's existing bookings — there is **no separate cleaning-buffer parameter**; the gap baked into the two slots (5PM→7PM, 6AM→8AM, both 2 hrs) already serves that purpose.
- **`Booking::reserveSlot($propertyId, $checkIn, $checkOut, $callback, $excludeBookingId)` is the only sanctioned way to create or move a booking** — `hasConflict()` alone is a bug. It is a SELECT, so calling it and then writing leaves a window in which two concurrent requests both see the slot free and both take it. That is not hypothetical: `VE-4C7INQOG` and `VE-YHLBMLUU` are two live production bookings on the same 2026-09-15 Day slot, `created_at` identical to the second, both paid (v7.0). `reserveSlot()` wraps the check and the write in one transaction under `lockForUpdate()` **on the `properties` row** — a real row that always exists, so serialization is deterministic instead of depending on InnoDB gap-lock behaviour over a range that may match nothing. All seven paths go through it: portal submit, admin create, admin extend stay, admin calendar drag-move, staff walk-in, customer reschedule. Keep the callback DB-only — mail, Pusher broadcasts and notifications belong after the commit.
- **`bookings.slot_hold` + its UNIQUE index is the database-level backstop**, and it is model-maintained: never mass-assign or hand-write it. `Booking::computeSlotHold()` returns `"{property_id}:{check_in_date}:{check_in_time}"` while the booking holds its slot and **NULL** when it doesn't (`cancelled`, `no_show`, or soft-deleted) — MySQL allows repeated NULLs in a unique index, which is exactly why a cancelled booking's slot frees up while two *live* bookings on one slot remain impossible to store. Its exclusion list must stay identical to `hasConflict()`'s; if they drift, the index will reject a booking the availability grid advertises as open. A `saving` hook recomputes it, and a `deleted` hook nulls it (`SoftDeletes::runSoftDelete()` bypasses `save()`, so the `saving` hook never fires on a soft delete).
- **`reserveSlot()` formally cancels overlapping expired holds inside its lock**, via `Booking::releaseAsExpiredHold()` (shared with `AutoCheckInOutBookings::cancelStalePendingBookings()`). This is required, not decorative: `hasConflict()` *ignores* a `pending` booking past `booking_hold_minutes`, but that row still carries a `slot_hold`, so without the release the index would refuse an INSERT for a slot the grid shows as free. It also means correctness no longer depends on the external cron pinger actually running.
- `Booking::confirmOnFirstPayment()` re-checks availability before promoting a booking to `confirmed`. A guest whose hold expired can still have a live PayMongo checkout link; paying through it used to confirm them onto a slot someone else had since taken. The payment is always recorded (never throw money away) — the booking is left `pending` and admins are notified, which is also the state the stale sweeper won't touch, since it skips anything with `amount_paid > 0`.
- The one deliberate exception is `Admin\BookingController::extendStay()`, which extends an already-checked-in guest's stay and is still free-choice on the new checkout time.
- Pricing is a flat package rate from `Property::getPackagePrice(Carbon $checkin)` (NOT the older `getPriceForDate()`, which is date-only and still used only by `PricingRule`/legacy paths): Mon–Thu and Sun-after-6PM → `base_price` (₱4,000 regular); Fri/Sat/Sun-before-6PM → `weekend_price` (₱6,000 peak). An active `pricing_rules` row for the date overrides both.
- **`Property::quoteFor($checkin, $slot)` — not `getPackagePrice()` — is what booking code calls.** It is to price what `slotDateTimes()` is to time: the single source of truth. It wraps `getPackagePrice()` and subtracts any matching seasonal promo, returning `['base', 'discount', 'total', 'promo']`. Every pricing path goes through it (portal preview/form/submit, staff walk-in + availability grid, admin create, customer reschedule); `getPackagePrice()` is only for a bare list price. Never recompute a discount at a call site — and never reimplement pricing in JavaScript, which is exactly how the walk-in form drifted (its client-side copy couldn't see `pricing_rules` or promos, and its overpayment guard measured against that wrong number; it now fetches `GET /staff/walkin/quote`).

### Seasonal promos (v6.0)

`discounts` was dead schema until v6.0 — `bookings.discount_amount` was hardcoded `0` everywhere. It now backs **automatic** promos: the guest types no code; a promo applies when the booking's **check-in date** is inside its window and `applies_to` matches the slot. Admin-only CRUD at `/admin/promotions` (`Admin\PromotionController`); staff can't create promos but walk-ins get them automatically. Rules that must survive edits:

- Discount comes off `base_amount` **only**, never extras — matches `recalculateBookingTotals()`'s `base + extras - discount`.
- Promos never stack; on overlap the **largest peso discount** wins (not the largest percentage — they differ once a `fixed` promo is involved), tie-broken by highest `id`.
- Deposits compute off the **discounted** total, and so do the walk-in overpayment guard and payment-type detection.
- `used_count` moves with reschedules (decrement old promo, increment new). Deleting a promo is `nullOnDelete()` on `bookings.discount_id`; `discount_amount` stays, so historical totals never change.
- Announcements are **in-app only, one-time**, guarded by `notified_at` (edits don't re-blast). Do not add email here — Brevo's free-tier quota is shared with 2FA, booking confirmations and password resets.
- The landing-page banner (`is_public`) is the primary channel, because guests who aren't logged in have no `user_id` and `NotificationHelper` cannot reach them at all.
- **`Discount::publicActive()` (the banner) deliberately includes promos whose window hasn't started yet**, while `isValidOn()` (the pricing) does not. A September promo must be advertised in August — that's when it can still influence a booking — but it must not discount an August stay. Don't "fix" the banner query to match the pricing query; only `expiry_date` bounds it.
- Dates are guarded in `PromotionController::validated()`: `expiry_date` can never be in the past, and `start_date` can't be moved into the past — but an already-running promo may **keep** its existing past `start_date` through an edit, so renaming a live promo doesn't force a date change.
- `Portal\ChatbotController` **is** promo-aware: it prices through `quoteFor()` and always injects a `CURRENT PROMOS` block built from the same `Discount::publicActive()` the landing banner uses, so the two can't disagree. The block is unconditional — when there are no promos it says so explicitly, because an empty section invites the model to invent one. Keep that "there are NO promos" branch if you touch it.
- `bookings:auto-checkinout` (`AutoCheckInOutBookings` console command) drives automatic status transitions off the stored `check_in_time`/`check_out_time` — it doesn't need to know about slots at all, just the literal stored datetimes.

### Disk-aware image storage

`config/filesystems.php`'s `public` disk switches to the `cloudinary` driver automatically when `CLOUDINARY_URL` is set (production), local disk otherwise (dev) — no controller code branches on this. Views must use the disk-aware accessors (`$image->url`, `$property->primaryImage->url`, `User::profile_image_url`) rather than hardcoding `asset('storage/'.$path)`, which only works for the local disk.

**These accessors (`PropertyImage`, `User`, `Package`) wrap `Storage::disk('public')->url()` in try/catch.** For the Cloudinary driver, `->url()` isn't pure string-building like every other disk — it makes a live Admin API call (`adminApi()->asset($id)`) to fetch the resource before it can return a URL, and throws `Cloudinary\Api\Exception\NotFound` **uncaught** if that asset doesn't exist on Cloudinary. `'throw' => false` in the disk config does *not* catch this — `Storage::url()` calls the adapter directly, bypassing Flysystem's exception-wrapping layer entirely. Keep this pattern (catch, log, return `null`/a default) on any new code that calls `->url()` on an image/file field. Also make sure local `.env` doesn't have a stray `CLOUDINARY_URL` set (see `project.md` v5.3) — if it does, local dev silently starts making the same live Cloudinary calls as production.

### Mail / broadcast failure handling

Outbound mail (registration, 2FA send/resend, verification, forgot-password, walk-in guest creation) and Pusher broadcasts (`NotificationHelper::create()`'s `event(new NotificationCreated(...))`) are wrapped in try/catch + `Log::error()` rather than left to bubble up — a transport hiccup must degrade gracefully (request still succeeds) rather than 500 the whole flow. Follow this pattern for any new mail/broadcast call site, especially inside a DB transaction (a failed non-critical email must not roll back the primary action). Several other `event(new BookingUpdated/FrontdeskUpdated/PropertyAvailabilityChanged(...))` call sites in bookings/calendar/frontdesk still have the old unprotected shape — treat them the same way if you touch them.

Notification `link` values must be generated as relative URLs (`route($name, $params, false)`) — an absolute URL bakes in whatever `APP_URL`/tunnel host was active at creation time and goes dead if that changes.

### Non-obvious column/enum names

These have caused real bugs before (see `project.md` §13 for the full list) — don't assume Laravel-convention defaults:

- `settings` table: `setting_key` / `setting_value` (not `key`/`value`)
- `notifications.is_read` (integer 0/1, not `read_at`); `type` is almost always `in_app` in practice even though other enum values exist
- `housekeeping_tasks.task_type` enum: `checkout_clean` (not `checkout_cleaning`/`checkout`), `daily_clean`, `maintenance`, `inspection`
- `payments.status` must be set explicitly to `success` at every creation site — the DB column defaults to `pending` and nothing auto-promotes it
- `users.status` is cast/compared as integer (`isActive()` → `(int)$this->status === 1`)
- `properties.amenities` is a JSON array; the *available* amenity options list is admin-managed via `Setting::get('property_amenities')`, not hardcoded

### AI & payments integration

- AI (chatbot, review moderation, insights/forecast): Groq API via `App\Services\GeminiService` (name is legacy — it now calls Groq, not Gemini) and `ReviewModerationService`. **The model is not hardcoded** — it comes from `config("services.groq.model")` (`GROQ_MODEL`, default `openai/gpt-oss-20b`), because Groq retires models on a rolling basis and `llama-3.1-8b-instant` was already decommissioned out from under this app. Check the live list with `GET https://api.groq.com/openai/v1/models` before assuming a model id still resolves. Groq's current chat models are all *reasoning* models, and hidden reasoning is billed against `max_tokens` — left unconstrained the reply comes back truncated — so `GeminiService` sends `reasoning_effort`. **Do not hardcode that value either:** the families disagree (`openai/gpt-oss-*` wants `low|medium|high`, `qwen/*` wants `none|default`, `groq/compound*` rejects the parameter outright), so `reasoningEffort()` derives it from the model id and a 400 mentioning `reasoning_effort` is retried once without it. `GROQ_REASONING_EFFORT` overrides; `omit` skips it. Reviews auto-publish via a keyword pre-filter + LLM classification pass; anything flagged, or if the AI call fails (fail-open), falls back to the admin approval queue.
- Payments: PayMongo (`app/Services/PayMongoService.php` — the old unreferenced duplicate under `app/Http/Services/` was deleted in v5.7). **Online checkout requests `payment_method_types => ['qrph']` only** — QR Ph covers GCash, Maya and bank apps in one activation, so the per-wallet integrations aren't enabled. `payments.payment_method` is `ENUM('qrph','cash')`; keep any new payment-writing code inside that set (`PaymentController::recordPaymongoPayment()` normalises unexpected gateway values rather than letting the INSERT fail).
- QR Ph is **asynchronous** — the guest scans a QR, often on a different device, so the success callback may never fire. `POST /webhooks/paymongo` (CSRF-exempt) is therefore the primary recording path, not a fallback; both it and the success callback go through the same `recordPaymongoPayment()`, made idempotent by `reference_number`. Don't add a second payment-recording code path.
- **QR Ph refunds live on a different API host** — `https://refunds-api.paymongo.com/v1/refunds`, not `https://api.paymongo.com/v1/refunds`. The main host returns `400 "Refunds are not allowed for payments with source type qrph."` for every QR Ph payment, which through v5.8 this project wrongly recorded as proof that QR Ph refunds are impossible. They are not (see `project.md` v5.9 for the re-verification). Verified constraints on the correct host: **full amounts only** (`422 partial_refund_not_allowed`), 30-day window, requires available wallet balance, and the refund is a **transfer link the guest must claim within 3 days** — not a reversal. There is **no GET endpoint and no documented webhook**, so refund progress can't be tracked in code.
- **The refund API is nonetheless the wrong tool here, and was rejected.** Full-only collides head-on with `Booking::calculateRefundPercentage()`'s 50% tier and with reschedule price differences — neither is ever a full refund of a payment. **The chosen direction is Send Money / Disbursements** (`POST https://api.paymongo.com/v2/batch_transfers`), a plain outbound transfer from the merchant Wallet: arbitrary amounts, InstaPay real-time, `callback_url` for status, ₱10/transfer, and — decisively — **simulator destination accounts** (`999999990001` → `succeeded`, `999999990003` → `account_not_found`, …) so every failure path can be rehearsed on test keys. Note `/v2/wallets` needs a **trailing slash** (301 otherwise).
- **Send Money is built (v5.9 Phase 4)** — `RefundTransferService` + `POST /admin/payments/{payment}/send`, recording every attempt in `refund_transfers`. Verified against real money on 2026-08-23 (two ₱1 InstaPay transfers, one `succeeded`, one `AC06`-rejected). Rules that came out of that and must not be undone: **a `201` does not mean the money arrived** (accepted as `pending`, rejected by the receiving institution 2s later), so only `succeeded` may close a refund; call PayMongo **outside** any `DB::transaction()` (money moving inside a transaction that later rolls back leaves no record of it); claim the refund in a locked transaction *before* the API call so a double-click cannot send twice; and **keep the manual `markRefundPaidOut()` flow** — it is the only path for cash and for failed transfers.
- **PayMongo Send Money facts that are nowhere in the docs** — all learned empirically, so don't "correct" them from the docs: `GET /v2/wallets/` **omits** `balance`/`account`/`limits` unless requested, and `fields` is a **repeated** param (`?fields=balance&fields=account`), not comma-separated — reading the balance without it silently yields `0` and looks like an empty wallet. **Write that query string literally into the URL**: passing `['fields' => ['balance','account']]` to Laravel's `Http::get()` serialises via `http_build_query()` to `fields[0]=…&fields[1]=…`, which PayMongo ignores, producing the same silent empty-wallet result (this shipped once and threw *"The PayMongo wallet has no source account"* on a funded, activated wallet). The fee is **₱10 per transfer, charged only on success** (`fee` reverts to `0` on failure, so retries are free) — but that **reversal is asynchronous**: reading `fee` at the instant a transfer flips to `failed` still returns `1000`, which is exactly when the inline poll reads it, so `syncStatus()` forces `fee = 0` for any non-`succeeded` status instead of trusting the API there. Live rejections use **ISO 20022 codes** (`AC06`/`BlockedAccount`, `metadata.sub_code` `RJCT` vs `ACTC`) — the `account_not_found`/`account_not_active` strings in `transfer-test-cases` are **simulator-only** and never appear live. `provider_reference_number` echoes our own `reference_number` on creation and only becomes the bank's trace after settlement, so re-read it. **The test-mode wallet returns `{"data":[]}`** — HTTP 200, no error, no wallet — so the simulator is unreachable and every failure path must be reasoned about rather than rehearsed. That also means **Send Money cannot work on test keys at all**; `RefundTransferService::send()` checks `PayMongoService::isTestMode()` up front and refuses with a message naming the real reason, rather than letting it surface as "Could not reach the PayMongo wallet" (which reads like a network fault). Checkout is unaffected: `qrph` checkout sessions are created and active on test keys. Webhook secrets are **per-mode** — swapping to test keys means swapping `PAYMONGO_WEBHOOK_SECRET` to the test one and registering the webhook again in test mode, or every delivery fails signature verification.
- **Refund recipient details cannot be derived from the QR Ph payment** — it carries none of `destination_account.{number,name,bic}`; `billing` is just what `createCheckoutSession()` sent, echoed back. Hence `refund_destinations` (asked of the guest) and `refund_transfers` (an immutable snapshot per attempt) are separate tables. There is **no account-name-inquiry endpoint**, so a wrong name only surfaces as a runtime rejection. Detect mobile-number-style accounts by **BIC, never by institution name** (`RefundDestination::MOBILE_WALLET_BICS`) — `MAYA BANK, INC` (`MYDBPHM2XXX`) is a real bank and a different institution from the `Maya Philippines, Inc.` wallet (`PAPHPHM1XXX`), and a `str_contains($name, 'maya')` test wrongly forces bank customers to enter a mobile number.
- 🟢 **GCash `AC06` is intermittent, and no payload field prevents it.** The API reaches GCash fine (`tr_9bc989fa…` landed). The proof it is nondeterministic: `tr_2125e015…` failed while being **identical in every merchant-controlled field** to two dashboard transfers that succeeded hours earlier — same source, destination, name, amount, rail, `purpose`, `description`, reference shape. Same payload, both outcomes; `AC06 BlockedAccount` on an account that accepted money 69 seconds later. **So retry — `RefundTransferService::send()` loops up to `MAX_ATTEMPTS` (3)**, one `refund_transfers` row and a fresh `reference_number` per attempt, stopping early on success or a non-retryable code (`RefundTransfer::RETRYABLE_ERROR_CODES` = `AC06`/`AB08`/`9910`/`91`; account-detail codes like `AC01`/`BE01` are excluded, and `status = 'error'` is never retried since the request shape is what broke). The admin is notified **once**, after the last attempt, with the attempt count — suppress in-loop failure notices with `syncStatus(..., announceFailure: false)`, but never suppress success, which is what closes the refund. **`MAX_ATTEMPTS = 3` is a guess, not a measurement** — the real success rate is unknown; measure it with `php artisan paymongo:probe-transfer` (sends one controlled transfer, every field settable, touches no DB row) before tuning it. **Four confident diagnoses here were already wrong**, each from a small sample where one variable happened to correlate: "GCash blocks the wallet", "Send Money needs more than ₱5" (falsified by our own ₱1 and ₱2 to Maya), "the dashboard sends something we can't see" (`GET /v2/transfers` shows both payloads in full — read it first), and "the `purpose` field decides". **Before concluding that a field matters, send the same payload twice.** `MINIMUM_AMOUNT` is `0.00`: do not reintroduce a minimum without a transfer that proves one. When a transfer fails, do not trust the error code's implied cause.
- `RefundDestination::NO_AUTO_TRANSFER_BICS` and `PESONET_ONLY_BICS` are both **empty** by design; the mechanisms remain for an institution that genuinely needs them. If you ever repopulate `PESONET_ONLY_BICS`, remember PESONet is batch-cleared on banking days (11am/2pm/5pm), so never poll it inline — `AutoCheckInOutBookings::syncPendingTransfers()` is the backstop that closes those when the callback doesn't arrive. Never delete the manual `markRefundPaidOut()` path either way: it is the only route for cash and for failed transfers.
- **Live testing found five bugs that the fake-backed test suite structurally could not.** Each sat in a seam the fakes stubbed over: a fake `PayMongoService` hid that `Http::get(['fields' => [...]])` serialises to `fields[0]=…` and silently returns a wallet with no `account`; direct method assertions hid that the payments *list* only ever offered the manual button; `compileString()` reports a view as valid because `{{ payment_status }}` inside a CSS comment **is** valid PHP, and only rendering catches the runtime "Undefined constant". Verify seams with a real render, a real read-only API call, or a real click — fakes prove logic and nothing else.
- **`PaymentController::webhook()` must never return a non-2xx status.** PayMongo auto-disables a webhook that repeatedly answers 4xx/5xx, it does not recover on its own, and nothing in the app can detect the disabled state — every payment after it simply stops being recorded. So the endpoint is a `try/catch (\Throwable)` shell around `handleWebhookEvent()`, which **returns arrays and never calls `response()`**; keep that split, and don't reintroduce a `401` for a bad signature or let an exception escape. The accepted cost is that PayMongo won't retry a failed event, so the `unmatched_booking` and `processing_error` paths notify admins instead. `transferCallback()` wraps each `syncStatus()` individually for the same reason.
- **When a webhook goes dead, check the registered URL before the secret.** In v6.9 production was registered at the bare origin (`https://villa-elena.onrender.com`, no `/webhooks/paymongo`); `POST /` is 405 from the router, so **nothing reaches the controller and nothing appears in the app log** — the empty log is the symptom, not evidence that deliveries stopped. `php artisan paymongo:webhooks` lists id/status/URL/events for the current key's mode, flags any wrong path, repairs it (`--webhook=… --url=…`), revives disabled ones (`--enable=all`) and parks dead ones (`--disable=tunnels`). Note the listing is **per-mode**: a test key sees only test-mode webhooks.
- **The dev machine's ngrok webhook shares the test-mode account with production, so it receives every test-mode event too** — and returns 502 whenever the tunnel isn't running, accruing the same failures that disabled production. Its ngrok domain is *reserved and static*, so a changing URL is not the issue; an absent tunnel is. `status` is per webhook, not per account, so this can't take production down — it costs the next local test session, silently. Park it with `--disable=tunnels` between sessions. **That probe treats only no-connection or 5xx as dead, never 4xx**: a 4xx means the host answered and rejected the request, which is a code bug to fix, not a registration to remove — the first cut used `>= 400` and would have disabled production, which was answering `401` from the pre-fix code.
- **`php artisan paymongo:tunnel` watches the ngrok endpoint and enables/disables that webhook to match**; `paymongo:webhooks --disable=tunnels` is the manual equivalent (nothing schedules either — `routes/console.php` has no entry). It probes **the registered URL end to end**, never the ngrok agent API: a healthy agent forwarding to a dead port still returns 502, which is the state this project was actually found in, so "a tunnel exists" is not the same question as "the endpoint answers". It refuses any webhook whose host isn't ngrok (`NGROK_HOSTS`) and refuses live keys — without that guard a mistyped `--webhook` would repoint production at ngrok *and* disable it on every exit. Exit-disable is best-effort: `pcntl` doesn't exist on Windows, `sapi_windows_set_ctrl_handler` catches Ctrl+C, and nothing catches `taskkill /F` or a closed terminal (measured — it leaves the hook enabled). That gap is the status quo, not a regression; the next run reconciles.
- **Webhook secrets are per-mode.** The `Paymongo-Signature` header's `te=` slot is signed with the *test* webhook's `whsk_…` and `li=` with the *live* one — different registrations, different secrets. `verifyWebhook()` tries `PAYMONGO_WEBHOOK_SECRET_TEST` then the generic `PAYMONGO_WEBHOOK_SECRET` for `te=`, and `_LIVE` then generic for `li=`. Setting both lets a test-mode and a live-mode webhook share one URL — which this deployment wants, since it runs test keys on the production host. Don't collapse this back to one secret compared against both slots.
- `bootstrap/app.php` exempts `webhooks/paymongo` from CSRF. Without it the route inherits the `web` group and returns 419 to every real delivery. `bootstrap/app.php` is synced into the Docker container as a **single file** in `docker-compose.yml` — never sync the whole `bootstrap/` directory, since `bootstrap/cache/` breaks container start.
- The first successful payment confirms a booking, via `Booking::confirmOnFirstPayment()` — call it after `recalculateFinancials()` in any new payment-recording path. `recalculateFinancials()` never touches `status`, and `AutoCheckInOutBookings::cancelStalePendingBookings()` cancels stale `pending` bookings, so a payment path that skips it will get paid bookings auto-cancelled.
- Render payment enums through `Payment::$method_label` / `$type_label` (or the `methodLabelFor()` / `typeLabelFor()` statics), never raw `ucfirst(str_replace(...))`. Sort payment lists by `payment_date` **plus `id`** — `payment_date` is date-only, so same-day payments tie and order randomly.
- Report export: `barryvdh/laravel-dompdf` + `maatwebsite/excel`, wired into `Admin\ReportController` (`GET /admin/reports/export/{pdf,excel}`). Both share the same `buildReportData()` the on-screen report uses. Excel export classes live in `app/Exports/` (`ReportExport` is a `WithMultipleSheets` wrapper around per-sheet classes). The PDF view (`admin/reports/pdf.blade.php`) is plain-table HTML — dompdf's CSS support doesn't cover flexbox/grid, unlike the rest of the admin panel's views.

### Frontend build

Vite multi-entry build — one JS/CSS pair per portal section (`admin`, `portal`, `auth`, `staff`, `payment`), each importing only what that section needs (e.g. `payment.js` skips Bootstrap JS/CSS entirely). All `resources/css/*.css` files import `base.css` for shared design tokens/utilities. When adding a page, `@vite([...])` the correct section entry in its layout rather than pulling in another section's bundle.

## Testing

`tests/` currently only contains the default Laravel skeleton (`ExampleTest.php` in both `Unit` and `Feature`) — there is no real test suite covering booking/pricing/payment logic yet. Testing config uses in-memory SQLite, array session/cache/mail, sync queue (see `phpunit.xml`).

## Deployment

Production runs on Render (Docker, free tier, no persistent disk, blocks outbound SMTP entirely) against an Aiven MySQL database, with Cloudinary for images, Pusher for realtime, and Brevo's HTTPS API (not SMTP) for mail via a custom `Mail::extend('brevo', ...)` in `AppServiceProvider`. `docker/start.sh` branches on `APP_ENV` (`local` skips config/route/view caching for live-reload; anything else caches them) and only runs migrations when `RUN_MIGRATIONS=true` is set for that deploy. A scheduled task (`bookings:auto-checkinout`, registered `everyMinute()` in `routes/console.php`) runs via an external cron pinger hitting the token-gated `GET /cron/run-schedule/{CRON_SECRET}` route, since Render Cron Jobs aren't free — that route just calls `Artisan::call('schedule:run')`. Locally, `composer dev` runs `php artisan schedule:work` alongside the other dev processes so this (including the stale-pending-booking auto-cancel) actually fires while developing; without it, nothing invokes the scheduler and pending bookings never get swept. Full details, required env vars, and the Aiven CA-cert/TLS setup are in `project.md` §15 — read it before changing deployment-related code (Dockerfile, `docker/start.sh`, `render.yaml`, `config/database.php`'s `aiven` connection).
