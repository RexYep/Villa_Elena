# Villa Elena Private Rental Resort
## Resort Management System — Project Documentation

**Version:** 5.1
**Stack:** PHP 8.2 / Laravel 12 / MySQL 8 / Bootstrap 5
**Local URL:** `http://127.0.0.1:8000` (Laravel Dev Server)
**Database:** `villa_elena_db`

---

## What Changed in v5.1 (Read This First)

Version 5.1 is a small, focused correction to the booking flow: v4.0 modeled bookings as a **free-choice 12–24 hour time window** with a separately-enforced 2-hour cleaning buffer between bookings. That was wrong — the resort actually only ever offers **two fixed package slots** per day, and there was never meant to be a *separate* buffer on top of them (the gap built into the two slots already is the cleaning buffer).

| Area | v4.0/v5.0 (Before) | v5.1 (Now) |
|---|---|---|
| Check-in/out time | Free-choice `<input type="time">` — guest could pick any check-in/check-out time, validated only against a 12–24 hour min/max window | **Two fixed package slots, no free time input**: **Day** (check-in 8:00 AM → check-out 5:00 PM, same day) and **Night** (check-in 7:00 PM → check-out 6:00 AM, next day). Every booking channel (public portal, customer reschedule, staff walk-in, admin-created) now submits a `slot` (`day`/`night`) instead of raw time fields |
| Cleaning buffer | A separate, explicitly-enforced 2-hour buffer padded onto both ends of every booking inside `Booking::hasConflict($bufferHours = 2)` | **Buffer removed as a separate concept** — the natural gap between the two fixed slots (5:00 PM checkout → 7:00 PM next check-in, and 6:00 AM checkout → 8:00 AM next check-in) *is* the 2-hour cleaning buffer, so `hasConflict()` no longer takes a `$bufferHours` parameter at all |
| 12–24 hour policy | Explicit `$hoursStay < 12` / `> 24` validation in every booking-creation controller | **Removed entirely** — moot now that duration is fixed per slot (Day = 9 hrs, Night = 11 hrs); a guest simply can't submit an invalid duration since there's no time input to misuse |
| Pricing | Segment table already existed (Mon–Thu / Fri–Sun-before-6PM / Sun-after-6PM) | **Unchanged, and confirmed still correct** — the new Night slot's fixed 7:00 PM check-in lands exactly past the existing "Sunday 6:00 PM" cutoff in `Property::getPackagePrice()`, so a Sunday-night booking still correctly reverts to the regular ₱4,000 rate while a Sunday-day booking stays at the peak ₱6,000 rate. No pricing code changed. |
| Extend Stay (admin, checked-in guests only) | Free-choice new check-out date/time | **Unchanged on purpose** — this extends an *existing* checked-in stay, not a new booking, so it deliberately stays free-choice (`hasConflict()` call updated only to drop the removed buffer argument) |
| Pre-existing test data | 30 bookings (+1 already-trashed) made under the old free-time system, plus their payments/reviews/notifications | **Fully wiped** (2026-08-08) — all 31 bookings hard-deleted (cascaded 24 payments + 7 reviews), 14 orphaned housekeeping tasks unlinked (not deleted), Villa status reset to `available`, and 97 stale notifications referencing the deleted bookings/reviews removed (kept 12 legitimate "New Guest Registered" notifications). Clean slate for testing the new fixed-slot flow. |

**Implementation:** `App\Models\Booking::SLOTS` (the two slot definitions) and `Booking::slotDateTimes($slot, $checkInDate)` (the single source of truth that turns a slot + date into a check-in/check-out `Carbon` pair) in `app/Models/Booking.php` — every controller that creates or reschedules a booking calls this instead of parsing raw time input. `Booking::slotKey()` does the reverse (existing booking → `'day'`/`'night'`/`null`) for pre-filling the reschedule form.

**Auto check-in/out unaffected:** `bookings:auto-checkinout` (`AutoCheckInOutBookings`) was re-verified against this change and needed **no code changes** — it was already reading the actual stored `check_in_time`/`check_out_time` values rather than assuming any particular time, so it works identically whether those values came from the old free-choice system or the new fixed slots (including the Night slot's overnight check-out correctly crossing midnight).

---

## What Changed in v5.0 (Read This First)

Version 5.0 is a large customer-facing and admin-facing feature pass, built incrementally over one extended session: it fills in the customer self-service gaps that were still missing after v4.0 (profile management, reviews, payment history, rescheduling, 2FA), adds automated content moderation so reviews can auto-publish instead of always waiting on an admin, makes the public portal fully data-driven instead of hardcoded, simplifies Property Management to match the single-villa reality more completely, and — the last item worked on — makes every notification across both the customer and admin portals actually clickable, which surfaced and fixed several real, previously-unnoticed bugs along the way.

| Area | v4.0 (Before) | v5.0 (Now) |
|---|---|---|
| Customer account | No self-service profile editing, password change, avatar, or account closure | Full **Profile Management** (`/my/profile`): edit info + avatar, change password, deactivate account (soft, keeps booking/payment/review history) |
| Customer reviews | Submit-only (`create`/`store`); no way to see, edit, or delete a submitted review | Full **My Reviews** (`/my/reviews`): list, edit (re-triggers moderation), delete |
| Customer payments | Payment history only visible per-booking on the booking detail page | Consolidated **My Payments** (`/my/payments`) page across all bookings |
| Rescheduling | Not possible — guest had to cancel and rebook | **Reschedule Booking** — guest can move an existing pending/confirmed booking to new dates/times; price difference is auto-refunded or billed as a new balance, reusing the same conflict/pricing logic as new bookings |
| Review moderation | Every review sat as `pending` until an admin manually approved it | **Automated moderation** — a fast keyword/regex pre-filter (profanity, phone numbers, emails, links) plus an LLM classification pass (reusing the existing Groq integration) auto-publishes clean reviews immediately; anything flagged (or if the AI is unavailable) falls back to the same admin approval queue as before, now with the specific reason shown to the admin |
| Login security | Password-only login | Optional **email-OTP 2FA**, opt-in per customer, that only challenges a *new/unrecognized device* (not every login) — trusted devices are remembered for 60 days; customers can see and revoke their trusted devices and view recent login history from their Profile page |
| Public portal — testimonials | 3 hardcoded fake reviews | Pulls the 3 latest real **approved** reviews, plus a new "See All Reviews" page listing every approved review |
| Public portal — contact info | Phone/email/address hardcoded in 3 different places (Contact section, Location section, Footer) | All pulled live from **Admin Settings**, kept in sync everywhere automatically |
| Public portal — contact form | `action="#"` — didn't submit anywhere | Actually sends a real email to the resort's configured address, with validation and a reply-to set to the sender |
| Public portal — social links | Facebook/Instagram/Twitter/WhatsApp icons, all `href="#"` | Only **Facebook + TikTok** (the two the resort actually has), pulling real admin-configured URLs |
| Admin → Property Management | Type dropdown still offered `villa`/`cottage`/`room`/`hall` (2 of those unused everywhere in the app); Status, Sort Order, Amenities, Pricing, Description all shown on every property regardless of type; amenities list hardcoded in two diverging copies | `cottage`/`hall` removed from the type enum entirely; once a Villa exists, adding another property is just "Add Room" (no type choice); Status and Sort Order removed from the form entirely (status stays fully system-managed by the check-in/out flow); Room-type properties no longer collect Amenities/Pricing/Description (those live on the Villa only) and no longer require a name; the amenities checklist is now a single admin-managed list (Settings → Amenities) instead of hardcoded |
| Payment status | Several payment-recording code paths never set `status`, so real, completed payments silently sat as `pending` (the column's DB default) forever | Fixed at all 5 creation sites (front-desk walk-in, front-desk record-payment, PayMongo success callback, admin manual record, admin refund); backfilled 21 existing mis-stamped rows to `success` |
| Notifications | Plain text, not clickable, on either portal. Admin's notification dropdown only ever had real data on the Dashboard page — every other admin page silently showed "No notifications yet" because of a copy-pasted, un-integrated partial | Every notification (customer and admin) now links to its actual booking/review/user and marks itself read when opened; the admin bell+dropdown were structurally broken (split across two unrelated places in the page, and only fed data by one controller) — rebuilt as one shared, always-present component fed by a global View Composer; a real "New review submitted" admin-notification bug (`paymentRecorded()` didn't exist, so recording a manual payment as admin crashed) was found and fixed in the same pass; 151 of 203 pre-existing notifications were backfilled with real links, and a subtler bug — links baking in whatever `APP_URL`/ngrok host was active at creation time, going dead the moment that tunnel changed — was caught and fixed by switching to relative URLs everywhere |

---

## What Changed in v4.0 (Read This First)

Version 3.0 modeled the resort as a **hotel with individually bookable rooms/villas**. During review, it was corrected that Villa Elena is actually **one single Villa, rented out in its entirety** — guests never book an individual room; they book the whole property exclusively for their group, and it comes with all rooms included. Version 4.0 re-architects the booking system around that reality without requiring a full schema rewrite.

| Area | v3.0 (Before) | v4.0 (Now) |
|---|---|---|
| `properties` table | Multiple independent rows, each separately bookable (Villa A–F) | **One master `type=villa` record** ("Villa Elena (Whole Villa)") is the only bookable listing. The old rows became `type=room` (Room A–F) — informational/status-only, not separately bookable. |
| Booking unit | Per-property (per-room) | Per-**Villa** — exclusive use of the entire resort for the date range |
| Availability check | Date-only overlap, per property | Date **+ time**, with a **2-hour cleaning buffer**, checked against the single Villa's bookings |
| Check-in/out | Date only | Date **and time** (`check_in_time`, `check_out_time` columns added to `bookings`) |
| Pricing | Simple weekday/weekend per-night rate, summed across nights | **Flat/package price based on the check-in day + time segment** (see [Pricing Model](#7-pricing-model-v40)) — bookings are 12–24 hour packages, not per-night stays |
| Homepage | Grid of multiple villa/room cards | **Single "big showcase" section** for Villa Elena, with a room-status strip and one "Book Now" CTA |
| Availability calendar | None | **FullCalendar**-based visual calendar (same library as the admin calendar module) on the property detail page, showing booked date ranges with times |
| Extra charges | `booking_extras` table existed but wasn't wired into any UI | Admin can now add/remove extra amenity charges directly from the booking detail page; totals auto-recalculate |

**Data migration note:** A one-time seeder (`ConvertVillasToRoomsSeeder`) converts the legacy Villa A–F rows into Room A–F (`type=room`) and creates the new master Villa record. See [Section 6.3](#63-property-management).

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Technology Stack](#2-technology-stack)
3. [System Architecture](#3-system-architecture)
4. [Database Schema](#4-database-schema)
5. [User Roles & Accounts](#5-user-roles--accounts)
6. [Modules Completed](#6-modules-completed)
   - [Authentication System](#61-authentication-system)
   - [Admin Dashboard](#62-admin-dashboard)
   - [Property Management](#63-property-management)
   - [Booking Management](#64-booking-management)
   - [Guest Management](#65-guest-management)
   - [Reports & Analytics](#66-reports--analytics)
   - [Settings](#67-settings)
   - [Customer Portal](#68-customer-portal)
   - [Public Booking Page](#69-public-booking-page)
   - [Staff Portal](#610-staff-portal)
   - [Payments Page (Admin)](#611-payments-page-admin)
   - [Online Payments — PayMongo](#612-online-payments--paymongo)
   - [Reviews Module](#613-reviews-module)
   - [Admin Notifications](#614-admin-notifications)
   - [Dashboard Search & Bell](#615-dashboard-search--bell)
   - [AI Smart Insights](#616-ai-smart-insights)
   - [AI Forecasting](#617-ai-forecasting)
   - [AI Chatbot](#618-ai-chatbot)
7. [Pricing Model (v4.0)](#7-pricing-model-v40)
8. [Booking Availability & Fixed Slots (v5.1)](#8-booking-availability--fixed-slots-v51)
9. [AI Integration](#9-ai-integration)
10. [Payment Integration](#10-payment-integration)
11. [File Structure](#11-file-structure)
12. [Routes Summary](#12-routes-summary)
13. [Known Issues Fixed](#13-known-issues-fixed)
14. [Build Progress Summary](#14-build-progress-summary)

---

## 1. Project Overview

Villa Elena Resort Management System is a full-stack web application built to manage all operations of **Villa Elena Private Rental Resort** — a single villa property with 6 rooms, rented out **exclusively as a whole** to one guest group at a time (not a hotel with independently bookable rooms). The system covers the complete resort lifecycle — from public browsing and online booking, to admin management, staff frontdesk operations, and guest self-service.

The system has **three portals**:
- **Admin Panel** — full control of all resort operations
- **Staff Portal** — frontdesk, walk-in bookings, check-in/out, payment recording, housekeeping
- **Customer Portal** — guest self-service for bookings, payments, notifications, and reviews

Public-facing customers interact with a **fourth surface**, the Public Portal (homepage, property detail, booking flow) — not authenticated, open to anyone browsing.

---

## 2. Technology Stack

| Layer | Technology | Version |
|---|---|---|
| Backend Framework | Laravel | 12.x |
| Language | PHP | 8.2 |
| Database | MySQL | 8.0 |
| Local Server | Laravel Dev Server | `php artisan serve` |
| Build Tool | Vite (`laravel-vite-plugin`) | multi-entry — see [File Structure](#11-file-structure) for the per-section `resources/css/`/`resources/js/` split (`npm run dev` / `npm run build`) |
| Frontend CSS | Bootstrap | 5.3 (npm package, was CDN) |
| Icons | Bootstrap Icons | 1.11 (npm package, was CDN) |
| Charts | Chart.js | 4.5 (npm package, was CDN) |
| Calendar | **FullCalendar** | 6.1.11 (npm package, was CDN) — used in both the admin calendar module and the customer-facing availability calendar |
| Fonts | Playfair Display + DM Sans / Jost | Google Fonts |
| AI Provider | Groq API | llama-3.1-8b-instant |
| Payment Gateway | PayMongo | Sandbox / Live |

### Local Development Configuration

| Setting | Value |
|---|---|
| Project Root | `C:\xampp\htdocs\villa-elena\` |
| Web URL | `http://127.0.0.1:8000` |
| Database | `villa_elena_db` |
| DB User | `root` (no password) |
| Session Driver | `file` |
| Cache Driver | `file` |
| Queue Driver | `sync` |

---

## 3. System Architecture

### Route Files

| File | Prefix | Middleware | Purpose |
|---|---|---|---|
| `routes/web.php` | `/` | none | Public portal + auth + payment routes |
| `routes/admin.php` | `/admin/` | `auth, role:admin` | Admin panel |
| `routes/staff.php` | `/staff/` | `auth, role:staff,admin` | Staff portal |
| `routes/customer.php` | `/my/` | `auth, role:customer` | Customer portal |

### Role Middleware

Registered in `bootstrap/app.php` under `withMiddleware` aliases:

```php
$middleware->alias([
    'role' => \App\Http\Middleware\RoleMiddleware::class,
]);
```

### After Login Redirects

| Role | Redirects To |
|---|---|
| admin | `/admin/dashboard` |
| staff | `/staff/frontdesk` |
| customer | `/my/` |

---

## 4. Database Schema

Managed via Laravel migrations with sequential timestamps to resolve foreign key order. **v5.0 adds 2 new tables** (`trusted_devices`, `login_activities`) for the 2FA/login-history feature — everything else reuses existing tables with new columns.

| # | Table | Purpose |
|---|---|---|
| 01 | `users` | All user accounts (admin, staff, customer) |
| 02 | `properties` | Villa + Room records (see note below) |
| 03 | `property_images` | Images attached to each property |
| 04 | `bookings` | All reservation records (now includes check-in/out **time**) |
| 05 | `payments` | Payment transactions per booking |
| 06 | `reviews` | Guest reviews and star ratings (now with automated-moderation flag) |
| 07 | `booking_extras` | Add-on/extra amenity charges per booking |
| 08 | `pricing_rules` | Date-range pricing overrides (e.g. holidays) — takes priority over the standard day/time pricing segments |
| 09 | `discounts` | Promo codes and discount rules |
| 10 | `notifications` | In-app notifications for all users (now with a click-through `link`) |
| 11 | `housekeeping_tasks` | Housekeeping job assignments |
| 12 | `packages` | Bundled booking packages |
| 13 | `availability_blocks` | Manual date blocks per property |
| 14 | `staff_logs` | Audit trail of all admin/staff actions |
| 15 | `settings` | Key-value system configuration (now also stores the admin-managed amenities list) |
| 16 | **`trusted_devices`** ← **NEW v5.0** | Devices a customer has verified via 2FA email OTP; lets a device skip OTP on future logins and lets the customer view/revoke them |
| 17 | **`login_activities`** ← **NEW v5.0** | Read-only per-login history (device, IP, timestamp, whether it required OTP) shown on the customer Profile page |

### v5.0 Schema Changes

```sql
-- users: opt-in 2FA flag
ALTER TABLE users ADD COLUMN two_factor_enabled BOOLEAN NOT NULL DEFAULT FALSE AFTER status;

-- trusted_devices (new table)
CREATE TABLE trusted_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,       -- raw value lives in a browser cookie
    device_label VARCHAR(255) NULL,          -- e.g. "Chrome on Windows"
    ip_address VARCHAR(45) NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,           -- 60 days from creation/last use
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- login_activities (new table)
CREATE TABLE login_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NULL,
    device_label VARCHAR(255) NULL,
    via_new_device_otp BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- reviews: automated-moderation reason (shown to admin when a review is auto-held)
ALTER TABLE reviews ADD COLUMN flag_reason VARCHAR(255) NULL AFTER admin_note;

-- properties: Room-type properties no longer require a name (Villa still does, at the app-validation level)
ALTER TABLE properties MODIFY property_name VARCHAR(150) NULL;

-- properties: cottage/hall removed — only villa/room are meaningful in this deployment
ALTER TABLE properties MODIFY type ENUM('villa', 'room') NOT NULL;

-- notifications: click-through destination
ALTER TABLE notifications ADD COLUMN link VARCHAR(255) NULL AFTER message;
```

**Data backfills applied (one-time, not repeatable via UI):**
- `payments.status` — 21 existing rows that should have been `success` (front-desk/PayMongo/admin payment recording never set the column, so they sat on the `pending` default) were corrected. All 5 creation call sites now set `status` explicitly going forward.
- `notifications.link` — 151 of 203 pre-existing notifications had a destination recovered by parsing their title/message for a `booking_ref` or review-related wording; the remaining 52 reference bookings that no longer exist and were left `null` (falls back to the notifications list page, not an error).

### v4.0 Schema Changes

```sql
-- bookings: added time columns alongside the existing date columns
ALTER TABLE bookings ADD COLUMN check_in_time  TIME NOT NULL DEFAULT '14:00:00' AFTER check_in_date;
ALTER TABLE bookings ADD COLUMN check_out_time TIME NOT NULL DEFAULT '12:00:00' AFTER check_out_date;
```

**Migration file:** `2026_07_14_000001_add_checkin_checkout_time_to_bookings_table.php`

**Data convention (no schema change, just data + usage):**
- `properties.type = 'villa'` → the single **master, bookable** record (only one should ever exist: "Villa Elena (Whole Villa)")
- `properties.type = 'room'` → one of the 6 individual rooms — **informational only** (status badge, images, housekeeping). Never independently bookable; never appears in customer-facing listings.
- A one-time seeder (`ConvertVillasToRoomsSeeder`) converted the legacy "Villa A"–"Villa F" rows to "Room A"–"Room F" (`type=room`) and created the new master Villa record, with `base_price`/`weekend_price` initially summed from the 6 rooms (later manually corrected — see [Pricing Model](#7-pricing-model-v40)).

### Important Column Notes

- `settings` table uses: `setting_key`, `setting_value`, `data_type`, `description`
- `housekeeping_tasks.task_type` enum: `checkout_clean`, `daily_clean`, `maintenance`, `inspection`
- `notifications` uses `is_read` (integer 0/1), `type` enum: `email`, `sms`, `in_app`, `booking_update`, `payment`, `cancellation`, `reminder`. In practice every row ever created uses `type='in_app'` — the other enum values are unused/dead (the icon-mapping logic in the views that branches on them never actually matches). **`link`** (NEW v5.0) — nullable relative URL path (e.g. `/admin/bookings/29`), used by `{customer,admin}.notifications.open` to redirect-and-mark-read. **Always generate with `route(..., false)`** (relative) — an absolute URL bakes in whatever `APP_URL`/ngrok host was active when the notification was created, which goes dead the moment that tunnel changes (this exact bug shipped once and was fixed — see [Known Issues Fixed](#13-known-issues-fixed)).
- `bookings.booking_ref` auto-generated in format `VE-XXXXXXXX`
- `bookings.paymongo_session_id` — stores PayMongo checkout session ID during payment
- `bookings.paymongo_payment_type` — stores `deposit` or `full_payment`
- `bookings.check_in_time` / `check_out_time` — **(v4.0)** added alongside the date columns for conflict checking and package pricing. **(v5.1)** No longer free-choice at the app level — every new booking's time comes from `Booking::slotDateTimes()` and is always exactly `08:00`/`17:00` (Day) or `19:00`/`06:00` (Night); the DB columns themselves are still plain `TIME` with no CHECK constraint, so this is enforced in application code, not the schema
- `users.status` cast as integer; `isActive()` checks `(int)$this->status === 1`
- `properties.base_price` — **(v4.0 meaning changed)** off-peak flat package rate (Mon–Thu, and Sun after 6PM)
- `properties.weekend_price` — **(v4.0 meaning changed)** peak flat package rate (Fri, Sat, Sun until 6PM)
- `properties.amenities` — stored as JSON array. Since v5.0, the checklist of *available* amenity options is no longer hardcoded — it's admin-managed at Settings → Amenities, stored as a JSON array under the `property_amenities` key in `settings` (`Setting::get('property_amenities')`), and only shown on the Villa's create/edit form (Room-type properties no longer collect amenities at all).
- `properties.type` — **(v5.0 — narrowed)** enum `villa`, `room` (was `villa`, `cottage`, `room`, `hall` — `cottage`/`hall` were dropped from the DB enum, since nothing in the app ever handled them). Exactly 1 `villa` row, 4 `room` rows in this deployment.
- `properties.property_name` — **(v5.0)** nullable. Room-type properties no longer require a name (they're purely informational sub-units of the Villa); the Villa's own name is still required at the application-validation level even though the column itself allows null.
- `properties.status` — **(v5.0)** no longer exposed on the admin create/edit form at all — it remains fully automatic, flipped only by the booking check-in/check-out lifecycle (front-desk actions, the `bookings:auto-checkinout` scheduled command, admin booking status changes) and still gates whether the Villa is hidden from the public portal listing when `maintenance`.
- `payments.payment_method` enum includes: `cash`, `gcash`, `bank_transfer`, `credit_card`, `online`, `card`, `paymaya`, `grab_pay`
- `payments.reference_number` — stores PayMongo payment ID for online payments
- `payments.status` — **(v5.0 — bug fixed)** enum `pending`, `success`, `failed`, `refunded`, defaults to `pending` at the DB level. Several creation code paths (front-desk walk-in, front-desk record-payment, PayMongo success callback, admin manual record, admin refund) never set this explicitly, so real completed payments silently stayed `pending` forever — nothing in the app actually reads/filters on this column for business logic (only displayed), but it was misleading in the customer-facing Payment History page. All 5 sites now set `'status' => 'success'` explicitly; 21 pre-existing rows were backfilled.
- `reviews.status` enum: `pending`, `approved`, `rejected`. **(v5.0)** Reviews now usually skip straight to `approved` via automated moderation — see [Reviews Module](#613-reviews-module) — `pending` is now mostly reserved for auto-flagged content or the (rare) case where the moderation AI call fails.
- `reviews.admin_reply` — management response to review
- `reviews.admin_note` — internal rejection reason (set by an admin)
- `reviews.flag_reason` — **(NEW v5.0)** why the automated moderation service held a review for manual review (e.g. `"Auto-flagged: possible PH mobile number detected"`); `null` for reviews that were never flagged, including ones sitting `pending` only because the moderation AI call failed (fail-open, not a policy violation).
- `booking_extras` — `booking_id`, `item_name`, `description`, `quantity`, `unit_price`, `total`; manageable directly from the admin booking detail page
- `users.two_factor_enabled` — **(NEW v5.0)** boolean, default `false`. Opt-in per customer via Profile → Security.

### ALTER TABLE Fixes Applied

```sql
-- Notifications: added booking_update type
ALTER TABLE notifications MODIFY COLUMN type ENUM('email','sms','in_app','booking_update','payment','cancellation','reminder');

-- Housekeeping: fixed task_type enum
ALTER TABLE housekeeping_tasks MODIFY COLUMN task_type ENUM('checkout_clean','daily_clean','maintenance','inspection');

-- Payments: added online and other methods
ALTER TABLE payments MODIFY COLUMN payment_method ENUM('cash','gcash','bank_transfer','credit_card','online','card','paymaya','grab_pay');

-- Payments: added full_payment type
ALTER TABLE payments MODIFY COLUMN payment_type ENUM('deposit','full_payment','partial','balance','refund');

-- Payments: added reference_number and received_by columns
ALTER TABLE payments ADD COLUMN reference_number VARCHAR(255) NULL AFTER payment_date;
ALTER TABLE payments ADD COLUMN received_by BIGINT UNSIGNED NULL AFTER reference_number;

-- Bookings: added PayMongo session tracking
ALTER TABLE bookings ADD COLUMN paymongo_session_id VARCHAR(255) NULL AFTER source;
ALTER TABLE bookings ADD COLUMN paymongo_payment_type VARCHAR(50) NULL AFTER paymongo_session_id;

-- Reviews: added admin interaction columns
ALTER TABLE reviews
    ADD COLUMN title VARCHAR(200) NULL AFTER rating,
    ADD COLUMN admin_reply TEXT NULL AFTER content,
    ADD COLUMN admin_reply_at TIMESTAMP NULL AFTER admin_reply,
    ADD COLUMN admin_note VARCHAR(300) NULL AFTER admin_reply_at,
    ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending';

-- v4.0: Bookings — check-in/out time columns
ALTER TABLE bookings ADD COLUMN check_in_time  TIME NOT NULL DEFAULT '14:00:00' AFTER check_in_date;
ALTER TABLE bookings ADD COLUMN check_out_time TIME NOT NULL DEFAULT '12:00:00' AFTER check_out_date;
```

---

## 5. User Roles & Accounts

### Seeded Accounts

| Role | Email | Password | Redirects To |
|---|---|---|---|
| Admin | `admin@villaelenareosrt.com` | `AdminTest123!` ⚠️ **changed during v5.0 testing, not reset back** | `/admin/dashboard` |
| Staff | `staff@villaelenareosrt.com` | `StaffTest123!` ⚠️ **changed during v5.0 testing, not reset back** | `/staff/frontdesk` |
| Customer | `guest@example.com` | `Guest@1234` (unchanged — test edits during v5.0 were always reset back to this) | `/my/` |

> ⚠️ **Action recommended:** the admin and staff passwords above are temporary values left over from live testing this session (the original hash wasn't captured before the first change, so it couldn't be restored). Change them to something real via Admin → Settings / the profile password form before this goes anywhere near production.

### Role Permissions

| Action | Admin | Staff | Customer |
|---|---|---|---|
| Manage properties (Villa + Rooms) | ✅ | ❌ | ❌ |
| Confirm/cancel bookings | ✅ | ❌ | ❌ |
| Add/remove extra charges on a booking | ✅ | ❌ | ❌ |
| Create walk-in bookings | ✅ | ✅ | ❌ |
| Check-in / Check-out | ✅ | ✅ | ❌ |
| Record payments | ✅ | ✅ | ❌ |
| Manage housekeeping tasks | ✅ | ✅ | ❌ |
| View reports & analytics | ✅ | ❌ | ❌ |
| Moderate reviews | ✅ | ❌ | ❌ |
| Manage settings | ✅ | ❌ | ❌ |
| View own bookings | ✅ | ✅ | ✅ |
| Cancel own booking | ❌ | ❌ | ✅ |
| Pay online (PayMongo) | ❌ | ❌ | ✅ |
| Submit reviews | ❌ | ❌ | ✅ |
| Browse & book the Villa | ✅ | ✅ | ✅ |

---

## 6. Modules Completed

### 6.1 Authentication System

**Files:**
- `app/Http/Controllers/Auth/AuthController.php`
- `app/Http/Middleware/RoleMiddleware.php`
- `resources/views/auth/` — login, register, forgot-password, reset-password

**Features:**
- Login with email and password
- Registration for new guests (triggers admin notification)
- Forgot/reset password flow
- Role-based redirects after login
- Account deactivation check on login

---

### 6.2 Admin Dashboard

**File:** `resources/views/admin/dashboard/index.blade.php`
**Controller:** `app/Http/Controllers/Admin/DashboardController.php`
**Route:** `GET /admin/dashboard` → `admin.dashboard`

**Features:**
- 8 KPI cards: Total Bookings, Revenue Today, Revenue This Month, Check-ins Today, Check-outs Today, Pending Bookings, Available Rooms, Total Guests
- Revenue chart (Chart.js — monthly bar)
- Booking sources donut chart
- Recent bookings table with status badges
- **🔔 Notification Bell** — dropdown with latest notifications, unread dot, mark-all-read
- **🔍 Search Modal** — live search across bookings, guests, properties (`Ctrl+K` shortcut)

**Revenue Fix:** `revenue_today` and `revenue_this_month` query from `payments` table using `payment_date` column, excluding refunds.

---

### 6.3 Property Management

**Controller:** `app/Http/Controllers/Admin/PropertyController.php`
**Views:** `resources/views/admin/properties/` (index, create, edit, show)
**Routes:** `admin.properties.*`

**v4.0 — Villa vs. Room model:**
- The property list, create, edit, and detail pages now visually distinguish the two roles a `properties` row can play:
  - **🟢 MASTER · BOOKABLE** badge — `type=villa`. Only one such record should exist (`Villa Elena (Whole Villa)`). This is the only listing customers can actually book.
  - **⚪ PART OF VILLA** badge — `type=room`. The individual rooms; status/reference only (available / occupied / maintenance), no independent booking, no independent booking/review stats.
- **Villa-uniqueness warning:** the Edit form still shows a warning banner if the admin tries to switch a Room to type "Villa" while a master Villa record already exists.
- The property **Show** page displays a contextual banner depending on type (Villa = "this is the master, bookable record"; Room = "info-only, 0 bookings/reviews here is expected") and clarifies that the **Block Dates** feature only affects real customer-facing availability when used on the Villa record — blocking a Room's dates is informational/internal only.

**v5.0 — Forms simplified to match the single-villa reality more completely:**
- **Villa-first, Room-after:** the Create form no longer offers a Villa/Room type choice once a master Villa already exists — it's forced to `type=room` via a hidden input, with a short note ("Adding a Room — part of Villa Elena") replacing the dropdown. The dropdown only appears at all for the very first property ever created. (The Edit form still allows switching an existing record's type, unchanged from v4.0.)
- **`cottage`/`hall` removed** from the `type` enum entirely (DB-level `ALTER TABLE`) — confirmed via a full audit that nothing in the app (portal, booking flow, pricing) ever handled those two values; they were leftover options from an earlier, more generic multi-property-type design.
- **Status field removed from the create/edit forms** — it's misleading to let an admin manually set something the check-in/check-out flow already manages automatically (see `properties.status` note above). The automatic behavior (occupancy flips, maintenance-hides-from-portal) is completely unchanged; only the manual form input is gone.
- **Sort Order removed** from both forms (kept its DB default of `0`, simply no longer exposed as an editable field).
- **Room-type properties no longer collect** Amenities, Pricing (base/weekend price), or Description — those fields now only appear on the Villa's form, since a Room's own values were already confirmed unused by any pricing/booking code path (bookings always reference the Villa's `property_id`). Room's `base_price` defaults to `0` server-side; Room's name is optional (see schema note above).
- **Amenities list is now admin-managed**, not hardcoded — see [Settings](#67-settings) → Amenities tab. `Admin\PropertyController` reads it via a small `amenityList()` helper (`Setting::get('property_amenities', ...)`, falling back to the original hardcoded list only as a first-run seed value).

**Known bug fixed (v4.0):** `PropertyController::update()` referenced an undefined `$old` variable when logging to `StaffLog` (`Undefined variable $old`, 500 error on `PUT /admin/properties/{id}`). Fixed by capturing `$oldData = $property->toArray()` before the update call.

---

### 6.4 Booking Management

**Controller:** `app/Http/Controllers/Admin/BookingController.php`
**Views:** `resources/views/admin/bookings/` (index, show, create)
**Routes:** `admin.bookings.*`

**v4.0 — What changed:**
- Booking creation/validation now requires **check-in/check-out time** (`check_in_time`, `check_out_time`), not just dates. **(v5.1)** These are no longer free-choice — the guest/staff/admin picks a **slot** (`day` or `night`, see [Section 8](#8-booking-availability--fixed-slots-v51)) and the times are derived server-side via `Booking::slotDateTimes()`.
- Availability checking uses `Booking::hasConflict()` (see [Booking Availability & Fixed Slots](#8-booking-availability--fixed-slots-v51)) instead of a date-only overlap query.
- Pricing uses `Property::getPackagePrice()` — a flat rate per booking based on the check-in day/time segment (see [Pricing Model](#7-pricing-model-v40)) rather than a per-night sum.
- **Extra Charges (NEW):** the booking Show page has an "Extra Charges" panel — admin can add ad-hoc amenity/add-on charges (item name, quantity, unit price) for anything a guest requests outside the standard package. Totals (`extras_amount`, `total_amount`, `balance_due`, `payment_status`) recalculate automatically on add/remove.
  - Routes: `POST /admin/bookings/{booking}/extras` (`admin.bookings.extras.store`), `DELETE /admin/bookings/{booking}/extras/{extra}` (`admin.bookings.extras.destroy`)
  - Controller methods: `storeExtra()`, `destroyExtra()`, `recalculateBookingTotals()` (private helper)
- Admin-created bookings are **not** subject to the 24-hour online booking limit (see below) — admin has discretion for extensions.

**Booking Status Workflow:**

| From | To | Side Effects |
|---|---|---|
| `pending` | `confirmed` | Manual admin confirmation |
| `confirmed` | `checked_in` | Property → occupied; housekeeping task (`checkout_clean`) created |
| `checked_in` | `checked_out` | Property → available; housekeeping task activated |
| `pending/confirmed` | `cancelled` | Property → available; guest notified; admin notified |

**Extra Routes:**
```
PATCH  /admin/bookings/{booking}/status          → admin.bookings.status
POST   /admin/bookings/{booking}/payment         → admin.bookings.payment
POST   /admin/bookings/{booking}/extras          → admin.bookings.extras.store
DELETE /admin/bookings/{booking}/extras/{extra}  → admin.bookings.extras.destroy
GET    /admin/bookings/lookup                    → admin.bookings.lookup  (AJAX)
```

---

### 6.5 Guest Management

**Controller:** `app/Http/Controllers/Admin/UserController.php`
**Views:** `resources/views/admin/users/` (index, show, form)
**Routes:** `admin.users.*`

**Features:**
- List guests and staff with role filter
- View guest profile with booking history
- Create/edit/delete accounts
- Activate/deactivate toggle
- Shared form blade for create and edit

---

### 6.6 Reports & Analytics

**Controller:** `app/Http/Controllers/Admin/ReportController.php`
**View:** `resources/views/admin/reports/index.blade.php`
**Route:** `GET /admin/reports` → `admin.reports.index`

**Features:**
- 6 Chart.js charts: Monthly Revenue, Monthly Bookings, Occupancy Rate, Booking Sources, Revenue by Property, Cancellation Rate
- 5 Period filters + custom date range
- KPI summary cards
- Top properties table

---

### 6.7 Settings

**Controller:** `app/Http/Controllers/Admin/SettingsController.php`
**Route:** `admin.settings.index` / `admin.settings.update`

**7 Tabs:** Resort Info, Booking Rules, Payments, Notifications, **Amenities** ← NEW v5.0, Social & Links, System

**Setting Model helpers:**
```php
Setting::get('resort_name', 'Villa Elena')  // cached 3600s
Setting::set('resort_name', 'New Name')     // upsert + cache clear
```

**v5.0 additions:**
- **Amenities tab (NEW)** — a repeatable text-input list (add/remove rows, vanilla JS) for managing the amenity checklist shown on the Villa property form. Stored as a JSON array under the `property_amenities` setting key (`Setting::get('property_amenities')` / decoded in `Admin\PropertyController::amenityList()`). Previously this list was hardcoded independently in two different Blade files (create + edit), which had already drifted out of sync with each other.
- **Social & Links tab** — swapped the unused `instagram_url` field for `tiktok_url` (the resort has Facebook + TikTok, not Instagram/Twitter/WhatsApp). `resort_email`, `resort_phone`, `resort_address`, and `facebook_url` already existed here but were never actually read by the public portal until v5.0 — see [Public Booking Page](#69-public-booking-page).

---

### 6.8 Customer Portal

**Controllers:** `app/Http/Controllers/Customer/{HomeController,ProfileController,ReviewController,PaymentController,BookingController}.php`
**Views:** `resources/views/customer/`
**Route prefix:** `/my/` → `customer.*`

| Page | Route | Description |
|---|---|---|
| Dashboard | `customer.home` | Stats, upcoming stays, recent bookings, notifications |
| My Bookings | `customer.bookings` | Paginated list with status filter |
| Booking Detail | `customer.bookings.show` | Full info, price breakdown, payment history, Pay Now / Reschedule / Write Review buttons |
| Cancel Booking | `customer.bookings.cancel` | Guest cancellation with reason |
| **Reschedule Booking** ← NEW v5.0 | `customer.bookings.reschedule` / `.update` | Move a `pending`/`confirmed` booking to new dates/times |
| **My Reviews** ← NEW v5.0 | `customer.reviews.index` / `.edit` / `.update` / `.destroy` | View, edit, delete previously-submitted reviews |
| **My Payments** ← NEW v5.0 | `customer.payments.index` | Consolidated payment history across all bookings |
| **Profile** ← NEW v5.0 | `customer.profile.edit` / `.update` / `.password` / `.deactivate` | Edit info + avatar, change password, deactivate account (soft) |
| **Security (2FA)** ← NEW v5.0 | `customer.profile.2fa.toggle` / `.devices.destroy` | Toggle email-OTP 2FA, manage trusted devices |
| Notifications | `customer.notifications` / `.notifications.open` | All notifications, now **click-through to the relevant booking/review** + per-item mark-as-read |

**v5.0 — Profile Management** (`ProfileController`, `resources/views/customer/profile.blade.php`):
- Edit name/phone/address/ID info + avatar upload (`Storage::disk('public')`, replaces old avatar on change).
- Change password — requires current password, `min:8|confirmed`, rotates `remember_token`.
- **Deactivate account** (soft delete via `status = 0`, not a hard `DELETE`) — chosen deliberately because `bookings`/`reviews`/`notifications` all `cascadeOnDelete()` on `user_id`; a real delete would silently wipe a guest's entire booking/payment/review history. Deactivating logs the guest out and blocks future logins (same convention as `Admin\UserController::toggleStatus()`) but keeps every record intact.

**v5.0 — My Reviews** (`Customer\ReviewController@index/edit/update/destroy`, `resources/views/customer/reviews.blade.php`): editing a review that was already `approved` re-runs it through the same automated moderation pipeline used on creation (see [Reviews Module](#613-reviews-module)) rather than blindly demoting it back to `pending` — a clean edit can stay live.

**v5.0 — My Payments** (`Customer\PaymentController@index`, `resources/views/customer/payments.blade.php`): `Payment::whereHas('booking', fn($q) => $q->where('user_id', auth()->id()))` — Payment has no direct `user_id`, only reachable via its booking.

**v5.0 — Reschedule Booking** (`Customer\BookingController@edit/update`, `resources/views/customer/reschedule_form.blade.php`): reuses the exact same validation/conflict/pricing logic as a new booking —
- `isCancellable()` gate (same `pending`/`confirmed`-only rule as cancellation).
- `Booking::hasConflict($propertyId, $checkin, $checkout, 2, $excludeBookingId: $booking->id)` — the `excludeBookingId` parameter (already existed for exactly this) stops the booking from falsely conflicting with its own current slot.
- Re-prices via `Property::getPackagePrice($checkin)`, same flat package-rate model as everywhere else.
- If the new slot is **cheaper**: auto-creates a `refund` Payment row for the difference, recomputes `amount_paid`/`balance_due`/`payment_status` from the payments table (same pattern as `cancelBooking()`).
- If **pricier**: increases `balance_due`; the guest settles it later via the existing Pay Now flow — no new payment code needed.
- No live AJAX price preview (the existing `portal.price-preview` endpoint can't exclude the booking's own slot, so it would falsely flag a conflict) — reschedule is a single synchronous submit, consistent with how `cancelBooking()` already works.

**v5.0 — 2FA / Login Activity** (`AuthController`, `TrustedDevice`/`LoginActivity` models, `App\Helpers\DeviceHelper`):
- **Method:** email OTP (6-digit code, 10-minute expiry, cached hashed via `Cache::put`) — chosen over TOTP/authenticator-app or SMS because it reuses the mail infrastructure the app already has (password reset, email verification), with zero new packages or per-message SMS cost.
- **Frequency:** only on a **new/unrecognized device**, not every login — a matching, unexpired `trusted_devices` row (matched against a `trusted_device` cookie) skips straight past the OTP step. A verified device is remembered for 60 days.
- **Enforcement:** fully optional, off by default — each customer toggles it themselves at Profile → Security. Turning it **on** is frictionless; turning it **off** requires the current password (same pattern as account deactivation — the sensitive direction needs re-confirmation, not the safe one).
- **Flow:** `login()` branches — 2FA off or device trusted → normal `Auth::attempt()` path; otherwise → generate+email the code, stash `2fa_user_id`/`2fa_remember` in the session, redirect to `two-factor.verify` (`resources/views/auth/two_factor.blade.php`). On correct code: `Auth::login()`, create the `trusted_devices` row + queue the cookie, and fall through the same `completeLogin()` helper the normal path uses (session regen, `last_login`, `StaffLog`, `LoginActivity::create()`).
- **Login Activity** is logged on **every** successful login regardless of 2FA status — shown as a simple read-only list (device label, IP, relative time, whether it required OTP) on the Profile page, alongside the Trusted Devices list (each with a "This device" tag and a Remove button that forces that device back through OTP next time).
- `DeviceHelper::label()` — a small static helper doing plain substring matching on the User-Agent string (no UA-parsing package needed) to produce labels like "Chrome on Windows".

**v5.0 — Notifications are now clickable** — see [Admin Notifications](#614-admin-notifications), the fix applies identically to the customer side (`customer.notifications.open` route, same redirect-and-mark-read pattern).

---

### 6.9 Public Booking Page

**Controller:** `app/Http/Controllers/Portal/PortalController.php`
**Views:** `resources/views/portal/`

**v4.0 — Redesigned as a single-villa showcase:**

| Page | Route | Description |
|---|---|---|
| Homepage | `home` | **Big showcase section** (not a card grid) for Villa Elena: large photo, description, guest capacity, amenities, a **room-status strip** (color-coded pills for each of the 6 rooms: available / occupied / maintenance), price, and one "Book Now" / "Reserve Now" CTA. **(v5.1)** Check-in/out selection is now a check-in **date** plus a **Day/Night slot** pick, not free-choice time. |
| Property Detail | `portal.property` | Gallery, amenities, **"Mga Kwarto sa Villa" room-status section**, pricing, **FullCalendar availability calendar** (month/list view, booked ranges shown as red events with hover tooltips showing exact times) plus a text list of booked ranges, and the date/time/guest booking form |
| Booking Form | `portal.book` | Shows the flat package rate applied (based on check-in day/time segment), special requests field, deposit info |
| Submit Booking | `portal.book.submit` | Validates the 2-hour buffer + 24-hour policy, creates booking, notifies admin + guest |
| Confirmation | `portal.confirmation` | Booking ref, what happens next |

**Guest Booking Flow:**
```
Browse Villa Elena → Pick check-in/out date & time → Login/Register →
Review flat package rate → Submit → Confirmation
```

**Homepage nav label** changed from "Rooms" to **"The Villa"** to match the single-property model.

**v5.0 — Reviews, contact info, and social links are now real, not hardcoded:**

| Page | Route | What changed |
|---|---|---|
| Homepage — Guest Voices | `home` | The 3 testimonial cards were hardcoded fake reviews ("Maria Santos", "Absolutely magical...") — now pulls the 3 latest **approved** reviews (`Review::where('status','approved')->latest()->take(3)`), with a real empty-state if none exist yet, plus a new **"See All Reviews"** button |
| **All Reviews** ← NEW v5.0 | `portal.reviews` | Full paginated list of every approved review, with average rating + total count header and any admin reply shown inline |
| Homepage — Contact / Location / Footer | `home` | Phone, email, and address were hardcoded in 3 separate places and could drift out of sync — now all pulled from `Setting::get('resort_phone'/'resort_email'/'resort_address', ...)` in `PortalController::home()`, so editing them once in Admin Settings updates everywhere |
| Homepage — Contact Form | `portal.contact.send` | The "Send Message" form posted to `action="#"` (did nothing) — now actually sends a real email via a new `App\Mail\ContactFormSubmitted` mailable to the resort's configured email, with `reply-to` set to the sender so the admin can just hit Reply, server-side validation, and a visible success/error message. Rate-limited (`throttle:5,60`) since it's a public unauthenticated form. |
| Homepage — Social Links | `home` | Was Facebook/Instagram/Twitter/WhatsApp, all `href="#"` — trimmed to just **Facebook + TikTok** (the two the resort actually uses) pulling real URLs from `facebook_url`/`tiktok_url` settings, `target="_blank"` |

---

### 6.10 Staff Portal

**Controller:** `app/Http/Controllers/Staff/FrontdeskController.php`
**Views:** `resources/views/staff/` (frontdesk, walkin)
**Route prefix:** `/staff/` → `staff.*`

**6 Tabs on Frontdesk:**

| Tab | Feature |
|---|---|
| Check-ins | Today's arrivals with one-click Check In button |
| Check-outs | Today's departures with Check Out button |
| Current Guests | All checked-in guests, nights remaining, overdue alerts |
| Pending | Unconfirmed bookings (view only) |
| Housekeeping | Start / Mark Done buttons for tasks |
| Properties | Color-coded availability grid |

**Walk-in Booking Form** (`/staff/walkin`):
- Select existing guest or create new account on-the-spot
- Live price calculator with weekday/weekend breakdown
- Record payment immediately upon booking
- Walk-in bookings auto-confirmed (no admin approval needed)
- New guest default password: `VillaElena@2026`

> ✅ **Confirmed up to date (re-checked v5.0):** `FrontDeskController::storeWalkin()` already validates `check_in_time`/`check_out_time` and prices via `getPackagePrice($checkin)`, consistent with the rest of the booking flow — no changes needed here.

**Payment Recording Modal:**
- Available on each booking row in Check-ins, Check-outs, Current Guests tabs
- Fields: amount, method (Cash/GCash/Bank/Card), type, notes
- Auto-updates `amount_paid`, `balance_due`, `payment_status`

**Check-in side effects:**
- Booking → `checked_in`, Property → `occupied`
- Housekeeping task created (`checkout_clean`) for checkout date
- Guest notified, Admin notified

**Check-out side effects:**
- Booking → `checked_out`, Property → `available`
- Housekeeping task → `in_progress`
- Guest notified, Admin notified

---

### 6.11 Payments Page (Admin)

**Controller:** `app/Http/Controllers/Admin/PaymentController.php`
**Views:** `resources/views/admin/payments/` (index, show)
**Routes:** `admin.payments.*`

**Features:**
- 5 KPI cards: Total Revenue, Today's Revenue, This Month, Pending Balance, Total Refunds
- Filter by method, type, date range, search
- Color-coded payment method and type badges
- **Record Payment modal** — type booking ref → AJAX auto-lookup guest info
- **Refund modal** — partial or full refund with reason
- Payment detail page with full booking summary

---

### 6.12 Online Payments — PayMongo

**Service:** `app/Services/PayMongoService.php`
**Controller:** `app/Http/Controllers/PaymentController.php`
**Views:** `resources/views/payment/` (checkout, success)

**Supported Payment Methods:** GCash, Credit/Debit Card, Maya, GrabPay

**Guest Payment Flow:**
```
Booking Detail → "💳 Pay Now" button
      ↓
Payment page — choose Deposit (30%) or Full Payment
      ↓
Redirect to PayMongo hosted checkout
      ↓
Guest pays via GCash / Card / Maya / GrabPay
      ↓
Redirect to /pay/{booking}/success
      ↓
Payment auto-recorded, booking auto-confirmed if deposit
Admin notified, Guest notified
```

**Environment Variables:**
```env
PAYMONGO_PUBLIC_KEY=pk_test_xxxxxxxxxxxx
PAYMONGO_SECRET_KEY=sk_test_xxxxxxxxxxxx
PAYMONGO_WEBHOOK_SECRET=whsk_xxxxxxxxxxxx
```

**Config (`config/services.php`):**
```php
'paymongo' => [
    'public_key'     => env('PAYMONGO_PUBLIC_KEY'),
    'secret_key'     => env('PAYMONGO_SECRET_KEY'),
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET', ''),
],
```

**Sandbox Test Cards:**
| Type | Card Number | CVV | Expiry |
|---|---|---|---|
| Visa (success) | `4343434343434345` | any | any future |
| Visa (fail) | `4111111111111111` | any | any |

**GCash sandbox:** any number, OTP = `111111`

**Routes:**
```
GET  /pay/{booking}           payment.page
POST /pay/{booking}/checkout  payment.checkout
GET  /pay/{booking}/success   payment.success
GET  /pay/{booking}/cancel    payment.cancel
POST /webhooks/paymongo       payment.webhook  (no CSRF)
```

---

### 6.13 Reviews Module

**Admin Controller:** `app/Http/Controllers/Admin/ReviewController.php`
**Customer Controller:** `app/Http/Controllers/Customer/ReviewController.php`
**Views:** `resources/views/admin/reviews/index.blade.php`, `resources/views/customer/review_form.blade.php`

**Review Flow (v5.0 — now with automated moderation):**
```
Guest checks out → "⭐ Write a Review" button appears
      ↓
Guest fills star rating (1–5), title, content (min 20 chars)
      ↓
ReviewModerationService::evaluate() —
  1) Fast keyword/regex pre-filter (profanity, PH mobile numbers,
     email addresses, bare URLs) — no API call, catches the
     obvious cases for free
  2) If clean, a Groq LLM classification pass (CLEAN vs FLAGGED)
  3) If the AI call fails/errors, fails OPEN to the old behavior
     (held for manual review, not silently published or rejected)
      ↓
   ┌─── CLEAN ───┐              ┌─── FLAGGED / AI unavailable ───┐
   │ status =    │              │ status = pending, flag_reason  │
   │ approved    │              │ set, Admin notified with the   │
   │ immediately │              │ specific reason                │
   └─────────────┘              └─────────────────────────────────┘
      ↓                                    ↓
Review live on the site           Admin: Approve / Reject / Reply
                                            ↓
                                   Guest notified either way
```

**`ReviewModerationService`** (`app/Services/ReviewModerationService.php`) — reuses the existing `GeminiService` (Groq/`llama-3.1-8b-instant`) rather than adding a new AI dependency. Config: `config/moderation.php` (blocked-word list, PH mobile/email/URL regex patterns).

Editing a previously-approved review re-runs the same pipeline (see [Customer Portal](#68-customer-portal) → My Reviews) instead of automatically bouncing back to `pending` on every edit.

**Admin Features:**
- 5 KPI cards: Total, Pending, Approved, Rejected, Avg Rating
- Filter by status, rating, property
- Card grid layout with color-coded top border
- Approve button — one click, guest notified
- Reject modal — optional reason, guest notified
- Reply inline — management response posted, guest notified
- Delete button with confirmation

**Customer Features:**
- "Write a Review" button appears only after `checked_out` status
- Prevents duplicate reviews per booking
- Star rating with hover animation
- Character counter (min 20 / max 1000)
- Review pending notice after submission

---

### 6.14 Admin Notifications

**Helper:** `app/Helpers/NotificationHelper.php`
**Admin controller:** `app/Http/Controllers/Admin/NotificationController.php` ← NEW v5.0

Centralized helper that creates notifications for both admins and guests. **v5.0: every notification now carries a `link` and is clickable** — see below.

**Usage:**
```php
use App\Helpers\NotificationHelper;

NotificationHelper::notifyAdmin('Title', 'Message', $link = null);
NotificationHelper::notifyGuest($userId, 'Title', 'Message', $link = null);
```

**Preset Methods** (each now computes its own `route(..., false)` link from the model it already receives):

| Method | Triggered When | Links To (admin-facing) |
|---|---|---|
| `NotificationHelper::newBooking($booking)` | Online booking submitted | `admin.bookings.show` |
| `NotificationHelper::paymentReceived($booking, $amount, $method)` | PayMongo payment success | `admin.bookings.show` |
| `NotificationHelper::paymentRecorded($booking, $amount, $method)` ← **NEW v5.0** | Admin/staff manually records a payment | `admin.bookings.show` |
| `NotificationHelper::bookingCancelled($booking, $reason)` | Guest cancels booking | `admin.bookings.show` |
| `NotificationHelper::newGuestRegistered($user)` | New guest registers | `admin.users.show` |
| `NotificationHelper::walkInBooking($booking, $staffName)` | Staff creates walk-in | `admin.bookings.show` |
| `NotificationHelper::guestCheckedIn($booking)` | Staff checks in guest | `admin.bookings.show` |
| `NotificationHelper::guestCheckedOut($booking)` | Staff checks out guest | `admin.bookings.show` |
| `NotificationHelper::refundIssued($booking, $amount, $reason)` | Admin issues refund | `admin.bookings.show` |

Guest-facing notifications (booking confirmations, payment receipts, review approve/reject/reply, etc.) are created directly via `Notification::create([...])` at ~11 call sites across `PaymentController`, `Admin\{Booking,Payment,Review}Controller`, `Staff\FrontDeskController`, and `AutoCheckInOutBookings` — every one now includes a `link` pointing to `customer.bookings.show` or `customer.reviews.index` as appropriate.

**How it works:** Finds all users with `role = admin` → creates one `Notification` record per admin → appears in the topbar bell dropdown on **every** admin page (see below — this used to only work on the Dashboard).

**Click-through (NEW v5.0):** clicking any notification (customer or admin) hits a small redirect-through route that marks *that one* notification read, then forwards to its `link` — no JS/AJAX required, works identically whether clicked from a dropdown or a full list:
- Customer: `GET /my/notifications/{notification}/open` → `customer.notifications.open` (`Customer\HomeController::openNotification()`)
- Admin: `GET /admin/notifications/{notification}/open` → `admin.notifications.open` (`Admin\NotificationController::open()`)
- Both `abort_if($notification->user_id !== auth()->id(), 403)` before touching anything.
- **New full list page for admin**, mirroring what the customer side already had: `GET /admin/notifications` → `admin.notifications.index` (`resources/views/admin/notifications/index.blade.php`, paginated). Previously "View all notifications →" just linked to the Dashboard — there was no real list.

**Two real bugs found and fixed while building this:**
1. **`paymentRecorded()` didn't exist** — `Admin\PaymentController::store()` (recording a manual payment) called `NotificationHelper::paymentRecorded(...)`, a method that was never actually defined in the helper. This would throw a fatal `Call to undefined method` error every time an admin manually recorded a payment. Added the missing preset (same shape as `paymentReceived()`).
2. **Notification links baked in the wrong host.** `route()` generates an *absolute* URL — inside a real HTTP request it correctly uses the current request's host, but from a console command or migration (no request context) it falls back to `APP_URL`, which is set to a specific ngrok tunnel in `.env`. Any notification created by `AutoCheckInOutBookings` (a scheduled command) — and, once discovered, every other notification link too — permanently baked in that ngrok host; the link would go dead (`ERR_NGROK_3200`) the moment that specific tunnel stopped running. **Fixed by generating every notification link as relative** (`route($name, $params, false)`) everywhere in the codebase, and converting all 151 already-backfilled absolute links in the database to relative paths.

---

### 6.15 Dashboard Search & Bell

**Bell markup:** `resources/views/layouts/admin.blade.php` (topbar) ← moved here in v5.0, see bug note below
**Search + JS:** `resources/views/admin/partials/topbar_features.blade.php`
**Data source:** `App\Providers\AppServiceProvider::boot()` — a View Composer ← NEW v5.0
**Routes:** `admin.search`, `admin.notifications.index`, `admin.notifications.open`, `admin.notifications.markRead`

**🔔 Notification Bell:**
- Dropdown showing latest 8 notifications, each **clickable** (v5.0) — links to the relevant booking/review/user and marks itself read
- Color-coded icons by notification type
- Gold unread dot indicator on bell
- "Mark all read" button (AJAX, no reload)
- Closes on outside click
- "View all notifications →" now goes to a real list page (`admin.notifications.index`), not the Dashboard

**v5.0 — Structural bug fixed: the bell only ever worked on the Dashboard page.** The bell button and its dropdown used to live in two unrelated places: the Dashboard page had its own hand-copied bell+dropdown inline in `@section('topbar-right')`, while every *other* admin page fell back to a generic topbar with no bell at all — `topbar_features.blade.php` (included at the very bottom of `<body>` on every page) held a second, disconnected copy of the dropdown that had nothing to visually anchor to and no data feeding it (only `DashboardController::index()` ever passed `$notifications`/`$unreadCount` to a view). Fixed by:
- Moving the bell button **and** its dropdown together into `layouts/admin.blade.php`'s topbar (they must be DOM siblings for the dropdown's `position:absolute` to anchor correctly under the bell — CSS position is relative to the nearest positioned ancestor, not to "wherever it looks right"), so it's now one shared component present in the topbar of *every* admin page.
- Removing the Dashboard's now-redundant duplicate inline copy.
- Adding a **View Composer** (`AppServiceProvider::boot()`) for `layouts.admin` + `admin.partials.topbar_features` that supplies `$notifications`/`$unreadCount` globally, so `DashboardController` no longer has to (and no other controller ever had to either).

**🔍 Search Modal:**
- Opens with click or `Ctrl+K`
- Searches: bookings (ref/status/guest), guests (name/email/phone), properties (name/type)
- Live results with 350ms debounce
- Quick filter hints: Pending, Checked In, VE- refs
- Closes with `ESC`
- Results link directly to admin detail pages

---

### 6.16 AI Smart Insights

**Service:** `app/Services/GeminiService.php` (uses Groq API)
**Route:** `GET /admin/insights` → `admin.insights`

Generates 5 bullet-point insights from real booking/revenue data using AI.

**Sample insights:**
- "Bookings increased 20% compared to last month"
- "December is the peak season based on historical data"
- "Cancellation rate is higher than average at 15%"

**Cached:** 1 hour per day-hour key to minimize API calls.

---

### 6.17 AI Forecasting

**Route:** `GET /admin/forecast` → `admin.forecast`

Linear regression algorithm on 6 months of booking and revenue data.

> ⚠️ **Still on dummy data (re-checked v5.0):** `Admin\ForecastController::index()` currently runs entirely on hardcoded arrays (`$dummyBookings`, `$dummyRevenue`, `$totalProperties = 4; // dummy`), not live database queries — this was already listed as pending in v4.0 and hasn't been addressed since.

**Outputs:**
- Predicted bookings for next month
- Predicted revenue for next month
- Booking growth % vs last month
- Revenue growth % vs last month
- Peak month identification
- Bar chart: 6 months historical + 1 month forecast

---

### 6.18 AI Chatbot

**Route:** `POST /chatbot` → `chatbot.reply`
**Provider:** Groq API (`llama-3.1-8b-instant`)

Floating chat widget on public portal. Answers questions about Villa Elena, pricing, and amenities using real database data injected into the system prompt.

> ✅ **Confirmed up to date (re-checked v5.0):** `Portal\ChatbotController`'s prompt is explicitly single-villa-aware ("SINGLE-VILLA private resort... only ONE bookable villa"), fetches the real master Villa record (`Property::where('type','villa')->first()`), and uses the real `getPackagePrice()`/`hasConflict()` results — no stale multi-villa references found.

---

## 7. Pricing Model (v4.0)

Villa Elena is rented as a **flat-rate package** — one of two fixed slots, Day (9 hrs) or Night (11 hrs), see [Section 8](#8-booking-availability--fixed-slots-v51) — not a per-night hotel stay, and the price does **not** vary by number of guests (private/exclusive resort, not per-head pricing). The rate depends only on **when the guest checks in**.

**Implementation:** `Property::getPackagePrice(Carbon $checkin): float` in `app/Models/Property.php`

| Segment | Days / Times | Rate |
|---|---|---|
| Regular | Monday – Thursday (any time) | `base_price` → **₱4,000** |
| Peak | Friday, Saturday (any time), and Sunday **before 6:00 PM** | `weekend_price` → **₱6,000** |
| Regular (again) | Sunday **6:00 PM onwards** | `base_price` → **₱4,000** |

**Priority order:**
1. An active `pricing_rules` entry covering the check-in date (e.g. holiday override) — takes precedence over the standard segments above.
2. Otherwise, the day-of-week / time-of-day segment table above.

**Important:** `getPriceForDate()` (the original per-calendar-date method, still used by `PricingRule` and legacy code paths) is **not** used for the booking flow anymore — it only checks Saturday/Sunday and has no time-of-day awareness, so it can't express the Friday-peak or Sunday-6PM-cutoff rules. All customer and admin booking creation now calls **`getPackagePrice($checkin)`** instead.

**Multi-day stays:** N/A as of v5.1 — every booking is exactly one fixed slot (Day = 9 hrs, Night = 11 hrs), so there's no "multi-day" case to speak of; check-out always falls on the same calendar segment as check-in (Day) or the very next day (Night), and pricing is always based purely on the check-in slot's day/time.

**Setup required after deploying v4.0:** the master "Villa Elena (Whole Villa)" property record must have its `base_price` and `weekend_price` manually set to **4000** and **6000** respectively via Admin → Properties → Edit (the auto-seeded values were a sum of the 6 rooms' old prices and are not correct for the new flat-rate model).

---

## 8. Booking Availability & Fixed Slots (v5.1)

**v5.1 rewrite:** v4.0 had guests pick any free-choice check-in/check-out time, validated against a 12–24 hour window, with a separately-enforced 2-hour buffer padded onto both ends of `hasConflict()`. That's gone — replaced with exactly two fixed package slots and no separate buffer concept.

**Implementation:** `Booking::SLOTS` (the slot definitions) + `Booking::slotDateTimes($slot, $checkInDate)` (slot + date → check-in/check-out `Carbon` pair) + `Booking::slotKey()` (existing booking → slot, for pre-filling forms) + `Booking::hasConflict()`, all in `app/Models/Booking.php`, plus `checkInDateTime()` / `checkOutDateTime()` helper accessors.

**The two fixed slots:**

| Slot | Check-in | Check-out | Duration |
|---|---|---|---|
| `day` | 8:00 AM | 5:00 PM (same day) | 9 hours |
| `night` | 7:00 PM | 6:00 AM (**next day**) | 11 hours |

**Rules:**
- Every booking channel (public portal, customer reschedule, staff walk-in, admin-created) submits a check-in **date** + a **`slot`** (`day` or `night`) — no raw time input anywhere in the booking-creation flow. `Booking::slotDateTimes()` is the single source of truth that turns those two values into the actual check-in/check-out datetimes; every controller calls it instead of parsing `check_in_time`/`check_out_time` from the request.
- Bookings are checked against the single master Villa's existing bookings using **full date+time**, not date-only (`Booking::hasConflict()`).
- **No separate cleaning buffer is enforced.** The gap built into the two fixed slots themselves (5:00 PM checkout → 7:00 PM next check-in, or 6:00 AM checkout → 8:00 AM next check-in — both exactly 2 hours) *is* the cleaning buffer. `hasConflict()` no longer takes a `$bufferHours` parameter — it does a plain datetime overlap check.
- **No 12–24 hour cap exists anymore** — moot, since duration is fixed per slot and there's no time input to misuse. **Admin/staff bookings use the same two fixed slots as customers** (no free-choice discretion for fresh bookings anymore).
- **Exception — "Extend Stay":** the one place free-choice time still exists is `Admin\BookingController::extendStay()`, which pushes out the check-out of an *already checked-in* guest's existing stay (not a new booking, so it's deliberately exempt from the fixed-slot policy). Its `hasConflict()` call was updated only to drop the removed `$bufferHours` argument — behavior otherwise unchanged.

**Frontend note:** every booking form (`portal/property.blade.php`, `portal/booking_form.blade.php`, `customer/reschedule_form.blade.php`, `admin/bookings/create.blade.php`, `staff/walkin.blade.php`) presents the slot choice as two radio cards ("Day 8AM–5PM" / "Night 7PM–6AM") instead of a time picker; all client-side price-preview JS was rewritten to compute off the selected slot rather than a raw time diff.

**Auto check-in/out compatibility:** `bookings:auto-checkinout` (`AutoCheckInOutBookings`) needed **zero changes** for this — it already worked purely off the stored `check_in_time`/`check_out_time` values rather than assuming a particular time, so it auto-checks-in/out fixed-slot bookings (including the Night slot's overnight check-out crossing midnight) exactly as it did free-time ones. Verified with a live test run on 2026-08-08.

**2026-08-08 data reset:** all 31 pre-v5.1 bookings (made under the old free-time system) were hard-deleted along with their cascaded payments (24) and reviews (7), to avoid any confusion testing against stale free-time data. See [Known Issues Fixed](#13-known-issues-fixed) for the full cleanup breakdown.

---

## 9. AI Integration

**Provider:** Groq API (switched from Gemini due to quota exhaustion)
**Model:** `llama-3.1-8b-instant`

```env
GROQ_API_KEY=gsk_xxxxxxxxxxxxxxxxxxxx
```

```php
// config/services.php
'groq' => [
    'key' => env('GROQ_API_KEY'),
],
```

| Provider | Status | Reason |
|---|---|---|
| Gemini 2.0 Flash | ❌ | Free quota exhausted |
| Gemini 2.0 Flash-Lite | ❌ | Same project quota exhausted |
| **Groq (Llama 3.1)** | ✅ | Free tier, fast, working |

---

## 10. Payment Integration

**Provider:** PayMongo (Philippine payment gateway)
**Mode:** Sandbox (test mode)
**Dashboard:** https://dashboard.paymongo.com

**Key Points:**
- Webhook not required for sandbox/demo — success callback handles payment recording
- For production, register webhook URL at PayMongo dashboard
- Webhook endpoint: `POST /webhooks/paymongo` (CSRF exempt)
- Ngrok required for webhook testing on localhost

---

## 11. File Structure

```
app/
├── Helpers/
│   └── NotificationHelper.php         admin + guest notification presets
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php
│   │   ├── Admin/
│   │   │   ├── DashboardController.php
│   │   │   ├── PropertyController.php    ← UPDATED v4.0 — villa/room warnings, $old fix
│   │   │   ├── BookingController.php     ← UPDATED v4.0 — time, buffer, package price, extras
│   │   │   ├── UserController.php
│   │   │   ├── ReportController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── ReviewController.php
│   │   │   ├── InsightsController.php
│   │   │   └── ForecastController.php
│   │   ├── Customer/
│   │   │   ├── HomeController.php
│   │   │   └── ReviewController.php
│   │   ├── Portal/
│   │   │   ├── PortalController.php      ← UPDATED v4.0 — single-villa listing, time, buffer, package price
│   │   │   └── ChatbotController.php
│   │   ├── Staff/
│   │   │   └── FrontdeskController.php   ⚠ pending v4.0 review (see 6.10)
│   │   └── PaymentController.php
│   └── Middleware/
│       └── RoleMiddleware.php
├── Models/
│   ├── User.php
│   ├── Property.php                      ← UPDATED v4.0 — getPackagePrice()
│   ├── PropertyImage.php
│   ├── Booking.php                       ← UPDATED v4.0 — checkInDateTime(), checkOutDateTime(), hasConflict()
│   ├── Payment.php
│   ├── Review.php
│   ├── BookingExtra.php
│   ├── PricingRule.php
│   ├── Discount.php
│   ├── Notification.php
│   ├── HousekeepingTask.php
│   ├── Package.php
│   ├── AvailabilityBlock.php
│   ├── StaffLog.php
│   └── Setting.php
└── Services/
    ├── GeminiService.php                (uses Groq API)
    └── PayMongoService.php

database/
├── migrations/
│   └── 2026_07_14_000001_add_checkin_checkout_time_to_bookings_table.php   ← NEW v4.0
└── seeders/
    └── ConvertVillasToRoomsSeeder.php    ← NEW v4.0 — one-time data migration

resources/css/                              ← NEW — Bootstrap 5 + Vite CSS architecture (replaced CDN links)
├── app.css                                 default Laravel/Tailwind entry (not used by app pages)
├── base.css                                shared design tokens (--stone, --terracotta, --muted, etc.),
│                                            shared semantic badge/status color variables (--tag-*), and
│                                            small cross-section utility classes (.tag-*, .text-muted-theme,
│                                            .mb-12, .d-flex-gap-10, .section-label) — imported by every
│                                            section's own CSS file below, not used directly by any view
├── admin.css                                imports base.css + admin panel's stone/terracotta tokens & styles
├── portal.css                               imports base.css + portal/customer tokens & styles (shared by
│                                            the public portal and the customer "My Bookings" area)
├── auth.css                                 imports base.css + login/register/password page styles
├── staff.css                                own navy/gold tokens, imports base.css for shared utilities/vars
└── payment.css                              imports base.css; standalone PayMongo checkout/success pages

resources/js/                               ← NEW — one Vite entry per section (multi-entry build)
├── app.js                                  default Laravel entry (not used by app pages)
├── bootstrap.js                            axios setup (Laravel default)
├── admin.js                                Bootstrap 5 (CSS+JS) + bootstrap-icons + admin.css
├── admin-charts.js                         Chart.js bundle — dashboard, reports, forecast pages
├── admin-calendar.js                       FullCalendar bundle — admin calendar module
├── portal.js                               Bootstrap 5 + bootstrap-icons + portal.css
├── portal-calendar.js                      FullCalendar bundle — customer-facing availability calendar
├── auth.js                                 Bootstrap 5 + bootstrap-icons + auth.css
├── staff.js                                Bootstrap 5 + bootstrap-icons + staff.css
└── payment.js                              bootstrap-icons + payment.css only — no Bootstrap JS/CSS,
                                             keeps the lightweight footprint the standalone pages had before

vite.config.js                              ← UPDATED — multi-entry `input: [...]` array (one per file above);
                                             Rollup auto-splits shared chunks (Bootstrap, FullCalendar core)
                                             across entries that import them

resources/views/
├── layouts/
│   ├── admin.blade.php       @vite(['resources/js/admin.js'])
│   ├── portal.blade.php      @vite(['resources/js/portal.js'])
│   ├── customer.blade.php    @vite(['resources/js/portal.js']) — shares portal's theme/assets
│   ├── auth.blade.php        @vite(['resources/js/auth.js'])
│   ├── staff.blade.php       ← NEW — shared layout for staff/frontdesk + staff/walkin
│   │                           (previously two standalone HTML pages, each with duplicated
│   │                           <head>/nav markup); @vite(['resources/js/staff.js'])
│   └── payment.blade.php     ← NEW — shared layout for payment/checkout + payment/success
│                               (previously standalone HTML pages); @vite(['resources/js/payment.js'])
├── auth/
├── admin/
│   ├── dashboard/     index.blade.php
│   ├── partials/
│   │   └── topbar_features.blade.php
│   ├── properties/    index, create, edit, show     ← UPDATED v4.0 — villa/room badges & warnings
│   ├── bookings/      index, show, create            ← UPDATED v4.0 — time fields, extra charges panel
│   ├── users/         index, show, form
│   ├── payments/      index, show
│   ├── reviews/       index.blade.php
│   ├── reports/       index.blade.php
│   ├── insights/      index.blade.php
│   ├── forecast/      index.blade.php
│   ├── calendar/      index.blade.php   (FullCalendar-based admin calendar)
│   └── settings/      index.blade.php
├── customer/
│   ├── home.blade.php
│   ├── bookings.blade.php
│   ├── booking_detail.blade.php
│   ├── notifications.blade.php
│   └── review_form.blade.php
├── payment/
│   ├── checkout.blade.php     ← UPDATED — now @extends('layouts.payment') instead of standalone HTML
│   └── success.blade.php      ← UPDATED — now @extends('layouts.payment') instead of standalone HTML
├── portal/
│   ├── home.blade.php          ← REDESIGNED v4.0 — single-villa "big showcase" + room-status strip
│   ├── property.blade.php      ← REDESIGNED v4.0 — room-status section, FullCalendar availability calendar, free time inputs
│   ├── booking_form.blade.php  ← UPDATED v4.0 — flat package rate display, time fields
│   └── confirmation.blade.php
├── partials/
│   └── chatbot.blade.php
└── staff/
    ├── frontdesk.blade.php     ← UPDATED — now @extends('layouts.staff') instead of standalone HTML
    └── walkin.blade.php        ← UPDATED — now @extends('layouts.staff') instead of standalone HTML
                                    ⚠ still pending v4.0 booking-logic review (see 6.10)

routes/
├── web.php            public portal + auth + PayMongo payment routes  ← UPDATED v5.0 — reviews, contact, 2FA routes
├── admin.php          /admin/* routes  ← UPDATED v4.0/v5.0 — booking extras + notifications routes
├── staff.php          /staff/* routes
└── customer.php       /my/* routes  ← UPDATED v5.0 — profile, reviews, payments, reschedule routes
```

### v5.0 New Files (not shown in the tree above — added this session)

```
app/
├── Helpers/
│   └── DeviceHelper.php                       User-Agent → "Chrome on Windows"-style label, no package needed
├── Http/Controllers/
│   ├── Customer/
│   │   ├── ProfileController.php              edit/update/password/deactivate/2FA-toggle/device-removal
│   │   ├── PaymentController.php               consolidated payment history (was an empty stub)
│   │   └── BookingController.php               reschedule (was an empty stub)
│   └── Admin/
│       └── NotificationController.php          index/open/markAllRead (split out of DashboardController)
├── Mail/
│   └── ContactFormSubmitted.php                portal contact form → real email
├── Models/
│   ├── TrustedDevice.php
│   └── LoginActivity.php
├── Notifications/
│   └── TwoFactorCodeNotification.php           the 2FA email OTP
└── Services/
    └── ReviewModerationService.php             keyword pre-filter + Groq classification

resources/views/
├── auth/
│   └── two_factor.blade.php                    OTP entry page
├── customer/
│   ├── profile.blade.php
│   ├── reviews.blade.php
│   └── payments.blade.php
│   (review_form.blade.php, booking_detail.blade.php, reschedule_form.blade.php updated in place)
├── admin/
│   └── notifications/
│       └── index.blade.php                     full admin notifications list (didn't exist before)
├── portal/
│   └── reviews.blade.php                       full public reviews list
└── emails/
    └── contact_form.blade.php                  contact form → admin email template

config/
└── moderation.php                              review moderation blocked-word list + PII/spam regex patterns
```

---

## 12. Routes Summary

### Public Routes (`web.php`)
```
GET  /                              home
GET  /properties/{property}         portal.property
GET  /reviews                       portal.reviews            ← NEW v5.0 (all approved reviews)
POST /contact                       portal.contact.send       ← NEW v5.0 (throttle:5,60)
GET  /book/{property}               portal.book
POST /book/{property}               portal.book.submit
GET  /booking/confirmed/{booking}   portal.confirmation
POST /chatbot                       chatbot.reply
GET  /pay/{booking}                 payment.page
POST /pay/{booking}/checkout        payment.checkout
GET  /pay/{booking}/success         payment.success
GET  /pay/{booking}/cancel          payment.cancel
POST /webhooks/paymongo             payment.webhook  (no CSRF)
GET  /login                         login
POST /login
GET  /register                      register
POST /register
GET  /forgot-password               password.request
POST /forgot-password               password.email
GET  /reset-password/{token}        password.reset
POST /reset-password                password.update
GET  /two-factor/verify             two-factor.verify         ← NEW v5.0
POST /two-factor/verify                                        ← NEW v5.0 (throttle:5,1)
POST /two-factor/resend             two-factor.resend         ← NEW v5.0 (throttle:3,1)
POST /logout                        logout
```

### Admin Routes (`/admin/`)
```
GET    /admin/dashboard
GET    /admin/search                          admin.search
GET    /admin/notifications                   admin.notifications.index    ← NEW v5.0
GET    /admin/notifications/{n}/open          admin.notifications.open     ← NEW v5.0
POST   /admin/notifications/mark-read         admin.notifications.markRead  (moved to Admin\NotificationController v5.0)
GET    /admin/bookings/lookup                 admin.bookings.lookup  (AJAX)
GET    /admin/properties         (CRUD)
GET    /admin/bookings           (CRUD)
PATCH  /admin/bookings/{id}/status
POST   /admin/bookings/{id}/payment
POST   /admin/bookings/{booking}/extras                admin.bookings.extras.store    ← NEW v4.0
DELETE /admin/bookings/{booking}/extras/{extra}         admin.bookings.extras.destroy  ← NEW v4.0
GET    /admin/users              (CRUD)
PATCH  /admin/users/{id}/toggle-status
GET    /admin/payments                        admin.payments.index
POST   /admin/payments                        admin.payments.store
GET    /admin/payments/{payment}              admin.payments.show
POST   /admin/payments/{payment}/refund       admin.payments.refund
GET    /admin/reviews                         admin.reviews.index
PATCH  /admin/reviews/{review}/approve        admin.reviews.approve
PATCH  /admin/reviews/{review}/reject         admin.reviews.reject
POST   /admin/reviews/{review}/reply          admin.reviews.reply
DELETE /admin/reviews/{review}                admin.reviews.destroy
GET    /admin/reports                         admin.reports.index
GET    /admin/insights                        admin.insights
GET    /admin/forecast                        admin.forecast
GET    /admin/settings
PUT    /admin/settings
GET    /admin/calendar                        admin.calendar.index
GET    /admin/calendar/events                  admin.calendar.events
PATCH  /admin/calendar/bookings/{booking}/move admin.calendar.move
POST   /admin/calendar/block                   admin.calendar.block
DELETE /admin/calendar/blocks/{block}          admin.calendar.deleteBlock
```

### Staff Routes (`/staff/`)
```
GET    /staff/frontdesk                       staff.frontdesk
PATCH  /staff/checkin/{booking}               staff.checkin
PATCH  /staff/checkout/{booking}              staff.checkout
GET    /staff/walkin                          staff.walkin
POST   /staff/walkin                          staff.walkin.store
POST   /staff/bookings/{booking}/payment      staff.payment
PATCH  /staff/tasks/{task}/start              staff.tasks.start
PATCH  /staff/tasks/{task}/complete           staff.tasks.complete
```

### Customer Routes (`/my/`)
```
GET    /my/                                   customer.home
GET    /my/bookings                           customer.bookings
GET    /my/bookings/{booking}                 customer.bookings.show
PATCH  /my/bookings/{booking}/cancel          customer.bookings.cancel
GET    /my/bookings/{booking}/reschedule      customer.bookings.reschedule        ← NEW v5.0
PATCH  /my/bookings/{booking}/reschedule      customer.bookings.reschedule.update ← NEW v5.0
GET    /my/notifications                      customer.notifications
GET    /my/notifications/{n}/open             customer.notifications.open        ← NEW v5.0
GET    /my/bookings/{booking}/review          customer.reviews.create
POST   /my/bookings/{booking}/review          customer.reviews.store
GET    /my/reviews                            customer.reviews.index             ← NEW v5.0
GET    /my/reviews/{review}/edit              customer.reviews.edit              ← NEW v5.0
PUT    /my/reviews/{review}                   customer.reviews.update            ← NEW v5.0
DELETE /my/reviews/{review}                   customer.reviews.destroy           ← NEW v5.0
GET    /my/payments                           customer.payments.index            ← NEW v5.0
GET    /my/profile                            customer.profile.edit              ← NEW v5.0
PUT    /my/profile                            customer.profile.update            ← NEW v5.0
PUT    /my/profile/password                   customer.profile.password          ← NEW v5.0
DELETE /my/profile                            customer.profile.deactivate        ← NEW v5.0
PUT    /my/profile/2fa                        customer.profile.2fa.toggle        ← NEW v5.0
DELETE /my/profile/devices/{device}           customer.profile.devices.destroy   ← NEW v5.0
```

---

## 13. Known Issues Fixed

| Issue | Cause | Fix Applied |
|---|---|---|
| Login showed "deactivated" for all | `status` column compared as string | Integer cast; `isActive()` uses `(int)$this->status === 1` |
| Migrations failed with FK errors | Same timestamp prefix | Sequential timestamps `200001–200015` |
| Sessions/cache errors | Default driver expected DB tables | `SESSION_DRIVER=file`, `CACHE_STORE=file` |
| Check-in SQL enum error | Used `checkout_cleaning` | Changed to `checkout_clean` |
| Settings page 500 | Used `value` column | Changed to `setting_value` |
| Routes 404 / wrong names | Missing Route group wrapper | Wrapped in `prefix(admin)->name(admin.)` |
| Staff login 500 | No controller/view for frontdesk | Built full Staff Portal |
| Staff got 403 on admin routes | Sidebar had admin links | Removed admin route links from staff sidebar |
| Notifications 500 | Used `read_at` column | Changed to `is_read` |
| `admin.reports.revenue` not found | Non-existent sub-routes in sidebar | Changed to `admin.reports.index` |
| Payment showed ₱0.00 | `status = 'success'` filter (no such column) | Changed to `payment_type != 'refund'` |
| Booking notification 500 | `type = 'booking_update'` not in enum | Added `booking_update` to notifications type enum |
| Check-in failed with enum error | `task_type = 'checkout'` not in enum | Changed to `checkout_clean` |
| Walk-in notification failed | `payment_method = 'online'` not in enum | Added `online` to payments method enum |
| PayMongo payment showed ₱0.00 | `success_url` had literal `__session_id__` placeholder | Removed placeholder; use stored `paymongo_session_id` |
| PayMongo insert failed | `payment_method = 'online'` not in enum | Altered payments table enum to include `online` |
| Search results 404 | Hardcoded `/villa-elena/public/` path | Changed to `{{ url('') }}` base URL |
| DashboardController not found | Instructions file saved as controller | Created clean `DashboardController.php` with proper namespace |
| AI Insights 500 | Used `@extends('layouts.admin')` — layout doesn't exist | Converted to standalone HTML |
| Gemini API 429 quota | Free tier exhausted | Switched to Groq API |
| Chatbot invented fake villa names | No real data in prompt | Real DB properties injected into system prompt |
| **(v4.0)** `Class "App\Models\Booking" not found` on property detail page | Autoload/cache not refreshed after model file update | `composer dump-autoload` + `php artisan config:clear/cache:clear/view:clear` |
| **(v4.0)** `Undefined variable $old` on `PUT /admin/properties/{id}` | `PropertyController::update()` referenced `$old` in `StaffLog::record()` without ever defining it | Added `$oldData = $property->toArray();` before the update call, passed `$oldData` instead |
| **(v4.0)** Customers could only book one room at a time, not the whole Villa | Original design modeled each room as an independently bookable `properties` row | Converted rooms to `type=room` (info-only), created single master `type=villa` record as the only bookable listing |
| **(v4.0)** No cleaning buffer between back-to-back bookings | Availability check was date-only overlap | Added `check_in_time`/`check_out_time` columns + `Booking::hasConflict()` with a configurable buffer (default 2 hours) |
| **(v4.0)** Guests could only pick fixed 2-hour time slots (e.g. couldn't select 11:00 AM) | Booking/search forms used `<select>` with hardcoded time options | Replaced with free-choice `<input type="time">`; buffer logic already worked correctly against arbitrary times since it was never actually restricted server-side |
| **(v5.0)** Manually recording a payment as admin crashed | `Admin\PaymentController::store()` called `NotificationHelper::paymentRecorded(...)`, a preset method that had never actually been defined | Added the missing `paymentRecorded()` preset |
| **(v5.0)** Real, completed payments showed as "Pending" in customer Payment History | 5 different payment-creation code paths (front-desk walk-in, front-desk record-payment, PayMongo success callback, admin manual record, admin refund) never set `status` explicitly, so they silently inherited the column's `pending` default | All 5 sites now set `'status' => 'success'` explicitly; 21 pre-existing mis-stamped rows backfilled via a data migration |
| **(v5.0)** Admin notification bell only ever worked on the Dashboard page | Bell button + dropdown lived in two disconnected places — Dashboard had its own hand-copied inline copy; every other page had a data-less, visually-unanchored copy at the bottom of `<body>` | Merged into one shared component in `layouts/admin.blade.php`'s topbar (DOM siblings, so the dropdown's `position:absolute` anchors correctly); removed the Dashboard's duplicate; added a global View Composer for the notification data |
| **(v5.0)** Clicking a notification could redirect to a dead ngrok URL (`ERR_NGROK_3200`) | `route()` generates an absolute URL; called outside an HTTP request (console commands, migrations) it falls back to `APP_URL`, permanently baking in whatever ngrok tunnel was configured at creation time | Generate every notification link as a **relative** URL (`route($name, $params, false)`) everywhere; converted all previously-stored absolute links to relative paths |
| **(v5.0)** ~50% of pre-existing notifications had no click-through destination at all | The `link` column didn't exist before v5.0 — every notification ever created up to that point had nothing to derive a destination from after the fact | Backfill migration parses each notification's title/message for an embedded `booking_ref` or review-related wording and recovers a real link for 151/203; the remaining 52 reference bookings that no longer exist in the database (deleted) and are left `null` by design |
| **(v5.1)** Booking policy said "12–24 hours" but was actually meant to be two fixed packages | v4.0 modeled the resort's two real packages (a daytime tour and an overnight stay) as an open-ended free-choice time window with min/max hour validation, instead of the two fixed slots they actually are | Replaced with `Booking::SLOTS` (`day` 8AM–5PM, `night` 7PM–6AM) + `Booking::slotDateTimes()`; every booking form now submits a slot choice, not raw time |
| **(v5.1)** Redundant 2-hour cleaning buffer on top of the fixed slots | Before the fixed slots existed, a buffer had to be explicitly enforced in `hasConflict()` since guests could pick any time; once the two fixed slots were introduced, the gap between them (exactly 2 hours on both ends) already *is* the buffer, making the separate `$bufferHours` padding redundant | Removed the `$bufferHours` parameter from `Booking::hasConflict()` entirely; conflict checks are now a plain datetime overlap |

---

## 14. Build Progress Summary

| Phase | Module | Status |
|---|---|---|
| 1 | System Blueprint & DB Design | ✅ Complete |
| 2 | Database Migrations (15 tables) | ✅ Complete |
| 3 | Eloquent Models (15 models) | ✅ Complete |
| 4 | Authentication & Role Middleware | ✅ Complete |
| 5 | Admin Dashboard UI | ✅ Complete |
| 6 | Property Management | ✅ Complete |
| 7 | Booking Management | ✅ Complete |
| 8 | Guest & Staff Management | ✅ Complete |
| 9 | Reports & Analytics | ✅ Complete |
| 10 | Settings (6 tabs) | ✅ Complete |
| 11 | Customer Portal | ✅ Complete |
| 12 | Public Booking Page | ✅ Complete |
| 13 | Staff Portal — Frontdesk | ✅ Complete |
| 14 | Staff Portal — Walk-in Booking | ✅ Complete |
| 15 | Staff Portal — Payment Recording | ✅ Complete |
| 16 | Admin Payments Page | ✅ Complete |
| 17 | Online Payments (PayMongo) | ✅ Complete |
| 18 | Reviews Module (Admin + Guest) | ✅ Complete |
| 19 | Admin Notifications (Bell + Helper) | ✅ Complete |
| 20 | Dashboard Search Modal | ✅ Complete |
| 21 | AI Smart Insights | ✅ Complete |
| 22 | AI Forecasting | ✅ Complete |
| 23 | AI Chatbot (Public Portal) | ✅ Complete |
| 24 | **(v4.0)** Single-Villa Booking Model (Villa + Room restructure) | ✅ Complete |
| 25 | **(v4.0)** Check-in/out Time + 2-Hour Buffer Logic | ✅ Complete |
| 26 | **(v4.0)** Flat Package Pricing (day/time segments) | ✅ Complete |
| 27 | **(v4.0)** Public Homepage Redesign (single showcase) | ✅ Complete |
| 28 | **(v4.0)** FullCalendar Availability Calendar (customer-facing) | ✅ Complete |
| 29 | **(v4.0)** Booking Extra Charges (admin UI) | ✅ Complete |
| 30 | **(v4.0)** Admin Properties Management (Villa/Room clarity) | ✅ Complete |
| 31 | **(v5.0)** Customer Profile Management (edit/password/avatar/deactivate) | ✅ Complete |
| 32 | **(v5.0)** Customer My Reviews (view/edit/delete) | ✅ Complete |
| 33 | **(v5.0)** Customer My Payments (consolidated history) | ✅ Complete |
| 34 | **(v5.0)** Customer Reschedule Booking | ✅ Complete |
| 35 | **(v5.0)** Automated Review Moderation (keyword filter + AI) | ✅ Complete |
| 36 | **(v5.0)** Customer 2FA (email OTP, per-device) + Login Activity | ✅ Complete |
| 37 | **(v5.0)** Public Portal — Real Reviews (homepage + full list page) | ✅ Complete |
| 38 | **(v5.0)** Public Portal — Dynamic Contact Info + Working Contact Form | ✅ Complete |
| 39 | **(v5.0)** Public Portal — Social Links Trimmed to Facebook + TikTok | ✅ Complete |
| 40 | **(v5.0)** Admin Property Management Simplification (single-villa forms) | ✅ Complete |
| 41 | **(v5.0)** Admin-Managed Amenities List (Settings tab) | ✅ Complete |
| 42 | **(v5.0)** Payment Status Bug Fix (5 sites + data backfill) | ✅ Complete |
| 43 | **(v5.0)** Clickable Notifications (Customer + Admin) + Bell/Dropdown Rebuild | ✅ Complete |
| 44 | **(v5.1)** Fixed Day/Night Booking Slots (replaced free-choice time + buffer) | ✅ Complete |
| 45 | **(v5.1)** Pre-v5.1 Data Reset (bookings/payments/reviews/stale notifications) | ✅ Complete |

### Pending / Optional

Re-verified directly against the live code/database (not just re-stated from memory) on 2026-08-08.

| Module | Status | Notes |
|---|---|---|
| ~~AI features — needs `GROQ_API_KEY`~~ | ✅ Resolved | `GROQ_API_KEY` is set in `.env` — confirmed live. The blocker this item was tracking is gone (Insights/Chatbot can call the API); Forecast still uses dummy data regardless — see next row |
| PayMongo Webhook | 🔲 Optional | Still just the local route + Ngrok setup — registering the production webhook URL on the PayMongo dashboard is an external, one-time deploy step, not something fixable in code |
| AI Forecasting real data | 🔲 **Still not done** | Re-checked `Admin\ForecastController::index()` directly — it still runs entirely on hardcoded dummy arrays (`$dummyBookings`, `$dummyRevenue`, `$totalProperties = 4; // dummy`), not live DB queries |
| ~~Reviews on property public page~~ | ✅ **Resolved (2026-08-08)** | Added a "Guest Reviews" section to `portal/property.blade.php`, scoped to that specific property's own **approved** reviews (average rating + count, individual review cards, admin replies shown inline, empty state if none yet). `Portal\PortalController::propertyDetail()` now queries `Review::where('property_id', $property->id)->where('status', 'approved')` and passes `reviews`/`avgRating`/`totalReviews` to the view |
| ~~Staff walk-in booking form~~ | ✅ **Confirmed done** | Re-checked `Staff\FrontDeskController` — it already validates `check_in_time`/`check_out_time` and prices via `$property->getPackagePrice($checkin)`. Not touched this session, but it's already correct — no longer pending |
| ~~AI Chatbot context refresh~~ | ✅ **Confirmed done** | Re-checked `Portal\ChatbotController` — the prompt is explicitly single-villa-aware ("SINGLE-VILLA private resort... only ONE bookable villa"), pulls the real master Villa record, real `getPackagePrice()`/`hasConflict()` results. No stale multi-villa references found |
| ~~Villa Elena base/weekend price values~~ | ✅ Resolved | Confirmed correctly set to ₱4,000 / ₱6,000 on the master Villa record |
| **(v5.0)** Admin account password | 🔲 **Still action required** | Re-checked — still the temporary `AdminTest123!` set during this session's testing. Not reset. |
| ~~(v5.0) Staff account password~~ | ✅ Resolved | Re-checked — no longer matches the temporary `StaffTest123!` this session set, so it's been changed to something else already |
| ~~(v5.0) 52 orphaned notifications with no click-through link~~ | ✅ **Superseded (2026-08-08)** | Moot after the v5.1 data reset wiped all pre-v5.1 bookings/notifications — see the next row |
| ~~(v5.0) Amenities list needs the admin's real content~~ | ✅ **Tooling fixed (2026-08-08)** | Curating the actual amenity names is a self-service admin task (Settings → Amenities, already built in v5.0) — not something that gets "completed" in code. What WAS a real bug: the master list said `"Swimming Pool"` while the Villa's actual saved amenity was `"Private Pool"`, silently orphaning that checkbox on the Property edit form (it couldn't render as checked, so re-saving the property from that form would have dropped it) — fixed in both the live `property_amenities` setting and the `PropertyController::DEFAULT_AMENITIES` seed constant. Also added **one-way sync** in `SettingsController::update()`: removing an amenity from Settings → Amenities now also strips it from every property that had it selected (previously the two lists could silently drift apart, which is exactly how the Swimming Pool/Private Pool mismatch happened in the first place) |
| **(v5.1)** Data reset — all pre-v5.1 bookings wiped | ✅ **Done (2026-08-08)** | 31 bookings (30 live + 1 already-trashed) hard-deleted, cascading 24 payments + 7 reviews; 14 housekeeping tasks unlinked (`booking_id` → null, not deleted); Villa property status reset to `available`; 97 stale notifications referencing the deleted bookings/reviews removed (kept 12 real "New Guest Registered" notifications). Clean slate for testing the new fixed-slot booking flow — no leftover free-time-era data anywhere |
| **(v6.0-planned)** Permanent Windows Task Scheduler entry for `php artisan schedule:run` | 🔲 **Pending — do this when hosting online, not before** | Right now the scheduler (`bookings:auto-checkinout` — auto check-in/out, plus the 1-hour stale-pending-booking auto-cancel, see [Section 8](#8-booking-availability--fixed-slots-v51)) only fires while someone manually runs `php artisan schedule:work` in an open terminal — it is **not persistent** and stops on terminal close / machine restart. That's intentional for now: this app still runs on a personal dev laptop via XAMPP, and a permanent 24/7 Task Scheduler entry only makes sense once it's deployed to a real always-on server. **Reminder for that day:** create a Windows Task Scheduler entry (or cron, if the eventual host is Linux) that runs `php artisan schedule:run` every minute |

---

*Documentation updated for Villa Elena Resort Management System — Capstone Project 2026*
*Version 5.1 — Updated August 2026 (Fixed Day/Night Booking Slots, Pre-v5.1 Data Reset)*
