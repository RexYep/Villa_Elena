# Villa Elena Private Rental Resort
## Resort Management System — Project Documentation

**Version:** 7.0
**Stack:** PHP 8.2 / Laravel 12 / MySQL 8 / Bootstrap 5
**Local URL:** `http://127.0.0.1:8000` (`php artisan serve`) or `http://localhost:8000` (Docker — see v5.3)
**Live URL:** `https://villa-elena.onrender.com` (Render, free tier — testing only, not yet handed to real guests)
**Database (local):** `villa_elena_db` (MySQL, XAMPP or standalone MySQL — same database either way)
**Database (live):** Aiven MySQL free tier, database `defaultdb`

---

## What Changed in v7.0 (Read This First)

### Two guests booked the same slot, and the availability check was never the problem

Found by testing the deployed system with two accounts booking the same date and slot. Both succeeded. The rows are still in production:

| id | ref | user | slot | status | paid | `created_at` |
|---|---|---|---|---|---|---|
| 9 | `VE-YHLBMLUU` | 7 | 2026-09-15 08:00 → 17:00 (Day) | confirmed | ₱2,000 | 2026-09-08 16:33:05 |
| 8 | `VE-4C7INQOG` | 9 | 2026-09-15 08:00 → 17:00 (Day) | confirmed | ₱2,000 | 2026-09-08 16:33:05 |

Both payments landed at `16:33:41`. Identical timestamps to the second — the fingerprint of a race, not of a broken rule.

**`Booking::hasConflict()` was correct and stayed correct.** It is a plain datetime overlap, so Day (08:00–17:00) and Night (19:00–06:00+1) on one date genuinely don't collide and are both bookable — the intended behaviour. The defect was that it is a **SELECT**, and every caller did:

```php
if (Booking::hasConflict(...)) { return back()->withErrors([...]); }   // ← both requests read here
// ...
$booking = Booking::create([...]);                                     // ← both requests write here
```

Nothing held anything across that gap, and there was no constraint underneath to catch the result. Two submits that arrive together both see a free slot before either row exists.

### Reproduced, then proved fixed, with real concurrent processes

Six OS processes, each waiting on a shared wall-clock instant, all going for one slot. Running the **old** shape (check, then create) against the new schema:

```
worker 3: GOT SLOT -> VE-WFVOLXN5 (id 141)
worker 1: EXCEPTION — Duplicate entry '14:2027-01-15:08:00:00' for key 'bookings.bookings_slot_hold_unique'
worker 2: EXCEPTION — Duplicate entry '14:2027-01-15:08:00:00' …
worker 4: EXCEPTION — …    worker 5: EXCEPTION — …
```

The old code really does attempt five inserts for one slot — the production bug, reproduced on demand. Through `reserveSlot()`:

```
worker 4: GOT SLOT -> VE-LXAJ2PAH (id 146)
worker 1: rejected — slot taken      worker 2: rejected — slot taken
worker 3: rejected — slot taken      worker 5: rejected — slot taken
worker 6: rejected — slot taken
```

One winner, five clean rejections, no exceptions. Day and night racing on the *same* date still yield one of each, which is the rule the system is supposed to enforce.

### `Booking::reserveSlot()` — the only way to create or move a booking

```php
$booking = Booking::reserveSlot($propertyId, $checkin, $checkout, fn () => Booking::create([...]));

if ($booking === null) {
    return back()->withErrors(['dates' => 'This selected date/slot is no longer available.'])->withInput();
}
```

It wraps the check and the write in one transaction under `lockForUpdate()` **on the `properties` row**. That choice matters: the walk-in path already had a transaction, but it locked `bookings` rows and leaned on InnoDB gap locks to block a row that *does not exist yet*. Locking a real, guaranteed-present row makes the serialization deterministic. The callback runs only when the slot is provably free and still inside the lock, so it must stay DB-only — mail, Pusher and notifications belong after the commit.

**A lock only works if every writer takes it**, which is why the walk-in's private version was removed rather than left alongside. All seven paths now share this one:

| Path | Before |
|---|---|
| `Portal\PortalController::submitBooking()` | check + create, no lock ← **the reproduced bug** |
| `Admin\BookingController::store()` | check + create, no lock |
| `Admin\BookingController::extendStay()` | check + update, no lock |
| `Admin\CalendarController::moveBooking()` | **no availability check whatsoever** |
| `Staff\FrontDeskController::store()` | own transaction + `bookings` row lock |
| `Customer\BookingController::update()` (reschedule) | check + update, no lock |

`moveBooking()` — the admin calendar's drag-and-drop — was the widest hole in the system and was found only while auditing for this fix: an admin could drag one booking directly on top of another and nothing objected. It now returns `409` with a reason, and the calendar's `eventDrop` handler surfaces that message instead of a generic one.

### `bookings.slot_hold` — the database-level backstop

Application locks stop races in code that remembers to take them. The unique index stops them in code that forgets:

```
slot_hold = "14:2026-09-15:08:00:00"   while the booking holds its slot
slot_hold = NULL                       cancelled / no_show / soft-deleted
```

A composite unique index on `(property_id, check_in_date, check_in_time)` would have been wrong: a cancelled booking is still a row, so it would block re-booking the slot it just released. MySQL permits repeated NULLs in a unique index, so the nullable column expresses exactly the invariant wanted — *at most one **live** booking per slot* — while leaving cancelled and deleted rows unconstrained.

`Booking::computeSlotHold()` owns the value; it is not fillable and no controller writes it. A `saving` hook recomputes it, and a `deleted` hook nulls it — `SoftDeletes::runSoftDelete()` updates through the query builder and never fires `saving`, so without that second hook a soft-deleted booking would hold its slot forever. Verified: cancelling frees the slot, soft-deleting frees the slot, and both are immediately re-bookable.

**Its exclusion list must stay identical to `hasConflict()`'s** (`cancelled`/`no_show` only). If they drift, the index rejects a booking that the availability grid is advertising as open.

### Expired holds are now released inside the lock, not only by cron

The two mechanisms disagreed about an abandoned unpaid hold. `hasConflict()` *ignores* a `pending` booking older than `booking_hold_minutes` — so the grid calls the slot free — but that row still carried a `slot_hold`, so the index would have refused the INSERT. A slot open to the eye and closed to the database.

`reserveSlot()` therefore formally cancels overlapping expired holds while holding the lock, through the new `Booking::releaseAsExpiredHold()`. `AutoCheckInOutBookings::cancelStalePendingBookings()` now calls the same method rather than keeping its own copy of the wording, the cancellation fields and the two notifications. Its guest and admin notifications go out via `DB::afterCommit()` — announcing a cancellation for a transaction that may still roll back would be a lie, and outside a transaction the callback fires immediately, so the sweeper behaves exactly as before (verified end to end).

The side effect is that **booking correctness no longer depends on the external cron pinger running.** Previously, if the pinger stalled, stale holds sat `pending` indefinitely.

### The second double-booking route: a late payment on a slot someone else now owns

Unrelated to concurrency, and it needed no simultaneity at all:

1. Guest A books → `pending`, PayMongo checkout session created.
2. A never pays. The hold expires; the slot stops blocking.
3. Guest B books the slot and pays → `confirmed`.
4. A returns to their **still-open** PayMongo QR and pays. `recordPaymongoPayment()` → `confirmOnFirstPayment()` promoted A to `confirmed` without ever re-checking availability.

`confirmOnFirstPayment()` now re-checks before promoting. The payment is still recorded — money in hand is never thrown away — but the booking is left `pending` and admins are notified to decide which guest keeps the slot. `pending` is also the correct resting state here: the stale sweeper skips anything with `amount_paid > 0`, so it stays visible and funded rather than being auto-cancelled out from under a paying guest.

`Admin\BookingController::updateStatus()` got the matching guard: reviving a `cancelled`/`no_show` booking re-acquires its slot, and after v7.0 that would otherwise surface as a raw duplicate-key 500 instead of a sentence.

### Migrating, and the two rows that are still double-booked

`2026_09_08_100000_add_slot_hold_to_bookings_table` backfills in PHP rather than SQL (one implementation shared with `computeSlotHold()`, and it runs on SQLite for the test suite), then adds the unique index.

**The index cannot be created while a duplicate exists**, and production has one — `VE-4C7INQOG` / `VE-YHLBMLUU` above. The migration does **not** cancel or refund anything: real guests have ₱2,000 each in play and choosing between them is the admin's call, not a migration's. It keeps the earlier row's `slot_hold`, nulls the later one so the index can be built, and prints both refs:

```
⚠️  Pre-existing double-bookings found while adding the slot_hold unique index.
    These rows were left untouched but excluded from the index — resolve them manually:
    VE-YHLBMLUU (id 9) collides with VE-4C7INQOG (id 8)
```

Both bookings stay fully intact and visible in the admin panel. **This still needs resolving by hand:** decide which guest keeps 2026-09-15 Day and refund the other through the existing Send Money flow.

Two consequences of that "left untouched but excluded" state, both handled:

- **The excluded row must stay editable.** Its `slot_hold` is NULL, so the `saving` hook would recompute the real value on the next save and hit the duplicate key — meaning the simplest admin edit on exactly the row that needs fixing (recording the refund, changing status) would 500. The hook therefore refuses to let an **existing** row re-claim a `slot_hold` that another row already holds; it stays excluded instead. A **new** row always gets its true value (`$booking->exists` is false during a create), so the index's protection against fresh double-bookings is untouched. Verified: editing and cancelling the excluded row both work, and a new booking on that slot is still refused.
- **Deploy the migration with the code, not after it.** Render only runs migrations when `RUN_MIGRATIONS=true` is set for that deploy (§15). If this code ships against the old schema, the `saving` hook writes a column that doesn't exist and *every* booking insert 500s. `Booking::slotHoldColumnExists()` (one cached `Schema::hasColumn` per process) makes the code tolerate both schema shapes during a rollout — but **set `RUN_MIGRATIONS=true` on the deploy that carries v7.0 anyway**, or the database-level backstop simply won't exist.

---

## What Changed in v6.9 (Read This First)

### PayMongo disabled the production webhook, and the URL was the reason

PayMongo emailed to say the webhook was disabled, listing the usual suspects (4xx/5xx responses, changed body, a firewall in the way). The application log had nothing at all — no rejected signature, no error, no delivery. That absence was the clue: the requests were never reaching the controller.

`php artisan paymongo:webhooks` (new, below) showed why. The production webhook was registered at the **bare origin**:

```
hook_Gowz…   disabled   https://villa-elena.onrender.com                     ← no path
hook_Z9mp…   enabled    https://…ngrok-free.dev/webhooks/paymongo            ← correct
```

`/` is `Route::get('/', …)`. A `POST` there is **405 Method Not Allowed**, produced by the router before any controller runs — which is exactly why nothing was logged. Confirmed against the live deployment:

```
POST https://villa-elena.onrender.com/                   → 405
POST https://villa-elena.onrender.com/webhooks/paymongo  → 401   (old code, unsigned probe)
```

Every delivery 405'd, PayMongo counted the failures, and disabled the endpoint. **So the first question when a webhook goes dead is not "is the secret right?" — it is "where is it actually pointed?"** A wrong URL leaves no trace in the app log, and the secret is the thing everyone reaches for first.

The registration has been corrected to `https://villa-elena.onrender.com/webhooks/paymongo`. It is still **disabled** — re-enable it with `php artisan paymongo:webhooks --enable=all` once the fixes below are deployed, since enabling it against the old code would just get it disabled again.

### The webhook endpoint can no longer return a non-2xx status, ever

The 405 is what disabled it this time, but the endpoint had two of its own ways to earn the same fate, and both are now closed:

- **`401` on a failed signature.** A mode-mismatched secret means *every* delivery fails, so this was never one bad request — it was a guaranteed disable.
- **`500` on any thrown exception.** A DB hiccup, an unmapped enum, a malformed body (`$request->json('data')` returns `null` or a string, and indexing that is a `TypeError`) — each would have been a 500.

`PaymentController::webhook()` is now a thin shell that calls `handleWebhookEvent()` inside `try/catch (\Throwable)` and always answers `200`. **The structural point is that `handleWebhookEvent()` returns arrays and never calls `response()`** — there is no path through it that can produce a status code at all. What happened is reported in the JSON body (`reason`: `invalid_signature`, `malformed_payload`, `unhandled_event`, `unmatched_booking`, `processing_error`) and in the log, not in the status.

The trade-off is real and deliberate: **PayMongo will not retry an event that fails mid-processing.** A wrong 200 costs one logged warning; a wrong 401 costs the entire endpoint and every payment after it, silently, until someone notices an email. The second is far more expensive. To cover the gap, both the `unmatched_booking` and `processing_error` paths now notify admins (through `notifyAdminsQuietly()`, which swallows its own failures so a broken DB can't turn the notification into the 500 we were trying to avoid) so money that arrived without being recorded is visible to a human.

`transferCallback()` got the same treatment: each `syncStatus()` is wrapped individually, so one bad transfer neither aborts the loop over the others nor 500s the response.

### Webhook secrets are per-mode now

The signature header is `t=…,te=…,li=…` — `te` is signed with the **test** webhook's secret, `li` with the **live** one, and those are two different `whsk_…` values from two separate registrations. The old code computed one HMAC from a single `PAYMONGO_WEBHOOK_SECRET` and tried it against both slots, so a test-mode webhook pointed at a deployment holding the live secret failed 100% of deliveries.

`verifyWebhook()` now maps each slot to its own candidate list:

| Slot | Secrets tried, in order |
|---|---|
| `te=` | `PAYMONGO_WEBHOOK_SECRET_TEST`, then `PAYMONGO_WEBHOOK_SECRET` |
| `li=` | `PAYMONGO_WEBHOOK_SECRET_LIVE`, then `PAYMONGO_WEBHOOK_SECRET` |

The generic variable still works on its own, so single-mode setups are unchanged. Setting both `_TEST` and `_LIVE` lets a test-mode and a live-mode webhook be registered against **the same URL** and both verify — which is what this deployment actually wants, since it runs test keys on the production host. The rejection log now names which secret is missing rather than just saying the signature was bad.

### `php artisan paymongo:webhooks`

New command (`app/Console/Commands/PayMongoWebhooks.php`), in the same spirit as `paymongo:probe-transfer` — production has no shell, so it runs from a dev machine against the PayMongo API using the key in `.env`.

```bash
php artisan paymongo:webhooks                                   # list: id, status, URL, events
php artisan paymongo:webhooks --webhook=hook_xxx --url=https://…/webhooks/paymongo
php artisan paymongo:webhooks --enable=all                      # revive every disabled one
php artisan paymongo:webhooks --disable=tunnels                 # park every endpoint whose host is gone
```

It prints which **mode** it is looking at up front (a test key sees only test-mode webhooks — an absent webhook is usually one registered in the other mode, not a missing one), and flags in red any URL whose path is not `/webhooks/paymongo`, printing the exact command to repair it. `--enable` warns before reviving a webhook whose URL is still wrong, because that revival lasts only until the next few deliveries.

A disabled webhook does **not** recover on its own, and nothing in the app can tell that it is disabled — every payment simply stops being recorded. This command is the only way to see that state from here.

### `php artisan paymongo:tunnel` — the watcher

`--disable=tunnels` is a **manual** command. Nothing schedules it, no hook fires it, and `routes/console.php` does not mention it — so parking the ngrok webhook was a thing you had to remember. `paymongo:tunnel` is the automatic version.

```bash
php artisan paymongo:tunnel                 # watch until Ctrl+C
php artisan paymongo:tunnel --once          # reconcile once and exit (no exit-disable)
php artisan paymongo:tunnel --dry-run       # report decisions, touch nothing
```

It resolves the target webhook from `--webhook`, then `PAYMONGO_TUNNEL_WEBHOOK`, then by matching the host ngrok is currently serving. Every `--interval` seconds (default 10) it probes and reconciles: healthy → enabled, dead → disabled, and it calls PayMongo **only on a transition**, so an idle overnight watch makes no API calls at all. If ngrok hands out a different URL than the one registered (what happens without a reserved domain), it repoints the registration first.

**The ngrok agent is not the signal, and this was measured, not assumed.** The obvious design is to poll `http://127.0.0.1:4040/api/tunnels` and treat "a tunnel exists" as healthy. That is wrong: the agent can be perfectly happy — tunnel listed, agent API answering 200 — while the endpoint still returns **502**, because nothing is listening on the port it forwards to. That is exactly the state this project was found in. So the watcher probes **the registered URL, end to end**, with the same request PayMongo would send. Do not "optimise" this into an agent-API check.

**It refuses to follow anything that is not a tunnel.** `NGROK_HOSTS` gates it, and that guard is not decorative. Without it, `--webhook=<the production hook>` has two independent ways to cause real damage: `reconcile()` would repoint production's registration at ngrok, and the exit path would disable production every time you closed the terminal. One mistyped id away, so the host is checked before the loop starts. `isTestMode()` is checked too — live keys are rejected outright.

**Exit-disable is best-effort on Windows, and you should know which half is guaranteed.** `pcntl` does not exist on Windows; `sapi_windows_set_ctrl_handler` does, and that is what catches Ctrl+C, with `register_shutdown_function` behind it. What no handler can catch is a hard kill — `taskkill /F`, closing the terminal window, losing power. Verified by measurement: a `taskkill /F` test left the webhook `enabled`, exactly as expected.

That residual gap is the *status quo ante*, not a regression: an abandoned watcher leaves the webhook exactly as enabled as never running one. The next `paymongo:tunnel` reconciles it, and `paymongo:webhooks --disable=tunnels` remains the manual broom.

**To make it part of the dev session**, add it to the `dev` script's `concurrently` list in `composer.json`:

```
"php artisan paymongo:tunnel"      # and add ,tunnel to --names
```

`--kill-others` then stops it with everything else. Note this makes every `composer dev` mutate the PayMongo account, which is why it is opt-in rather than the default.

### Two webhooks in the SAME mode means two different secrets

The `_TEST` / `_LIVE` split above solves a *cross-mode* mismatch. It does not solve this one, and the difference is easy to miss: **both** registrations in this account are test-mode, so both sign the `te=` slot — with **different** `whsk_…` values, because a secret belongs to a registration, not to a mode.

So if Render's `PAYMONGO_WEBHOOK_SECRET` happens to hold the *ngrok* webhook's secret, production deliveries still fail verification even with everything else correct.

Nothing more is needed in code — `verifyWebhook()` already tries a *list* per slot (`_TEST`, then the generic), so a deployment that must accept both can set one in each variable. In practice the two environments have separate envs and each only needs its own:

| Where | `PAYMONGO_WEBHOOK_SECRET` should be |
|---|---|
| Render | `hook_Gowz…`'s secret (the `onrender.com` registration) |
| Local `.env` | `hook_Z9mp…`'s secret (the ngrok registration) |

PayMongo shows a webhook's secret **only when it is created**, so it cannot be read back from the API or from `paymongo:webhooks` — if it was never saved, the registration has to be recreated to get a new one. After deploying, send a test event from the dashboard and read the log: an `invalid_signature` warning whose hint names the test-mode secret means this is what's wrong.
### The ngrok webhook is the same trap, one step behind

The test-mode account holds a second registration pointing at the dev machine's ngrok tunnel, and it is **enabled**. Both webhooks live in the same account, so **both receive every test-mode event** — including the ones production generates. While the tunnel is not running, each of those deliveries hits ngrok's edge and comes back **502**:

```
POST https://vagueness-widow-anchovy.ngrok-free.dev/webhooks/paymongo  → 502  (×3, consistent)
```

The domain itself is a **reserved static** ngrok domain, so the URL is not the problem — it does not rotate between sessions. The problem is that the local end is only there while someone is actively developing, and PayMongo keeps delivering the other 99% of the time. It is accumulating exactly the failure count that disabled production, just more slowly.

**A 502 here does not mean "ngrok is not running"** — that was the first reading, and it was too narrow. ngrok's edge returns 502 whenever it cannot reach its *upstream*, which includes a perfectly healthy agent forwarding to a port where nothing is listening (no `php artisan serve`). Both look identical from PayMongo's side, and both count toward disabling; but only the end-to-end probe distinguishes "reachable" from "an agent is running". See `paymongo:tunnel` below.

The blast radius is small and worth stating plainly: `status` is **per webhook**, not per account (that is how one row could read `disabled` while the other read `enabled`), so this cannot take production down. What it costs is the *next* local test session — you start ngrok, make a payment, nothing records, and the reason is a webhook PayMongo quietly switched off days earlier.

So `--disable=tunnels` exists to park it between sessions, and `--enable=<id>` brings it back. It probes each enabled endpoint first and only disables the ones whose **host is absent** — no connection, or 5xx from an edge.

**A 4xx never counts as absent, and that distinction is load-bearing.** A 4xx means something answered: the host is up and rejected that particular request. When this was first written the rule was `>= 400`, and production — still on the old code, answering `401` to the unsigned probe — was one command away from being disabled by the tool built to protect it. A code problem gets fixed in code, not by deregistering the endpoint.

### Files touched

| File | Change |
|---|---|
| `app/Http/Controllers/PaymentController.php` | `webhook()` split into an always-200 shell + `handleWebhookEvent()`; `announceWebhookFailure()`, `notifyAdminsQuietly()` added; `transferCallback()` per-transfer `try/catch` |
| `app/Services/PayMongoService.php` | `verifyWebhook()` made per-mode; `hasWebhookSecret()`, `listWebhooks()`, `updateWebhook()`, `enableWebhook()`, `disableWebhook()`, `probeEndpoint()`, `endpointIsDead()` added |
| `app/Console/Commands/PayMongoWebhooks.php` | New — list, `--webhook`/`--url` repair, `--enable`, `--disable` |
| `app/Console/Commands/PayMongoTunnel.php` | New — watches the ngrok endpoint and enables/disables the webhook to match |
| `config/services.php` | `webhook_secret_test`, `webhook_secret_live`, `tunnel_webhook` |
| `.env.example` | Both new variables, documented |

---

## What Changed in v6.8 (Read This First)

### The availability calendar on the property page became an input

`portal/property.blade.php` put a full FullCalendar month grid *beside* the photo gallery in a two-column `.top-grid`, both above the property name. Two dense grids of equal visual weight opened the page, and the calendar was `selectable: false` with no `dateClick` — so a visitor read a date off it and then **retyped that date by hand** into the booking form six inches away. A large widget that looks interactive but is not reads as clutter, which is exactly how it felt.

**Layout.** `.top-grid` is gone. Order is now header (type / name / meta) → full-width gallery → `.detail-grid`, with the calendar demoted into the left column below Amenities as a peer of the other sections. The name is above the fold instead of below the gallery.

**The calendar now fills the form.** Clicking an open slot pill sets `#checkin`, checks the matching slot radio, fires the existing server price preview, and flashes the booking card. The binding is **two-way** — editing the date or slot in the form moves the highlight in the calendar — because they are two views of one selection, not two independent controls.

**Slot-aware cells replaced the "Booked" event bars.** The old calendar drew one red bar across a booked date, which **lied**: a date booked for `night` still has its `day` slot free (the 6:00 AM → 8:00 AM gap). Each day cell now renders two pills, Day over Night, each independently open / booked / selected. There are no FullCalendar events on this page at all any more, so the popovers, the list-view hover overrides, and the `listMonth` toggle all went with them; the toolbar is `prev,next` + title, and `validRange` blocks navigating into the past.

**`PortalController::buildSlotAvailability()` replaced `$bookedRanges`.** The view is handed `['2026-09-14' => ['night' => 108]]` — closed slots per date, with the booking id — instead of raw date ranges. It **deliberately mirrors `Booking::hasConflict()` clause for clause**: same status filter, same abandoned-pending-hold exemption, same datetime-overlap test through `Booking::slotDateTimes()`. That equivalence is the whole point and is the thing to re-check if either side is edited — a stricter calendar shows a slot as closed that the server would accept, a looser one rejects the guest *after* they pick. The old `$bookedRanges` query omitted the pending-hold exemption, so abandoned unpaid holds were being drawn as booked when the booking form itself would have taken them.

Verified by cross-checking all **240 slot-days** (120 days × 2 slots) against `hasConflict()` directly: 0 mismatches. Then verified by real clicks in a browser — a fake would not have caught that the page's `html` carries `scroll-behavior: smooth`, nor proved that clicking a *booked* pill correctly falls through to the cell handler and picks the still-open slot instead.

**Redundant availability signals removed.** Four things answered "is this free?": the calendar, the price preview's server check, a submit button labelled *"Check Availability"*, and a footer note saying *"Some dates may not be available. We'll confirm availability when you proceed"* — which contradicted the calendar directly above it. The note is deleted and the button reads **"Reserve Now →"** from the start, going disabled as **"Not Available"** only when the server preview actually says so. The three Tagalog user-facing strings in that component (`Kinukumpirma...`, `Hindi Available`, and the popover's `Naka-book ang Villa...`) are now English like the rest of the page; code comments stay Tagalog per this repo's convention.

**Live (Pusher) updates still work**, but patch the slot map rather than adding/removing events — `applyBlocked()` recomputes which (date, slot) pairs a broadcast booking covers using the same overlap math, and `applyFreed()` clears entries by booking id, which is why the id is carried in the map.

### Follow-up: on a phone, that calendar showed no availability at all

The mobile breakpoint set `.slot-pill { font-size: 0; height: 6px }` below 560px, on the reasoning that "Day" / "Night" can't fit in a ~43px cell. What it actually produced was fourteen unlabelled 6px bars per week — **the one piece of information the section exists to show was deleted on the device most guests browse from**, and the colour difference between a beige open bar and a pink booked one at that size is not readable. A pill you can't read is also a pill you can't aim a thumb at.

The labels are back on every width down to 320px, bought with space rather than surrendered:

- **The calendar takes the page gutters back.** Below 900px the layout is one column, so `.fc-wrap` gets negative side margins equal to `.main`'s padding (`-20px`, then `-16px` under 480px) and drops its own padding to 10px/6px. That is ~+8px per cell — the difference between "Night" fitting and not. Verified for horizontal overflow at 320 / 360 / 390 / 414 / 480 / 540 / 768 / 900 / 1024: `scrollWidth == clientWidth` at all nine, and **0 of 56 pill labels clipped** (`scrollWidth > clientWidth`) at the three narrowest.
- **Pills are `flex` with a sun / moon icon + label**, matching the legend's *Top = Day · Bottom = Night*. The icon is the first thing dropped (under 380px), never the word — position and colour already carry the rest, but the word is the part that shouldn't have to be inferred. `is-taken`'s line-through moved onto the label span so it doesn't strike the icon.
- **Type scales instead of vanishing**: 11px → 10px → 9.5px, with the day numbers and weekday headers coming down with it.
- **`.fc a { color: inherit; text-decoration: none }`** — FullCalendar's day numbers and weekday headers are anchors, and were inheriting the portal's blue underlined link style, so every date looked tappable-as-a-link on every width. This was wrong on desktop too.

**Two empty rows are gone**, worth roughly a third of a phone screen: `fixedWeekCount: false` drops the padded sixth week, and `hidePastWeekRows()` (on `datesSet`) hides any week with nothing pickable in it — the current month's first row is usually entirely before today and blocked by `validRange`. Those out-of-range cells carry **no `data-date` attribute**, so the test can't be "every cell is in the past"; it is "no cell in this row has a date `>= today`".

Verified by rendering the real page in 390 / 360 / 540px frames and clicking a pill: `#checkin` filled, the slot radio checked, the card flashed and the price preview fired — the delegated handler still resolves because it uses `closest()`, and the click now lands on the icon or the label span rather than the pill itself.

### Follow-up: the gallery decided its own height, and picked 932px

The hero gallery was `min-height: 460px` with `grid-template-rows: 1fr 1fr` and `.gallery-img { height: 100% }`. A grid container with no definite height makes those `1fr` rows — and therefore the `height: 100%` on the image — resolve against nothing, so the image fell back to its **intrinsic** size. On a 1366×641 laptop the villa's 798×600 photo rendered at **1240×932**: one and a half viewports tall for a single picture, and upscaled 1.55× past its own resolution, so it was blurry as well as enormous. `min-height` was only ever a floor; nothing was a ceiling. The taller the source photo, the worse it got.

The height is now the layout's decision, not the file's:

```css
.gallery { height: clamp(280px, 38vw, 520px); max-height: 56vh; }
```

The clamp tracks screen width; `max-height` handles the case width alone can't see — a **short** window, where 38vw still resolves to something taller than the screen. Measured share of the viewport, one photo and three: 1920×1080 → 48%, 1440×900 → 56%, 1366×768 → 56%, 1366×641 → 56% (was **145%**), 1024×768 → 51%. `object-fit: cover` was already there, so a bounded box crops rather than stretches — the framing improved as a side effect, since the old stretched box was showing mostly roof and sky.

Below 900px the rows take over (`height: auto`), each capped the same two ways: `min(clamp(190px, 34vw, 300px), 34vh)`. The `vh` half only bites on a short, wide window — 900×700 went 69% → 54%, while 768×1024 stayed at 41%.

**One explicit row, not three.** The ≤480px rule declared `grid-template-rows: 200px 120px 120px` for a property that may well have one photo, leaving ~200px of empty grid under it — real, since this villa currently has exactly one image. Only the main tile's row is explicit now; the secondary tiles land in **implicit** rows (`grid-auto-rows`), which exist only if there is something to put in them. One photo on a 390px phone: 422px of gallery → 195px. Three: 422px.

Verified at 13 viewport sizes from 320×700 to 1920×1080, with the DOM patched to three tiles as well as the one this property has, checking gallery height, per-tile height, and `scrollWidth == clientWidth` at each.

### Follow-up: the calendar legend was answering two questions in one flat row

It read: *Open · Booked · Your pick · ☀ Top = Day · ☾ Bottom = Night* — five sibling items of equal weight covering **two unrelated questions** (what does this colour mean, and which pill is which slot). Worse, half of it had gone stale: once the pills carried the words "Day" and "Night" themselves, *"Top = Day, Bottom = Night"* was teaching a positional code the guest no longer has to learn.

It is now two labelled groups:

- **SLOTS** — `☀ Day 8:00 AM – 5:00 PM` and `☾ Night 7:00 PM – 6:00 AM next day`. This replaces the top/bottom instruction with the thing the grid genuinely cannot show: **the times**, and the fact that night check-out is the *following* morning — the single most misread part of the two-slot model. The strings are built from `Booking::SLOTS`, not typed into the view, so a slot change can't leave the legend lying (the booking card's own `<small>` times are still hardcoded; worth folding into the same source next time that file is touched).
- **AVAILABILITY** — Open / Booked / Your pick.

**The keys are pill-shaped, not square patches**, and the three state colours are now declared **once** for both (`.slot-pill.is-open, .legend-swatch.is-open { … }`), so the key cannot drift from the grid it explains. The Booked key also carries a strike line through it, mirroring the pill's struck label — the same second signal, for the same reason: colour alone fails a colour-blind guest, and fails anyone reading a phone in sunlight.

The group labels share a fixed 86px column so both rows start at the same edge; on phones (≤560px) that column collapses to its natural width, since there is no spare space to spend on alignment there. 122px tall at 390px and at 320px, no overflow at either.

### The customer dashboard on a phone

`customer/home.blade.php` had one `@media(max-width:768px)` block containing three rules. What it left behind:

**The hero's buttons were ragged.** Stacking the hero set `flex-direction: column` and `text-align: center` but kept the desktop `align-items: center`, so each button shrank to its own text width — *Browse Properties* wide, *My Bookings* narrower, both floating centred. `align-items: stretch` plus `flex: 1 1 0` on the buttons is the whole fix; under 560px they stack full-width. The heading also went to `clamp(21px, 5.6vw, 30px)`, since 30px broke "Welcome back, Nick" across two lines with an orphan.

**The page scrolled sideways on small phones** — 26px at 360px, with the amount column clipped mid-peso. `.booking-row` is a three-column flex (thumb / details / money) that cannot compress below its content, so the details wrapped to four lines and the money hung off the edge. The money column now drops to its own line under the details at 560px and below. Note the second, subtler half: that first fix *made the overflow worse* (45px), because a grid item's automatic minimum size is its content's min-content width — the badge + amount + balance sitting on one unbreakable line forced the whole `1fr` column to 354px inside a 293px page. `flex-wrap: wrap` on that inner row is what actually removed it. **When a page scrolls sideways, measure the element whose `right` exceeds `clientWidth` and then walk *up* — the overflowing box is usually not the one setting the width.**

**Two nav bugs, on every customer page rather than just this one.** The sign-out form in `layouts/customer.blade.php` carried `style="display:inline"`, which outranks the `@media (max-width: 900px)` rule in `portal.css` that hides `.nav-right > form` — so the sign-out that was *designed* to live in the hamburger menu on phones was also crowding the topbar, and the brand wrapped onto two lines beside it. The inline style is now a class (`.nav-logout-form`, hidden by that same rule) and `.nav-brand` is `white-space: nowrap`.

**Rows became links.** A recent-booking row's only tap target was the reference number; the whole row is an `<a>` now. Same for notification rows, which had no link at all.

### The notifications card was redundant — because it was inert

Three things led to notifications: the nav link, the topbar bell (whose unread dot already works on every page, via the `layouts.customer` view composer), and this card. The card showed five unread items **that could not be clicked**, plus a "No new notifications" empty box that took a third of the sidebar on the days nothing was wrong.

It is not deleted; it is conditional and actionable. It renders only when something is unread, retitled **"Needs your attention"**, and every row opens `customer.notifications.open` — which marks the notification read and jumps to whatever it is about (a refund destination form, a booking). That makes it the one place a guest can *act* on an alert, rather than a third route to the same list. When nothing is unread the card is absent, which is the common case.

Two dead pieces went with it: the row keyed its dot off `$notif->read_at`, **a column that does not exist** (notifications use `is_read` — see §13), and the query it reads is unread-only, so the "read" state it was styling for was unreachable twice over.

### The booking detail page, same two faults

`customer/booking_detail.blade.php` repeated the dashboard's pattern: a flex hero that only *looked* stacked on a phone, and a horizontal-scroll table.

**The hero was six flex siblings** — ref, check-in, arrow, check-out, nights, badges — plus a review CTA. Wrapping them individually produced a different ragged arrangement at every width: at 390px the two dates sat side by side with the nights count stranded next to the badges; at 360px the arrow stayed with check-in while check-out dropped to the next line; the badge column stayed right-aligned while everything else was centred. The review CTA, styled `display:block; margin-top:14px`, is a **flex child** — neither declaration does what it was written to do there, so it rendered as a floating pill inside the row instead of a button.

Fixed by grouping rather than by adding breakpoints: `.hero-stay` holds check-in → check-out → nights (one sentence, so it moves as one), `.hero-badges` holds the two badges, and the CTA became `.hero-cta` with real classes and a CSS `:hover` instead of inline styles and `onmouseover`. Under 600px `.hero-stay` becomes `grid-template-columns: 1fr auto 1fr` with the nights count spanning below — **grid, because flex-wrap decides what drops by what happens to run out of room first**, which is what split the dates in the first place. The arrow's `display: none` was removed: it is the only thing saying the two dates are a span rather than two unrelated days.

**Payment History scrolled sideways** and the page went with it. `.pay-table` was `display: block; overflow-x: auto; white-space: nowrap` under 600px, which ran the headers together ("METHODTYPE"), clipped the amount column mid-peso at 360px, and still contributed a **275px min-content** width — pushing the whole page 38px wider than the screen. Each payment is now a small block of labelled lines (`thead` hidden, `td::before { content: attr(data-label) }`), so nothing scrolls and nothing is cut off. `.info-row` also got a 16px gap, since long values like "Wednesday, September 2, 2026" were touching their labels.

Verified at 320 / 360 / 390 / 768 and on desktop, across three booking states (checked-out with a review CTA, checked-out already reviewed, confirmed with a balance due and the cancel form): `scrollWidth == clientWidth` everywhere.

### The bookings list: a filter bar that read as clutter, and cards three times taller than they needed to be

**Six filter chips, wrapping.** `.filter-bar` was `flex-wrap: wrap`, so on a phone the six status chips fell into three ragged rows of different widths (51 / 85 / 99, then 104 / 102, then 95) taking 127px of vertical space before the first booking. Nothing was broken, but it read as scattered pills rather than one control.

Under 560px it is a **3-column grid** of equal chips (two tidy rows, 87px); under 340px, two columns. A horizontally-scrolling chip strip is the other common answer and was rejected: with only six options, hiding half of them behind a swipe costs more than it saves. Verified at 320 / 360 / 375 / 390 that no chip label is clipped (`scrollWidth > clientWidth` on zero of six at every width) — the 340px cut-off is where "Confirmed" stops fitting a third of the row.

**One booking was 307px tall.** The mobile rule was `flex-direction: column` on the whole card, which put the thumbnail alone on the first line, then the details, then the badge, then the amount — barely one and a half bookings per phone screen on a list page whose entire job is scanning. Image and details now stay side by side and only the badge/amount pair drops below, indented to line up with the details: **307px → 158px**, so three and a half fit instead of one and a half. That rule also moved from ≤768 to ≤600 — between those widths there is room for the original three-column row, and the card is 105px there.

**A one-line detail that cost a line.** `.bc-dates` was a flex container holding an icon and a single text node. A text node is one flex item, so it could not break around the icon: the whole date string dropped below it, leaving the calendar icon alone on its own line. Plain inline flow fixed it (68px → 45px).

**Cards became links**, matching the dashboard — the tap target was the reference number alone, in a 68px-tall card mostly made of whitespace.

Verified at 320 / 360 / 375 / 390 / 768 / 1100: zero horizontal overflow, filters still submit (the chips are `<button type="submit" name="status">`, untouched), and the active chip still highlights.

### My Payments: the amount was the column you had to swipe for

`customer/payments.blade.php` had **no media queries at all** — one six-column table (Date / Booking / Method / Type / Status / Amount) inside a `overflow-x: auto` wrapper. That wrapper is why nothing looked broken: the page never overflowed, it just quietly hid the right-hand columns behind a sideways swipe on a phone. The rightmost column is **Amount** — the one number a guest opens this page for.

Under 600px each payment is now a small card: booking reference and amount on the first line (amount pushed right with `margin-left: auto`), property underneath, then date · method · type · status on a third line. Done with `order` on the existing `<td>`s rather than by restructuring the markup, so the desktop table is untouched.

This is the same class of fix as the booking-detail payment table, but the mechanism differs and the difference matters: **there the sideways scroll leaked out and broke the whole page** (the table's min-content width forced the grid column wider than the screen); **here it was properly contained and therefore invisible** — the page measured clean at every width both before and after. A layout that passes an overflow check can still be hiding the content that matters; `scrollWidth == clientWidth` is a floor, not a verdict.

Checked at 320 / 360 / 390 / 768 / 1366, including a refund row (the negative red amount and its Refund pill survive the reflow) and the paginator (164px wide, well inside a 345px viewport). At 768 the desktop table still fits without scrolling — min-content 595px against 713px available — so the stacked layout deliberately stops at 600px.

### My Profile: an upload control that never said it was one

The photo field was a bare `<input type="file">` wearing `.form-control` — Chrome renders that as **"Choose File | No file chosen"**, with no label saying what it is for, no visible change after picking a file, and no button of its own. The only button on the card is **Save Changes**, at the bottom of a seven-field form. A guest who picks a photo gets one piece of feedback — the words "No file chosen" become a filename, inside a small grey box — and no reason to believe anything else is needed.

The control now names itself and finishes its own sentence:

- **"Profile photo"** as a heading, with the format/size limit beside it, and a **Change photo** button (a styled `<label for>`; the real input is visually hidden but still keyboard- and screen-reader-reachable — `clip`, not `display: none`).
- Choosing a file **shows it immediately in the avatar circle** (`URL.createObjectURL`), prints the filename, and reveals an **Upload photo** button *next to the picture* along with a Cancel that restores the original.
- **Upload photo is a second `submit` in the same form**, so no new route, no new controller path, and no second way for an avatar to reach the database — `Save Changes` still works exactly as before for anyone who scrolls past it.

Verified by handing the input a real `File` through a `DataTransfer` (the same event a picker fires): preview swaps to a blob URL, the initials placeholder hides, the filename and both buttons appear, and Cancel restores the stored photo and empties the input. The button's `form` still resolves to `PUT /my/profile` with `enctype="multipart/form-data"`, and the input keeps `name="avatar"` and its `accept` list, so `ProfileController::update()` is untouched.

### The same page's phone layout

**The tabs were three unlabelled icons.** `@media (max-width:560px)` hid `.profile-tab-btn span`, leaving a person, a shield and a bell — which is which is a guess. Same inversion as the availability calendar: **the icon is what gives, never the word**. The labels now shrink (12px, tighter padding) and stay; below 400px the *icon* is dropped instead. All three read as words at 320px.

**Two switches were orphaned.** `.toggle-row` and `.device-row` were `flex-wrap: wrap` on mobile, so the 2FA switch and the device Remove button fell below their own descriptive paragraph — separated from the thing they control. Both are now a `1fr auto` grid: the control stays beside its text at every width, without overflowing.

### My Reviews: the layout was fine, the content wasn't

This page has no media queries and, with the data currently seeded, doesn't need any — at 320 / 360 / 390 the card, the status badge, the stars and the two buttons all sit correctly and `scrollWidth == clientWidth`. Checking it against real content is what found the bug.

**A pasted link breaks the page sideways.** Review text is written by guests, and guests paste URLs. Injecting one into the rendered card (`https://photos.example.com/albums/villa-elena-anniversary-weekend-2026-full-resolution-set`) pushed the page **89px wider than the screen at 320px, 49px at 360px, 19px at 390px** — a long unbroken token cannot wrap, so it runs past the card and drags the document with it. No breakpoint fixes that; the word itself has to be allowed to break. `overflow-wrap: anywhere` now covers every free-text field on the card — content, title, the admin reply, the rejection note, plus the property name (admin-entered, same exposure). Re-running the identical injection afterwards: 0px of overflow at all three widths.

**This is the general lesson from this page.** The three pages before it were broken by their own CSS and looked broken in a screenshot. This one looks perfect and stays perfect right up until a guest types something longer than the seed data. **Test a text field with the text a real person would put in it** — a URL, a 60-character property name, a title that fills two lines — not only with the row that happens to be in the database.

Two smaller things while there: `.review-content` was **13px, smaller than the 14px metadata above it** — the review is the point of the card, so it now matches; and the booking reference in the meta line links to that booking, as it already does on the dashboard, the bookings list, and the payments page.

### Notifications: text that was being deleted rather than wrapped

Like the reviews page, this one looks right at every width and reports no page overflow — and like the reviews page, that is only true of the text currently in the database.

**A long token was being silently cut off, and the page still measured clean.** `.notif-body` is `flex: 1` with the default `min-width: auto`, which forbids a flex item from shrinking below its longest word. Give it a payment reference (`qrph_A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6`) and the body grows **77px past the right edge of its own card** at 320px — and `.notif-card` is `overflow: hidden`, so the tail is simply clipped. No ellipsis, no scrollbar, `scrollWidth == clientWidth` at the document level: the guest loses the end of the reference and nothing anywhere says so. `min-width: 0` plus `overflow-wrap: anywhere` fixes it (measured after: 272 against a card edge of 289, wrapped inside).

This is the third distinct way a page in this portal has hidden an overflow — a `overflow-x: auto` wrapper (Payments), a plain document-level overrun (Reviews), and now `overflow: hidden` **discarding** the content. Only the middle one is visible to a page-level overflow check. **Measure the element against its own container, not the document against the viewport.**

**Phone padding.** After the icon, the unread dot and 22px of padding, a 320px screen left the message 171px. `14px 16px` padding and a 34px icon below 480px gives it 193px.

### The icon system had never once run

`$icons` mapped `$notif->type` through keys `booking_update`, `payment`, `cancellation`, `reminder`. **All 279 notification rows have `type = 'in_app'`** (as CLAUDE.md says: the other enum values exist but are unused), so no key ever matched and every notification in the system rendered the grey `bi-info-circle` fallback. A colour-coded icon system that had never displayed a single colour.

The title is the only field that actually varies, so the icon is chosen from keywords in it — cancelled/failed → red `x-circle`, refund → indigo, payment → blue `credit-card`, rescheduled → amber, check-in/welcome → green `box-arrow-in-right`, check-out/complete → green `box-arrow-right`, booking → green `calendar-check`, with the same info fallback. On the current data that is four distinct icons on the first page instead of one, and each matches its title ("Check-out Complete → box-arrow-right", "Payment Received! → credit-card").

Keyword matching on a title is a compromise, not a design: the honest fix is for `NotificationHelper` to write a real category. If that ever happens, this `match` is the thing to replace.

### Reschedule form: nothing overflowed, but the slot picker was a 16px target

Measured first, and the layout itself is sound — 0px of horizontal overflow at 320 / 360 / 390 and on desktop, the summary card and the notice box wrap correctly, the submit button is already full-width. Two things were still worth changing.

**The slot choice was two bare Bootstrap radios.** The same decision — Day or Night — is made on `portal/property.blade.php` through `.slot-option` cards: a bordered, fully-tappable label that highlights gold when selected. Here it was `<input type="radio">` plus a text label, so the tap target was the ~16px circle and the two screens disagreed about what the same question looks like. The picker now uses that same card, built from `Booking::SLOTS` rather than hardcoded strings (so the times cannot drift from the model, and Night now says **"7:00 PM – 6:00 AM next day"**, which the old label omitted). Tap area went from a 16px radio to **312 × 67px** at 390px; tapping anywhere on the card selects it, and `:has(input:checked)` moves the highlight.

**The summary card wasted a line on phones.** `flex-wrap: wrap` under 480px dropped the property name below the logo even though both fit side by side (48px icon + ~180px of text at 320px). Now `align-items: flex-start` keeps them on one line, with `overflow-wrap: anywhere` on the name and date line for the same reason as the reviews card.

**Not fixed in that pass — built straight after, see the next section:** this form asked for a date and slot with **no availability feedback at all**. The property page has the slot calendar; here the guest picks blind and only learns the slot is taken when the server rejects the submission. Reusing `PortalController::buildSlotAvailability()` here would close that gap — a bigger change than a responsiveness pass, so it is noted rather than done.

### Built: the reschedule form now shows availability (v6.8)

The gap noted above is closed. The guest no longer picks blind.

**`buildSlotAvailability()` moved out of `PortalController` and into `Booking::slotAvailabilityMap(int $propertyId, ?int $excludeBookingId = null)`.** Two screens needed it, and two copies of that logic would have drifted — which is the exact failure the original doc-comment warns about, since the map has to mirror `hasConflict()` clause for clause. The property page now calls the model; the private method is gone.

**The `$excludeBookingId` parameter is the whole reason this is not a copy-paste.** A booking must not block its own slot when it is the one being rescheduled — precisely what `hasConflict($property, $in, $out, $booking->id)` already does at submit time. Verified on booking 140: with the exclusion its own `night` slot disappears from the closed map while the `day` slot, held by a *different* booking, still blocks.

**`Booking::pastSlotsToday()`** is new alongside it: the server also rejects a check-in that has already started today (`$checkin->isPast()`), and without this the form would offer a slot this morning at nine tonight and only reject it after submission.

**What the form does now.** Each slot card shows live state for the chosen date — *Available* (green), *Already booked* (red), or *Already started today* — and an unavailable card is disabled and dimmed. If the slot you had selected becomes unavailable when you change the date, the selection moves to the open one instead of silently staying on a choice the server will refuse. If both slots are gone, submit is disabled, a notice names the date, and **three real open (date, slot) options appear as chips** that fill the form when tapped — so a fully-booked date is a redirection, not a dead end.

**Verified against the server, not just visually.** Cross-checked the map against `Booking::hasConflict()` over 90 days × 2 slots for the reschedule path (**180 comparisons, 0 mismatches**) and 120 days × 2 slots for the property page after the extraction (**240 comparisons, 0 mismatches**) — the equivalence the original comment asks anyone touching either side to re-check. Then in the browser: the booking's own date offers its own slot, a half-booked date disables only the taken half, a fully-booked date disables both and suggests Oct 16 Day / Oct 16 Night / Oct 17 Day, and clicking a chip fills the form and re-enables the button. No console errors, no overflow at 360 or 390, and the property page's calendar still paints 11 booked and 43 open pills exactly as before.

Note: `Booking.php` and `Customer/BookingController.php` both fail `pint --test` — with the identical fixer list they failed at HEAD, before this change. Left alone rather than reformatting unrelated code inside this diff.

### Refund destination: the amount was last on the page it exists for

The form itself needed nothing — 0px of horizontal overflow at 320 / 360 / 390 and on desktop, in all three states (first entry, editing a saved destination, and the "institution list unreachable" branch). The 95-entry institution `<select>` holds a 73-character option name ("The Hong Kong and Shanghai Banking Corporation Limited, Philippine Branch") without widening anything, because a native select truncates its own label.

**The summary card stacked into three rows on phones.** `flex-wrap: wrap` below 480px put the 💸 icon alone on the first line, the booking reference on the second, and **₱4,000.00 REFUND DUE last** — the number the page exists to confirm, at the bottom of a 238px card. It is now a two-row grid: icon and reference together, then the amount as its own line above a divider with its label pushed to the right, so it reads as the headline it is. 238px → **160px**, and the money is the first thing the eye lands on.

`overflow-wrap: anywhere` on the reference and date line, for the same reason as the reviews and notification cards.

**How this was checked without touching data.** `RefundDestinationController::edit()` requires `isAwaitingPayout()` — a refund whose `status` is still `pending` — and every refund in the local database has already been paid out, so the page 403s for all of them. Rather than inserting a fake pending refund, a temporary local route rendered the view with an **unsaved** `Payment::make()` (plus an optional unsaved `RefundDestination`), which exercises the real Blade, the real institution list, and the real layout while writing nothing. Confirmed afterwards: 9 refund payments and 6 destinations, the same counts as before. The one wrinkle worth remembering is that `route('customer.refunds.destination.update', $payment)` needs a key, so the fabricated model was given an in-memory `id` purely so the form action could be generated.

### Review form: the most important control on the page was the smallest

No media queries and none needed for the layout — 0px of horizontal overflow at 320 / 360 / 390 and on desktop, in both modes (new review and edit). What the measurements found instead was the rating widget.

**27px stars, side by side.** Each `.star-btn` measured **27 × 48px** with 6px gaps: well under the 44px a fingertip needs, with the neighbouring rating right next to it — on the one input the whole page exists to collect. They are now `flex: 1 1 0` with `max-width: 56px`, so the five share the row evenly and take whatever it can give: **42 × 48px at 320px, 50px at 360px, 56px at 390px and above**, and the row can never outgrow the card (266px against 288px of space at 320px).

**The rating label was clipping itself.** `.rating-label { height: 16px }` against a 21px line box, so the descender of "Excellent" / "Average" was cut at every width. The intent — reserve the space so the layout doesn't jump when a rating is picked — is `min-height`, not `height`.

**Five buttons that all said "★".** No accessible name, no pressed state, and the real value in a hidden input: a screen-reader user had five identical unlabelled buttons and no way to tell what was selected. Each now carries `aria-label="3 stars"` and an `aria-pressed` that `setRating()` keeps in sync. The prompt also said *"Click to rate"* on a device where you tap; it now reads "Select a rating".

**Checked and not a bug:** the star hover handler writes inline `style.color`, which outranks the `.active` class, and on a phone `mouseleave` may never fire after a tap. Simulating a real tap sequence (`mouseenter` → `click`, no `mouseleave`, three times including lowering the rating) left the colours correct every time, because the hover paint and the selection paint agree by construction. Worth knowing before anyone "tidies" that handler.

---
## What Changed in v6.6 (Read This First)

### The first real deploy since v5.2 — and the two mail bugs it exposed

Everything from v6.0 through v6.5 was built, verified locally, and **never deployed**. Pushing it surfaced three problems, none of which announced itself.

**1. Aiven was 16 migrations behind.** Production was still on the 2026-08-12 schema while local had moved five doc versions past it. Nothing warns about this: `RUN_MIGRATIONS` defaults to `false` — correctly, since migrations should be a decision rather than a side effect of every deploy — so the schema drifts silently for as long as nobody looks, and a green deploy proves only that the container started. The pre-deploy check and the order of operations now live in [§15.6](#156-deploying-changes); the short version is **`php artisan migrate:status --database=aiven | grep -c Pending` must print `0`** before you push.

**2. Four Render env vars were silently wrong**, found by diffing `render.yaml` against every `env()` call in `config/`. `BROADCAST_CONNECTION` sat at `log` while the Pusher keys were filled in, so the bell and every live dashboard update did nothing at all; `MAIL_FROM_NAME` was absent, so confirmations went out from a sender named **`Example`**; `GROQ_MODEL` was absent, so production ran a *different model* from the one local was tested against. All four are now in `render.yaml` with the reason written next to them.

**3. `MAILER_DSN` held only the API key.** `brevo+api` is one Symfony scheme (`<transport>+<protocol>://`), not two settings, so the bare key threw *"The mailer DSN must contain a scheme"* — and because every mail call site is deliberately wrapped in try/catch, that exception never reached a user. **Every email in the system was failing**, not just the password reset that happened to get noticed. The v6.0 guard catches a *blank* DSN; it does not catch a malformed one.

### The verification page was describing something it hadn't done

Fixing the transport revealed a second bug underneath it. `/email/verify` is reached two ways: registration and the resend button **do** send a link, but **every login by an unverified user lands on the same page**, where `verifyNotice()` only calls `view()` and sends nothing. The copy claimed *"We sent a verification link"* in both cases, so a user logging in weeks after registering went looking for a message that was never sent on that visit.

The page now renders three distinct states off a flashed `verification_sent` flag — just sent / send failed / **nothing sent on this visit**. This is the same fix as the v5.7 payment-success page, and the same principle: **a page must not assert something the request did not actually do.** Both bugs in this version were invisible for the same reason — a caught exception and a hardcoded sentence, each one describing a success that never happened.

---

## What Changed in v6.5 (Read This First)

### Phase 4 — the engine now grades itself

Until now the system only made promises. `expected_impact` was a forecast, and nothing ever came back to check it. `recommendations.realized_impact` has existed since v6.3 and been **permanently NULL**. It is now filled.

**Route:** `GET /admin/prescriptive/accuracy` → `admin.prescriptive.accuracy`
**Filled by:** `OutcomeTracker::settleDue()`, run from `prescriptive:generate` right after the engine

### The measurement, and the one column that makes it possible

```
realized_impact = actual_revenue − baseline_projection
```

`baseline_projection` is a **new column, frozen on the day the recommendation was made**: the revenue expected over that window if nothing were done. This is the whole reason a new column was needed — `expected_impact` only ever stored the *delta*, not the level, and the level is what reality has to be compared against.

**It deliberately is not recomputed at settlement time.** By then the fill rates have moved, so recomputing would produce a different number than the one actually promised. A forecast quietly adjusted after the answer is known is not a forecast.

`actual_revenue` is `base_amount − discount_amount` on bookings that went ahead in that window and slot — **not `total_amount`**, because that includes extras (food, add-on services) which were never part of the projection. Counting them would inflate every result against a baseline that excludes them, and make the engine look cleverer than it is. Cancelled and no-show bookings are excluded; the slot filter matters (a day booking must not score a night recommendation).

Every date in `[target_start, target_end]` was open when the recommendation was generated — advisors only ever group *consecutive open* dates — so any booking found there arrived **after** the forecast. Nothing already-sold is counted as a win.

### 🔴 The honesty problem, and how the page handles it

**This is not a controlled experiment, and the page says so before it shows a single number.** There is no control group: the same September without the promo is unobservable. Everything is measured against the model's *own* baseline, so a result mixes two questions together — did the action work, and was the baseline right?

The accuracy page therefore splits into two sections that must not be merged:

1. **"Is the model honest?"** — measured **only on dismissed/expired** recommendations. Nothing was done, so the gap between forecast and reality is *pure `DemandModel` error*, with no intervention muddying it. This is the cleanest self-test available, and the genuinely valuable number.
2. **"Did the actions pay off?"** — measured on **applied** recommendations. Useful, but confounded by definition; section 1 is what tells the two apart.

A sample-size warning appears automatically under either section while it holds fewer than 10 settled windows, saying in plain words that it is an early signal and not a result. Expected revenue is a probability spread over a handful of slots — **one booking can flip the sign of a single window** — so only aggregates across many settled recommendations mean anything.

### What is deliberately left unscored

**Maintenance recommendations are never given a `realized_impact`.** The window is closed on purpose, so actual revenue there is always zero, and comparing that to the baseline would just return the projected cost — the plan restated as if it were a result. The real question ("what would a different window have earned?") is unanswerable, because it did not happen. These are marked `settled_at` with a NULL score and shown on the page as *not measurable*, alongside a count. The same applies to any recommendation predating this feature, which has no frozen baseline.

Being visibly unable to score something is better than producing a number that looks like a measurement and is not.

### Verified (2026-09-03)

Settling was exercised against a synthetic past window inside a rolled-back transaction, covering every branch:

| Case | Result |
|---|---|
| Applied, ₱120 base − ₱20 discount booked in window | `actual = 100`, `realized = 0` against a ₱100 baseline ✅ |
| A ₱500 **day-slot** booking in the same window, night-slot recommendation | correctly **excluded** ✅ |
| Dismissed, window empty apart from a **cancelled** booking | `actual = 0`, `realized = −250` ✅ |
| Maintenance | settled, score NULL ✅ |
| Legacy row with no frozen baseline | settled, score NULL ✅ |

The accuracy page was rendered both with settled data and in its empty state.

The live database currently has **nothing settled** — every open window is still in the future — so the page correctly reports how many are waiting rather than inventing a score.

---

## What Changed in v6.4 (Read This First)

### Prescriptive analytics, Phase 3 — and three bugs that only running it could find

Four additions on top of the v6.3 engine, plus a **pre-existing pricing bug** the work exposed.

| Added | Where |
|---|---|
| `PeakRateAdvisor` — recommends **raising** rates on strong dates | `app/Services/Prescriptive/Advisors/` |
| What-If Simulator — the same model, driven by the admin | `GET /admin/prescriptive/simulate` |
| Dashboard **Recommended Actions** widget (top 3) | `Admin\DashboardController` + dashboard view |
| AI morning briefing — one call per run, prose only | `app/Services/Prescriptive/BriefingWriter.php` |

### 🔴 A shipped pricing bug: a `pricing_rules` row never applied on its last day

**This predates the prescriptive work and affected the existing Pricing Rules feature directly.**

`Property::getPackagePrice()` compared a **DATE** column against a full **DATETIME**:

```php
->where('end_date', '>=', $checkin)     // $checkin is 2026-10-17 19:00:00
```

MySQL widens `end_date` to midnight, so `2026-10-17 00:00 >= 2026-10-17 19:00` is **false**. Since every check-in is 8:00 AM or 7:00 PM (`Booking::SLOTS`), the consequences were:

- A **one-day pricing rule was completely inert** — a "Christmas Day rate" would never once apply.
- The **last day of any range** silently fell back to the regular rate.

Measured before the fix: a Nov 1–5 rule at ₱888 returned ₱888 on Nov 3 and **₱12** on Nov 5. Both `getPackagePrice()` and `getPriceForDate()` now use `whereDate(...)` against `toDateString()`. Verified across single-day, first day, middle, last day, and the days on either side.

It surfaced because applying a peak-rate recommendation created a correct `pricing_rules` row and **the quote did not move**. A fake would have reported success; only the real end-to-end read caught it.

### Two engine bugs found by running it twice under changing conditions

1. **A cache outage silently wiped the page.** Redis went down in dev, so every advisor threw inside `Setting::get()`, produced no fingerprints — and `expireStale()` dutifully expired **every open card**, leaving one log line as the only trace. Advisors now declare `type()`, and expiry is scoped to the types whose advisor actually *finished*. A silent advisor is not evidence that its recommendations stopped being true.
2. **Expired recommendations could never come back.** The dedupe rule "never resurrect the dead" was applied to `expired` as well as `applied`/`dismissed` — but `expired` is the engine's own bookkeeping, not a human decision. A card killed because someone booked the date stayed dead **even after that booking was cancelled**. Now only `applied` and `dismissed` are permanent; `expired` revives to `new` when the opportunity is regenerated. Found when 23 regenerated cards came back as `skipped`.

### PeakRateAdvisor — and why it needs its own elasticity

Same optimization as the promo advisor, opposite direction: `E(u) = p(u) × price × (1 + u)`, `p(u) = p × (1 − e·u)`, optimum at `u* = (1 − e)/(2e)`.

**`prescriptive_peak_elasticity` (default 0.6) is deliberately separate from `prescriptive_elasticity` (1.5).** Someone shopping a quiet Tuesday is hunting a bargain — elastic. Someone booking a peak Saturday for a reunion has a fixed date — inelastic. One number for both is guaranteed to be wrong for one of them. The mathematics then gives the mirror-image classic result: **raising price only pays when demand is inelastic (e < 1)**, and the advisor returns early when it is not.

An engine that can only discount is not optimizing revenue — it is giving it away. This is the half that answers *"does your system just hand out discounts?"*

> ⚠️ **`type = 'fixed'`, never `'percentage'`, and this is load-bearing.** A `percentage` pricing rule is computed by `getPackagePrice()` as `base_price × (1 + price/100)` — always against the **base** rate, never `weekend_price`. Peak dates are almost always weekends, so a "+15%" rule on a Saturday yields `4,000 × 1.15 = 4,600` against the ₱6,000 currently charged: **a recommendation to raise prices would quietly cut them.** The advisor emits an absolute amount, `PrescriptiveController::createPricingRule()` forces `'fixed'` as a second layer, and consecutive dates are only merged when their **current price matches** (a Fri+Mon merge would flatten ₱6,000 and ₱4,000 into one number).

### What-If Simulator — `GET /admin/prescriptive/simulate`

The cards answer questions nobody asked. Here the owner picks the dates, slot, and price change and sees what the same `DemandModel` projects. Negative = discount, positive = increase; the matching elasticity is selected automatically and **named on screen**, with an invitation to change it in Settings and re-run to see how sensitive the answer is.

Read-only and `GET` — nothing is written, so it is safe to refresh, bookmark, and demo repeatedly. Range is capped at 90 days: a year of inference on six months of data is not a simulation.

Verified against hand-computed ratios at −50/−30/−10/0/+15/+50%; all six matched to within the probability ceiling's rounding.

### The AI's role, drawn tightly

`BriefingWriter` makes **one Groq call per engine run** — not one per card, and never one per page view (the mistake Insights and Forecast still make, calling the API on every visit). The output is stored in `settings` and simply read by the page.

The prompt hands over the already-computed figures and forbids the model from inventing a number, adding a recommendation, or using markdown. It fails **open**: on any error the briefing is **cleared, not left stale** — a briefing describing recommendations that no longer exist is worse than none — and the page stands on its own, because the cards are the product and the paragraph is decoration. `--no-briefing` skips the call while developing.

Sample of real output, which uses only figures from the card: *"Prioritize scheduling two days of maintenance for October 5–6, 2026, as this window minimizes expected revenue loss to PHP 4 compared to the typical PHP 6."*

### Verified (2026-09-03)

Live local DB throughout. All three action types driven through `apply()` inside rolled-back transactions: promo → `Discount` → `quoteFor()` **drops**; peak rate → `PricingRule` → `quoteFor()` **rises** (₱14 → ₱16.80, after the boundary fix); maintenance → `AvailabilityBlock`. Recommendations, simulator, dashboard, and settings pages all rendered for real. Double-apply refused.

> One test artifact worth knowing: `Setting::set()` inside a rolled-back transaction leaves the **new value in Redis** while the DB reverts, so settings read stale afterwards. Not a product problem — nothing rolls back in production — but it will confuse the next person testing this way. `Cache::forget('setting_<key>')` clears it.

---

## What Changed in v6.3 (Read This First)

### Prescriptive analytics — the thing the project is named after, finally built

The system's full title is *"Web-Based Resort Management System with **Prescriptive Analytics**"*, and until now the prescriptive layer did not exist. What existed was:

| Layer | Where |
|---|---|
| Descriptive (*what happened*) | Dashboard KPIs, Reports + PDF/Excel |
| Diagnostic (*why*) | `/admin/insights` — 5 AI one-liners |
| Predictive (*what will happen*) | `/admin/forecast` — real 6-month data, LLM-written outlook |
| Prescriptive (*what to do about it*) | **nothing** |

The closest thing to it was line 4 of the forecast prompt, `"2 actionable recommendations to maximize revenue"`. **That line is now deleted**, and its deletion is the point: those recommendations were written by the model out of general hospitality knowledge, not computed from Villa Elena's data. They could not answer *"where did that number come from?"*, and the system could not act on them. Two competing sources of advice — one computed, one improvised — is worse than one, so the forecast prompt now explicitly says *"Do NOT recommend specific actions, discounts, price changes, or promo campaigns."* Forecast is the **outlook**; `/admin/prescriptive` is the **decision**.

### The rule the whole feature is built on

> **The engine decides (rules + optimization over the resort's own data). Nothing is executed until a human presses Apply.**

`prescriptive:generate` writes rows to **one table, `recommendations`, and nothing else**. No `Discount`, no `PricingRule`, no `AvailabilityBlock`, no `Setting`. It can run every night forever without changing a single price or anything a guest can see. Execution lives only in `Admin\PrescriptiveController::apply()`.

Auto-apply was considered and **rejected**: a system that silently lowers prices is hard to defend and dangerous in a real business. The manual gate is also what makes the feature *measurable* — the accept/dismiss rate is real evidence of usefulness, which auto-apply would destroy.

### The two advisors (Phase 1)

**`IdleDatePromoAdvisor`** — for each open future date × slot, grid-searches candidate discounts and picks the one maximizing expected revenue:

```
E(d) = p(d) × price × (1 − d)
p(d) = p × (1 + elasticity × d)      [capped at probability_ceiling]
```

When `d = 0` wins, **nothing is recommended** — that is the feature working, not failing.

**`MaintenanceWindowAdvisor`** — the villa has to close sometime; the only question is *when*, and every answer has a price. It scores every candidate window in the horizon by `Σ p × price` and picks the cheapest. Reported impact is the **avoidable** cost (average window − best window), not the window's own cost, so it never pretends maintenance is free.

### Three modelling decisions that were arrived at by being wrong first

1. **The discount response is multiplicative, not additive.** The first version used `p + e·d` and every single date came back recommending the maximum 20% — the corner solution. That is the signature of a broken model, not a thrifty villa: additive response claims an 8%-fill date gains the same 15 points as a 30%-fill date. Multiplicative makes `elasticity` the textbook **price elasticity of demand**, puts the optimum in the interior at `d* = 50(e−1)/e` (≈17% at e = 1.5), and reproduces the classic result that **cutting price only pays when demand is elastic (e > 1)**. Do not revert this to additive.
2. **Fill rates are Jeffreys-smoothed:** `(booked + 0.5) / (sample + 1)`. Under a multiplicative response, a raw `0/26` gives `p = 0`, and zero multiplied by anything stays zero — so the dates with *no* bookings at all, exactly the ones most needing help, could never receive a recommendation. `0/26` means *rare*, not *impossible*. The **raw** counts are still what the evidence text shows the admin.
3. **Blocked days are excluded from the fill-rate denominator.** A day the villa was closed was never *offered*, so counting it as "not sold" makes healthy days look weak and invents problems to solve.

### Every assumption is admin-editable, on purpose

Settings → **Prescriptive Engine** (`prescriptive_*` keys, same pattern as `booking_hold_minutes`): history window, planning horizon, idle threshold, **discount response (elasticity)**, max discount, minimum worth, maintenance days. The elasticity is a **guess, not a measurement** — Villa Elena has no promo history to fit it against — and every recommendation card says so in its own evidence list. The first question anyone asks about a recommendation is *"where did that number come from?"*, and the answer must be a field the owner can point at, never a constant buried in code.

`config/prescriptive.php` holds the non-admin tunables (discount candidates, probability ceiling, lead times, `max_promo_run_days`, `extra_holidays`).

### Smaller things that matter

- **Philippine holidays are computed, not hardcoded** (`HolidayCalendar`) — fixed dates from RA 9492, plus Easter-derived Holy Week via Meeus/Butcher (no `ext-calendar` needed). Used as a **guard**: never discount a holiday, never schedule maintenance on one. Eid'l Fitr/Adha are deliberately absent (proclaimed yearly on moon sighting) — add them to `config('prescriptive.extra_holidays')`.
- **Dedupe by `fingerprint`**, so the nightly run updates one card instead of stacking a new one each morning. A dismissed or applied recommendation is **never resurrected** — the admin's "no" is an answer, not an invitation to ask again tomorrow.
- **Open recommendations that stop being regenerated are auto-expired**, which covers both "the date passed" and "someone booked it".
- **Apply re-validates before writing.** A card can sit in an open tab for hours; a maintenance block is refused if a booking has since landed inside the window.
- **Promo runs are capped at 14 days** so *"20% off every night for a month"* can't be approved with one click.
- The claim is taken in a `lockForUpdate()` transaction **before** the record is created, so a double-click cannot create two promos — same shape as the refund-transfer guard, for the same reason.

### Verified end to end (2026-09-01)

Not just unit-level: `prescriptive:generate` run against the live local DB (54 bookings, 36 in the trailing window), the page rendered for real (`->render()`, 119k chars), and `apply()` driven for both action types inside a rolled-back transaction. Confirmed the full loop: recommendation → Apply → real `Discount` row → **`Property::quoteFor()` drops the price** (₱12 → ₱10.20 on a 15% card) → `Discount::publicActive()` picks it up for the landing banner. Double-apply correctly refused.

> ⚠️ **Note for local demos:** the local villa's `base_price`/`weekend_price` are **₱12/₱16** (small amounts left over from live PayMongo testing), so every promo card is worth pennies and is filtered out by the ₱500 *Minimum Worth* setting. To see promo cards locally, set the villa's real rates (₱4,000/₱6,000) or lower *Minimum Worth*.

### Still to do

Nothing from this list remains — Phase 3 shipped in **v6.4** and Phase 4 (outcome tracking) in **v6.5**, both below.

---

## What Changed in v6.2 (Read This First)

### A repeat-unpaid-booking cooldown, and what was already there before it

The question that started this: a guest books, never pays the 50% downpayment, `bookings:auto-checkinout` correctly auto-cancels the stale `pending` booking once `booking_hold_minutes` elapses (`AutoCheckInOutBookings::cancelStalePendingBookings()`) — but nothing stopped them from immediately booking again, holding another slot, and repeating the cycle indefinitely with zero cost.

Two anti-abuse mechanisms already existed and are easy to conflate:

- `Booking::hasActivePendingBooking()` — blocks a *second simultaneous* unpaid hold. One at a time, not a repeat-offense guard.
- `Booking::hasExcessiveCancellations()` (3+ `status='cancelled'` bookings in 30 days) — forces **full payment instead of a deposit** on the next booking. This already counted auto-cancelled bookings too, since the sweeper also just sets `status='cancelled'`. But it only changes payment *terms*; it never stops a guest from opening yet another unpaid pending booking in the meantime, and it can't distinguish a guest who legitimately cancelled from one who never intended to pay.

**New: a booking-creation cooldown, specifically for repeated non-payment.** A `bookings.cancelled_by` enum (`guest` / `admin` / `system`) was added — set at all three cancellation sites (`AutoCheckInOutBookings::cancelStalePendingBookings()`, `Customer\HomeController::cancelBooking()`, `Admin\BookingController::updateStatus()`) — so the two failure modes can finally be told apart instead of guessed at by parsing `cancellation_reason` text. `Booking::bookingCooldownEndsAt($userId)` counts only `cancelled_by='system'` rows in a lookback window; once a guest hits the threshold, `Portal\PortalController::submitBooking()` blocks new bookings until the cooldown expires (message includes the exact retry time).

Three new admin-configurable settings (Settings → Booking Rules, same pattern as `booking_hold_minutes`):

| Setting | Default | Meaning |
|---|---|---|
| `booking_cooldown_threshold` | 3 | Auto-cancelled (unpaid) bookings within the window that trigger the cooldown |
| `booking_cooldown_window_days` | 30 | Lookback window for counting auto-cancels |
| `booking_cooldown_hours` | 24 | How long new bookings are blocked once triggered |

Deliberately **not** implemented: the Terms of Service already states "we may suspend or deactivate an account... for repeated no-shows" (`terms.blade.php` §03), but that has never been backed by code — no automated account suspension exists, and this change doesn't add one. It only blocks new *bookings* for a limited window; the account itself stays fully usable (can still log in, view history, message support). Full suspension was considered and explicitly deferred — it's a much bigger blast radius (blocks login entirely) for a problem a temporary cooldown already solves.

`cancelled_by` is nullable and starts `NULL` on existing rows — there's no reliable way to backfill who cancelled bookings that predate this column, and `NULL` correctly excludes them from the cooldown count rather than guessing.

---

## What Changed in v6.1 (Read This First)

### Legal pages — two, not three

The landing-page footer had three dead `href="#"` links: Privacy Policy, Terms of Service, and Cookie Policy. Two of them are now real pages; **the Cookie Policy link was deliberately removed**, not built.

The reason is what the system actually does. It sets **strictly-necessary cookies only** — the Laravel session cookie, `XSRF-TOKEN`, and `remember_web_*` when the guest ticks *Remember me*. There is no analytics, no advertising, no cross-site tracking, and therefore no consent banner. A standalone page for three essential cookies is padding; those three are documented in a **`#cookies` section inside the Privacy Policy** instead, as a table naming each cookie, what it does, and how long it lasts (the session cookie's name and lifetime are read from `config('session.*')`, so they can't go stale).

| Route | Name | View |
|---|---|---|
| `GET /privacy-policy` | `portal.privacy` | `portal/legal/privacy.blade.php` |
| `GET /terms-of-service` | `portal.terms` | `portal/legal/terms.blade.php` |

Both are public (no auth), rendered by `Portal\PortalController::privacy()` / `terms()`, and share `portal/legal/_styles.blade.php` (styling) and `portal/legal/_scripts.blade.php` (sidebar scroll-spy) so the two pages can't drift apart visually.

### The rule that matters: legal text reads from live data, never hardcoded

Every number the two pages quote comes from `PortalController::legalContext()`, which pulls from the **same sources the booking flow uses**: the villa's `base_price`/`weekend_price`, `Setting::get('deposit_percentage')`, `Booking::pendingHoldMinutes()`, `Booking::SLOTS` (rendered through `slotDateTimes()`, including the computed slot durations), `Booking::MAX_RESCHEDULES`, and `Booking::RESCHEDULE_CUTOFF_DAYS`.

This is not decoration. The exact bug fixed in v5.5 was **published policy text disagreeing with enforced code** — `portal/booking_form.blade.php` advertised *"Free cancellation 48 hours before check-in"* while `calculateRefundPercentage()` implemented 7 days / 3–6 days / under 3 days. A Terms of Service page is the worst possible place to repeat that mistake, so nothing in it is typed as a literal number that the code also owns. If you add a rule to these pages, wire it to its constant.

The cancellation tiers themselves (100% within 24 hours of booking or 7+ days out, 50% at 3–6 days, none under 3 days) are still written as prose, because they live in `if` branches rather than in named constants — if those tiers ever move, `terms.blade.php` §9 must move with them.

`PortalController::LEGAL_LAST_UPDATED` drives the "Last updated" date on both pages. **Bump it by hand when the policy wording changes** — it deliberately does not track deploys or file mtimes, because it tells a guest when the *policy* changed, not when the app was last shipped.

### Booking policies moved into a modal, behind a required checkbox

`portal/booking_form.blade.php` used to show a **Booking Policies** card in the form body — five paragraphs the guest scrolled past on the way to *Proceed to Payment*, agreed to by a line of small print (*"By proceeding, you agree to our booking policies"*). Nobody reads that.

The card is gone. In its place, directly above the submit button:

- A consent row — **`I have read the` + a highlighted `booking policies` button** — bound to `name="policies_accepted"`.
- Clicking the highlighted words opens a modal (`#policiesModal`) holding the exact content the card held: deposit, check-in time, check-out time, cancellation tiers, reschedule limits — plus a link out to the full Terms of Service page.
- The modal's footer has **"I've read these"**, which ticks the checkbox and closes it.
- **`Proceed to Payment` is disabled until the box is ticked.**

Three details that are load-bearing, not decoration:

1. **The highlighted words are a `<button>`, and the consent row is NOT wrapped in a `<label>`.** Interactive content inside a label activates the label — so wrapping the row would make *opening the policies* tick the box, which is exactly the consent the checkbox is supposed to represent. Only the words "I have read the" are the `<label for>`. Verified by clicking: the link opens the modal and leaves the box unticked.
2. **The disabling happens in JS, never in the markup.** If the inline script fails, the guest is not stranded with a permanently dead button — the checkbox's native `required` still blocks submission, and `submitBooking()` still rejects it. Three layers, degrading in that order.
3. **`'policies_accepted' => 'accepted'` in `PortalController::submitBooking()` is the real enforcement.** A disabled button is an affordance; anyone can POST around it. The custom message names the box so the top-of-form `$errors->first()` alert is intelligible, and `$errors->has('policies_accepted')` re-renders the row in the red `.is-invalid` state.

The modal body carries Bootstrap's own **`modal-body`** class alongside `.policy-modal-body`, because `modal-dialog-scrollable` puts `overflow-y` on `.modal-body` specifically. Without it the content overflowed a `.modal-content` that is `overflow:hidden` (for the rounded corners) and **the footer — including "I've read these" — was clipped off-screen**, making the flow uncompletable. This was caught by clicking through a real render, not by reading the markup; keep the class if you restyle the modal.
### Landing-page gallery — one photo at a time

`portal/home.blade.php`'s gallery was a 7-tile mosaic grid showing everything at once. It is now a single auto-advancing slideshow (`#gallerySlideshow`): one photo visible, cross-fade every 5 seconds (`data-interval`, which also drives the progress bar's animation duration), slow Ken Burns zoom on the active slide, prev/next controls, dot indicators, and a progress bar.

It pauses on hover, while the lightbox is open, when the browser tab is hidden, and when the gallery scrolls out of view (`IntersectionObserver`) — so it isn't animating unwatched. Existing `openLightbox()` behaviour is unchanged; arrows and dots call `stopPropagation()` so they don't open it. The photo list moved into a `$galleryShots` array at the top of the section — add or remove a photo there and both the slides and the dots follow.

---
## What Changed in v6.0 (Read This First)

### Seasonal promos — automatic, admin-only, advertised on the landing page

The `discounts` table has existed since the original schema (migration `2026_03_07_200008`) and was **never wired to anything**: `bookings.discount_amount` was hardcoded to `0` in all four booking-creating paths, and there was no admin UI. v6.0 turns it into a working feature.

**The mechanic is automatic, not a promo code.** Nothing is typed by the guest. A promo applies when the booking's **check-in date** falls inside the promo's window and the slot matches — the same shape as `pricing_rules`, which is also matched against the stay date rather than the booking date. This was a deliberate choice over code entry: a code is one more thing to lose, mistype, or leak, and on a single-villa resort there is no segmentation a code would buy you.

**Who can create one: admin only.** The routes live in `routes/admin.php` (`role:admin`). Staff can't create or edit a promo — but a walk-in they book *does* get the discount automatically, and the availability grid shows them the discounted price so what they quote at the counter matches what the system charges. Rationale: a promo is a direct reduction in revenue, so it needs one owner and a `staff_logs` trail (`created_promo`, `updated_promo`, `toggled_promo`, `deleted_promo`, `announced_promo`).

**How guests find out — both, doing different jobs:**

| Surface | Reaches | Why it's needed |
|---|---|---|
| **Landing page** (hero pill + promo band above the villa showcase) | Everyone, logged in or not | This is the primary channel. Most first-time bookers **have no account**, so they have no `user_id` and `NotificationHelper` cannot reach them at all. Controlled by the promo's `is_public` flag. Includes **not-yet-started** promos — see below. |
| **In-app notification** (opt-in checkbox on the admin form) | Registered customers with `status = 1` | For returning guests. One-time per promo — guarded by `notified_at`, which editing does **not** reset, so fixing a typo doesn't re-spam every guest's bell. |
| **Price surfaces** (property page preview, booking form, staff availability grid, walk-in form) | Whoever is looking at a price | The discount is shown as its own line against the struck-through base rate, so the guest sees *why* the total dropped. |

**No email, deliberately.** Production sends through Brevo's HTTPS API on a limited free tier, and that same quota carries 2FA codes, booking confirmations and password resets. A marketing blast that exhausts it doesn't just fail to market — it locks guests out of their accounts. The in-app bell is the right channel for this.

**Advertising runs ahead of pricing — on purpose.** `Discount::publicActive()` (what the banner shows) includes promos whose `start_date` hasn't arrived yet; `Discount::isValidOn()` (what sets the price) does not. A promo dated for September has to be visible in **August**, because that is the only window in which it can still influence a booking — if it first appeared on September 1 it would be advertised only while it was already running, too late for anyone planning ahead. The banner renders those as *"For stays Sep 01 – Sep 30"* rather than *"Until…"*, and the admin list badges them **Scheduled**. Only `expiry_date` bounds the banner. This was corrected after the first real promo (`Ber Months Special`, Sep 1–30, created Aug 25) showed the discount correctly on the property page and booking form — both of which match against the guest's chosen **check-in date** — while being invisible on the landing page.

**Date validation** (`PromotionController::validated()`): `expiry_date` can never be in the past — such a promo is dead the instant it's saved, yet still looks alive in the form. `start_date` can't be moved into the past either, with one deliberate exception: an already-running promo legitimately *has* a past start date, so an edit that leaves it untouched passes. Otherwise, renaming a live promo would force the admin to change its dates. The date inputs carry matching `min` attributes, with the running promo's own start date as its floor.

### The one rule that matters: `Property::quoteFor()` is the only place a price is computed

Mirrors what `Booking::slotDateTimes()` does for time. Every path that prices a booking — public portal preview, booking form, submit, staff walk-in, staff availability grid, admin create, customer reschedule — calls `quoteFor($checkin, $slot)` and gets back `['base', 'discount', 'total', 'promo']`. Nothing recomputes a discount on its own. Two places computing a price means the guest eventually sees one number and is charged another.

**This immediately caught a real one.** `resources/views/staff/walkin.blade.php` had a **JavaScript reimplementation of `getPackagePrice()`** driven off `data-base`/`data-weekend`. Its own comment admitted it couldn't see `pricing_rules` overrides. With promos it would also miss the discount — and because the overpayment guard measures against that client-side `calculatedTotal`, staff would have been allowed to enter a payment the server then rejects as exceeding the total. Replaced with a fetch to the new `GET /staff/walkin/quote` (`FrontDeskController::priceQuote()`). That endpoint deliberately omits the `$checkin->isPast()` rejection that `Portal\PortalController::pricePreview()` performs, because a walk-in can legitimately check in mid-slot — check-out is the boundary there, as `storeWalkin()` already enforces.

### Rules baked in

- **The discount comes off `base_amount` only, never extras.** Extras are pass-through costs (food, added services) that a campaign shouldn't discount, and this matches the existing shape of `Admin\BookingController::recalculateBookingTotals()`: `base_amount + extras - discount_amount`.
- **Promos never stack.** When windows overlap, the one producing the **largest peso discount** wins (not the largest percentage — those differ once a `fixed` promo is in play). Tie-break by highest `id`. Deterministic, so preview and charge can't disagree.
- **The deposit is computed off the discounted total**, not the base — otherwise a 50% deposit on a discounted booking asks for more than half of what's actually owed.
- **`usage_limit` can overshoot by one** under exactly simultaneous bookings. `increment()` is atomic at SQL level so no count is lost, but the limit check isn't locked. Accepted: locking the promo row on every booking costs more than one extra discounted stay.
- **Rescheduling re-prices**, and moves the usage count with it — decrement the old promo, increment the new one — so a promo's limit isn't consumed by a booking that no longer uses it.
- **Deleting a promo doesn't rewrite history.** `bookings.discount_id` is `nullOnDelete()`; `discount_amount` stays, so past totals remain correct and only the attribution is lost. Deactivating is preferred and is what the UI nudges toward.

### Verified end-to-end against a running server, not fakes

Per the v5.9 lesson that fake-backed tests structurally miss seams, every path was exercised over real HTTP with real logins:

| Path | Result |
|---|---|
| Public booking (`POST /book/{property}`) | Fri night, base ₱6.00 → 20% → total ₱4.80, `discount_id` set, `used_count` 0→1 |
| Staff walk-in (`POST /staff/walkin`) | base ₱6.00 → 25% → ₱4.50; paying ₱4.50 recorded as `full_payment` / `paid` — proof the overpayment guard now measures against the discounted total |
| Admin create (`POST /admin/bookings`) | base ₱6.00 → ₱4.50, `discount_id` set |
| Reschedule in/out of a night-only promo | out → discount cleared, `discount_id` null, `used_count` decremented; in → re-applied and incremented |
| Admin create + notify | 6 notifications for 6 active customers, `link` = `/` (relative, per the notification-link rule) |
| Edit an announced promo | value changed 20→25%, notification count stayed at 6 — no re-blast |
| Slot targeting | night-only promo returned no discount on the day slot |
| Landing page, admin index/create/edit, staff availability + walk-in, both quote endpoints | all render 200 with the promo visible |

Local rates are ₱4.00/₱6.00 rather than ₱4,000/₱6,000 (test values left over from the ₱1 live-transfer work in v5.9), which is why the figures above look small. That also forced a fix: the admin form's live "what guests will pay" preview reads the villa's **actual** `base_price`/`weekend_price` from the DB instead of hardcoding ₱4,000/₱6,000 — a preview that lies about the price is worse than no preview.

---

## What Changed in v5.9 (Read This First)

### The v5.6 finding "QR Ph cannot be refunded" was WRONG — it was the wrong endpoint

v5.6 concluded, from a live test, that PayMongo categorically refuses to refund QR Ph payments, and closed the refund-API item as *impossible*. **That conclusion was based on a request sent to the wrong host.**

QR Ph refunds do not live on the main API. They have their own host:

| | Host |
|---|---|
| Card / wallet refunds | `https://api.paymongo.com/v1/refunds` |
| **QR Ph refunds** | **`https://refunds-api.paymongo.com/v1/refunds`** |

v5.6 tested only the first one, which is correct for every *other* source type and rejects `qrph` categorically — hence the confident-looking, repeatable, and entirely misleading error.

**Re-verified 2026-08-22** against test keys (`sk_test_`), using the four existing paid `qrph` test payments:

| Test | Result |
|---|---|
| `api.paymongo.com/v1/refunds`, full ₱2 | `400 parameter_invalid` — *"Refunds are not allowed for payments with source type qrph."* (v5.6's error, reproduced exactly) |
| `refunds-api.paymongo.com/v1/refunds`, full ₱2 | **Accepted** — created `ref_d228b731…`, `livemode: false`, then `status: failed` |
| Same host, **partial** ₱10 of ₱100 | `422 partial_refund_not_allowed` — *"Only full amounts are allowed to be refunded for qrph payments."* |
| `GET` the refund id, both hosts | 404 on both — no retrieve endpoint exists |
| `GET /v2/wallets` (test mode) | `{"data":[]}` — no wallet, therefore no available balance |

The full refund passed validation and then failed at execution. The docs state *"Merchant's wallets should have enough available balance in order to do a refund request"*, and test mode has no funded wallet — so **the contract is verifiable in test mode, but the success path is only observable live.**

### What a QR Ph refund actually is — not a reversal

This is the part that shapes the design. PayMongo does **not** push the money back to the payer. It generates a **transfer link** that the guest opens and claims, choosing their own bank or e-wallet:

```
processing  → link generated
refunding   → waiting for the guest to claim it
succeeded   → guest claimed it
failed      → link expired (3 days) or was declined
```

So a QR Ph refund still depends on the guest doing something, within 3 days, or it fails. It removes the resort's manual GCash transfer; it does not make the refund fire-and-forget.

### Constraints that survive, and what they mean for us

| Constraint | Consequence |
|---|---|
| **Full refunds only** — verified twice (₱10 of ₱100, and ₱1,000 of ₱2,000), and confirmed by PayMongo support as a **permanent limitation of the QR Ph method itself**, not a test-mode or account-settings restriction | Partial refunds **cannot** use the API. This directly collides with `Booking::calculateRefundPercentage()`'s **50% tier** (cancel 3–7 days out), and with every reschedule-to-cheaper-slot difference. Those stay manual permanently |
| 30-day window (per docs, untested) | Older refunds stay manual |
| Requires available wallet balance | A refund can fail for reasons unrelated to the request |
| No GET endpoint (404 on both hosts). The dashboard *does* offer `payment.refund.updated` and `payment.refunded` webhook events — but the qrph refund object is invisible to the main API (`GET /v1/refunds/{ref_id}` → *"No such refund"*), so whether those events fire for qrph refunds is **unverified** | The `processing → succeeded` progression probably cannot be tracked in code. Untested, because the refund never reaches a trackable state without a funded wallet |
| `payment_method = 'cash'`, or `reference_number` NULL (rows predating the `$fillable` fix) | No PayMongo payment to refund — manual |

**The refund API was investigated and then rejected as the wrong tool — see the next section.** `PayMongoService` still has no refund method, and the manual "Mark Paid Out" flow remains the only implemented path.

The verification script is **not** in the repo (it is throwaway, and it talks to a live payment API); it lives in the session scratchpad as `paymongo_refund_spike.php`, with a safety rail that refuses to run against `sk_live_` keys.

### The refund API is the wrong tool. Send Money (Disbursements) is the right one

The full-refund-only restriction is fatal, not inconvenient. `Booking::calculateRefundPercentage()` has a **50% tier** (cancel 3–7 days out), and `Customer\BookingController`'s reschedule path refunds an arbitrary price *difference*. Neither can ever be a full refund of a payment. The refund API can only serve the easiest case while the policy-driven cases stay manual forever.

**PayMongo's Send Money / Disbursements product has no such restriction**, because it is a plain outbound transfer from the merchant Wallet rather than a reversal of a payment:

| | Refund API | **Send Money** |
|---|---|---|
| Partial / arbitrary amounts | ❌ `422 partial_refund_not_allowed` | ✅ Any amount within rail limits |
| Testable before going live | ❌ No funded test wallet → always `failed` | ✅ Simulator destination accounts |
| Status tracking | No GET, webhook unverified | ✅ `callback_url` + `GET /v2/transfers/{id}` |
| Speed | 3-day claim link, can expire unclaimed | InstaPay real-time, 24/7 |
| Cost | Free | ₱10/transfer (first weekly free) |

**Rails and limits:** InstaPay real-time up to ₱50,000 (the villa's maximum booking is ₱6,000, so this never binds); PESONet same/next banking day up to ₱10,000,000; wallet-to-wallet instant, unlimited.

**The call:**

```
POST https://api.paymongo.com/v2/batch_transfers      (Basic auth, secret key)
{"transfers":[{
  "provider": "instapay",  "amount": 10000,  "currency": "PHP",
  "source_account":      {"number":…, "name":…, "bic":…},   ← from GET /v2/wallets/
  "destination_account": {"number":…, "name":…, "bic":…},
  "callback_url": "…", "reference_number": "…", "metadata": {…}
}]}
```

Amounts are in centavos. Statuses are `pending` → `succeeded` / `failed`, with rail-level ISO 20022 error codes (`AC01` invalid account, `AM04` insufficient funds, `DT05` cut-off).

**Test simulator** (works with `sk_test_` keys) — destination account numbers that force an outcome:

| Account number | Outcome |
|---|---|
| `999999990001` | `succeeded` |
| `999999990003` | `account_not_found` |
| `999999990004` | `account_not_active` |
| `999999990005` | `account_limit_reached` |

Anything else stays `pending` as a no-op. This is the decisive advantage over the refund API: **every failure path can be rehearsed before real money is involved.**

### Verified against the live API, 2026-08-22 (test keys)

| Probe | Result |
|---|---|
| `GET /v1/wallets/receiving_institutions?provider=instapay` | 200, ~16KB. GCash = **`GXCHPHM2XXX`** (G-Xchange, Inc.), Maya Bank = `MYDBPHM2XXX`, Maya Philippines = `PAPHPHM1XXX` |
| `POST /v2/batch_transfers` | `500` — *"failed to get first source account: failed to get wallet by params"* |
| `GET /v2/wallets/` | 200 `{"data":[]}` |
| `GET /v2/wallets/transactions` | 404 *"wallet not found"* |

**The single blocker is the wallet.** It was activated in live mode (Statement of Acceptance signed), but test mode still reports no wallet at all. The docs never explain how to obtain a test-mode wallet or test funds — this is an open support question. Note the endpoints **require the trailing slash**: `/v2/wallets` 301-redirects.

### Payouts do NOT sweep the Wallet — the earlier worry was unfounded

A concern was raised that the weekly (Wednesday) payout would empty the Wallet, leaving nothing to refund from. The docs say otherwise:

> *"Payouts land in your PayMongo Wallet."* … *"You can **configure** a workflow in your Dashboard that automatically forwards each payout from your Wallet to a registered bank account or e-wallet."*

Forwarding to a bank is an **opt-in workflow, not automatic**. Left alone, collected funds accumulate in the Wallet — exactly where refunds need them. Manual top-up is possible (transfer to the Wallet's dedicated account number) but is a convenience, not a requirement.

Wallet states are `deactivated` → `activated` → `frozen`. While deactivated, *"accepted payments can still land, but manual top-ups and sending are unavailable."* Tiers carry direction-aware daily/monthly caps.

### What this forces us to build: recipient details

Send Money needs `destination_account.{number, name, bic}`. **The QR Ph payment does not carry any of it.** Verified against a real paid payment (`pay_fahRhy…`, ₱2,000):

```json
"source":  { "type": "qrph",
             "provider": { "bank_institution_code": null } },
"billing": { "name": "Nick Salvador", "phone": "09222222222" }
```

`billing` is **not payer data** — it is what `PayMongoService::createCheckoutSession()` sent, echoed back. It is the app's own booking record, not the name registered on the guest's GCash account. There is no payer account number field at all.

Worse, **there is no account-name-inquiry endpoint** — no way to verify a name before sending. Validation happens at submission, so a wrong name surfaces as a runtime `account_not_found`, after the transfer is attempted.

So the guest must be asked where to send the refund. Partially mitigated: `users.phone` already exists and, for GCash, the mobile number *is* the account number — but it is nullable, it is a booking contact number rather than a confirmed wallet number, and neither the institution nor the registered account name is known.

### Built in v5.9: asking the guest where to send the refund

The first piece of the Send Money path is in, and it deliberately does not depend on the wallet — it is needed even while refunds are sent by hand.

| Piece | File |
|---|---|
| `refund_destinations` table (one row per refund, `payment_id` unique, `cascadeOnDelete`) | `database/migrations/2026_08_23_090000_create_refund_destinations_table.php` |
| Model, with `toTransferAccount()` (the exact `destination_account` shape) and `masked_account_number` | `app/Models/RefundDestination.php` |
| `Payment::refundDestination()`, `needsRefundDestination()`, `isReadyToSend()` | `app/Models/Payment.php` |
| `PayMongoService::receivingInstitutions()` — live list, 24h cache + never-expiring stale fallback | `app/Services/PayMongoService.php` |
| Guest form + guards | `Customer\RefundDestinationController`, `customer/refund_destination.blade.php` |
| Notification now links to the form when details are missing | `NotificationHelper::withRefundDestinationPrompt()` |

**The institution list is not hardcoded** — `GET /v1/wallets/receiving_institutions?provider=instapay` returns 94 receiver-capable institutions, filtered to those advertising `receiver` (some are `sender`-only and would guarantee a failed transfer). The BIC the guest picks is validated against that live list, so an unknown institution is rejected before ₱10 is spent discovering it.

**The guest may choose any account** — the destination is not locked to the account they paid from. Send Money is a plain transfer, not a reversal, and some guests pay from someone else's wallet. The control is at the far end: an admin reviews before money leaves, capped at the refund amount.

**Deliberately not logged:** `StaffLog` records *that* a destination was set and which institution, never the account number or name. The account number is masked everywhere except the moment of sending.

**No financial behaviour changed.** A refund is still deducted from the booking the moment it is approved (`Booking::recalculateFinancials()`), whether or not the guest ever supplies details. A missing destination only changes what the admin sees: *"waiting on guest"* rather than *"ready to send"*.

Verified end-to-end inside a rolled-back transaction (15 assertions): notification links to the form and stays relative, cash refunds never ask for bank details, the unique constraint blocks a second destination, and deleting a refund cascades the PII away with it.

### Phase 2: getting the details actually filled in

Phase 1 built the form. Phase 2 makes sure someone reaches it.

**Guests are redirected straight to it** after self-cancelling (`Customer\HomeController::cancelBooking()`) or rescheduling to a cheaper slot (`Customer\BookingController::update()`), whenever the resulting refund still needs a destination.

This is deliberately a **redirect, not extra fields inside the cancel/reschedule form**. Putting three required fields into a cancellation means a validation error can *block a cancellation* — unacceptable. The cancellation completes first; only then do we ask. If the guest walks away, the notification still links back to the form.

**Admins can enter the details on the guest's behalf** (`PUT admin/payments/{payment}/destination` → `Admin\PaymentController::setRefundDestination()`), because in practice these often arrive by text or phone call. Without it, a guest who never returns to the site could never be refunded.

Both doors share one set of rules — `RefundDestination::rules()`, `messages()`, and `mobileNumberError()` — so one form cannot silently drift looser than the other. GCash and Maya get a stricter check (11 digits, starts `09`) because the generic digits-only rule would accept `12345`; real banks vary in length, so nothing extra is imposed there.

**Admin UI:** the payments list distinguishes two states that used to look identical — `NOT SENT` (waiting on *you*) versus `NEEDS DETAILS` (waiting on *the guest*) — with the count surfaced in the awaiting-payout banner and an *Add Details* shortcut. The payment detail page gains a **Refund Destination** card: institution, account name, and a masked account number behind a click-to-reveal, plus who supplied it and when. Once paid out, the card becomes a read-only record of where the money actually went.

`needsRefundDestination()` and `isReadyToSend()` read the relation **accessor**, not `->exists()`, and `Admin\PaymentController::index()` eager-loads `refundDestination` — verified at 0 extra queries with eager loading versus one per row without it.

### Phase 3: making "Mark Paid Out" mean something

§6.11 records the failure this closes: *"Mark Paid Out" was pressed, and **no money moved*** — the out-of-band step was skipped and nothing in the UI asked for it. A `confirm()` dialog is far too easy to click through.

**"Mark Paid Out" now requires the transfer reference** from the actual GCash/Maya/bank receipt, stored in `payments.transaction_ref` — a column that had been unused since it was created (0 of 59 rows). If you did not send the money, there is nothing to paste. Cash refunds keep it optional, since money handed across a desk has no transfer reference.

Two guards sit in front of it:

- **No destination, no payout.** You cannot have sent money to an account nobody recorded. The escape hatch is Phase 2's *Add Details* — so this is sequencing, not obstruction.
- The list **doesn't render a Mark Paid Out button** for refunds still missing a destination. Showing a button that is guaranteed to error teaches staff to ignore errors.

The confirmation modal shows the destination account inline, so the admin is looking at where the money goes while pasting the reference from the receipt. `transaction_ref` is displayed next to `reference_number` on the payment page — deliberately adjacent, because they are opposites: `reference_number` is the **inbound** PayMongo payment (`pay_…`), `transaction_ref` is the **outbound** transfer the resort sent.

**Aging nudges** run from `bookings:auto-checkinout` (the existing daily cron — same command that already sweeps stale pending bookings). After `Payment::PAYOUT_NUDGE_DAYS` (3), an unsent refund pokes **whoever is actually blocking it**:

| Stuck on | Who gets told |
|---|---|
| Missing guest details | **The guest** — "we can't send it until you tell us where" |
| Details present, money unsent | **The admins** — "the guest was told they would hear back" |

Mixing those two would send *"you need to do something"* to someone who can do nothing about it. Repeat nudges are deduped against existing `notifications` rows keyed on the notification `link` (unique per refund), so no new column was needed and a daily cron can't spam. The payments list also shows a `{n}D WAITING` badge once a refund passes the threshold.

`daysAwaitingPayout()` measures from `created_at`, not `payment_date` — `payment_date` is date-only, and using a date-only column for aging produces off-by-one days.

Verified in a rolled-back transaction: a 5-day-old refund without details nudges the guest, a 5-day-old refund with details nudges the admins, a 1-day-old refund is left alone, and a second run of the command creates zero further notifications.

### Browser walkthrough — and the two bugs it caught

The whole lifecycle was then driven through a real browser: guest cancels → redirected to the form → bad GCash number rejected → details saved → admin sees the refund → confirms with a transfer reference → refund closes. All test data was restored afterwards.

Two defects surfaced that no amount of unit-level checking would have:

1. **`admin/payments/index.blade.php` never rendered validation errors.** It printed `session('success')` and `session('error')` but not `$errors`, so any `back()->withErrors()` looked like the button simply did nothing. This pre-dated v5.9 — the refund modal's own `refund_amount` / `refund_reason` errors were invisible too. Fixed by adding an `$errors->any()` block.
2. **The Refund Destination card showed `READY TO SEND` on an already-paid-out refund.** The condition tested only "does a destination exist". Now ordered `isReadyToSend()` → `needsRefundDestination()` → `SENT`.

One workflow note for anyone automating this later: the guest cancel button calls a native `confirm()`, which freezes browser automation. Submit `#cancelForm` directly instead of clicking it.

### The first real transfer — ₱1 through live InstaPay, 2026-08-23

The Wallet was activated in live mode and a single ₱1 transfer was pushed to a GCash number via a throwaway spike (not app code). **The test-mode wallet was re-checked first and still returns `{"data":[]}`**, so the simulator remains unreachable and every observation below came from real money.

The transfer was **accepted** (`201`, `status: "pending"`) and then **rejected downstream** two seconds later. Five things were learned, **none of which are documented by PayMongo**:

1. **`GET /v2/wallets/` hides `balance`, `account` and `limits` by default.** They are absent from the payload, not null. They must be requested explicitly, and `fields` is a **repeated** parameter, not comma-separated: `?fields=balance&fields=account&fields=limits`. Reading `$wallet['balance']['available']` without this silently yields `0` and looks exactly like an empty wallet.
2. **The fee is ₱10.00 per transfer** (`fee: 1000`) — there is no fee table anywhere in the docs.
3. **A failed transfer costs nothing — but the fee reverts *asynchronously*.** `fee` is `1000` at creation and eventually `0` on a failed transfer, and **the gap between those two matters**: querying at the moment the status flips to `failed` still returns `1000`. The inline poll hits exactly that early window, so four failed transfers were stored with a ₱10 fee that was never charged. The spike script only ever saw `0` because it was re-checked minutes later. `syncStatus()` therefore **forces `fee = 0` on anything that is not `succeeded`** rather than believing the API at that instant. Ground truth is the wallet balance: six transfers, one success, ₱12 deducted (₱2 + ₱10).
4. **Live error codes are ISO 20022, not the documented test-mode ones.** This transfer failed with `provider_error_code: "AC06"`, `provider_error_message: "BlockedAccount"`, `metadata.sub_code: "RJCT"`. The codes listed in `transfer-test-cases` (`account_not_found`, `account_not_active`, `account_limit_reached`) are **simulator strings only**. Error handling must map ISO codes. Had the simulator been available, it would have taught the wrong vocabulary.
5. **`provider_reference_number` changes value.** At creation it echoes back whatever `reference_number` we sent; after settlement it becomes the real trace id, alongside `end_to_end_id`, `instruction_id` and `clearing_cycle`. **Re-read it after settlement** — the value captured from the `201` is not the bank's reference.

**The architectural consequence is the important part: `201` does not mean the money arrived.** PayMongo accepted the instruction, passed it to InstaPay, and the receiving institution rejected it. So:

- `markRefundPaidOut()` must **never** be driven by a `201`. It waits for `succeeded`.
- A failure must **reopen** the refund, or the guest sees "sent" for money that bounced back.
- `callback_url` (or polling) is **mandatory**, not an optimisation. It was deliberately omitted in the spike only because a local dev host is unreachable from PayMongo.

*Why this particular transfer failed is not our bug:* the destination GCash account was blocked for cash-in at GCash's end, confirmed by their own SMS to the account holder. The presence of `instruction_id` and `clearing_cycle` proves the instruction genuinely reached the InstaPay network.

#### Then a ₱1 transfer that succeeded — same hour, different institution

A second ₱1 was sent to a **Maya** wallet (`PAPHPHM1XXX`) and **arrived**, confirmed by the recipient. `status: "succeeded"`, `metadata.sub_code: "ACTC"`, settled 2.25s after creation. Both outcomes are now observed against real money:

| | succeeded | failed |
|---|---|---|
| `status` | `succeeded` | `failed` |
| `metadata.sub_code` | `ACTC` | `RJCT` |
| `fee` | `1000` (₱10 charged) | `0` (reversed) |
| `provider_error_code` | absent | `AC06` |
| time to settle | 2.25s | 2.02s |

**The fee reversal is confirmed empirically**, not just inferred from one field: the wallet still read ₱38.82 immediately before the second transfer, i.e. the failed one cost nothing. **₱10 is charged only when the money actually lands.**

*The BIC was never the problem.* Both transfers carried a valid BIC and PayMongo resolved both correctly (`GXCHPHM2XXX` → `G-Xchange, Inc.`, `PAPHPHM1XXX` → `Maya Philippines, Inc.`). The difference was entirely the state of the destination account. Note also that **`Maya Philippines, Inc.` (`PAPHPHM1XXX`, the wallet) is a different institution from `MAYA BANK, INC` (`MYDBPHM2XXX`, a real bank)** — see the `MOBILE_WALLET_BICS` note in `RefundDestination`.

#### ⚠️ Open question: a ₱10,000/month cap on money entering the Wallet

The same `fields=limits` call exposed this on the live wallet (`type: "custom"`):

```
limits.transactions.inward.monthly   = 1000000    → PHP 10,000.00
running_transaction.inward.monthly   =    5882    → PHP 58.82 used
limits.balance                       = 5000000    → PHP 50,000.00 max held
limits.transactions.outward.*        = effectively unlimited
```

Outbound is unconstrained, so refunds themselves are not capped. **Inbound is capped at ₱10,000/month** — roughly two bookings at the ₱4,000–6,000 package rates.

**PayMongo support says no such cap applies.** That is reassuring but not conclusive: the wallet API reports the field, and support has been wrong twice already in this same investigation — first claiming partial QR Ph refunds were possible, then claiming G-Xchange is not listed as an InstaPay receiver when their own endpoint lists it as one.

So treat this as **probably fine, worth watching rather than trusting**. The wallet response carries `running_transaction.inward.monthly` alongside the limit, so the answer is observable rather than argued: if inward volume approaches ₱10,000 in a month and payouts keep arriving, the cap is not enforced. If they stop, it is. No code depends on this either way — refunds are capped by the *available balance* check, which fails safe and points at the manual flow.

#### 🟢 GCash `AC06` is intermittent — the same payload both fails and succeeds

**The API can reach GCash. It always could.** `tr_9bc989fac1d90b7844eca10a` is an API transfer to `09508912563` that landed on 2026-08-26 at 16:49. Nothing about `/v2/batch_transfers` is blocked, and no field in the payload determines the outcome.

The two probes that settled it were 69 seconds apart, sent from the same command, differing in one field:

| | `purpose` | `description` | `reference_number` | Result |
|---|---|---|---|---|
| `tr_2125e015…` 16:48 | `own-account` | `own-account` | `kdqyv59sk…` | `AC06` |
| `tr_9bc989fa…` 16:49 | `own-account` | `Villa Elena refund for booking …` | `pzlow3yqx…` | **succeeded** |

That alone looks like `description` matters. It does not — because `tr_2125e015…` (the failure) is **identical in every merchant-controlled field** to the two dashboard transfers that succeeded earlier the same day (`tr_3477975c…` ₱10, `tr_0d54b95e…` ₱6): same source, same destination, same name, same amount, same rail, same `purpose=own-account`, same `description=own-account`, same lowercase reference shape. Same payload, both outcomes.

**So the payload does not decide. G-Xchange rejects a large share of inbound InstaPay from this wallet nondeterministically and reports it as `AC06 / BlockedAccount`** — a code that reads like a permanent fact about the account, on an account that accepted money 69 seconds later. Sixteen InstaPay transfers to GCash are on record; three landed.

**Four diagnoses were wrong before this one**, and they failed the same way every time: a small sample, one variable that happened to correlate, and **no attempt ever repeated with an identical payload**. In order — "GCash blocks the Wallet" (killed by a dashboard success), "Send Money needs more than ₱5" (killed by our own ₱1 and ₱2 to Maya), "the dashboard sends something invisible" (killed by reading `GET /v2/transfers`, which shows both payloads in full), and "the `purpose` field decides" (killed by `tr_2125e015…`). The rule that would have caught all four: **before concluding a field matters, send the same payload twice.**

**Fix — retry, don't surrender.** `RefundTransferService::send()` now loops up to `MAX_ATTEMPTS` (3), one `refund_transfers` row and a fresh `reference_number` per attempt, and stops early on success or on a non-retryable code. `RefundTransfer::RETRYABLE_ERROR_CODES` holds `AC06`, `AB08`, `9910`, `91`; the account-detail codes (`AC01`, `AC02`, `AC03`, `AC04`, `BE01`, …) are deliberately excluded, since retrying a wrong account number just repeats the mistake more slowly. `status = 'error'` is not retried either — the request never reached PayMongo, so its shape is what is broken.

The admin is notified **once**, after the last attempt, with the attempt count in the message — a rejection retried three times must not look like one that was never retried, or the admin just presses the same button again. Failure announcements are suppressed inside the loop via `syncStatus(..., announceFailure: false)`; success always records immediately, since that is what closes the refund.

Verified against a faked PayMongo that rejects once then accepts, inside a rolled-back transaction: two rows written, `AC06` then `succeeded`, ₱0 fee on the failure and ₱10 on the success, refund closed with the bank trace, no failure notification.

**What is still unknown: the success rate.** Three of sixteen is not a rate — most of those attempts varied other fields, and the sample is tiny. `php artisan paymongo:probe-transfer` exists for exactly this: it sends one controlled transfer with every field settable (`--purpose`, `--description`, `--reference`, `--to`, `--bic`, `--provider`), touches no refund/payment/transfer row, and prints the real settled status. Run it a handful of times at ₱1 with everything held constant and count. If GCash lands roughly half the time, three attempts is plenty; if it is one in ten, `MAX_ATTEMPTS` needs raising or GCash refunds belong on the manual path after all. **`MAX_ATTEMPTS = 3` is a guess about how long an admin will wait at a button (~10s per attempt), not a measured figure.**

`RefundDestination::KNOWN_TRANSFER_ISSUES` still warns above the Send button, now saying the true thing: GCash rejects intermittently, the app retries, and a failure is free.

#### Switching back to test keys — what survives and what does not

Verified on 2026-08-27 by calling the endpoints with the test secret key:

| | Test mode | Notes |
|---|---|---|
| `GET /v2/wallets/` | **`{"data":[]}`** | HTTP 200, no error, no wallet |
| **Send Money / refund payout** | ❌ impossible | No wallet means no `source_account`, so `/v2/batch_transfers` can never be built |
| `GET /v1/wallets/receiving_institutions` | ✅ 94 entries, GCash included | The refund-destination form keeps working |
| `qrph` checkout session | ✅ created, `active`, `livemode: false` | Guests can still pay through the flow |
| Webhook secret | ⚠️ different per mode | Must swap `PAYMONGO_WEBHOOK_SECRET` too |

The empty wallet is the trap: it is a **success** response, so nothing throws where it is fetched, and the failure only surfaces later as *"Could not reach the PayMongo wallet"* — which reads like a network fault and sends you looking in the wrong place. `RefundTransferService::send()` therefore checks `PayMongoService::isTestMode()` **before claiming the refund**, so test mode produces a clear message and no `error` row, no admin notification, no half-written attempt.

Everything else in the refund flow still works on test keys: recording a refund, asking the guest for a destination, and `markRefundPaidOut()`. Only the automatic payout needs live keys.

---

*Superseded — the earlier incorrect diagnosis, kept for the reasoning lesson:*

#### ⚠️ GCash rejects InstaPay transfers from the Wallet — `AC06`, on every account tried

Three separate live attempts to **G-Xchange, Inc. (`GXCHPHM2XXX`)** over InstaPay have all come back `AC06 / BlockedAccount`, across **two different GCash accounts**, one of which the holder confirms is not restricted. The identical code path to **Maya (`PAPHPHM1XXX`) succeeds**, so this is not our bug and not InstaPay generally.

What is known:

- It is **not account-specific** — two accounts, same code.
- GCash's own SMS after the first attempt named the flow explicitly: *unable to process the InstaPay cash-in via PayMongo, funds returned*, and asked the holder to contact GCash support about their cash-in service. GCash evidently treats this specific source differently.
- **Every attempt is free** (`fee` reverts to `0`), so the retries cost nothing.

**Update — PESONet failed too, and GCash is now off the automatic path entirely.** The PESONet probe to the same GCash account was rejected with **`RR04` (Regulatory Reason)** — a compliance-level refusal, distinct from InstaPay's `AC06 BlockedAccount`. So GCash rejects transfers from the PayMongo Wallet on **both rails, for two different stated reasons**.

Routing GCash to PESONet was therefore not just useless but actively worse: PESONet is batch-cleared, so the admin waits a **full banking day** to learn what InstaPay reports in two seconds. `RefundDestination::NO_AUTO_TRANSFER_BICS` now blocks GCash from automatic transfer altogether — `canSendTransfer()` returns false, the Send button is not rendered, the detail page explains why, and the manual form opens by default because it is the only option. `PESONET_ONLY_BICS` and `preferredProvider()` are kept for when GCash (or another institution) becomes reachable.

**GCash is the most common e-wallet among Filipino guests, so in practice a large share of refunds are manual.** That is the honest state of things, and the reason the manual flow was never removed. Re-test occasionally; remove the BIC from `NO_AUTO_TRANSFER_BICS` only when a real transfer lands, never on the strength of PayMongo's institution list.

---

*Superseded context — the reasoning that led to the PESONet attempt:*

**PayMongo support confirmed it and named the fix: use PESONet for G-Xchange.** They also stated there is no enablement gap on our side (the Wallet is activated and Maya works), and that PESONet carries a much higher per-transaction ceiling (₱10,000,000 vs InstaPay's ₱50,000).

⚠️ **Their stated *reason* is wrong, and the wrong part matters.** Support said G-Xchange "is not listed as an InstaPay receiver". PayMongo's own live endpoint says otherwise:

```
GET /v1/wallets/receiving_institutions?provider=instapay
  GXCHPHM2XXX   G-Xchange, Inc.   type = sender + receiver
```

So **`receiving_institutions` cannot be trusted to answer "can this institution actually receive over this rail?"** — it lists GCash as an InstaPay receiver that rejects every InstaPay transfer. This is why `RefundDestination::PESONET_ONLY_BICS` is a hardcoded map rather than something derived from the API: the API is the thing that was wrong. Treat that constant as the record of empirically-proven routing, and only remove a BIC from it after a real transfer proves InstaPay works.

This also means the guest-facing dropdown, built from the InstaPay list, happily offers GCash — so without the routing map every GCash refund would fail. Note too that the PESONet rows report an **empty `type`**, so `receivingInstitutions()`'s `in_array('receiver', $type)` filter would drop them all; that filter needs revisiting if PESONet is ever offered as a guest-visible choice.

**Product consequence, and it is significant:** GCash is the most common e-wallet among Filipino guests. If it cannot receive, the automatic path covers Maya and banks but not GCash, and those refunds fall back to the manual `Mark Paid Out` flow. That is precisely why the manual path was kept.

### Phase 4: the system sends the money itself

`Admin\PaymentController::sendRefundTransfer()` → `POST /admin/payments/{payment}/send`. The manual `markRefundPaidOut()` path is **kept, not replaced** — it is still the only option for cash, for institutions InstaPay cannot reach, and whenever a transfer fails.

**New tables and classes**

- `refund_transfers` — one row per *attempt*, with its own snapshot of institution/account. Separate from `refund_destinations` because the destination is the *current* answer to "where does this go?" (the guest can still change it) while a transfer is the immutable record of "where it actually went and what happened". If the guest corrects their details after a failure, the question *"where did the first attempt go?"* still has an answer.
- `RefundTransfer` — status helpers plus `failureReason()`, which maps ISO 20022 codes to something an admin can act on.
- `RefundTransferService` — all the money-moving logic, deliberately out of the controller.
- `PayMongoService::wallet()`, `walletBalance()`, `sendTransfer()`, `getTransfer()`.

**The ordering that matters**

1. **Claim inside a short transaction** (`lockForUpdate` on the payment + a check for an existing `pending`/`succeeded` transfer), writing the `pending` row *before* any API call. A double-click or two admins at once cannot produce two transfers, and the guard is in the **database**, not in the view — hiding a button is decoration, not a lock.
2. **Call PayMongo outside that transaction.** Money moving inside a transaction that later rolls back leaves no record it ever happened.
3. **Wait inline only for InstaPay** — up to 8s, against a measured ~2.25s settlement. PESONet is never polled inline: it clears in banking-day batches, so waiting would just hold the admin's request open for nothing.
4. **`succeeded` is the only thing that closes the refund.** `syncStatus()` is the single place that sets `status = success` from a transfer.

**Four things can close a pending transfer, all through `syncStatus()`:** the inline poll, the `callback_url`, opening the payment detail page (which syncs anything in flight), and `AutoCheckInOutBookings::syncPendingTransfers()` on the scheduled run. That last one is the **backstop and is not optional** — `callback_url` is deliberately omitted when `APP_URL` is local (PayMongo cannot reach `127.0.0.1`), and even in production PayMongo gives no delivery guarantee. Without it a PESONet transfer that actually landed would sit `pending` forever and the debt would stay open in the records. It swallows per-transfer errors so an unreachable PayMongo cannot take down the check-in/check-out run it shares a command with.

**Failure keeps the refund open.** A failed transfer never marks the refund paid out; the admin is notified with a plain-language reason, the raw code is shown for support calls, and the refund can be sent again — free, since failed transfers are not charged. `status = 'error'` is kept distinct from `'failed'`: the former never reached PayMongo at all, which is a different problem from a bank rejection.

**The callback is not trusted.** `POST /webhooks/paymongo/transfer` (CSRF-exempt) reads only *which* transfer changed, looks that id up in our own table, and then fetches the real state over an authenticated `GET`. The payload shape is undocumented and it is unclear whether it is signed, so nothing in it is believed. A forged call can at most make us ask PayMongo about a transfer that is already ours.

**Balance is checked first**, including the ₱10 fee — a balance exactly equal to the refund is not enough. An unreachable wallet returns `null`, not `0.0`, and is deliberately *not* treated as "no funds"; the check is a guard against a known failure, not a gate.

**Verified** with a fake `PayMongoService` returning the exact shapes observed live (including `fee: 1000` → `0` on failure and the `ACTC`/`RJCT` sub-codes): 24 assertions across success, `AC06` failure, insufficient balance, unreachable API, double-send, and cash — all passing, inside a rolled-back transaction. No real transfer was made from app code.

### What live testing found after Phase 4 shipped

Five defects, none of which the 24-assertion suite could have caught. Worth reading as a set, because the pattern is the same each time: **the verification exercised the logic and skipped the seam.**

**1. The list page steered every admin into the wrong button.** `Send Refund` was only ever on the *detail* page; the payments list still offered `Mark Paid Out` — the manual fallback — as the sole action. The first real test went straight into it, and a ₱2 refund was recorded as sent with the *booking reference* typed in as the transfer receipt, while `refund_transfers` held zero rows. `Mark Paid Out` never calls PayMongo; that is the whole point of it. Fixed by making `Send Refund` the primary action in the list, rewording the banner, and making the manual modal say **"This does not send any money"** outright.

**2. `Http::get()` silently broke the `fields` parameter.** `['fields' => ['balance','account']]` serialises through `http_build_query()` to `fields[0]=…&fields[1]=…`, which PayMongo ignores — returning a wallet with no `account`, indistinguishable from a wallet that has none. Live symptom: *"The PayMongo wallet has no source account. Nothing was sent."* on a funded, activated wallet. **Write that query string literally into the URL.** The spike script had it right because it built the URL by hand; the service regressed it by using the idiomatic array. The guard behaved correctly and refused to send, so no money moved.

**3. The guest could change the destination mid-flight.** `isAwaitingPayout()` stays true while a transfer clears, so nothing stopped a guest editing GCash → Maya while money was already on its way to GCash — the record would say Maya, the money would land in GCash, and the refund would look lost. Invisible when everything settled in two seconds; a full-day window once PESONet entered the picture. Both `Customer\RefundDestinationController::authorizeRefund()` and `Admin\PaymentController::setRefundDestination()` now refuse while `hasTransferInFlight()`, and the admin form is hidden rather than shown-then-rejected. **A failed transfer reopens the lock** — that is exactly when the details need correcting.

**4. `MAYA BANK, INC` was being forced to enter a mobile number.** `isMobileWallet()` matched on institution *name* (`str_contains($name, 'maya')`), which catches both `Maya Philippines, Inc.` (`PAPHPHM1XXX`, the wallet — mobile number) and `MAYA BANK, INC` (`MYDBPHM2XXX`, a real bank — ordinary account number). A Maya Bank customer could never have submitted valid details. Now keyed on BIC via `MOBILE_WALLET_BICS`. **Never detect wallet-vs-bank by name.**

**5. A Blade expression inside a CSS comment.** `{{ payment_status }}` written inside `/* … */` still compiles — Blade does not care where it appears — producing `e(payment_status)` and a runtime *"Undefined constant"*. `compileString()` reported the view as fine, because that **is** valid PHP; only rendering catches it.

**The verification lesson, and it is the general one:** every one of these lived in a seam the tests stubbed over — the fake `PayMongoService` overrode `wallet()`, so the query-string bug was unreachable; the assertions called methods directly, so the button wiring was unreachable; `compileString()` checks syntax, so a runtime constant was unreachable. Fakes prove logic and prove nothing about the edges where the code meets Laravel, PayMongo, or a browser. Those need a real render, a real read-only API call, or a real click.

**One data-repair note:** refund #143 was reopened (`status` back to `pending`, `transaction_ref` and `processed_by` cleared) after being falsely marked paid out, with a `refund_payout_reversed` StaffLog entry recording why. Safe to do because `recalculateFinancials()` sums refunds **regardless of status**, so booking financials were unaffected — confirmed before and after.

### Phase 5: "Refunded" was a lie until the money actually moved

A booking's badge read **Refunded** the moment a refund was *approved* — `recalculateFinancials()` sums refund rows **regardless of `status`**, so `payment_status` flipped to `refunded` before anything was sent. With InstaPay that gap was two seconds. With PESONet it is a full day, and the guest sees "Refunded" while holding no money.

**`payment_status` was deliberately left alone.** It answers *"is this booking's money settled?"* and drives filters, reports, the calendar and six views; overloading it with delivery stages would mix two different questions and ripple everywhere. Instead `Booking::refundStage()` **derives** the stage from the records that already know the truth — `payments.status` plus `refund_transfers.status` — so there is no column that can drift out of sync:

| Stage | Shown as | Meaning |
|---|---|---|
| `owed` | **Refund** | Approved, nothing sent yet |
| `processing` | **Refund Processing** | A transfer is in flight (the normal state for GCash/PESONet) |
| `failed` | **Refund Failed** | A transfer was attempted and rejected — needs attention |
| `refunded` | **Refunded** | Money actually delivered |

Rendered via `$booking->payment_status_label` / `payment_status_class` in `admin/bookings/show` and `customer/booking_detail`. Both controllers eager-load `payments.refundTransfers` — without it `refundStage()` is a query per refund. The customer view also had **no `.p-refunded` rule at all**, so that badge had been rendering unstyled.

**A "Refund On Its Way" notification** now fires when a transfer is submitted but has not settled — `NotificationHelper::refundOnTheWay()`, called only when the transfer is still `pending` after the inline wait, so instant transfers don't produce two notifications in a row. It names the institution and, for PESONet, the actual clearing windows. Without it the guest gets "approved" and then silence for a day, which in money terms reads as a refund that vanished.

Verified across all five stages (including `none`) inside a rolled-back transaction, plus against the real bookings in the local database.

### Wallet QR — considered, not chosen

`Wallet QR` would remove the recipient-details problem entirely (*"no account number required"* — scan the recipient's QR Ph code and push funds via `POST /v2/qr/transfer`). It is **not** the primary path for three reasons: the docs state *"Wallet QR does not support test mode"* (live keys only, so zero rehearsal); the API needs the QR **payload string**, which a guest cannot extract from their wallet app — only a screenshot, so something has to decode it; and it is undocumented whether a personal GCash receive-QR is a valid transfer target. Revisit as a convenience once the InstaPay path is stable.

---

## What Changed in v5.8 (Read This First)

### Groq retired `llama-3.1-8b-instant` — every AI feature was returning an API error

The model this app had hardcoded since v4.0 was **decommissioned by Groq**. It now returns:

```
400 {"error":{"message":"The model `llama-3.1-8b-instant` does not exist or you do not have access to it.","code":"model_not_found"}}
```

This broke all four AI call sites at once — the public chatbot, admin insights, admin forecast, and review moderation — with no code change on our side. Confirmed against the live account: `GET https://api.groq.com/openai/v1/models` no longer lists it.

**Fix — the model is no longer hardcoded.** It reads from `GROQ_MODEL` (default `openai/gpt-oss-20b`), so the next retirement is an env change, not a code deploy:

| Area | Before | v5.8 (Now) |
|---|---|---|
| Model id | Hardcoded `llama-3.1-8b-instant` in `GeminiService` | `config('services.groq.model')` from `GROQ_MODEL`, default `openai/gpt-oss-20b` |
| Reasoning | N/A (Llama 3.1 was not a reasoning model) | Sends `reasoning_effort`, **derived from the model id** by `GeminiService::reasoningEffort()`; a 400 naming the parameter is retried once without it. `GROQ_REASONING_EFFORT` overrides, `omit` skips |
| Token budget | `max_tokens` fixed at 1024 for every call | `ask(string $prompt, int $maxTokens = 1024)`; the forecast report passes `2048` |
| Failures | Returned an error string to the caller, logged nothing | Same string (the fail-open contract `ReviewModerationService` depends on) **plus** `Log::error` with model + status + body |
| Stray `<think>` blocks | Would have been rendered to the guest verbatim | Stripped by `GeminiService::stripReasoning()` |

### Why `reasoning_effort` matters here

Every chat model Groq currently offers is a **reasoning** model, and reasoning tokens are billed against the same `max_tokens` budget as the answer. Measured on the forecast prompt at `max_tokens: 1024`:

| `reasoning_effort` | Hidden reasoning | Actual answer | Result |
|---|---|---|---|
| default | 2,244 chars | 1,845 chars | Report truncated mid-section |
| **`low`** | 121 chars | **3,518 chars** | Nearly 2x the usable output |

Left at the default, the admin forecast would have silently cut off partway through — a subtler failure than the outright 400, and easy to mistake for the model being bad.

### `reasoning_effort` is not portable across models

Each family accepts a **different, mutually exclusive** set of values — there is no value that works everywhere. Verified against the live API:

| Model family | Accepts | Sending anything else |
|---|---|---|
| `openai/gpt-oss-*` | `low` \| `medium` \| `high` | `400 "must be one of low, medium, or high"` |
| `qwen/*` | `none` \| `default` | `400 "must be one of none or default"` |
| `groq/compound*` | *(unsupported)* | `400 "not supported with this model"` |

So a hardcoded `reasoning_effort` re-breaks every AI feature on the next model swap — the exact failure the env var was meant to prevent. `GeminiService::reasoningEffort()` therefore derives the value from the model id, and any 400 naming the parameter is retried once without it, so even an unrecognised future family degrades to a working call instead of an error string. Verified end to end across all four families above, plus a forced-invalid value (`high` on qwen) to confirm the retry path logs and recovers.

### Model choice on Groq's free tier

`openai/gpt-oss-20b` is the default: fastest, and it keeps its reasoning in a **separate `reasoning` field**, so `content` stays clean for the two call sites that parse the reply strictly (`ChatbotController`'s intent JSON, and `ReviewModerationService`'s exact `CLEAN` / `FLAGGED: <reason>` one-liner). `openai/gpt-oss-120b` is a drop-in upgrade via `GROQ_MODEL` if forecast quality matters more than latency. Avoid `qwen/qwen3.6-27b` — it inlines `<think>` into `content`.

All four call sites re-verified end to end after the swap: chatbot intent returns valid JSON, moderation returns clean verdicts on both a genuine and a spam review, insights returns exactly 5 lines, and the forecast renders a complete report (2,695 chars, closing section intact).

---

## What Changed in v5.7 (Read This First)

### QR Ph replaces GCash and Maya as the online payment method

GCash and Maya each require a **separate application and activation** on PayMongo. QR Ph is already activated on the resort's account and **covers both — plus every bank app that supports QR Ph** — through a single integration. Switching to it is what makes real payments possible at all; the per-wallet route was blocked on approvals that hadn't been granted.

Verified against the live PayMongo API before building: `payment_method_types: ['qrph']` is accepted by Checkout Sessions on this account, so the hosted-checkout architecture is unchanged. Only the requested method list changed.

| Area | Before | v5.7 (Now) |
|---|---|---|
| `payments.payment_method` | `ENUM('gcash','paymaya','cash')` | `ENUM('qrph','cash')` |
| PayMongo request | `payment_method_types => ['gcash','paymaya']` | `['qrph']` |
| Manual record dropdowns (admin ×2, staff ×2) | Cash / GCash / PayMaya | Cash / QR Ph |
| Checkout page badges | `💙 GCash` `💚 Maya` | QR Ph + GCash + Maya + Bank apps, **plus an explainer** that a QR will be shown to scan |
| Dead duplicate `app/Http/Services/PayMongoService.php` | Still present, unreferenced, still requesting `grab_pay` | Deleted |

**41 existing rows were relabeled** (`gcash` ×37, `paymaya` ×4) to `qrph`. Most of those were *manual* records — staff receiving a direct GCash transfer, which is not the same thing as QR Ph — so the migration writes the original method into `notes` first (`[Originally recorded as GCash before the QR Ph switch.]`). Narrowing the ENUM erases the value; it should not erase the history.

The migration takes **four** steps, and the order matters in the opposite direction from v5.5's shrink: widen the ENUM to admit `qrph` → relabel → narrow. Relabeling first fails with `Data truncated`, because the target value isn't in the ENUM being written to yet. (v5.5 relabeled *toward* an existing value, so one ALTER sufficed there.)

On the checkout page the wallet names are still shown alongside "QR Ph". Almost nobody recognises the QR Ph brand name; listing it alone reads as *"GCash is gone"* to a guest whose GCash app is exactly what they'll scan with.

### QR Ph is asynchronous — which broke an assumption the success page was built on

With GCash the guest is redirected into the wallet, authorises, and is redirected back, so arriving at `success_url` implied payment. **QR Ph has no such guarantee.** PayMongo displays a QR code; the guest scans it with a phone that is frequently *not* the device showing the QR. That browser tab may never navigate again, and if it does, settlement may not have completed.

Two consequences, both now handled:

1. **The webhook is the primary recording path, not a fallback.** This is why the v5.6 webhook work had to land first. It also means registering the webhook is now a **prerequisite**, not an optional extra — see Pending/Optional.
2. **The success page can be reached before payment exists.** It used to render an unconditional green check and *"Payment Successful!"*, with the amount taken from `line_items` — the amount *requested*, not received. It now renders a second, honest state: a waiting notice explaining that confirmation arrives automatically, the outstanding balance, a "Not yet received" payment-status chip (previously a blank cell), and a retry link.

**A real financial bug surfaced while doing this.** The callback recorded a payment when the session status was `'paid'` **or `'active'`** — but `active` means the session is still *open*, i.e. unpaid. Combined with reading the amount from `line_items`, a guest who opened checkout and returned without paying would have had a full payment recorded against their booking with no money received. Rare under GCash (you couldn't get back without authorising); routine under QR Ph. The check is now the authoritative one: an actual payment object inside the session carrying `status === 'paid'`, with the amount and reference read from that payment.

---

## What Changed in v5.6 (Read This First)

### Refunds — the guest was never told anything

v5.5 fixed the refund *accounting* (pending vs. paid out). What it didn't fix was that **nobody told the guest**. Cancelling a booking produced two notifications and both went to the admin; the guest got only a flash message, which disappears on the next page load, leaving no record of whether a refund was even owed.

| Gap | Before | Now |
|---|---|---|
| **Guest self-cancels** | `Customer\HomeController::cancelBooking()` called `bookingCancelled()` and `refundIssued()` — **both `notifyAdmin()`**. The guest received nothing. (The *admin*-initiated cancel path did notify the guest, so this was an oversight in one path, not a design choice) | `bookingCancelledForGuest()` — one notification covering both the cancellation and the refund amount/percentage, or an explicit "not eligible for a refund" |
| **Refund marked paid out** | `markRefundPaidOut()` only flipped `status` and wrote a `StaffLog` row. Completely silent — the guest was told a refund was coming and then never heard again, and no admin got confirmation the refund was closed | `refundPaidOut()` notifies **both**: the guest ("Refund Sent … allow a few banking days") and all admins ("Nothing further is pending on this refund") |
| **"Refund Processed" was a lie** | The admin refund path told the guest *"has been processed"* while the refund row was still `status='pending'` — no money had moved. Guests would go looking in GCash for something that hadn't been sent | Retitled **"Refund Approved"**, explicitly stating the money hasn't been sent yet and that a second notification follows. Same correction applied to the reschedule flash message ("has been refunded" → "will be refunded") and the admin success message |

All four refund-creating sites (`Customer\HomeController`, `Customer\BookingController`, `Admin\BookingController`, `Admin\PaymentController`) now notify the guest. The wording lives in **three new guest-facing presets** in `NotificationHelper` rather than in scattered `Notification::create()` blocks, so the pending-vs-sent distinction can't drift apart per call site again.

### PayMongo webhook — signature verification could never have passed

`PayMongoService::verifyWebhook()` hashed the raw body and compared it to the **entire `Paymongo-Signature` header**. That header is not a bare hash — it looks like:

```
Paymongo-Signature: t=1496734173,te=5f1a3b...,li=9c2d7e...
```

`t` is the timestamp, `te` the test-mode signature, `li` the live-mode one, and the signed payload is `"{timestamp}.{rawBody}"` — not the body alone. **Every genuine PayMongo event would have been rejected with 401**, silently, even with the webhook correctly configured in the dashboard. Now parses the header, reconstructs the signed payload, and accepts either `te` or `li` so it works in test and live mode without a code change.

### The webhook now actually records payments

The handler matched a booking and then only called `Log::info()` — the comment called it "a fallback for missed callbacks", but it never wrote anything. **The success callback was the only thing creating `Payment` rows**, and it only runs if the guest returns to the site after paying. Close the GCash browser tab and PayMongo has the money while the system still shows the booking unpaid.

- Both paths now call one shared `recordPaymongoPayment()` — payment row, notifications, `recalculateFinancials()`, auto-confirm, confirmation email. (Deliberately shared: this codebase already had *seven* diverging copies of the money math, fixed in v5.5.)
- **Idempotent** via `reference_number`, which holds the PayMongo `pay_xxx` ID in both paths — so a retry or a webhook-plus-callback double delivery records once. This only works because `reference_number` was added to `Payment::$fillable` in v5.5; before that it was always `NULL` and the guard could never match.
- Unmatched bookings and already-recorded payments still return **200** — a non-2xx would make PayMongo retry something no retry can fix.
- `payment_method` is normalised against the narrowed `gcash/paymaya/cash` ENUM, with the original value kept in `notes` and logged. Without this, an unexpected source type would fail the INSERT, return non-2xx, and have PayMongo retry forever while the payment stayed unrecorded.

**This needed no live PayMongo account** — webhooks work in test mode with the test secret key. What it *does* need is a publicly reachable URL: register `POST /webhooks/paymongo` in the PayMongo dashboard against the Render URL or an ngrok tunnel, and set `PAYMONGO_WEBHOOK_SECRET`. It cannot fire against `localhost`.

### Verified against real money

QR Ph was tested end to end on **live keys** with two real ₱2 payments (`VE-AVFHSPDY`, 2026-08-16): a ₱2 deposit, then the ₱2 balance. Both were delivered by webhook and recorded correctly; the booking auto-confirmed and settled to fully paid.

**PayMongo reports QR Ph as `source.type = "qrph"`** — matching the ENUM exactly, so the unmapped-method fallback never fired.

Two webhook deliveries were rejected with 401 during the session. Diagnosed via the ngrok request inspector: both carried `livemode: false` — **test events sent from the PayMongo dashboard**, which sign the `te=` slot with the test secret while the app runs live keys. Correct rejection, nothing lost; real payments sign `li=`. The rejection log now says this itself (see Known Issues).

### Payment display and notification fixes found after the live test

| Issue | Detail | Fix |
|---|---|---|
| Payment history in random order | `payment_date` is a **date, no time**, so two payments on the same day tie and MySQL returns them arbitrarily — the deposit showed *above* the later balance payment | `id` added as tiebreaker in both payment controllers; the two booking-detail histories had **no ordering at all** and now sort newest-first |
| `Full_payment`, `Qrph`, `FULL PAYMENT`, `Full payment` | Six views each did their own `ucfirst(str_replace(...))` on the raw enum, producing four different renderings of the same value | `Payment::typeLabelFor()` / `methodLabelFor()` (static, so `NotificationHelper` can use them too) plus `type_label` / `method_label` accessors, used everywhere |
| **Admin told the wrong balance** | `paymentReceived()` was called **before** `recalculateFinancials()`, so "Balance due" was always one step behind. Live evidence: ₱2 paid on a ₱4 booking reported *"Balance due: ₱4.00"*; the final ₱2 reported *"₱2.00"* on an already fully-paid booking | Notification moved after the recompute, in both the PayMongo and manual-record paths |

The four notification rows already written with `via Qrph` were relabeled. **Their "Balance due" figures were deliberately left alone** — those are what was actually sent at the time, and rewriting them would hide that the bug happened.

### QR Ph payments CANNOT be refunded through PayMongo — ⚠️ SUPERSEDED, THIS WAS WRONG

> **Corrected in v5.9 — read that section instead.** The conclusion below is false. The test hit `api.paymongo.com/v1/refunds`; QR Ph refunds live on a **different host**, `refunds-api.paymongo.com/v1/refunds`, which does accept them. The error quoted here is real and reproducible — it is simply what the *wrong* endpoint returns for a `qrph` source type. The record is kept because the reasoning failure is instructive: a categorical-sounding error, repeated four ways, was treated as proof about the payment method when it was only ever proof about one URL. **The support chatbot dismissed below was closer to right than this section was.**

`POST /v1/refunds` rejects every QR Ph payment:

```
HTTP 400 parameter_invalid
"Refunds are not allowed for payments with source type qrph."
```

Tested four ways against real live payments (`VE-AVFHSPDY`): both payments, full and partial amounts, and both **before and after** settlement (`available_at` 2026-08-18 17:00). Identical categorical error every time — so this is a restriction on the **source type**, not a timing or balance condition.

PayMongo's support chatbot states the opposite (that dashboard and API refunds route back to the customer's GCash automatically). It appears to answer generically about "QR code payments" without checking the `qrph` source-type restriction. **The API response is authoritative; the chatbot is not.** Worth confirming with a human at support@paymongo.com, quoting the error above, since it shapes the entire refund design.

Consequences, and they are structural:

- ~~**No refund automation is possible for QR Ph.** The Pending/Optional item proposing a `POST /v1/refunds` integration is closed — not deferred, impossible.~~ **False (v5.9)** — automation is possible for *full* refunds on the other host, and for **any** amount via Send Money (`/v2/batch_transfers`), which is the direction actually chosen. Only *partial refunds through the refund API* are impossible.
- ~~**Every refund must be sent by hand**~~ — still true *in practice*, because no code has been written yet, and permanently true for partial/cash refunds. But it is a choice now, not a limitation.
- The original transaction fee is not returned on a refund (per support; consistent with industry norms — observed fee was ₱0.03 on ₱2.00, ~1.5%).

Settlement behaviour observed on the live payments, matching what support described: QR Ph clears to `available_at` in about one banking day, then batches into a weekly (default Wednesday) payout.

### Switching between PayMongo test and live mode

Swapping the two API keys is **not** sufficient. Three things go wrong quietly if the rest is skipped.

**To go live:**

1. Move the live values into the active names in `.env` (they are parked as `PAYMONGO_PUBLIC_KEY_LIVE` / `PAYMONGO_SECRET_KEY_LIVE` so they are never lost):
   ```
   PAYMONGO_PUBLIC_KEY=pk_live_…
   PAYMONGO_SECRET_KEY=sk_live_…
   ```
2. **Rebuild the container** — `docker compose up -d --build`. Env vars are read at container start, so editing `.env` alone changes nothing, and a plain `up -d` recreates from the *image*, silently reverting synced source too. This bit us twice in one session.
3. **Re-check the ngrok URL.** The live webhook (`hook_ryzReXGN…`) is registered against one specific hostname. A free ngrok URL changes on every restart, and when it does the webhook points at a dead host — PayMongo takes the money and the app never records it, with nothing in the local logs to show for it. Either claim ngrok's free static domain (`ngrok http --url=<domain> 8000`) or re-register the URL in the PayMongo dashboard after every restart.

`PAYMONGO_WEBHOOK_SECRET` does **not** change — it belongs to the live webhook and is already correct. In test mode it simply will not match (webhooks are mode-specific and no test-mode webhook is registered), so `401 invalid signature — rejected … livemode: false` in the logs is expected, not a fault.

**To go back to test:** blank the two active keys and paste the test pair from Dashboard → Developers → API Keys with the Test toggle on, then rebuild. Test keys are not stored anywhere in the repo — `.env` is gitignored and `.env.example` ships empty.

**If prices were lowered for cheap live testing, restore them.** `properties.base_price` / `weekend_price` for the villa must return to ₱4,000 / ₱6,000. This is the easiest step to forget and the most expensive to miss: a real guest would be quoted ₱4.

### Still deliberately manual: sending refund money

**"Mark as Paid Out" does not move money.** It is `$payment->update(['status' => 'success'])` — a logbook entry meaning "I, the admin, have sent this." The admin sends the money out-of-band (own GCash/Maya transfer to the guest's number) and *then* presses the button.

> **v5.9 correction:** this section used to read *"and cannot be made to"*, on the grounds that PayMongo refuses QR Ph refunds outright. That was wrong — see v5.9. `PayMongoService` still has no refund or transfer method, but that is now unbuilt work rather than a hard limit: **Send Money** (`/v2/batch_transfers`) can send any amount, including the 50% tier. The manual flow stays correct regardless for cash refunds and as the fallback whenever a transfer is ineligible or fails.

This was demonstrated the hard way during live testing: the guest-cancel flow was run end to end, "Mark Paid Out" was pressed, and **no money moved** — because the out-of-band step was skipped, and nothing in the UI asked for it. The app told the guest "Refund Sent" while the resort still held the cash. See the safeguards in Pending/Optional.


---

## What Changed in v5.5 (Read This First)

Version 5.5 tightens up three areas that had drifted out of step with the single-villa / fixed-slot model, plus a cluster of money-handling bugs found while working on them. The theme running through all of it: **screens were showing numbers that were technically computed but operationally meaningless**, and **money flows claimed to be finished when nothing had actually happened**.

### Payments — e-wallets and cash only

| Area | Before (v5.4) | v5.5 (Now) |
|---|---|---|
| `payments.payment_method` | `ENUM('gcash','paymaya','card','cash','bank_transfer')` — dropdowns also offered "Credit Card", "Online Banking", and a bare "Online" that **wasn't even a valid enum value** (would have thrown on save) | `ENUM('gcash','paymaya','cash')`. The resort only accepts e-wallets online plus cash in person. 17 existing `card`/`bank_transfer` rows were relabeled to `gcash` before narrowing the ENUM (MySQL rejects rows using a value being removed) |
| `payments.payment_type` | `ENUM('deposit','full_payment','balance','extra','partial','refund')` — `deposit` and `partial` were used interchangeably across the codebase to mean the same thing (a payment less than the full amount) | `ENUM('full_payment','balance','extra','partial','refund')`. Consolidated on `partial`; 5 existing `deposit` rows relabeled |
| PayMongo checkout | `payment_method_types` requested `['gcash','paymaya','grab_pay']`, while the on-screen "Accepted Payment Methods" badges advertised Card + GCash + Maya + GrabPay — neither list matched the other or the DB | Both now say GCash + Maya, matching the ENUM |
| `Admin\PaymentController::store()` | `'payment_method' => 'required'` / `'payment_type' => 'required'` — no `in:` rule at all, so any string reached the DB | Proper `in:` rules on both |

**Note on wording:** the *guest-facing* "Deposit" concept on the checkout page (the 50% needed to confirm a booking) is unchanged and still correct — only the stored `payment_type` behind it changed to `partial`.

### Rescheduling — closing a real cancellation-policy loophole

Rescheduling had **no limit of any kind**: no cap on how many times, no cutoff on how close to check-in, and no column even tracking it. That made the cancellation policy bypassable:

1. Guest cancels 2 hours before check-in → `calculateRefundPercentage()` returns **0%**.
2. Instead, the guest *reschedules* to next month. Free, no penalty, unlimited.
3. Now check-in is 30 days out → cancel → **100% refund**.

The refund tier reads `checkInDateTime()` (the *new* date), so one reschedule turned a 0% refund into 100%, and the peak slot was held closed the whole time.

| Rule | Value | Why |
|---|---|---|
| `Booking::RESCHEDULE_CUTOFF_DAYS` | **7 days** before check-in | Deliberately the *same* boundary as the 100%-refund tier. Once the 100% tier is gone, rescheduling is gone too — so there's nothing left to gain by rescheduling instead of cancelling |
| `Booking::MAX_RESCHEDULES` | **2** per booking | New `bookings.reschedule_count` column. Existing bookings start at 0 (fresh allowance, not retroactively locked out) |

`Booking::rescheduleBlockReason()` is the single source of truth — it returns the reason a booking can't be moved (or `null`), and that same string is what the guest sees. The controller re-checks it in `update()`, not just `edit()`, so a form left open past the cutoff can't slip through.

**Also corrected:** `portal/booking_form.blade.php` advertised *"Free cancellation 48 hours before check-in"* while the code actually implements 7 days = 100%, 3–6 days = 50%, under 3 days = nothing. The terms text now matches the code, and pulls the reschedule numbers from the constants so it can't drift again.

### Refunds — they were claiming to be done when no money had moved

| Bug | What was happening | Fix |
|---|---|---|
| **Fully-paid booking tagged "Partial"** | Rescheduling to a cheaper slot always set `payment_status = 'partial'`, even when the guest had fully paid the new lower total. Reproduced: ₱6,000 paid → reschedule to ₱4,000 → refund ₱2,000 → `paid=4000, balance=0`, but status read `partial` | Fixed via the centralized recalculation below |
| **Seven copies of the same money math** | `amount_paid`/`balance_due`/`payment_status` were recomputed inline in 7 places and had drifted apart. The PayMongo success callback **ignored refunds entirely**, so a booking with a prior refund would have its `amount_paid` jump back up after the next online payment | New `Booking::recalculateFinancials()` — one implementation, called from all 7. Two explicit rules: real payments count only when `status='success'`; refunds count **as soon as they're approved**, regardless of payout state (the guest is owed it either way) |
| **Refunds marked `success` with no money sent** | There is no refund API (and cash can't be API-refunded anyway) — admin sends the money by hand. But refund rows were written as `status='success'` immediately, and the admin notification said *"₱X **refunded**"*, past tense. Nothing anywhere tracked whether the payout actually happened, so a guest's refund could be forgotten with no trace | Refunds now start as `status='pending'`. The Payments page shows a **"NOT SENT"** badge, an *"N refunds (₱X) awaiting payout"* banner, and a **Mark Paid Out** action. Notification retitled *"Refund To Send"* and links straight to the filtered list. Marking paid out changes **no amounts** — the refund was already deducted when approved |
| **Audit trail was entirely blank** | `reference_number` and `received_by` were passed by nearly every `Payment::create()` call site but **missing from `$fillable`**, so mass assignment silently dropped them: all 46 payment rows had both as `NULL`. This also broke the duplicate-payment guard in `PaymentController::success()`, which looks up an existing row by `reference_number` — a lookup that can never match when the column is always `NULL` | Both added to `$fillable`, plus a `receivedBy()` relationship |
| **Walk-in payment recorded with no Payment row** | If staff typed an amount but left Payment Method blank, `storeWalkin()` still wrote `amount_paid` onto the booking but skipped `Payment::create()` (gated on `$request->payment_method`). Found one real instance: `VE-OLRWMOGX`, `amount_paid = ₱3,999.96`, **zero payment records** — invisible to revenue reports, and the two sources of truth disagreed | Validation now rejects an amount with no method. **The existing bad row was left untouched** — it's real data and needs a human decision on whether ₱4,000 was actually received |

`Admin\ReportController`'s net-revenue calculation was also corrected: it filtered refunds by `status='success'`, which after this change would have excluded not-yet-paid-out refunds and **overstated net revenue**. Refunds there now count on approval, matching `recalculateFinancials()`.

### Staff Portal — rebuilt around the single villa

| Area | Before | v5.5 (Now) |
|---|---|---|
| Frontdesk stat cards | "Occupied **1** / Available **3**" — counted *all* property rows: 1 villa + 3 unnamed info-only `type=room` records. Read as "3 units still free" when the only bookable unit was taken | Replaced with a **Villa status strip**: real status, current guest, checkout time, plus the next few Day/Night slots as one-click links into the walk-in form |
| "Properties" tab | One card per property row — so three blank cards, since those room records have `property_name = NULL` since v5.0 | Removed. Superseded by the Villa strip and the new Availability page |
| Slot availability | **Did not exist anywhere.** The admin calendar is `check_in_date`→`check_out_date` based and therefore **slot-blind** — a Day booking and a Night booking on the same date look identical, so you can't tell which half of the day is free without clicking each event. The walk-in form did no availability check at all until submit | New **Availability page** (own sidebar item, `/staff/availability`) — a 14-day slot grid, Day and Night per row, four states (Available / Booked / Blocked / Passed). Clicking a free slot opens the walk-in form pre-filled with that date and slot |
| Housekeeping | Task shows a due **date** only. Nothing ever closed the loop: check-in creates the task, auto-checkout flips it to `in_progress`, and "Complete" is manual — buried in a tab. Result: **11 of 13 open tasks overdue** (worst: 48 days), 6 stuck in `in_progress` | Each task now shows its **real deadline** — the *next check-in time* — because the turnaround between slots is only 2 hours (5PM→7PM, 6AM→8AM). A colour-coded banner sits at the top of the frontdesk with a one-click **Mark cleaned**. Checkout's success message now names the deadline instead of saying "task activated" |
| Theme | Its own navy/gold palette, dark sidebar | Same stone/terracotta earth theme as admin, cream sidebar. This was never really a design decision — **admin used to be navy too** and was migrated to the earth palette; staff simply never got migrated with it |

`buildSlotGrid()` deliberately calls `Booking::hasConflict()` per slot rather than writing its own overlap query, so the grid and the actual booking rules can never disagree — including expired unpaid holds freeing up automatically.

**Backlog cleared:** a migration closed 13 → 5 open tasks (6 were seed rows with no `booking_id`; 2 were real tasks whose bookings checked out days earlier). They're marked `completed` — the only other enum value — but each carries an explicit note saying it was auto-closed and never actually marked done, so it doesn't read as a real cleaning record.

### Public portal touch-ups

- **Contact section:** Facebook and TikTok are now regular contact rows (icon + label + link) matching Phone and Email, instead of bare circular icon buttons. The duplicate social icons in the footer were removed, so social links live in exactly one place.
- **Reviews:** the reviewer's uploaded profile picture now shows in the avatar on both the homepage testimonials and `/reviews`, falling back to the initial when there's none. Customers could already upload one; it just was never displayed.
- **Customer dashboard:** the "My Bookings" button in the welcome banner wasn't clickable — the banner's decorative `::before` circle overlapped it. Fixed with `position:relative; z-index:1` on the CTA row.

### One workflow note worth remembering

Mid-session, dropdown changes appeared not to take effect. The cause wasn't caching — the Docker container was running **stale code**. `resources/`, `app/`, `routes/`, `config/`, and `database/` are only synced into the container by Compose Watch, which requires `docker compose up --watch`; a plain `docker compose up` leaves the container frozen on whatever the image was built with.

The dangerous part isn't the stale UI. Migrations run from the host hit the **same MySQL** the container uses, so a stale container ends up running **old code against a new schema** — here, container code still writing `payment_type = 'deposit'` against an ENUM that no longer had it, which fails with `Data truncated`. **Always restart the container after running migrations** if Watch isn't active, or the next error you chase may be an artifact rather than a real bug. Diagnose it by reading the file *inside* the container (`docker exec <container> grep ...`) rather than assuming it's a cache.

---

## What Changed in v5.4 (Read This First)

Version 5.4 removes a real friction point in the Staff walk-in flow: a brand-new walk-in guest (no existing account) previously **had to** get a full login account created for them — `full_name` and `email` were both mandatory, and a password-reset email was always sent, whether or not the guest wanted portal access. Many walk-in guests just want to be checked in; they don't want to hand over an email or manage a login. The fix separates **"guest record"** (needed for every booking, for history/payments/reviews) from **"login account"** (now fully optional).

| Area | Before | v5.4 (Now) |
|---|---|---|
| New walk-in guest | `full_name` **and** `email` both mandatory; a `User` account was always created and a password-reset email always sent, regardless of whether the guest wanted one | Staff now answers **"Gagawa ba ng login account?"** (Yes/No, defaults to **No**). A `User` record (role=`customer`) is **always** created either way — that's the guest record, kept for booking/payment/review history — but email is only required, and a password-reset email only sent, when the answer is **Yes** |
| `users.email` column | `NOT NULL` — every account, including walk-in-only guest records, had to have *some* email value | **Nullable** (new migration) — a guest-record-only account can have no email at all. **Scoped narrowly on purpose**: this only loosens the DB constraint; `AuthController::register()` (public self-registration) still independently enforces `'email' => 'required'` at the validation layer, so online sign-ups are completely unaffected |
| Existing guest (has an account already) | Reused via the "Existing Guest" dropdown | **Unchanged** — this path already worked the way it should |
| "Existing Guest" dropdown display | `{{ $customer->full_name }} — {{ $customer->email }}` — showed a dangling `—` with nothing after it for any guest with no email | Falls back to phone, then a plain "walang email/phone naka-record" label, so guest-record-only accounts don't look broken in the list |

**Implementation:** `Staff\FrontDeskController::storeWalkin()` gained a `create_account` (`yes`/`no`) field; `email` validation changed from `required_if:guest_type,new` to `required_if:create_account,yes`. The `User::create()` call and the `Password::sendResetLink()` call are both gated on `create_account === 'yes'`. `resources/views/staff/walkin.blade.php` gained a second toggle (Yes/No) inside the "New Guest" panel, only shown once "New Guest" is selected; picking "No" removes the `required` attribute from the Email input client-side and swaps the explanatory note.

**Also fixed in this pass:** testing the above surfaced a real, currently-live instance of the already-documented "unprotected Pusher broadcast" issue (see v5.2's Known Issues, and the Pending/Optional row about the other unprotected `event()` call sites) — every `event(new FrontdeskUpdated(...))` / `event(new PropertyAvailabilityChanged(...))` call in `Staff\FrontDeskController` (7 call sites across `storeWalkin`, `recordPayment`, `checkIn`, `checkOut`, `startTask`, `completeTask`) fired *after* its DB write had already committed, with no protection — a `BroadcastException` (confirmed locally: `auth_key should be a valid app key`) would bubble all the way up to a 500 error page, even though the underlying booking/payment/check-in/check-out/task action had already fully succeeded. Fixed by wrapping all 7 in try/catch + `Log::error()`, the same pattern already used for `NotificationHelper::create()`'s broadcast (v5.2). Verified: re-ran the exact walk-in scenario that previously threw — booking now completes with a normal success redirect, and the broadcast failure is logged instead of surfacing to the user. **Follow-up the same day:** the rest of the "~12 unprotected call sites" pending item (`BookingController`, `CalendarController`, `HomeController`, `PortalController`) got the identical fix, plus a previously-uncounted pair in `Admin\PaymentController` — see [Known Issues Fixed](#13-known-issues-fixed) for the full breakdown, including the refund one that could have silently dropped a valid refund record.

### Current test data in the local database (as of 2026-08-13)

v5.1 wiped every pre-fixed-slot booking (see that section). The database was then re-seeded **by hand, in two passes**, purely so the app has something realistic to exercise — this is test data, not a seeder class, and it is **not reproducible via `db:seed`** (both passes were run through throwaway one-off Artisan commands that were deleted immediately afterwards). If the database is ever reset, this data is gone and would need re-creating.

| Pass | What | Why |
|---|---|---|
| 2026-08-08 | **9 bookings** — 7 past (July 2026, all `checked_out`, each with a Payment + completed housekeeping task) + 2 future (Sept 2026, one fully paid, one deposit-only with a remaining balance). Spread across all 7 existing customer accounts. **5 of the 7 past bookings have approved reviews** (some with admin replies) | General testing across the whole app. The 2 past bookings deliberately left **without** a review are for manually testing the "Write a Review" flow — one of them belongs to the developer's own customer account. The deposit-only future booking doubles as a fixture for the "check-in blocked by outstanding balance" path |
| 2026-08-11 | **29 more bookings** spread across **March–June 2026** (all `checked_out`, each with a Payment), with a mild upward trend into the already-seeded July | AI Forecasting reads a trailing **6-month** window. Only ~2 months of real data existed, so the forecast had nothing meaningful to work with. This backfills Mar–Jun so the 6-month window (Mar–Aug) is genuinely populated — see the Forecast row in Pending/Optional |

Totals as of 2026-08-13: **45 bookings, 6 reviews** — 5 seeded above, plus **1 genuinely written through the app** by the developer's own customer account on 2026-08-09, which is what confirmed the customer "Write a Review" flow actually works end-to-end. That leaves exactly 1 past booking still review-less if another manual test of that flow is wanted.

Note that the 2026-08-11 pass is what exposed the Insights `created_at`-vs-`payment_date` revenue bug (see Known Issues Fixed) — every one of those 29 historical payments carries a real backdated `payment_date` but a `created_at` of the day it was seeded, which is exactly the condition that made the bug visible.

---

## What Changed in v5.3 (Read This First)

Version 5.3 is a direct response to a real incident: something that worked in local dev needed code changes to work on Render, and after those changes went out, local dev broke in return (`Cloudinary\Api\Exception\NotFound` on a page that never touched Cloudinary code directly). Root cause turned out to be twofold — a real unguarded-exception bug, and a local `.env` that had drifted into a mix of local and production credentials. v5.3 fixes both, and adds a way to actually run the **same Docker image** used on Render locally, so this class of "only breaks in one environment" bug gets caught before a deploy instead of after.

| Area | Before (v5.2) | v5.3 (Now) |
|---|---|---|
| Cloudinary image URL accessors | `PropertyImage::getUrlAttribute()`, `User::getProfileImageUrlAttribute()`, `Package::getImageUrlAttribute()` called `Storage::disk('public')->url($path)` with no error handling | For the Cloudinary driver, `->url()` makes a **live Admin API call** (`adminApi()->asset($id)`) to fetch the resource before it can build a URL — unlike every other disk, where `->url()` is pure string-building. If that specific asset doesn't exist on Cloudinary, it throws `Cloudinary\Api\Exception\NotFound` **uncaught** — `'throw' => false` in `config/filesystems.php` doesn't protect this, since `Storage::url()` calls the adapter directly and never passes through Flysystem's exception-wrapping layer (confirmed in `vendor/laravel/framework/.../FilesystemAdapter.php`). All 3 accessors now wrap the call in try/catch + `Log::error()`, returning `null` (or `Package`'s existing default image) instead of 500ing the page |
| Local `.env` | Had drifted into a mix of local and production values — `CLOUDINARY_URL` and a duplicate `MAIL_MAILER=brevo`/`MAILER_DSN` block had been appended at the bottom (from copying Render's env vars in for reference at some point), silently overriding the local `smtp`/local-disk settings above them | Cleaned back to local-only values (local disk, SMTP). **Confirmed this has zero effect on Render** — `.env` is gitignored and never deployed; Render reads its own, separately-configured dashboard env vars (`render.yaml`'s `sync: false` entries) |
| Local dev runtime | `php artisan serve` (Windows-native PHP) — a genuinely different runtime (PHP CLI server, no nginx/php-fpm) from what Render actually runs | **Docker Desktop, running the same `Dockerfile`** Render builds from (`docker compose up --watch`) — same PHP 8.2-fpm-alpine + nginx + supervisord stack in both places. `php artisan serve` still works fine and is unaffected; Docker is now available as an option for testing changes against the real runtime before pushing |
| `.dockerignore` | Didn't exist | **Added** — without it, a local `docker build` run from the actual working directory (not a fresh git clone, unlike Render's build) copies the local `vendor/` (installed **with** dev packages), `node_modules/`, and `.env` straight into the image, clobbering the clean `--no-dev` install. This is exactly why local Docker builds failed with `Class "Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider" not found` the first time — the fix also means a local `docker build` now actually reproduces Render's build context instead of silently diverging from it |
| `docker/start.sh` caching | Always ran `config:cache`/`route:cache`/`view:cache` on container start | Now branches on `APP_ENV` — `local` runs `config:clear`/`route:clear`/`view:clear` instead (so edits show up without a container restart); anything else (Render sets `APP_ENV=production`) keeps the original caching behavior unchanged |
| Local Docker + host MySQL | N/A | A local Docker container can't reach `root@localhost` MySQL grants (it connects as a different apparent host). Rather than opening `root` to the network, a scoped `villa_docker`@`%` MySQL user was created with privileges on `villa_elena_db` only — used solely by `docker-compose.yml`, wired via `host.docker.internal` |
| Local Docker file I/O speed | N/A | First working version bind-mounted the whole repo (`.:/var/www/html`) — functional, but multi-second page loads, since every PHP file `vendor/` include crosses the Windows↔WSL2 filesystem boundary on every request. Replaced with **Docker Compose Watch** (`develop.watch`): only `storage/` is bind-mounted (small, needs to persist uploads/sessions/cache); `app/`, `resources/`, `routes/`, `config/`, `database/` are synced in by Compose Watch instead of live-mounted, so requests read from the container's own fast filesystem while edits still show up within a second or two. Brought page loads from multi-second down to ~0.05–0.4s, matching `php artisan serve`. **`bootstrap/cache/` is deliberately not bind-mounted or synced** — mounting the host's copy (built against a full `composer install`, dev packages included) over the container's clean `--no-dev` build reproduces the exact same `PailServiceProvider not found` class of crash the `.dockerignore` fix addressed at build time |
| Local Redis cache + Docker `app` service | Redis (`CACHE_STORE=redis`) already existed as a **v5.2** addition, but only as a standalone service — nothing else in `docker-compose.yml` to integrate with yet, since the Dockerized `app` service didn't exist until v5.3 | Now wired into the same `docker-compose.yml` as the `app` service: `REDIS_HOST=127.0.0.1` (in `.env`) still correctly reaches Redis's published port for host-based `php artisan serve`, but the containerized `app` service overrides `REDIS_HOST` to `redis` (the Compose service name — `127.0.0.1` inside that container means the container itself, not the Redis one) and declares `depends_on: redis`. Production is unaffected — Render still caches via `CACHE_STORE=database` (no free Redis provider wired up there) |

**Not a code-path branch — a config-only difference:** worth calling out explicitly, since it looks superficially similar to the Cloudinary bug: `MAIL_MAILER` differs between local (`smtp`) and Render (`brevo`), but `Mail::extend('brevo', ...)` in `AppServiceProvider::boot()` registers unconditionally in both environments — Laravel's Mail manager just picks whichever transport `MAIL_MAILER` names. No application code (mailables, `Mail::to()->send()` call sites) branches on environment. This is the safe kind of environment difference; the Cloudinary bug above was not (custom code with an actual unguarded failure path), which is the distinction worth remembering before assuming any local/prod config difference is automatically fine.

---

## What Changed in v5.2 (Read This First)

Version 5.2 is the first deployment of the system off the developer's local machine — moving from "runs on XAMPP on one laptop" to "has a real, testable URL anyone with the link can open." No application features changed; this was entirely infrastructure and bug-fixing work needed to make the existing v5.1 app actually run correctly outside local dev. It surfaced several real bugs that had been silently masked by always running against the same, incrementally-hand-edited local database.

**Stack chosen (all free tiers, picked specifically for a testing-phase deploy with $0 cost):**

| Concern | Service | Why |
|---|---|---|
| Hosting / compute | **Render** (free Web Service, Docker runtime) | Free tier, straightforward GitHub-connected auto-deploy |
| Database | **Aiven** (free MySQL "Developer Tier") | Always-free (no trial/expiry), no credit card, real MySQL |
| Property images | **Cloudinary** | Render's disk is ephemeral — local file storage doesn't survive a restart/redeploy |
| Realtime (admin dashboard toasts) | **Pusher** | Already used locally; same free-tier account, same code path |
| Transactional email | **Brevo**, via its HTTPS API (not SMTP) | See the SMTP-block row below — this wasn't optional |
| Scheduled task (`bookings:auto-checkinout`) | Free external cron (e.g. cron-job.org) hitting a token-gated route | Render Cron Jobs are **not** free ($1/mo minimum) |

| Area | Before (local-only) | v5.2 (Now) |
|---|---|---|
| Web server | `php artisan serve` only | `Dockerfile` + `docker/` (nginx + php-fpm + supervisord), listens on Render's injected `$PORT` |
| Database driver | Hardcoded to local MySQL via `.env` | `config/database.php` unchanged in shape, but a new **`aiven`** connection was added (separate `AIVEN_DB_*` env vars) purely for one-off maintenance commands run from a developer machine — Render's free plan has no Shell tab, so there's no other way to run `php artisan migrate:fresh` etc. against production |
| Property image storage | `Storage::disk('public')` → local disk always | `config/filesystems.php`'s `public` disk now switches to the `cloudinary` driver automatically when `CLOUDINARY_URL` is set, local disk otherwise — **zero controller code changes needed** |
| Image URLs in views | ~15 places hardcoded `asset('storage/'.$path)`, assuming local-disk serving | All switched to disk-aware accessors (`$image->url`, `$property->primaryImage->url`, new `User::profile_image_url`) that resolve correctly regardless of which disk is active |
| Outbound email | Gmail SMTP | **Render blocks all outbound SMTP traffic on its free tier** (ports 25/465/587, any host — confirmed this is what broke a previous deploy attempt too). Switched to Brevo's HTTPS API via `symfony/brevo-mailer` + a custom `Mail::extend('brevo', ...)` in `AppServiceProvider` — HTTPS isn't blocked |
| Logging | `LOG_CHANNEL=stack` (file) | `stderr` in production — Render has no persistent disk, so a log file written inside the container is invisible in the dashboard's Logs tab; `stderr` streams straight into it |
| Scheduled task | Manual `php artisan schedule:work` in an open terminal (see the v6.0-planned note this row replaces) | Token-gated `GET /cron/run-schedule/{CRON_SECRET}` route (`routes/web.php`) that runs `schedule:run` — hit every few minutes by a free external cron pinger, since Render's own Cron Jobs feature isn't free |
| Session/cache tables | Never existed — local always used `SESSION_DRIVER=file` / `CACHE_STORE=file` | **New migration** `2026_08_10_000000_create_sessions_and_cache_tables.php` — production uses `database` for both (no persistent disk for file-based drivers on Render) |
| `reviews` table migration | Out of date relative to the actual local schema — `overall_rating`/`comment` columns that the app hadn't used in a long time, missing `title`/`admin_reply_at`/`admin_note` that the app *does* use | `create_reviews_table` migration corrected to match reality (see Known Issues below) |
| Unhandled mail/broadcast failures | Registration, 2FA (send + resend), verification (send + resend), forgot-password, and walk-in guest creation all called mail-sending code with no error handling; `NotificationHelper::create()`'s Pusher broadcast (`event(new NotificationCreated(...))`) was likewise unprotected | All wrapped in try/catch + `Log::error()` — a transport hiccup now degrades gracefully (user continues, just doesn't get that one email/push) instead of 500ing the whole request |

**Not yet done (see [Section 15](#15-deployment) and Pending/Optional below for the running list):** PayMongo webhook not yet registered against the live URL; the other ~12 `event(new BookingUpdated/FrontdeskUpdated/PropertyAvailabilityChanged(...))` call sites (bookings, calendar, front desk) have the same unprotected-broadcast shape as the `NotificationHelper` bug that was fixed, but haven't been patched yet since none have actually failed in testing so far.

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
   - [Prescriptive Analytics (Recommendations)](#618-prescriptive-analytics-recommendations)
   - [AI Chatbot](#619-ai-chatbot)
   - [Seasonal Promotions (v6.0)](#619-seasonal-promotions-v60)
7. [Pricing Model (v4.0)](#7-pricing-model-v40)
8. [Booking Availability & Fixed Slots (v5.1)](#8-booking-availability--fixed-slots-v51)
9. [AI Integration](#9-ai-integration)
10. [Payment Integration](#10-payment-integration)
11. [File Structure](#11-file-structure)
12. [Routes Summary](#12-routes-summary)
13. [Known Issues Fixed](#13-known-issues-fixed)
14. [Build Progress Summary](#14-build-progress-summary)
15. [Deployment (v5.2, local Docker parity in v5.3)](#15-deployment)

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
| AI Provider | Groq API | `openai/gpt-oss-20b` (env-overridable via `GROQ_MODEL`) |
| Payment Gateway | PayMongo | Sandbox / Live |
| Caching | **Redis** (`predis/predis` client) | **NEW v5.2** — local dev only, see below; production still caches via the `database` driver (no free Redis provider wired up on Render yet) |
| Reports Export | **barryvdh/laravel-dompdf** + **maatwebsite/excel** | **NEW v5.2** — PDF/Excel export on the Admin Reports page, see [Reports & Analytics](#66-reports--analytics) |

### Local Development Configuration

| Setting | Value |
|---|---|
| Project Root | `C:\xampp\htdocs\villa-elena\` |
| Web URL | `http://127.0.0.1:8000` (`php artisan serve`) or `http://localhost:8000` (Docker) |
| Database | `villa_elena_db` (credentials in local `.env`, not reproduced here) |
| Session Driver | `file` |
| Cache Driver | `redis` (**v5.2** — was `file`; see Redis Caching below) |
| Queue Driver | `sync` |
| Docker (optional, **NEW v5.3**) | `docker compose up --watch` — runs the same `Dockerfile`/image Render deploys, for testing changes against the real production runtime before pushing. See [What Changed in v5.3](#what-changed-in-v53-read-this-first) |

### Redis Caching (NEW v5.2, local dev only)

Added specifically to make the caching layer real rather than just described — `App\Models\Setting::get()` already called `Cache::remember(..., 3600, ...)` before this (see [Settings](#67-settings)), it just meant nothing (`file` driver) until now. As of v5.2, `CACHE_STORE=redis` / `REDIS_CLIENT=predis` in the local `.env`, backed by a standalone Redis container (not the full app) — no PHP `redis` extension needs to be installed into XAMPP's `php.ini`:

```bash
docker compose up -d redis      # starts just the redis service from docker-compose.yml
```

Also newly cached: `Admin\DashboardController::index()`'s KPI stats (`Cache::remember('admin_dashboard_stats', 60, ...)`) — previously ran 8 fresh COUNT/SUM queries on every admin dashboard load; a 60s TTL was chosen over event-driven invalidation to avoid touching every booking/payment mutation call site for a monitoring-only figure.

**Production is unaffected on purpose** — `.env.example`'s `CACHE_STORE` stays `database` for Render (no free Redis add-on there yet, same reasoning as the rest of the [Deployment](#15-deployment) stack being picked for $0 cost). If a free Redis provider (e.g. Upstash) is added later, only `.env`/`render.yaml` need to change — no application code depends on which cache driver is active.

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
| 09 | `discounts` | **Seasonal promos** — automatic discounts on the villa base rate, matched against the booking's check-in date and slot (v6.0). Originally shaped for promo codes; `code` is now nullable and unused by the automatic flow |
| 10 | `notifications` | In-app notifications for all users (now with a click-through `link`) |
| 11 | `housekeeping_tasks` | Housekeeping job assignments |
| 12 | `packages` | Bundled booking packages |
| 13 | `availability_blocks` | Manual date blocks per property |
| 14 | `staff_logs` | Audit trail of all admin/staff actions |
| 15 | `settings` | Key-value system configuration (now also stores the admin-managed amenities list) |
| 16 | **`trusted_devices`** ← **NEW v5.0** | Devices a customer has verified via 2FA email OTP; lets a device skip OTP on future logins and lets the customer view/revoke them |
| 17 | **`login_activities`** ← **NEW v5.0** | Read-only per-login history (device, IP, timestamp, whether it required OTP) shown on the customer Profile page |

### v7.0 Schema Changes

`2026_09_08_100000_add_slot_hold_to_bookings_table.php` — the database-level guarantee that one slot holds at most one live booking.

```sql
ALTER TABLE bookings
  ADD COLUMN slot_hold VARCHAR(64) NULL AFTER check_out_time;

-- backfilled in PHP, not SQL (see below), then:
ALTER TABLE bookings
  ADD UNIQUE KEY bookings_slot_hold_unique (slot_hold);
```

**Why a nullable column instead of `UNIQUE (property_id, check_in_date, check_in_time)`:** a cancelled booking is still a row, so a composite index over the real columns would keep blocking the slot it had just released. `slot_hold` is `"{property_id}:{check_in_date}:{check_in_time}"` while a booking holds its slot and **NULL** when it doesn't (`cancelled`, `no_show`, soft-deleted) — and MySQL allows repeated NULLs in a unique index. The constraint therefore expresses exactly the intended invariant: *at most one **live** booking per slot*, with cancelled and deleted rows unconstrained.

The column is maintained solely by `Booking::computeSlotHold()` via a `saving` hook and a `deleted` hook. Both hooks are required — `SoftDeletes::runSoftDelete()` updates through the query builder and never fires `saving`, so without the `deleted` hook a soft-deleted booking would hold its slot permanently.

**The backfill runs in PHP, not as a `CONCAT()` UPDATE**, for two reasons: it shares one implementation with `computeSlotHold()` (a SQL copy would drift), and SQLite — which the test suite uses — has no `CONCAT()`.

**The migration will not create the index if a live duplicate already exists, so it handles that instead of failing.** Production has one (`VE-4C7INQOG` / `VE-YHLBMLUU`, both on 2026-09-15 Day, ₱2,000 each). The migration deliberately **cancels and refunds nothing** — real guests' money is involved and choosing between them is an admin decision, not a migration's. It keeps the earlier row's `slot_hold`, nulls the later one so the index can be built, leaves both bookings fully intact and visible, and prints the colliding refs plus a `Log::warning`. Those rows still need resolving by hand.

### v5.7 Schema Changes

`2026_08_16_090000_switch_payment_method_to_qrph.php` — four steps, and **the order is load-bearing**:

```sql
-- 1) Preserve the original method before the value disappears
UPDATE payments SET notes = TRIM(CONCAT(COALESCE(notes,''),
       ' [Originally recorded as GCash before the QR Ph switch.]'))
 WHERE payment_method = 'gcash';   -- same for 'paymaya' / PayMaya

-- 2) WIDEN first — nothing can hold 'qrph' until the ENUM admits it
ALTER TABLE payments
  MODIFY payment_method ENUM('qrph','gcash','paymaya','cash') NOT NULL;

-- 3) Relabel (41 rows: 37 gcash + 4 paymaya)
UPDATE payments SET payment_method = 'qrph'
 WHERE payment_method IN ('gcash','paymaya');

-- 4) Narrow to the final shape
ALTER TABLE payments
  MODIFY payment_method ENUM('qrph','cash') NOT NULL;
```

Skipping step 2 fails with `Data truncated for column 'payment_method'` — MySQL will not store a value the ENUM doesn't list. Note this is the **mirror image** of the v5.5 shrink below, which relabeled toward an *existing* value (`gcash`) and so needed only one ALTER. The notes step is written to be idempotent (it skips rows already tagged), so a partially-applied run can be re-run safely.

`down()` restores the wider ENUM only — which row was `gcash` and which was `paymaya` is no longer recoverable from the column, and is deliberately left readable in `notes` instead.

### v5.5 Schema Changes

```sql
-- payments: only e-wallets + cash are accepted now.
-- The 17 existing card/bank_transfer rows are relabeled FIRST — MySQL
-- rejects the ALTER while rows still use a value being removed.
UPDATE payments SET payment_method = 'gcash'
  WHERE payment_method IN ('card', 'bank_transfer');
ALTER TABLE payments
  MODIFY payment_method ENUM('gcash','paymaya','cash') NOT NULL;

-- payments: 'deposit' and 'partial' meant the same thing (a payment
-- less than the full amount). Consolidated on 'partial'; 5 rows moved.
UPDATE payments SET payment_type = 'partial' WHERE payment_type = 'deposit';
ALTER TABLE payments
  MODIFY payment_type ENUM('full_payment','balance','extra','partial','refund') NOT NULL;

-- bookings: caps how many times a guest may move one booking.
-- Existing rows start at 0 — a fresh allowance, not a retroactive lockout.
ALTER TABLE bookings
  ADD COLUMN reschedule_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER source;
```

**Data-only migration (no schema change):** `2026_08_15_120000_close_stale_housekeeping_tasks` closed the housekeeping backlog — any open task more than 7 days past its `due_date`, or whose booking checked out more than 3 days ago. 13 open → 5. Marked `completed` (the only other enum value) with an explicit note that they were auto-closed rather than genuinely done.

**No schema change, but behaviourally important:** `payments.status` on **refund** rows now means *"has the money actually been sent?"* — `pending` until an admin marks it paid out, `success` after. It does **not** gate whether the refund counts against the booking; that happens the moment the refund is approved. See `Booking::recalculateFinancials()`.

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
- **Export PDF / Export Excel (NEW v5.2)** — `GET /admin/reports/export/{pdf,excel}` (`ReportController::exportPdf()`/`exportExcel()`), reusing the same `buildReportData()` the index view uses (respects whatever period/date-range filter is active). PDF via `barryvdh/laravel-dompdf` rendering `admin/reports/pdf.blade.php` (a plain-table layout — dompdf's CSS support is limited, no flexbox/grid). Excel via `maatwebsite/excel`, `App\Exports\ReportExport` (a 3-sheet `WithMultipleSheets` workbook: Summary KPIs, Revenue by Month, Top Properties).

---

### 6.7 Settings

**Controller:** `app/Http/Controllers/Admin/SettingsController.php`
**Route:** `admin.settings.index` / `admin.settings.update`

**7 Tabs:** Resort Info, Booking Rules, Payments, Notifications, **Amenities** ← NEW v5.0, Social & Links, System

**Setting Model helpers:**
```php
Setting::get('resort_name', 'Villa Elena')  // cached 3600s — real Redis cache locally since v5.2 (was always Cache::remember(), just backed by the `file` driver before)
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

**v5.5 — Reschedule limits (closes a cancellation-policy loophole).** Until v5.5 there was **no limit of any kind** on rescheduling: no cap on how many times, no cutoff on how close to check-in, and no column even tracking it. `isCancellable()` (status is `pending`/`confirmed`) was the only gate.

That made the cancellation policy bypassable. Cancelling 2 hours before check-in yields **0%**; instead the guest reschedules a month out — free, unlimited — and then cancels from there for **100%**, because `calculateRefundPercentage()` reads `checkInDateTime()`, the *new* date. The peak slot stays blocked the whole time.

| Constant | Value | Reasoning |
|---|---|---|
| `Booking::RESCHEDULE_CUTOFF_DAYS` | **7 days** before check-in | Deliberately the same boundary as the 100%-refund tier. Once the 100% tier is gone, rescheduling is gone too — so there is nothing left to gain by rescheduling instead of cancelling |
| `Booking::MAX_RESCHEDULES` | **2** per booking | Backed by the new `bookings.reschedule_count` column. Existing bookings start at 0 |

`Booking::rescheduleBlockReason()` is the single source of truth: it returns the reason a booking can't be moved (or `null` when it can), and **that same string is what the guest is shown** — so the UI can't claim something different from what's enforced. `reschedulesRemaining()` drives the "N left" counters. The guard runs in `update()` as well as `edit()`, so a form left open past the cutoff can't slip through, and the booking detail page swaps the Reschedule button for the blocking reason once it no longer applies.

Verified against live bookings: those 14–27 days out are reschedulable; past-dated ones are refused; the count limit refuses correctly at 2.

**Also corrected:** `portal/booking_form.blade.php` advertised *"Free cancellation 48 hours before check-in"*, which never matched the implemented tiers (7 days = 100%, 3–6 days = 50%, under 3 days = 0%). The terms text now states the real policy and pulls the reschedule numbers from the constants so the two can't drift apart again.

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

**Controller:** `app/Http/Controllers/Staff/FrontDeskController.php`
**Views:** `resources/views/staff/` (frontdesk, walkin, availability, partials/task_deadline)
**Route prefix:** `/staff/` → `staff.*`
**Sidebar:** Frontdesk · **Availability (v5.5)** · Walk-in Booking

**Frontdesk page structure (v5.5):**

| Element | Feature |
|---|---|
| Stat cards | Check-ins today, Check-outs today, Pending bookings, Housekeeping |
| **Villa status strip** | The villa's real status, current guest, checkout time, plus the next Day/Night slots as one-click links into the walk-in form |
| **Cleaning banner** | Only when a task is open — shows the real deadline (next check-in time), colour-coded by urgency, with a one-click **Mark cleaned** |
| Tabs | Check-ins · Check-outs · Current Guests · Pending · Housekeeping (+ an Availability link) |

**v5.5 — what changed and why.** The stat row used to show "Occupied / Available" counted across *all* property rows — 1 villa plus 3 unnamed info-only `type=room` records. It read as "3 units still free" when the only bookable unit was already taken. The "Properties" tab had the same root cause and rendered three blank cards (those room records have `property_name = NULL` since v5.0). Both were removed in favour of the Villa strip and the Availability page. The room records still exist in the database for housekeeping history; they're just no longer surfaced at the frontdesk.

**Availability page (v5.5)** — `GET /staff/availability` → `staff.availability`

A 14-day grid, one row per date, two columns (Day 8AM–5PM / Night 7PM–6AM). Four states: **Available** (shows the package price, click to book), **Booked** (guest name + booking ref), **Blocked** (admin `AvailabilityBlock`, shows the reason), **Passed** (slot's check-in time has elapsed). Prev / Today / Next navigation, clamped so it never shows the past.

This exists because the admin calendar **cannot answer the frontdesk's actual question.** It's built on `check_in_date` → `check_out_date`, so it's slot-blind: a Day booking and a Night booking on the same date look the same, and you must click each event to find out which half of the day is taken. Verified against real data — Aug 29 shows `Day: Sample Guest` and `Night: Reynald` as separate cells, which no date-range view can express.

`buildSlotGrid()` calls **`Booking::hasConflict()` per slot** rather than writing its own overlap query, so what the grid shows and what the booking form enforces can never diverge — including expired unpaid holds freeing their slot automatically. Pricing comes from `getPackagePrice()` for the same reason (verified: Sunday Day = ₱6,000 peak, Sunday Night = ₱4,000 regular).

Clicking a free slot opens `/staff/walkin?date=…&slot=…` with both pre-filled and a confirmation note. Before this, the walk-in form did **no availability check at all** until submit — staff filled in the whole form only to be told the slot was taken.

**Housekeeping (v5.5).** Tasks previously showed a due **date**, which is close to useless here: the gap between checkout and the next check-in is only **2 hours** (5PM→7PM, 6AM→8AM), and with a single villa there's no other unit to fall back on if it isn't ready. Each task now shows its real deadline — the next check-in time — computed in one query for all tasks via `attachCleaningDeadlines()`.

Nothing used to close the loop: check-in creates the task, auto-checkout flips it to `in_progress`, and marking it complete was manual and buried in a tab. **11 of 13 open tasks were overdue** (worst: 48 days) and 6 were stuck in `in_progress`. Fixed by surfacing the most urgent task in a banner at the top of the page with a one-click **Mark cleaned**, and by having checkout's success message name the deadline (`"Villa must be cleaned and ready by Aug 19, 8:00 AM"`) instead of the old `"Housekeeping task activated"`.

**Still open on housekeeping:** `task_type` has three values no code ever creates — `daily_clean`, `maintenance`, `inspection` (all 53 rows are `checkout_clean`) — and staff has no way to create a task manually, so a broken aircon can't be logged. The two are the same gap; see Pending/Optional.

**Walk-in Booking Form** (`/staff/walkin`):
- Select an existing guest, **or** register a new one on-the-spot — **(v5.4)** for a new guest, staff is asked whether to also create a login account (defaults to **No**); either way a guest record (`User`, role=`customer`) is created for booking/payment/review history, but email + the password-reset email are only involved if the answer is Yes. See [What Changed in v5.4](#what-changed-in-v54-read-this-first)
- Live price calculator, flat package rate based on check-in day/slot (Day/Night, see [Section 8](#8-booking-availability--fixed-slots-v51)) — not weekday/weekend per-night
- Record payment immediately upon booking
- Walk-in bookings auto-confirmed (no admin approval needed)
- New guest password (when an account **is** created): random/unguessable (`Str::random(20)`), never shown to anyone — guest sets their own via the password-reset email

**Payment Recording Modal:**
- Available on each booking row in Check-ins, Check-outs, Current Guests tabs
- Fields: amount, method (**Cash / GCash / PayMaya** — v5.5, Card and Bank Transfer removed), type, notes
- Recalculates via `Booking::recalculateFinancials()` (v5.5) rather than its own inline copy of the math

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
- Filter by method, type, date range, search — plus `?status=awaiting_payout` (v5.5)
- Color-coded payment method and type badges
- **Record Payment modal** — type booking ref → AJAX auto-lookup guest info. Method is now Cash / GCash / PayMaya only, and both method and type are validated with real `in:` rules (v5.5 — previously just `required`, so any string reached the DB)
- **Refund modal** — partial or full refund with reason
- Payment detail page with full booking summary

**v5.5 — refunds now track whether the money actually moved.** No refund API is wired up (and cash can't be API-refunded regardless) — an admin sends the money by hand. *(v5.9: a QR Ph refund API does exist and could cover full refunds; it is simply not built yet. Partial and cash refunds stay manual permanently.)* But refund rows used to be written as `status = 'success'` immediately, and the admin notification read *"₱X **refunded**"* in the past tense, while nothing anywhere recorded whether the payout had happened. A guest's refund could be forgotten entirely with no trace in the system.

Refunds now start as `status = 'pending'`, and the page gains:
- a **"NOT SENT"** badge on any refund awaiting payout,
- an *"N refunds (₱X) awaiting payout"* banner with a filter shortcut,
- a **Mark Paid Out** action (`PATCH admin/payments/{payment}/paid-out` → `admin.payments.paidOut`), confirm-guarded and `StaffLog`-recorded.

**Marking a refund paid out changes no amounts.** The refund is deducted from the booking the moment it's *approved* — the resort owes it either way — so `status` tracks only the payout itself. This rule is enforced centrally in `Booking::recalculateFinancials()`: real payments count only when `status='success'`, refunds count regardless of status. `Admin\ReportController`'s net-revenue figure follows the same rule (it previously filtered refunds by `status='success'`, which after this change would have hidden un-paid-out refunds and overstated net revenue).

The notification preset is now `"Refund To Send"` and links directly to the awaiting-payout list rather than the booking.

---

### 6.12 Online Payments — PayMongo

**Service:** `app/Services/PayMongoService.php`
**Controller:** `app/Http/Controllers/PaymentController.php`
**Views:** `resources/views/payment/` (checkout, success)

**Supported Payment Method (v5.7): QR Ph only** — `payment_method_types => ['qrph']`

QR Ph is the national QR standard, so a single activation covers GCash, Maya and participating bank apps. Enabling `gcash`/`paymaya` individually on PayMongo requires a **separate application per wallet**; QR Ph was already activated on this account, which is what made real payments possible. The checkout page still names the wallets next to "QR Ph" — the brand name alone reads as "GCash is gone" to a guest whose GCash app is exactly what they'll scan with.

`payments.payment_method` is `ENUM('qrph','cash')`. History: v5.5 narrowed it from a 5-value set to `gcash/paymaya/cash`; v5.7 replaced the wallets with `qrph` (see [v5.7 Schema Changes](#v57-schema-changes)).

**Guest Payment Flow:**
```
Booking Detail → "💳 Pay Now" button
      ↓
Payment page — choose Deposit (50%, from Setting `deposit_percentage`) or Full Payment
      ↓
Redirect to PayMongo hosted checkout
      ↓
Guest pays via GCash / Maya
      ↓
   ┌── Guest returns to site ──┐   ┌── Guest closes the tab ──┐
   │ GET /pay/{booking}/success│   │ POST /webhooks/paymongo  │
   └───────────┬───────────────┘   └────────────┬─────────────┘
               └──────────┬─────────────────────┘
                          ↓
        PaymentController::recordPaymongoPayment()
        (one shared implementation, idempotent)
                          ↓
Payment recorded, financials recalculated, booking auto-confirmed
Admin notified, Guest notified, confirmation email sent
```

**Two entry points, one implementation (v5.6).** The success callback only fires if the guest comes back to the site. Before v5.6 it was the *only* thing writing `Payment` rows — the webhook handler just called `Log::info()` — so a guest who paid and closed the browser left PayMongo holding the money while the system still showed the booking unpaid. Both paths now share `recordPaymongoPayment()`, guarded for idempotency by `reference_number` (the PayMongo `pay_xxx` ID), so a double delivery records once.

**Webhook signature format.** `Paymongo-Signature` is not a bare hash — it is `t=<timestamp>,te=<test-sig>,li=<live-sig>`, and the signed payload is `"{timestamp}.{rawBody}"`. `verifyWebhook()` hashed only the body and compared it against the whole header string, so every real event was rejected with 401. Fixed in v5.6; both `te` and `li` are accepted so the same code works in test and live mode.

**Setup (works in test mode — no live account needed):** register `POST /webhooks/paymongo` in the PayMongo dashboard and set `PAYMONGO_WEBHOOK_SECRET`. The URL must be publicly reachable — the Render URL or an ngrok tunnel. It cannot fire against `localhost`.

**No refund automation wired up yet (v5.9).** `PayMongoService` has no refund or transfer call, so refunds are approved in-app and the money is sent by hand; see §6.11.

Two API paths exist and were both investigated in v5.9 — **read that section before touching refund code**:

- `https://refunds-api.paymongo.com/v1/refunds` — real, and accepts QR Ph (v5.6 wrongly concluded otherwise by testing the wrong host), but **full amounts only**, which the 50% cancellation tier can never satisfy. **Rejected.**
- `POST https://api.paymongo.com/v2/batch_transfers` (Send Money / Disbursements) — arbitrary amounts, testable via simulator accounts, status callbacks. **This is the chosen direction**, blocked only on obtaining a test-mode wallet, and requiring guest bank/e-wallet details the QR Ph payment does not carry.

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

**`ReviewModerationService`** (`app/Services/ReviewModerationService.php`) — reuses the existing `GeminiService` (Groq, model from `GROQ_MODEL`) rather than adding a new AI dependency. Config: `config/moderation.php` (blocked-word list, PH mobile/email/URL regex patterns).

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
| `NotificationHelper::refundIssued($booking, $amount, $reason)` | Refund approved (any path) | `admin.payments.index?status=awaiting_payout` |

**Guest-facing presets (NEW v5.6)** — the refund lifecycle is the one flow where wording had to stay consistent across four different entry points, so it lives here instead of in inline `Notification::create()` blocks:

| Method | Triggered When | Links To (guest-facing) |
|---|---|---|
| `NotificationHelper::bookingCancelledForGuest($booking, $refundAmount, $refundPct)` | Guest cancels their own booking | `customer.bookings.show` |
| `NotificationHelper::refundApprovedForGuest($booking, $amount, $reason)` | Refund approved but **not yet sent** | `customer.bookings.show` |
| `NotificationHelper::refundPaidOut($payment)` | Admin marks a refund paid out — notifies **guest *and* admins** | `customer.bookings.show` / `admin.bookings.show` |

The wording distinction is deliberate and load-bearing: **"Approved" ≠ "Sent".** A refund row is created as `status='pending'` and no money moves until an admin manually sends it and marks it paid out (see §6.11). Saying "processed" at approval time — which is what the code did before v5.6 — sends guests looking in their GCash for money that hasn't left yet.

Other guest-facing notifications (booking confirmations, payment receipts, review approve/reject/reply, etc.) are still created directly via `Notification::create([...])` at ~11 call sites across `PaymentController`, `Admin\{Booking,Payment,Review}Controller`, `Staff\FrontDeskController`, and `AutoCheckInOutBookings` — every one includes a `link` pointing to `customer.bookings.show` or `customer.reviews.index` as appropriate.

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

LLM-written outlook over 6 months of **live** booking and revenue data (the hardcoded `$dummyBookings`/`$dummyRevenue` arrays were replaced on 2026-08-11 — see the Pending/Optional table).

> **(v6.3) This page is the outlook only.** The prompt used to ask for *"2 actionable recommendations"*; that request was removed and replaced with an explicit instruction **not** to recommend actions, discounts, price changes, or campaigns. Recommendations are computed — not written by the model — over at [§6.18](#618-prescriptive-analytics-recommendations). If this prompt ever asks for advice again, the two pages will contradict each other and both become untrustworthy.

**Outputs:**
- Expected bookings for each of the next 3 months
- Expected revenue (PHP) for each of the next 3 months
- 3 key factors influencing performance
- Bar chart: 6 months historical + forecast
- Footer link through to Recommendations (what to *do* about the outlook)

---

### 6.18 Prescriptive Analytics (Recommendations)

**Routes:** `GET /admin/prescriptive` → `admin.prescriptive.index`, plus `regenerate` / `{rec}/apply` / `{rec}/dismiss` (all POST)
**Generated by:** `prescriptive:generate`, scheduled `dailyAt('01:30')` in `routes/console.php`
**Admin only** — same reasoning as Promotions (`routes/admin.php`): applying a card creates a real discount, and the page exposes internal business data (weak dates, projected revenue, the elasticity assumption).

The layer that answers **"what should I do?"** — as distinct from Insights (*why*) and Forecast (*what will happen*). Full rationale in **What Changed in v6.3**.

**Pipeline:**

```
DemandModel          p(date, slot) from smoothed historical fill rates
                     price via Property::quoteFor()  ← never reimplemented
   ↓
Advisors             IdleDatePromoAdvisor     → best discount by expected revenue
                     PeakRateAdvisor          → best increase (inelastic peak dates)
                     MaintenanceWindowAdvisor → cheapest window to close
   ↓
PrescriptiveEngine   dedupe by fingerprint, refresh open cards, expire stale ones
   ↓
recommendations      one table. Nothing else is written. Guests see nothing.
   ↓
Apply (a human)      → Discount / AvailabilityBlock + StaffLog + applied_record_id
```

**Each card carries:** title, plain-language summary, a **"Why this?"** evidence list built from the actual numbers (including the elasticity assumption, named out loud), projected peso impact, confidence (from sample size — the *weakest* day-of-week in the window, not the total), and the target window.

**Confirmation modals state the exact effect**, not "Are you sure?" — including an explicit warning that an applied promo is visible to guests immediately (landing banner + booking price), while a block simply removes dates from the calendar.

**Key files:** `app/Services/Prescriptive/{DemandModel,PrescriptiveEngine,HolidayCalendar,BriefingWriter,OutcomeTracker}.php`, `app/Services/Prescriptive/Advisors/`, `app/Models/Recommendation.php`, `app/Http/Controllers/Admin/PrescriptiveController.php`, `resources/views/admin/prescriptive/{index,simulate,accuracy}.blade.php`, `config/prescriptive.php`.

**Also surfaces in two other places:** the Dashboard's *Recommended Actions* widget (top 3, queried **outside** the 60-second KPI cache so an applied card disappears immediately), and the **What-If Simulator** at `/admin/prescriptive/simulate` — the same `DemandModel`, but with the admin asking the question.

**Outcome tracking (v6.5):** once a window closes, `OutcomeTracker` fills `actual_revenue` and `realized_impact` against the `baseline_projection` frozen when the forecast was made, and `/admin/prescriptive/accuracy` reports it. Read that page's two sections as it labels them: **dismissed/expired** recommendations test the model cleanly, **applied** ones are confounded by the intervention. Maintenance windows are never scored — see v6.5 for why.

---

### 6.19 AI Chatbot

**Route:** `POST /chatbot` → `chatbot.reply`
**Provider:** Groq API (model from `GROQ_MODEL`, default `openai/gpt-oss-20b`)

Floating chat widget on public portal. Answers questions about Villa Elena, pricing, and amenities using real database data injected into the system prompt.

> ✅ **Confirmed up to date (re-checked v5.0):** `Portal\ChatbotController`'s prompt is explicitly single-villa-aware ("SINGLE-VILLA private resort... only ONE bookable villa"), fetches the real master Villa record (`Property::where('type','villa')->first()`), and uses the real `getPackagePrice()`/`hasConflict()` results — no stale multi-villa references found.

> ✅ **Promo-aware (v6.0).** Prices through `quoteFor()` on the availability/price path, and a `CURRENT PROMOS` block — built from the same `Discount::publicActive()` that feeds the landing-page banner — is injected into **every** prompt, not just when the intent extractor guesses "promo". Guests ask in too many ways ("discount ba meron?", "mura ba sa September?") to rely on intent classification for this; a few lines of context are cheaper than a missed promo.
>
> Three things that must not be undone:
> - **The "no promos" branch is explicit.** When `publicActive()` is empty the prompt states *"There are NO promos or discounts running right now… do NOT invent one."* An empty section invites the model to hallucinate a discount, which on a pricing question is a promise the resort then has to honour.
> - **Never mention a promo code.** The prompt bans it outright — there are no codes in this system, and a chatbot asking for one sends the guest hunting for something that doesn't exist.
> - **No date means no discounted figure.** On a `get_price` intent with no check-in date, the bot gives the list price and asks for a date rather than quoting a promo price it can't yet verify — whether a promo applies depends entirely on the check-in date.
>
> Verified with live Groq calls: promo question → named the promo and its window; Sept 10 check-in → ₱4.00 → ₱3.20 with the promo named on the card; Aug 28 check-in → no discount, `promo: null`; "what's your promo code?" → correctly answered that none is needed; promo deactivated → *"wala kaming mga promos."*

**Bug fixed while here:** the chatbot's property card read `p.nights` / `p.total` / `p.per_night`, fields the controller stopped sending when the system moved to the fixed-slot package model — so the card had been rendering **"₱NaN/night"**. It now reads `p.price` (the amount actually charged) with `p.base_price` struck through beside it when a promo applies, plus the slot label.

---

### 6.19 Seasonal Promotions (v6.0)

**Routes:** `/admin/promotions` (admin-only — see the [route list](#admin-routes-admin))
**Controller:** `app/Http/Controllers/Admin/PromotionController.php`
**Model:** `app/Models/Discount.php`
**Views:** `resources/views/admin/promotions/{index,form}.blade.php`

Automatic seasonal discounts on the villa base rate. Full rationale and the rules that must not be undone are in the [v6.0 notes](#what-changed-in-v60-read-this-first); this is the mechanical reference.

**Schema additions** (`2026_08_25_100000_add_seasonal_fields_to_discounts_table.php`):

| Column | Purpose |
|---|---|
| `code` | Made **nullable** — the automatic flow never uses it |
| `description` | One-line subtext for the landing-page card and the notification body |
| `start_date` | Window start; `NULL` = starts immediately (`expiry_date` is the end, **inclusive**) |
| `applies_to` | `all` / `day` / `night` — lets a promo target a single slot |
| `is_public` | Whether it appears on the landing page. A non-public promo still applies, it just isn't advertised |
| `notified_at` | Set once when announced; stops edits from re-blasting the bell |

Plus `bookings.discount_id` (`2026_08_25_100001`, `nullOnDelete`) for attribution — `discount_amount` remains the authority on the money.

**Key model methods:**

- `Discount::bestFor(float $base, Carbon $checkIn, ?string $slot): ?Discount` — the largest-peso-discount winner among overlapping promos
- `Discount::isValidOn(Carbon $checkIn, ?string $slot)` — window + slot + active + limit
- `Discount::publicActive()` — what the landing page advertises. Bounded only by `expiry_date`, so **upcoming** promos are included; ordered running-first, then soonest-starting. Do not narrow this to match `isValidOn()`
- `Discount::isUpcoming()` — drives the *"For stays …"* wording on the banner instead of *"Until …"*
- `Discount::calculateDiscount(float $amount)` — clamps percentages at 100 and never exceeds the amount, so no booking can go negative
- `$promo->value_label` / `->state` / `->state_badge` / `->window_label` / `->slot_label` — display accessors; render enums through these rather than rebuilding strings, same rule as `Payment::$method_label`

**Admin actions:** create, edit, toggle active, announce (one-time in-app blast to active customers), delete. Every action writes a `staff_logs` entry.

---

## 7. Pricing Model (v4.0)

Villa Elena is rented as a **flat-rate package** — one of two fixed slots, Day (9 hrs) or Night (11 hrs), see [Section 8](#8-booking-availability--fixed-slots-v51) — not a per-night hotel stay, and the price does **not** vary by number of guests (private/exclusive resort, not per-head pricing). The rate depends only on **when the guest checks in**.

**Implementation:** `Property::getPackagePrice(Carbon $checkin): float` in `app/Models/Property.php` — but **do not call it directly from booking code.** Since v6.0 the entry point is `Property::quoteFor(Carbon $checkin, ?string $slot)`, which wraps it and applies any active seasonal promo (see [v6.0 notes](#what-changed-in-v60-read-this-first)). It returns `['base', 'discount', 'total', 'promo']`. `getPackagePrice()` remains the source of the *base* rate and is still called directly where only a list price is wanted.

| Segment | Days / Times | Rate |
|---|---|---|
| Regular | Monday – Thursday (any time) | `base_price` → **₱4,000** |
| Peak | Friday, Saturday (any time), and Sunday **before 6:00 PM** | `weekend_price` → **₱6,000** |
| Regular (again) | Sunday **6:00 PM onwards** | `base_price` → **₱4,000** |

**Priority order:**
1. An active `pricing_rules` entry covering the check-in date (e.g. holiday override) — takes precedence over the standard segments above.
2. Otherwise, the day-of-week / time-of-day segment table above.
3. **Then** (v6.0) any matching seasonal promo from `discounts` is subtracted from whatever rate steps 1–2 produced. Promos are a discount *on* the rate, never a replacement for it — that's the difference between a `pricing_rule` and a promo, and why both can be in play on the same date.

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
- **`hasConflict()` is never called on its own by a path that then writes (v7.0).** It is a SELECT; calling it and then creating leaves a window where two concurrent requests both see the slot free and both take it — which is exactly what happened in production (`VE-4C7INQOG` / `VE-YHLBMLUU`, same Day slot, identical `created_at`, both paid). **`Booking::reserveSlot($propertyId, $checkIn, $checkOut, $callback, $excludeBookingId)` is the single source of truth for creating or moving a booking**, the way `slotDateTimes()` is for time and `quoteFor()` is for price. It does the check and the write in one transaction under `lockForUpdate()` on the **`properties`** row — a row that always exists, so serialization is deterministic rather than relying on InnoDB gap-lock behaviour over a range that may match nothing. It returns the callback's value, or `null` when the slot is gone. Read-only uses of `hasConflict()` (availability grids, the chatbot, form pre-checks) are fine and unchanged.
- **`bookings.slot_hold` + its UNIQUE index is the schema-level backstop (v7.0)** for any path that forgets. Value is `"{property_id}:{check_in_date}:{check_in_time}"` while the booking holds its slot, **NULL** when it doesn't (`cancelled`, `no_show`, soft-deleted) — MySQL allows repeated NULLs in a unique index, so a cancelled booking's slot frees up while two live bookings on one slot stay impossible to store. Maintained solely by `Booking::computeSlotHold()` through `saving`/`deleted` hooks; not fillable, never written by a controller. **Its exclusion list must stay identical to `hasConflict()`'s** — if they drift, the index will reject a booking the availability calendar is showing as open.
- **Expired unpaid holds are released inside `reserveSlot()`'s lock** (`Booking::releaseAsExpiredHold()`, shared with the stale sweeper). Necessary because `hasConflict()` ignores a `pending` booking past `booking_hold_minutes` while that row still carries a `slot_hold` — without the release, the index would refuse an INSERT for a slot the grid calls free. It also means slot correctness no longer depends on the external cron pinger running.
- **No separate cleaning buffer is enforced.** The gap built into the two fixed slots themselves (5:00 PM checkout → 7:00 PM next check-in, or 6:00 AM checkout → 8:00 AM next check-in — both exactly 2 hours) *is* the cleaning buffer. `hasConflict()` no longer takes a `$bufferHours` parameter — it does a plain datetime overlap check.
- **No 12–24 hour cap exists anymore** — moot, since duration is fixed per slot and there's no time input to misuse. **Admin/staff bookings use the same two fixed slots as customers** (no free-choice discretion for fresh bookings anymore).
- **Exception — "Extend Stay":** the one place free-choice time still exists is `Admin\BookingController::extendStay()`, which pushes out the check-out of an *already checked-in* guest's existing stay (not a new booking, so it's deliberately exempt from the fixed-slot policy). Its `hasConflict()` call was updated only to drop the removed `$bufferHours` argument — behavior otherwise unchanged.

**Frontend note:** every booking form (`portal/property.blade.php`, `portal/booking_form.blade.php`, `customer/reschedule_form.blade.php`, `admin/bookings/create.blade.php`, `staff/walkin.blade.php`) presents the slot choice as two radio cards ("Day 8AM–5PM" / "Night 7PM–6AM") instead of a time picker; all client-side price-preview JS was rewritten to compute off the selected slot rather than a raw time diff.

**Availability display (v6.8):** the public property page's calendar renders the two slots **per day cell** as independent pills rather than one bar per booking, because a date is rarely wholly free or wholly taken — `night` booked leaves `day` open. The per-date map comes from `PortalController::buildSlotAvailability()`, which mirrors `hasConflict()` exactly (including its abandoned-pending-hold exemption) so the calendar and the booking form can never disagree. Clicking a pill fills the booking form; the two stay in sync both ways. See [What Changed in v6.8](#what-changed-in-v68-read-this-first).

**Auto check-in/out compatibility:** `bookings:auto-checkinout` (`AutoCheckInOutBookings`) needed **zero changes** for this — it already worked purely off the stored `check_in_time`/`check_out_time` values rather than assuming a particular time, so it auto-checks-in/out fixed-slot bookings (including the Night slot's overnight check-out crossing midnight) exactly as it did free-time ones. Verified with a live test run on 2026-08-08.

**2026-08-08 data reset:** all 31 pre-v5.1 bookings (made under the old free-time system) were hard-deleted along with their cascaded payments (24) and reviews (7), to avoid any confusion testing against stale free-time data. See [Known Issues Fixed](#13-known-issues-fixed) for the full cleanup breakdown.

---

## 9. AI Integration

**Provider:** Groq API (switched from Gemini due to quota exhaustion)
**Model:** `openai/gpt-oss-20b` — **not hardcoded**, read from `GROQ_MODEL` (see v5.8 below)

```env
GROQ_API_KEY=gsk_xxxxxxxxxxxxxxxxxxxx
GROQ_MODEL=openai/gpt-oss-20b
# GROQ_REASONING_EFFORT=      # optional override; auto-derived per model when unset
```

```php
// config/services.php
'groq' => [
    'key'              => env('GROQ_API_KEY'),
    'model'            => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
    'reasoning_effort' => env('GROQ_REASONING_EFFORT'),   // auto-derived when unset
],
```

| Provider | Status | Reason |
|---|---|---|
| Gemini 2.0 Flash | ❌ | Free quota exhausted |
| Gemini 2.0 Flash-Lite | ❌ | Same project quota exhausted |
| Groq — `llama-3.1-8b-instant` | ❌ | **Decommissioned by Groq** — returns `400 model_not_found`, broke chatbot/insights/forecast/review-moderation (fixed v5.8) |
| **Groq — `openai/gpt-oss-20b`** | ✅ | Free tier, fast, current default. Reasoning model — see v5.8 |
| Groq — `openai/gpt-oss-120b` | ✅ | Same free tier, stronger reasoning, slower. Drop-in via `GROQ_MODEL` if forecast quality matters more than latency |
| Groq — `qwen/qwen3.6-27b` | ✅ | Works. Needs `reasoning_effort: none` — at `default` it emits its scratchpad as an inline `<think>` block in `content` (the `gpt-oss` models keep it in a separate `reasoning` field). Both handled automatically; `stripReasoning()` remains the backstop |

---

## 10. Payment Integration

**Provider:** PayMongo (Philippine payment gateway)
**Mode:** Sandbox (test mode)
**Dashboard:** https://dashboard.paymongo.com

**Key Points:**
- Webhook not required for sandbox/demo — success callback handles payment recording
- For production, register webhook URL at PayMongo dashboard
- Webhook endpoint: `POST /webhooks/paymongo` (CSRF exempt — via `validateCsrfTokens(except: [...])` in `bootstrap/app.php`; this was **documented but never actually implemented** until v5.7, see Known Issues)
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
│   │   │   ├── ForecastController.php
│   │   │   └── PrescriptiveController.php
│   │   ├── Customer/
│   │   │   ├── HomeController.php
│   │   │   └── ReviewController.php
│   │   ├── Portal/
│   │   │   ├── PortalController.php      ← UPDATED v4.0 — single-villa listing, time, buffer, package price
│   │   │   └── ChatbotController.php
│   │   ├── Staff/
│   │   │   └── FrontDeskController.php   ⚠ pending v4.0 review (see 6.10)
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
│   ├── prescriptive/  index.blade.php, simulate.blade.php, accuracy.blade.php
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

### v5.7 File Changes

**QR Ph migration**

| File | Change |
|---|---|
| `database/migrations/2026_08_16_090000_switch_payment_method_to_qrph.php` | **New.** Preserves the original method in `notes`, then widens → relabels 41 rows → narrows the ENUM to `('qrph','cash')` |
| `app/Http/Services/PayMongoService.php` | **Deleted.** Unreferenced duplicate still requesting `grab_pay`; `CLAUDE.md` had warned about editing the wrong copy |
| `app/Services/PayMongoService.php` | `payment_method_types => ['qrph']`; `verifyWebhook()` rewritten for the real `t=/te=/li=` header format |
| `app/Http/Controllers/PaymentController.php` | Shared `recordPaymongoPayment()`; authoritative paid-check in `success()`; `$known` set → `['qrph','cash']`; `paymentConfirmed` passed to the view; notification moved after the recompute; enriched signature-rejection log |
| `app/Models/Payment.php` | `methodLabelFor()` / `typeLabelFor()` statics plus `method_label` / `type_label` accessors |
| `resources/views/payment/{checkout,success}.blade.php` | QR explainer on checkout; honest waiting state on success |
| `app/Http/Controllers/{Admin/PaymentController,Admin/BookingController,Staff/FrontDeskController}.php` | Validation → `in:qrph,cash` |

**Webhook reachability**

| File | Change |
|---|---|
| `bootstrap/app.php` | `validateCsrfTokens(except: ['webhooks/paymongo'])` — the route was in the `web` group and returned **419** to every real delivery, despite the docs claiming it was exempt |
| `docker-compose.yml` | Single-file Compose Watch sync for `./bootstrap/app.php`; `bootstrap/` was never watched, so middleware changes could not reach the container. Deliberately **not** the whole directory — `bootstrap/cache/` must never be synced |

**Paid-booking auto-cancellation**

| File | Change |
|---|---|
| `app/Models/Booking.php` | New `confirmOnFirstPayment()` — one definition of "the first successful payment confirms the booking" |
| `app/Console/Commands/AutoCheckInOutBookings.php` | Stale-pending sweeper now skips any booking with `amount_paid > 0` |
| `app/Http/Controllers/{Admin/PaymentController,Admin/BookingController,Staff/FrontDeskController}.php` | All three manual record-payment paths call `confirmOnFirstPayment()` |

**Payment display and notifications**

| File | Change |
|---|---|
| `app/Helpers/NotificationHelper.php` | Uses `Payment::methodLabelFor()` instead of its own `ucfirst()` (source of *"via Qrph"*) |
| `app/Http/Controllers/Customer/PaymentController.php` | `id` tiebreaker on the `payment_date` sort |
| `app/Http/Controllers/Admin/PaymentController.php` | Same tiebreaker; `paymentRecorded()` moved after the recompute |
| `resources/views/{customer/payments,customer/booking_detail,admin/payments/index,admin/payments/show,admin/bookings/show}.blade.php` | Raw enum renders replaced with `type_label` / `method_label`; booking-scoped histories sorted newest-first |

### v5.5 New Files

| File | Purpose |
|---|---|
| `resources/views/staff/availability.blade.php` | 14-day Day/Night slot grid — the frontdesk's availability view |
| `resources/views/staff/partials/task_deadline.blade.php` | Shared "Ready by *next check-in*" line for housekeeping task rows |
| `database/migrations/2026_08_14_090000_shrink_payment_method_enum.php` | Relabels card/bank_transfer → gcash, narrows ENUM to e-wallets + cash |
| `database/migrations/2026_08_14_090001_shrink_payment_type_enum.php` | Relabels deposit → partial, drops `deposit` from the ENUM |
| `database/migrations/2026_08_14_100000_add_reschedule_count_to_bookings_table.php` | Adds `bookings.reschedule_count` |
| `database/migrations/2026_08_15_120000_close_stale_housekeeping_tasks.php` | Data-only — closes the overdue housekeeping backlog (13 → 5) |

**Key methods added (existing files):** `Booking::recalculateFinancials()` (the single money-math implementation, replacing 7 inline copies), `Booking::isReschedulable()` / `rescheduleBlockReason()` / `reschedulesRemaining()` + the `MAX_RESCHEDULES` / `RESCHEDULE_CUTOFF_DAYS` constants, `Payment::isRefund()` / `isPaidOut()` / `isAwaitingPayout()` / `scopeAwaitingPayout()` / `receivedBy()`, `Admin\PaymentController::markRefundPaidOut()`, `Staff\FrontDeskController::availability()` / `buildSlotGrid()` / `attachCleaningDeadlines()`.

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
GET  /privacy-policy                portal.privacy            ← NEW v6.1
GET  /terms-of-service              portal.terms              ← NEW v6.1
POST /contact                       portal.contact.send       ← NEW v5.0 (throttle:5,60)
GET  /book/{property}               portal.book
POST /book/{property}               portal.book.submit
GET  /booking/confirmed/{booking}   portal.confirmation
POST /chatbot                       chatbot.reply
GET  /pay/{booking}                 payment.page
POST /pay/{booking}/checkout        payment.checkout
GET  /pay/{booking}/success         payment.success
GET  /pay/{booking}/cancel          payment.cancel
POST /webhooks/paymongo             payment.webhook           (no CSRF)
POST /webhooks/paymongo/transfer    payment.webhook.transfer  (no CSRF)  ← NEW v5.9
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
PATCH  /admin/payments/{payment}/paid-out     admin.payments.paidOut             ← now requires transfer_reference (v5.9)
PUT    /admin/payments/{payment}/destination  admin.payments.destination         ← NEW v5.9      ← NEW v5.5
POST   /admin/payments/{payment}/send         admin.payments.send                ← NEW v5.9 Phase 4
GET    /admin/promotions                      admin.promotions.index             ← NEW v6.0
GET    /admin/promotions/create                admin.promotions.create            ← NEW v6.0
POST   /admin/promotions                       admin.promotions.store             ← NEW v6.0
GET    /admin/promotions/{promotion}/edit      admin.promotions.edit              ← NEW v6.0
PUT    /admin/promotions/{promotion}           admin.promotions.update            ← NEW v6.0
PATCH  /admin/promotions/{promotion}/toggle    admin.promotions.toggle            ← NEW v6.0
POST   /admin/promotions/{promotion}/notify    admin.promotions.notify            ← NEW v6.0
DELETE /admin/promotions/{promotion}           admin.promotions.destroy           ← NEW v6.0
GET    /admin/reviews                         admin.reviews.index
PATCH  /admin/reviews/{review}/approve        admin.reviews.approve
PATCH  /admin/reviews/{review}/reject         admin.reviews.reject
POST   /admin/reviews/{review}/reply          admin.reviews.reply
DELETE /admin/reviews/{review}                admin.reviews.destroy
GET    /admin/reports                         admin.reports.index
GET    /admin/insights                        admin.insights
GET    /admin/forecast                        admin.forecast
GET    /admin/prescriptive                    admin.prescriptive.index
GET    /admin/prescriptive/simulate           admin.prescriptive.simulate
GET    /admin/prescriptive/accuracy           admin.prescriptive.accuracy
POST   /admin/prescriptive/regenerate         admin.prescriptive.regenerate
POST   /admin/prescriptive/{rec}/apply        admin.prescriptive.apply
POST   /admin/prescriptive/{rec}/dismiss      admin.prescriptive.dismiss
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
GET    /staff/availability                    staff.availability          ← NEW v5.5 (?start=Y-m-d)
PATCH  /staff/checkin/{booking}               staff.checkin
PATCH  /staff/checkout/{booking}              staff.checkout
GET    /staff/walkin                          staff.walkin
GET    /staff/walkin/quote                    staff.walkin.quote  (AJAX)         ← NEW v6.0
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
GET    /my/refunds/{payment}/destination      customer.refunds.destination       ← NEW v5.9
PUT    /my/refunds/{payment}/destination      customer.refunds.destination.update ← NEW v5.9
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
| **(v7.0)** Two accounts booked and paid for the exact same date + slot (`VE-4C7INQOG` / `VE-YHLBMLUU`, 2026-09-15 Day, identical `created_at`) | `Booking::hasConflict()` was correct but is a **SELECT** — every caller checked, then created, with no lock and no constraint across the gap, so two simultaneous submits both saw a free slot before either row existed | `Booking::reserveSlot()` does the check and the write in one transaction under `lockForUpdate()` on the `properties` row; all six booking-creating/moving paths go through it. Reproduced and verified fixed with six concurrent OS processes |
| **(v7.0)** Admin calendar drag-and-drop could drop one booking directly on top of another | `Admin\CalendarController::moveBooking()` had **no availability check at all** — found while auditing for the race above | Routed through `reserveSlot()`; returns `409` with a reason, and the `eventDrop` handler now shows that message instead of a generic one |
| **(v7.0)** A guest whose hold expired could pay through their still-open PayMongo link and be confirmed onto a slot another guest had since taken | `recordPaymongoPayment()` → `confirmOnFirstPayment()` promoted `pending` → `confirmed` without re-checking availability. Needed no concurrency at all | `confirmOnFirstPayment()` re-checks first; the payment is still recorded (never discard money), the booking stays `pending` — which the stale sweeper skips, since `amount_paid > 0` — and admins are notified to decide |
| **(v7.0)** Reviving a `cancelled`/`no_show` booking silently re-acquired a slot someone else now held | `Admin\BookingController::updateStatus()` allowed any status transition with no availability check | Guard added for the revive transition; without it the new unique index would surface as a raw duplicate-key 500 instead of a message |
| **(v7.0)** The walk-in path's own transaction was the only lock in the system | It locked `bookings` rows and relied on InnoDB gap locks to block a row that doesn't exist yet — and a lock only works if *every* writer takes it, so the portal and admin paths walked straight past it | Its private version was removed in favour of the shared `reserveSlot()`, which locks the always-present `properties` row |
| **(v6.9)** PayMongo disabled the production webhook; nothing in the app log | Registered at the bare origin `https://villa-elena.onrender.com` instead of `…/webhooks/paymongo` — `POST /` is 405 from the router, before any controller | URL corrected via `paymongo:webhooks --webhook=… --url=…`; the command now flags any URL whose path isn't `/webhooks/paymongo` |
| **(v6.9)** Webhook answered `401` on every delivery when the secret didn't match the registered mode | One `PAYMONGO_WEBHOOK_SECRET` was tried against both the `te=` and `li=` slots, but those carry *different* secrets | `verifyWebhook()` maps each slot to its own secret (`_TEST` / `_LIVE`, generic as fallback), so test and live webhooks can share one URL |
| **(v6.9)** Any exception in webhook processing returned `500`, which PayMongo counts toward disabling | `webhook()` did the work inline, so a DB error, an unmapped enum or a malformed body escaped as a 500 | `handleWebhookEvent()` returns arrays and never calls `response()`; the shell catches `\Throwable` and always answers 200, notifying admins instead |
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
| **(v6.8)** Booking detail page scrolled sideways on phones, with payment amounts cut off | `.pay-table` used `display:block; overflow-x:auto; white-space:nowrap` under 600px — headers ran together and the table still contributed a 275px min-content width to the page | Table becomes labelled blocks on phones (`thead` hidden, `td::before { content: attr(data-label) }`) |
| **(v6.8)** Bookings list: filter chips fell into three ragged rows on phones; each booking card was 307px tall | `.filter-bar` relied on `flex-wrap`, and the mobile rule stacked the whole card with `flex-direction: column` | Filters become a 3-column grid under 560px (2 under 340px); card keeps thumb + details side by side and drops only the badge/amount below — 307px → 158px |
| **(v6.8)** Bookings list: the calendar icon sat alone on its own line above the dates | `.bc-dates` was a flex container whose single text node could not wrap around the icon | `.bc-dates` returned to inline flow with a margin on the icon |
| **(v6.8)** My Payments hid the Amount column behind a sideways swipe on phones | Six-column table with no media queries; the `overflow-x:auto` wrapper contained the overflow so nothing looked broken, but the rightmost column (Amount) was off-screen | Under 600px each payment becomes a card — reference + amount, property, then date/method/type/status — via `order` on the existing cells |
| **(v6.8)** Guests could not tell how to upload a profile photo | The field was a bare `<input type="file">` with no label, no preview, and no button — the only button on the card is "Save Changes" at the bottom of the form | Named control + "Change photo" label-button, live preview of the chosen file, and an "Upload photo" submit that appears next to the avatar (same form, so `ProfileController::update()` is unchanged) |
| **(v6.8)** Profile tabs became three unlabelled icons on phones; 2FA and device switches fell below their own descriptions | `@media` hid `.profile-tab-btn span`; `.toggle-row`/`.device-row` used `flex-wrap: wrap` | Tab labels shrink instead of vanishing (the icon drops below 400px); toggle/device rows became a `1fr auto` grid |
| **(v6.8)** A URL pasted into a review scrolled the whole page sideways (89px at 320px) | Long unbroken tokens can't wrap, so the text ran past the card and widened the document; no breakpoint can fix that | `overflow-wrap: anywhere` on every free-text field of the review card (content, title, admin reply/note, property name) |
| **(v6.8)** Long references in a notification were silently truncated with no ellipsis or scrollbar | `.notif-body` is `flex: 1` with default `min-width: auto`, so it grew 77px past its card, and `.notif-card { overflow: hidden }` clipped the tail — the document-level overflow check still read 0 | `min-width: 0` + `overflow-wrap: anywhere`; phone padding tightened to give the message 193px instead of 171px at 320px |
| **(v6.8)** Every notification showed the same grey "info" icon | The icon map keyed on `notifications.type`, whose only value in practice is `in_app`, so no branch ever matched and the fallback always won | Icon and colour derived from keywords in the title (payment / refund / cancelled / check-in / check-out / booking / rescheduled), fallback kept |
| **(v6.8)** Reschedule form's slot choice was a ~16px radio, and looked nothing like the same choice on the booking page | The form used bare Bootstrap `.form-check` radios instead of the `.slot-option` cards used in `portal/property.blade.php` | Same card pattern, built from `Booking::SLOTS`; tap target 16px → 312×67px, and Night now states "next day" |
| **(v6.8)** Reschedule form let guests pick a date/slot with no availability shown, failing only on submit | The slot availability map lived as a private method on `PortalController`, so only the property page could use it | Extracted to `Booking::slotAvailabilityMap($propertyId, $excludeBookingId)` (+ `pastSlotsToday()`); the form now marks each slot Available / Already booked / Already started today, disables what can't be picked, and offers the next three open slots as chips |
| **(v6.8)** Refund destination page put the refund amount last, under a stacked icon and reference | `.booking-summary` used `flex-wrap: wrap` below 480px, so all three parts got their own line | Two-row grid on phones: icon + reference, then the amount as its own divided line with its label right-aligned (238px → 160px) |
| **(v6.8)** Review form's star rating was a 27px-wide tap target per star, and the rating label was cut off | Stars were fixed-size inline buttons; `.rating-label` used `height: 16px` against a 21px line box | Stars are `flex: 1 1 0` with `max-width: 56px` (42–56px per star, always inside the card); label uses `min-height`; stars gained `aria-label`/`aria-pressed` |
| **(v6.8)** Booking detail hero rearranged itself differently at every phone width | Six flex siblings wrapped independently, so the dates, arrow and nights count split apart; the review CTA was a flex child styled `display:block; margin-top` | Parts grouped into `.hero-stay` / `.hero-badges` / `.hero-cta`; under 600px the stay group is a `1fr auto 1fr` grid, so the arrow always sits between the two dates |
| **(v6.8)** Customer dashboard scrolled sideways on 360px phones, clipping the booking amounts | `.booking-row`'s three-column flex can't compress; and once the money column was wrapped to its own line, its own unbreakable content set a 354px min-content floor for the whole `1fr` grid column | Money column drops below the details at 560px and under, **and** wraps internally (`flex-wrap: wrap`), which removes the min-content floor |
| **(v6.8)** Customer topbar: brand wrapped to two lines, "Sign out" crowded the phone topbar despite a rule hiding it | `style="display:inline"` on the sign-out form outranks the stylesheet rule in `portal.css`; nothing stopped the brand from wrapping | Inline style replaced with `.nav-logout-form` (hidden by the same rule); `.nav-brand { white-space: nowrap }` |
| **(v6.8)** Dashboard notification rows styled a "read" state that could never appear | Row used `$notif->read_at` — the table has `is_read`, and the query is unread-only | Dead `read_at` branch and `.notif-dot-read` removed; rows now link to `customer.notifications.open` |
| **(v6.8)** The property photo rendered 1240×932 — taller than the whole laptop viewport, and upscaled past its own resolution | `.gallery` had `min-height` but no height; with indefinite row heights the image's `height: 100%` fell back to its intrinsic size, so the photo sized the layout | `height: clamp(280px, 38vw, 520px)` + `max-height: 56vh` on desktop; `min(clamp(...), NNvh)` rows below 900px; secondary phone tiles moved to implicit rows so a single-photo property leaves no empty grid |
| **(v6.8)** The availability calendar showed no availability on phones | The ≤560px breakpoint set `.slot-pill { font-size: 0; height: 6px }`, so both slot labels became unlabelled 6px bars | Labels kept at every width: calendar goes full-bleed into the page gutters below 900px, type scales 11→9.5px, sun/moon icon drops before the word does; `fixedWeekCount: false` + `hidePastWeekRows()` reclaim two empty rows |
| **(v5.0)** Manually recording a payment as admin crashed | `Admin\PaymentController::store()` called `NotificationHelper::paymentRecorded(...)`, a preset method that had never actually been defined | Added the missing `paymentRecorded()` preset |
| **(v5.0)** Real, completed payments showed as "Pending" in customer Payment History | 5 different payment-creation code paths (front-desk walk-in, front-desk record-payment, PayMongo success callback, admin manual record, admin refund) never set `status` explicitly, so they silently inherited the column's `pending` default | All 5 sites now set `'status' => 'success'` explicitly; 21 pre-existing mis-stamped rows backfilled via a data migration |
| **(v5.0)** Admin notification bell only ever worked on the Dashboard page | Bell button + dropdown lived in two disconnected places — Dashboard had its own hand-copied inline copy; every other page had a data-less, visually-unanchored copy at the bottom of `<body>` | Merged into one shared component in `layouts/admin.blade.php`'s topbar (DOM siblings, so the dropdown's `position:absolute` anchors correctly); removed the Dashboard's duplicate; added a global View Composer for the notification data |
| **(v5.0)** Clicking a notification could redirect to a dead ngrok URL (`ERR_NGROK_3200`) | `route()` generates an absolute URL; called outside an HTTP request (console commands, migrations) it falls back to `APP_URL`, permanently baking in whatever ngrok tunnel was configured at creation time | Generate every notification link as a **relative** URL (`route($name, $params, false)`) everywhere; converted all previously-stored absolute links to relative paths |
| **(v5.0)** ~50% of pre-existing notifications had no click-through destination at all | The `link` column didn't exist before v5.0 — every notification ever created up to that point had nothing to derive a destination from after the fact | Backfill migration parses each notification's title/message for an embedded `booking_ref` or review-related wording and recovers a real link for 151/203; the remaining 52 reference bookings that no longer exist in the database (deleted) and are left `null` by design |
| **(v5.1)** Booking policy said "12–24 hours" but was actually meant to be two fixed packages | v4.0 modeled the resort's two real packages (a daytime tour and an overnight stay) as an open-ended free-choice time window with min/max hour validation, instead of the two fixed slots they actually are | Replaced with `Booking::SLOTS` (`day` 8AM–5PM, `night` 7PM–6AM) + `Booking::slotDateTimes()`; every booking form now submits a slot choice, not raw time |
| **(v5.1)** Redundant 2-hour cleaning buffer on top of the fixed slots | Before the fixed slots existed, a buffer had to be explicitly enforced in `hasConflict()` since guests could pick any time; once the two fixed slots were introduced, the gap between them (exactly 2 hours on both ends) already *is* the buffer, making the separate `$bufferHours` padding redundant | Removed the `$bufferHours` parameter from `Booking::hasConflict()` entirely; conflict checks are now a plain datetime overlap |
| **(v5.2)** `php artisan migrate:fresh` against a clean Aiven database failed (`Unknown column 'admin_note' in 'reviews'`) | The `create_reviews_table` migration was years out of date relative to what the app actually uses — it still had `overall_rating`/`comment`, and never had `title`/`admin_reply_at`/`admin_note` at all. Those columns only existed locally because they'd been added directly to the dev database at some point, outside any migration — so `migrate:fresh` had never actually been run against this schema before | Rewrote `create_reviews_table` to match the real, in-use schema. Verified with a full column-by-column diff (throwaway local scratch database, freshly migrated, vs. the real local dev database) that this was the *only* such gap before trusting it against production |
| **(v5.2)** `SQLSTATE[42S22]: Table 'sessions' doesn't exist` (production only) | `SESSION_DRIVER=database`/`CACHE_STORE=database` are required in production (Render has no persistent disk for the `file` drivers used locally) — but no migration for `sessions`/`cache`/`cache_locks` had ever existed in this codebase, since local dev never needed them | Added `2026_08_10_000000_create_sessions_and_cache_tables.php` |
| **(v5.2)** Production database ended up with tables missing/schema stale despite a completed `migrate:fresh --seed` run reporting no errors | A full local database export/import (data **and** schema, including the `migrations` tracking table) was used to seed the production DB before deployment — Laravel trusted the imported `migrations` table's "already ran" records and skipped re-running anything, even though the import itself had silently dropped some tables (likely a foreign-key-order or charset issue in the dump) | Ran `migrate:fresh --seed` against Aiven from a developer machine (via the new `aiven` connection, since Render's free plan has no Shell tab) to rebuild the schema purely from migration files instead of trusting a data dump. General lesson: a dump's `migrations` table state doesn't prove the dump is actually complete |
| **(v5.2)** Registering a new account returned a 500 error on the live site | `AuthController::register()` called `$user->sendEmailVerificationNotification()` with no error handling — any transport hiccup (misconfigured mailer, provider rejection, etc.) crashed the whole request instead of just skipping that one email. Root transport issue was Render blocking outbound SMTP entirely on its free tier (see the v5.2 summary table above for the Brevo API switch) | Wrapped in try/catch (registration itself still succeeds even if the email fails); same fix applied to 2FA send/resend, verification resend, forgot-password, and walk-in guest account creation (`FrontDeskController`) — the latter was worse, since it ran inside a DB transaction and would have rolled back the entire walk-in booking over a failed email |
| **(v5.2)** (same 500, second cause) Registration could still fail even after the mail fix | `NotificationHelper::create()` — called by `newGuestRegistered()` during registration to notify admins — broadcasts via Pusher **synchronously** (`QUEUE_CONNECTION=sync` doesn't defer job exceptions the way a real queue worker would), so a Pusher config problem threw before the request ever reached the email code | Wrapped the `event(new NotificationCreated($notification))` call in try/catch + `Log::error()`; the notification row is still saved for the in-app bell either way, only the realtime push is skipped on failure |
| **(v5.3)** `Cloudinary\Api\Exception\NotFound` crashed pages back in local dev, after having worked fine on Render | Local `.env` had drifted to include the same `CLOUDINARY_URL` as production (copied in for reference during v5.2 deploy setup, never removed) — combined with `PropertyImage`/`User`/`Package`'s `->url()` accessors making an **uncaught, live Cloudinary Admin API call** just to build a URL string, any property image row referencing an asset no longer present on Cloudinary crashed the whole page | Wrapped all 3 accessors in try/catch + `Log::error()`, returning `null`/default-image instead of throwing; separately, cleaned the local `.env` back to local-only values (see v5.3 summary table) |
| **(v5.3)** Local `docker build` failed with `Class "Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider" not found` | No `.dockerignore` existed — building from the actual local working directory (not a fresh clone, unlike Render) let `COPY . .` copy the local `vendor/` (installed **with** dev packages) over the image's freshly-installed `--no-dev` vendor, leaving Composer's package manifest referencing a dev-only provider whose class files the `--no-dev` autoloader had excluded | Added `.dockerignore` (excludes `vendor/`, `node_modules/`, `.env`, logs, etc.) so the local build context matches what Render's git-clone-based build actually sees |
| **(v5.3)** Same class of crash again, different cause: `docker compose up` failed with the identical `PailServiceProvider not found` error even after the `.dockerignore` fix | `docker-compose.yml` bind-mounted `./bootstrap/cache` from the host — the host's package-manifest cache (built against a full local `composer install`, dev packages included) overwrote the container's correctly-built `--no-dev` manifest at container start | Removed the `bootstrap/cache` bind mount entirely; the container regenerates its own manifest from the packages it actually has installed |
| **(v5.3)** `Target class [App\Http\Controllers\Staff\FrontdeskController] does not exist` when logging in as staff — only inside Docker, worked fine on `php artisan serve` | The controller's **file** is `FrontDeskController.php` (capital D) but its **class declaration** was `class FrontdeskController` (lowercase d) — a longstanding mismatch invisible on Windows/NTFS, since Windows file lookups are case-insensitive. `routes/staff.php` imported/referenced it as `FrontdeskController` (lowercase, matching the class), so Composer's PSR-4 autoloader looked for `FrontdeskController.php` — which doesn't exist on Linux's case-sensitive filesystem inside the Docker container. (`routes/web.php` separately imports the same class as `FrontDeskController`, capital D, matching the actual file — that one worked by coincidence, since PHP's class-existence check is case-insensitive once the *file* is found.) Exactly the class of "only breaks in one environment" bug the v5.3 Docker-parity work was meant to catch | Renamed the class declaration to `FrontDeskController` (capital D, matching the file) and fixed all 9 references in `routes/staff.php` (the `use` import + 8 `[FrontDeskController::class, ...]` route actions) to match; also fixed 3 cosmetic comment mentions elsewhere for consistency |
| **(v5.3)** Local Docker page loads took multiple seconds, `php artisan serve` did not | Bind-mounting the whole repo (`.:/var/www/html`) meant every PHP `vendor/` file `stat()`/read on every request crossed the Windows↔WSL2 filesystem boundary — compounded by `docker/start.sh` intentionally skipping Laravel's route/view/config caching in local dev (more file reads per request, not fewer) | Switched to Docker Compose Watch (`develop.watch`) for `app/`, `resources/`, `routes/`, `config/`, `database/` — edits sync in instead of being live-mounted, so requests read from the container's own filesystem. Brought load times from multi-second down to ~0.05–0.4s |
| **(v5.4)** Every walk-in guest was forced to get a login account, even ones who just wanted to be checked in | `full_name` and `email` were both mandatory for any "new guest" walk-in, and a `User` account + password-reset email were created unconditionally — there was no way to just record a guest without also creating account/login machinery for them | Added a `create_account` (Yes/No, defaults to No) choice to the walk-in form; a guest record (`User`, role=`customer`) is still always created for booking/history purposes, but email + the password-reset email are now only involved when staff picks Yes. `users.email` changed to nullable (new migration) to allow a guest record with no email at all — `AuthController::register()` independently still requires email for public self-registration, unaffected |
| **(v5.4)** Walk-in bookings / payments / check-in / check-out / housekeeping tasks could 500 even though the underlying action had already succeeded | All 7 `event(new FrontdeskUpdated(...))`/`event(new PropertyAvailabilityChanged(...))` broadcast calls in `Staff\FrontDeskController` fired unprotected, after their DB write had already committed — a Pusher failure (confirmed locally: `auth_key should be a valid app key`) threw all the way up to a 500 page, so staff saw an error despite the action having gone through | Wrapped all 7 in try/catch + `Log::error()`, same pattern as `NotificationHelper::create()` (v5.2) |
| **(v5.4)** Admin notification bell was spammed with a brand-new "Guest Arrived — May Balance Pa" notification **every single minute**, forever, for the same booking | `AutoCheckInOutBookings::autoCheckIns()` correctly refuses to auto-check-in a guest who still has an outstanding balance (that decision needs a named staff member at the Frontdesk, for accountability) — but it alerted admin about it with no dedup guard, and the command runs `->everyMinute()` (`routes/console.php`). So for as long as the booking sat `confirmed`-with-a-balance, every tick created another `Notification` row + another `StaffLog` entry. Confirmed against real data: one booking (`VE-RGFUE55L`) generated **20 duplicate notifications** in 20 minutes before the balance was settled | Added a "have we already alerted about this exact booking?" check (`StaffLog::where(action: 'auto_checkin_skipped_balance', target_id: $booking->id)->exists()`) before notifying — one alert per booking, until staff resolves it at the Frontdesk. Also gave that notification a click-through `link` to the booking (it had none). Cleaned up the 19 leftover duplicates |
| **(v5.4)** Staff walk-in form said the Villa was unavailable **for every date**, even far-future ones, whenever any guest happened to be checked in right now | `Staff\FrontDeskController` (`index()` + `walkinForm()`) and `Admin\BookingController::create()` used `Property::where('status', 'available')` to decide which property to offer in the form. But `properties.status` is **real-time occupancy** (flipped to `occupied` by the check-in lifecycle), not date-specific availability — so with a guest checked in, the query returned zero rows and the Villa vanished from the form entirely, before the real per-date/slot check (`Booking::hasConflict()`) was ever reached | Changed all 3 sites to `where('status', '!=', 'maintenance')` — only a deliberate admin maintenance block should hide the Villa from the form; transient `occupied` should not, since it says nothing about the date being booked. The genuine availability check remains `Booking::hasConflict()` at submit time |
| **(v5.4)** A guest could book a slot whose check-in time had already passed — e.g. booking the "Day" (8:00 AM) slot at 10:00 PM tonight, which they could never actually check into | `'check_in_date' => 'required|date|after_or_equal:today'` validates only at **date** granularity — it has no idea the chosen *slot's* start time is already in the past for today's date. Nothing anywhere else re-checked it | Added an `$checkin->isPast()` guard to all 5 booking-creating paths: `PortalController` (`pricePreview`, `bookingForm`, `submitBooking`), `Customer\BookingController::update()` (reschedule), and `Admin\BookingController::store()`. **`Staff\FrontDeskController::storeWalkin()` deliberately uses a looser rule** — it checks `$checkout->isPast()` instead, because a walk-in guest is physically standing there: arriving at 9:00 AM for the 8:00 AM–5:00 PM Day slot is normal and should still be allowed; only a fully-elapsed slot should be refused |
| **(v5.4)** Admin → Insights reported nonsense revenue: "increased significantly to PHP 206,000, up from PHP 0 last month", despite prior months having real revenue | `InsightsController` summed `Payment::whereMonth('created_at', ...)` — the row's **insert** timestamp — instead of `payment_date`, the date the payment actually happened. Every historical payment row inserted during a backfill/seed therefore counted as "this month", collapsing months of revenue into one bucket and leaving genuinely-earlier months at ₱0 | Switched both `$revenueThisMonth` and `$revenueLastMonth` to `payment_date` (matching `DashboardController` and `ForecastController`), and added the `status = 'success'` + `payment_type != 'refund'` filters those two already had — so pending/failed payments and refunds no longer inflate the figure. Verified: now correctly reports ₱32,000 this month vs ₱34,000 last month |
| **(v5.4)** Same issue everywhere else it appeared — admin booking create/status-update, calendar drag-move, customer self-cancel, **public online booking submit**, and manual payment/refund recording could all 500 despite the underlying action already having succeeded | The identical unprotected-broadcast shape, in `Admin\BookingController` (x4), `Admin\CalendarController` (x1), `Customer\HomeController` (x1), `Portal\PortalController` (x2 — the online booking one is guest-facing, not just staff-facing), and a previously-uncounted pair in `Admin\PaymentController` (x2) | Wrapped all 10 in try/catch + `Log::error()`. The `Admin\PaymentController` refund one was the most serious: it sat inside a `DB::transaction()` closure, so an uncaught Pusher failure there would have silently rolled back an otherwise-valid refund payment record. Verified live: the refund now persists correctly even when the broadcast throws. Closes out the entire "~12 unprotected call sites" pending item (turned out to be 18 total across the app, once actually counted) |

| **(v5.5)** A guest could dodge the cancellation policy entirely by rescheduling first | Rescheduling had **no cutoff and no cap** — only an `isCancellable()` status check. Cancelling 2 hours before check-in gives 0%, but rescheduling a month out was free and unlimited, and `calculateRefundPercentage()` reads `checkInDateTime()` (the *new* date) — so cancelling from the moved date returned 100%. The peak slot stayed blocked throughout | Added `RESCHEDULE_CUTOFF_DAYS = 7` (deliberately the same boundary as the 100%-refund tier, so there's nothing to gain by moving instead of cancelling) and `MAX_RESCHEDULES = 2`, backed by a new `bookings.reschedule_count` column. `rescheduleBlockReason()` is the single source of truth for both the guard and the guest-facing message, and is re-checked in `update()` not just `edit()` |
| **(v5.5)** A fully-paid booking displayed as "Partial" after rescheduling to a cheaper slot | The refund branch of `Customer\BookingController::update()` unconditionally set `payment_status = 'partial'`. Reproduced: ₱6,000 paid → reschedule to ₱4,000 → ₱2,000 refunded → `amount_paid=4000`, `balance_due=0`, yet status read `partial`, i.e. contradicting its own zero balance | Replaced with the new centralized `Booking::recalculateFinancials()` |
| **(v5.5)** Seven diverging copies of the same `amount_paid`/`balance_due`/`payment_status` math | The recalculation was written inline in 7 controllers and had drifted. Most seriously, `PaymentController::success()` (PayMongo callback) **ignored refunds entirely**, so a booking with an earlier refund would have `amount_paid` jump back up after the next online payment | One `Booking::recalculateFinancials()` used everywhere, with two explicit rules: real payments count only at `status='success'`; refunds count from approval regardless of payout state. Cancellation behaviour verified unchanged (50% → `partial`, 100% → `refunded`, none → `paid`) |
| **(v5.5)** Refunds claimed to be complete when no money had been sent | There is no refund API, and cash can't be API-refunded — an admin pays out by hand. But refund rows were written `status='success'` immediately and the admin notification said *"₱X **refunded**"*, past tense. Nothing tracked whether the payout happened, so a guest's refund could be forgotten with no trace | Refunds now start `pending`; the Payments page gained a "NOT SENT" badge, an awaiting-payout banner + filter, and a **Mark Paid Out** action. Notification retitled *"Refund To Send"*. Amounts are unaffected by payout state — the refund is deducted on approval |
| **(v5.5)** Net revenue in Reports would have been overstated | `ReportController` subtracted refunds filtered by `status='success'` — correct while every refund was written as `success`, but wrong the moment refunds started life as `pending`, which would have hidden un-paid-out refunds from the deduction | Removed the status filter on the refund sum so it matches `recalculateFinancials()`. Caught before shipping, not in production |
| **(v5.5)** Every payment row had a `NULL` audit trail, and the duplicate-payment guard could never fire | `reference_number` and `received_by` were passed by nearly every `Payment::create()` call site but were **missing from `Payment::$fillable`**, so mass assignment silently discarded them — confirmed: 0 of 46 rows had either populated. This also disabled the duplicate guard in `PaymentController::success()`, which looks for an existing row by `reference_number` — impossible to match when it's always `NULL` | Both added to `$fillable`; added a `receivedBy()` relationship |
| **(v5.5)** A walk-in booking recorded ₱3,999.96 as paid with **zero** payment records | `storeWalkin()` writes `amount_paid` onto the booking from `payment_amount`, but only creates the `Payment` row `if ($amountPaid > 0 && $request->payment_method)` — and Payment Method was optional. Leaving it blank produced a booking claiming payment that no payment row backed: invisible to revenue reports, and the two sources of truth disagreed. Found one real instance (`VE-OLRWMOGX`) while scanning all 45 bookings for stored-vs-computed drift | Validation now rejects an amount with no method, with the error surfaced on the field. **The existing row was deliberately left as-is** — it's real business data and needs a human to decide whether ₱4,000 was actually received |
| **(v5.5)** Frontdesk told staff there were 3 units still available when the only bookable one was taken | The stat cards ran `Property::where('status','occupied'/'available')->count()` across **all** property rows — 1 villa plus 3 unnamed info-only `type=room` records left from the pre-v4.0 multi-unit model. The "Properties" tab had the same cause and rendered three blank cards (`property_name` is `NULL` on those rows since v5.0) | Both replaced with a single Villa status strip (real status, current guest, checkout time, next free slots) and the new Availability page. Room records stay in the database for housekeeping history, just aren't surfaced at the frontdesk |
| **(v5.5)** Housekeeping tasks piled up unresolved — 11 of 13 overdue, worst by 48 days, 6 stuck in `in_progress` | Nothing closed the loop: check-in creates the task, auto-checkout flips it to `in_progress`, but marking it complete was manual **and** buried in a tab nobody opened. The task also displayed only a due *date*, which is nearly useless when the turnaround between slots is 2 hours | Tasks now show their real deadline (the next check-in time, resolved in one query by `attachCleaningDeadlines()`); the most urgent one surfaces in a colour-coded banner at the top of the frontdesk with one-click **Mark cleaned**; checkout's success message names the deadline. A data-only migration closed the existing backlog (13 → 5), tagging each closed row with a note that it was auto-closed rather than genuinely done |
| **(v5.5)** Blade silently stopped compiling half a template, producing `syntax error, unexpected end of file` | `@php($slot = $row['slots'][$slotKey])` compiled to a bare `<?php(...)` with **no closing tag** — Blade fell back to treating `@php` as a block opener, so every directive after it was emitted as raw PHP and the whole rest of the file became one unterminated PHP block | Removed the parenthesised `@php(...)` form from both staff views in favour of `@foreach($row['slots'] as $slotKey => $slot)`, which needs no temporary assignment at all |
| **(v5.5)** Reviewer profile pictures never appeared, though customers could upload them | Both the homepage testimonials and `/reviews` hardcoded the first initial into `.author-avatar`; `User::profile_image_url` was never referenced | Both now render the uploaded image when present, falling back to the initial. `.author-avatar` gained `overflow:hidden` + an `img` rule to keep the circular crop |
| **(v5.5)** "My Bookings" button on the customer dashboard was unclickable | The welcome banner's decorative `::before` gradient circle is positioned over the CTA row and was capturing the clicks | `position:relative; z-index:1` on `.welcome-cta` |
| **(v5.6)** A guest who cancelled their own booking was never told anything | `Customer\HomeController::cancelBooking()` issued two notifications and **both were `notifyAdmin()`** — the guest got only a flash message, which is gone after one page load, so there was no lasting record of the cancellation or of whether a refund was owed. The admin-initiated cancel path *did* notify the guest, which is what made this look intentional rather than a miss | New `bookingCancelledForGuest()` preset, covering both the cancellation and the refund amount/percentage (or an explicit "not eligible") in one notification |
| **(v5.6)** Marking a refund paid out was completely silent | `markRefundPaidOut()` only flipped `status` and wrote a `StaffLog` row. The guest had been told a refund was coming and then never heard again; no admin got confirmation the refund was closed. This is the end of the refund lifecycle and it produced no signal at all | New `refundPaidOut($payment)` preset notifying **both** sides — guest ("Refund Sent … allow a few banking days") and all admins ("Nothing further is pending on this refund") |
| **(v5.6)** Guests were told their refund was "processed" while the money was still sitting in the resort's account | v5.5 correctly made refunds start as `status='pending'`, but the guest-facing notification still read *"has been processed"*, and the reschedule flash message said *"has been refunded"* — both past tense, both wrong at that point. Guests would go looking in GCash for money that hadn't been sent | Retitled **"Refund Approved"**, stating plainly that the money hasn't been sent yet and that a second notification follows. Reschedule message and the admin success message corrected to match. All four refund-creating sites now route through shared presets so the pending-vs-sent wording can't drift apart again |
| **(v5.6)** PayMongo webhook signature verification could never have succeeded | `verifyWebhook()` computed `hash_hmac('sha256', $rawBody, $secret)` and compared it against the **entire `Paymongo-Signature` header**. That header is `t=<timestamp>,te=<test-sig>,li=<live-sig>`, and the signed payload is `"{timestamp}.{rawBody}"` — not the body alone. Every genuine PayMongo event would have been rejected 401, silently, even with the webhook correctly registered | Header is now parsed into its components, the signed payload reconstructed as `timestamp . '.' . body`, and compared against both `te` and `li` so the same code works in test and live mode. Verified against synthetically-signed payloads including tampered-body and wrong-timestamp cases |
| **(v5.6)** A guest who paid and closed the browser stayed "unpaid" forever | The webhook handler found the booking and then only called `Log::info()` — its own comment described it as "a fallback for missed callbacks", but it never wrote anything. `PaymentController::success()` was the sole creator of `Payment` rows, and it only runs if the guest returns to the site after paying. PayMongo would have the money while the booking showed unpaid, with no reconciliation path short of reading the PayMongo dashboard by hand | Extracted `recordPaymongoPayment()` — payment row, notifications, `recalculateFinancials()`, auto-confirm and confirmation email — and called it from **both** the success callback and the webhook. Idempotent via `reference_number` (the PayMongo `pay_xxx` ID), verified by replaying the same event twice: one payment row, no duplicate notifications |
| **(v5.7)** The success callback could record a full payment for a booking that had paid nothing | It recorded whenever the checkout session status was `'paid'` **or `'active'`** — but `active` means the session is still *open*, i.e. unpaid — and it took the amount from `line_items`, which is the amount *requested*, not received. So a guest who opened checkout and came back without paying would be marked paid. Rare under GCash (returning to the site required authorising in the wallet); **routine under QR Ph**, where the guest scans on a different device and can land on the page before — or without — paying | The check is now authoritative: a payment object inside the session with `status === 'paid'`, with the amount and reference read from that payment rather than from the line items. Verified across four cases (paid / active-with-no-payment / failed payment / expired session) — only the first records |
| **(v5.7)** Payment history listed in unpredictable order | Both payment lists sorted by `payment_date` alone — a **date column with no time**. Two payments on the same day tie exactly, so MySQL returned them in arbitrary (effectively insertion) order, putting the deposit *above* the later balance payment. The payment histories on the customer and admin booking-detail pages had **no `ORDER BY` at all** | `id` added as a tiebreaker in `Customer\PaymentController` and `Admin\PaymentController`; the three booking-scoped histories now sort newest-first in the view without extra queries |
| **(v5.7)** The same enum rendered four different ways | Six views each ran their own `ucfirst()` / `strtoupper()` / `str_replace()` over the raw column, yielding `Full_payment`, `Full payment`, `FULL PAYMENT` and `Qrph` for the same two values. `NotificationHelper` had its own copy again, which is how admins were told a payment arrived *"via Qrph"* | `Payment::typeLabelFor()` and `methodLabelFor()` — static so the helper can share them — plus `type_label` / `method_label` accessors. Every render site now goes through them |
| **(v5.7)** Admin notifications reported the balance from *before* the payment | `NotificationHelper::paymentReceived()` ran **before** `recalculateFinancials()`, so `balance_due` was always one payment stale. Caught in the live QR Ph test: ₱2 paid against a ₱4 booking announced *"Balance due: ₱4.00"*, and the closing ₱2 announced *"₱2.00"* on a booking that was by then fully paid — the one number an admin would act on, consistently wrong | Notification moved after the recompute in `recordPaymongoPayment()` and in `Admin\PaymentController::store()` (which had the same ordering) |
| **(v5.7)** Rejected webhooks were undiagnosable from the logs | A failed signature logged only `invalid signature attempt` and an IP. Two live rejections could only be explained by pulling the raw payloads out of the ngrok inspector — they turned out to be harmless `livemode: false` dashboard test events | The rejection log now includes the event id, type and `livemode` (parsed for logging only, never trusted or acted on) plus a plain-language hint distinguishing "test event while live — expected" from a genuine secret mismatch |
| **(v5.7)** The system auto-cancelled bookings that had **already paid**, telling the guest their downpayment "wasn't completed" | `AutoCheckInOutBookings::cancelStalePendingBookings()` selected on `status = 'pending'` and `created_at` only — never on `amount_paid`. Meanwhile the three **manual** record-payment paths (`Admin\PaymentController::store()`, `Admin\BookingController::recordPayment()`, `Staff\FrontDeskController::recordPayment()`) called `recalculateFinancials()`, which updates `amount_paid`/`balance_due`/`payment_status` but **never `status`**. Only the PayMongo path auto-confirmed. Net effect: staff takes a ₱2,000 downpayment at the front desk → booking stays `pending` → the sweeper cancels it and notifies the guest that payment wasn't completed, while the resort is holding their money. **Observed live** on `VE-KX24HC95` (₱2,000 paid, auto-cancelled 2026-08-16 01:20) | Two layers. **Root cause:** new `Booking::confirmOnFirstPayment()` — one definition of "the first successful payment confirms the booking" — called from all three manual paths; `recordPaymongoPayment()` was refactored onto it too, so the PayMongo and manual paths can no longer diverge. **Safety net:** the sweeper now skips any booking with `amount_paid > 0`, so a future path that forgets to promote status leaves a booking merely stuck at `pending` rather than cancelled while holding cash. Verified three ways: manual payment → confirmed and survives the sweep; paid-but-unpromoted → survives; genuinely unpaid → still cancelled |
| **(v5.7)** The webhook endpoint returned **419 Page Expired** to every PayMongo delivery | `POST /webhooks/paymongo` lives in `routes/web.php`, so it inherits the `web` middleware group — including `ValidateCsrfTokens`. A server-to-server webhook carries no session and no CSRF token, so Laravel rejected it **before the controller ever ran**. Both `project.md` and `CLAUDE.md` had asserted the route was "CSRF exempt" for several versions; `bootstrap/app.php` contained no such exemption. Every earlier test passed because they invoked the controller method directly, bypassing middleware entirely — the defect only appears when the request goes through the real stack | Added `$middleware->validateCsrfTokens(except: ['webhooks/paymongo'])`. Authentication for this route is the HMAC signature, not CSRF. Verified by POSTing to the **public ngrok URL** from outside: bad signature → `401 {"error":"Invalid signature"}`, correctly signed → `200 {"received":true}` with the payment recorded |
| **(v5.7)** Docker container silently ran the old `bootstrap/app.php` | The CSRF fix above appeared to do nothing — the container serving `localhost:8000` kept returning 419. Compose Watch syncs `app/`, `resources/`, `routes/`, `config/` and `database/`, but **`bootstrap/` was never in the watch list**, so middleware changes could never reach the container. A variant of the v5.5 stale-container trap, but not fixable by running `--watch`: the path simply wasn't covered | Added a **single-file** sync entry for `./bootstrap/app.php` (deliberately *not* the whole `./bootstrap` directory — `bootstrap/cache/` must never be synced; the host copy reflects a full dev `composer install` and overwrites the image's `--no-dev` manifest, breaking container start with `Class ...ServiceProvider not found`) |
| **(v5.7)** The success page told every visitor "Payment Successful!" | Unconditional green check and confirmation text, with a blank cell where the payment status belonged whenever the booking was still `unpaid`. Under QR Ph a guest can legitimately reach this page before settlement completes, so the page would confidently confirm a payment the system had not received | Second state added: a waiting notice explaining confirmation arrives automatically (true — the webhook delivers it), the outstanding balance, a "Not yet received" chip in place of the blank cell, and a retry link |
| **(v5.8)** Every AI feature broke at once, with no change on our side | `GeminiService` hardcoded `llama-3.1-8b-instant`, which Groq **decommissioned**. The API now answers `400 model_not_found`, and `ask()` returns that error string straight to the caller — so the chatbot showed it to guests, insights and forecast rendered it as their "report", and review moderation fell back to the manual queue for every review. Nothing was logged, because the failure branch only returned the string. The forecast had a second, quieter problem waiting: Groq's replacement models are all **reasoning** models whose hidden reasoning is charged against `max_tokens`, so at the default effort the report came back truncated mid-section | Model and reasoning effort moved to config (`GROQ_MODEL`, default `openai/gpt-oss-20b`; `GROQ_REASONING_EFFORT`, default `low`), so the next retirement is an env change rather than a deploy. `ask()` now takes a per-call `$maxTokens` (forecast passes 2048), logs failures with model/status/body while keeping the error-string return the fail-open moderation path depends on, and strips inline `<think>` blocks in case a model that inlines its scratchpad is ever configured. Re-verified end to end on all four call sites — see v5.8 above |
| **(v6.0)** "Forgot password" always failed with *"Hindi maipadala ang reset link ngayon"* | Not a mail fault at all — that message is just what `AuthController::sendResetLink()`'s catch-all prints. The real error, visible only in `storage/logs/laravel.log`, was `SQLSTATE[42S02] ... Table 'villa_elena_db.password_reset_tokens' doesn't exist`. This project's custom `0001_01_01_000000_create_users_table.php` **replaced** Laravel's default users migration, which is also where the framework creates `password_reset_tokens` — so the password broker had nowhere to store its token and threw before a single mail call was made. The same omission as the v5.2 `sessions`/`cache` gap, from the same replaced migration; it surfaced later only because nobody had exercised the reset flow | New migration `2026_09_02_100000_create_password_reset_tokens_table.php` (guarded with `Schema::hasTable()`, so it is safe on any database that already has the table). Verified with `Mail::fake()`: `Password::sendResetLink()` now returns `passwords.sent` and writes the token row. **Must also be run against Aiven** (`php artisan migrate --force --database=aiven`) — production is missing the table too |
| **(v6.0)** A guest who paid a 50% downpayment got a confirmation email; paying the **remaining balance** sent nothing at all | `recordPaymongoPayment()` gated the send on `$wasPending` — the return of `confirmOnFirstPayment()`, which is true only for the payment that flips a booking `pending → confirmed`. So exactly one email could ever exist per booking, no matter how many payments followed, and the guest got no receipt and no confirmation that the booking was now fully paid. **Worse, found while confirming it:** the three *manual* payment paths (`Admin\PaymentController::store()`, `Admin\BookingController::recordPayment()`, `Staff\FrontDeskController::recordPayment()`) sent **no email at any point** — a guest paying cash at the front desk never received one, not even the first confirmation. Only the PayMongo path had a send at all | New `App\Helpers\BookingMailHelper::paymentRecorded()` — one gate (`email_notifications_enabled` + try/catch + log) that all four payment paths now call, so they cannot drift apart again. `$wasPending` no longer decides *whether* to send, only *which form*: `BookingConfirmedMail` now takes `$amountPaid` and `$isFirstConfirmation` and renders three variants — "Booking Confirmed" (first payment), "Payment Received" + remaining balance, and "Fully Paid" — with a new *This Payment* row distinguishing the amount just paid from the running total. Refunds are excluded (`Admin\BookingController::recordPayment()` also accepts `payment_type = 'refund'`, which must not trigger a thank-you-for-your-payment receipt). Verified by rendering all three variants for real (per the v5.8 lesson that `compileString()` proves nothing) and by driving two payments through `recordPaymongoPayment()` in a rolled-back transaction: 2 emails, `Booking Confirmed` then `Fully Paid`, where the old code sent 1 |
| **(v6.0)** The final-payment email read as if the guest had paid more than they did | The receipt showed *This Payment ₱6.00* directly above *Total Paid To Date ₱12.00*, with no row accounting for the ₱6.00 downpayment in between — so the two numbers looked like a contradiction rather than a running total | The summary is now computed in `BookingConfirmedMail::summary()` (PHP, unit-testable) rather than assembled inline in Blade, which just loops the rows it returns. Whenever earlier payments exist it emits a *Previously Paid* row before the current one, so `previous + current = Total Paid` always closes on the page — on partial payments as well as the final one (the current row is labelled *Final Payment* only when it clears the balance, *This Payment* otherwise). Both cases always end with an explicit *Balance Remaining* (**including ₱0.00** — the explicit zero is what answers "do I still owe anything?") and a *Payment Status* row of `Partial Payment`/`Fully Paid`. **`Previously Paid` is derived, never passed in:** `amount_paid − amountPaid`, which is only correct because all four payment paths call `recalculateFinancials()` *before* the mail is built — a new payment path that mails first would silently report it wrong. A first payment deliberately omits the row entirely (a `Previously Paid: ₱0.00` row is noise, and there is no confusion to resolve when nothing was paid earlier) |
| **(v6.0)** Switching `MAIL_MAILER` to `brevo` sent no mail, and in some flows threw `TypeError: Dsn::fromString(): Argument #1 ($dsn) must be of type string, null given` | Three separate problems stacked. **(1)** `AppServiceProvider`'s `Mail::extend('brevo', …)` passed `config('services.brevo.dsn')` straight into `Dsn::fromString()`. With `MAIL_MAILER=brevo` but no `MAILER_DSN` set, that is `null` — and the resulting `TypeError` pointed into Symfony's internals, naming neither Brevo nor the missing env var. **(2)** The `.env` had `MAIL_MAILER=brevo` paired with Brevo's **SMTP relay** credentials (`smtp-relay.brevo.com`, an `xsmtpsib-` key). Those belong to the `smtp` mailer; the HTTPS API needs an `xkeysib-` **API v3** key, a different credential entirely. Switching was done by commenting out whole blocks, which is exactly how the mailer and its credentials drifted apart. **(3)** Probing the transports directly showed the Brevo relay login was wrong too — `535 5.7.8 Authentication failed`, because the relay's login is the one Brevo issues (`…@smtp-brevo.com`), not the account's Gmail address. So *both* transports were broken, which is why no email went out either way | `brevoDsn()` now resolves `MAILER_DSN` first (production/`render.yaml` keeps working unchanged), else builds the DSN from a plain `BREVO_API_KEY` (`rawurlencode`d — the key sits in a URL's userinfo), else throws a `RuntimeException` naming the missing variable, the `xkeysib-`/`xsmtpsib-` distinction, and the `config:clear` step. `.env`/`.env.example` restructured so **`MAIL_MAILER` alone selects the transport** — smtp settings and `BREVO_API_KEY` coexist without conflicting, so no block-commenting is needed. New `php artisan mail:test <email>` prints the resolved config, refuses an `xsmtpsib-` key in `BREVO_API_KEY`, and surfaces the real exception — the silent-failure mode existed because every mail call site is deliberately wrapped in try/catch (a booking must not 500 over an email), so failures only ever reached `laravel.log`. Verified by building each transport for real: `BREVO_API_KEY`, `MAILER_DSN`, a key needing URL-encoding, and DSN-wins-over-key all produce `brevo+api://api.brevo.com`; nothing set gives the new clear error; and an auth-only probe (`EsmtpTransport::start()`, no message sent) confirmed Gmail authenticates while the Brevo relay does not |
| **(v6.0)** Mail transports split: **SMTP locally, Brevo in production only** | The two had been getting mixed in one `.env` — `MAIL_MAILER=brevo` paired with SMTP-relay credentials, and a commented "switch" block labelled `#brevo` that actually contained a second copy of the *Gmail SMTP* settings, so uncommenting it silently produced SMTP again. Separately, live probing showed the Brevo SMTP relay rejects the account's Gmail address as a login (`535 5.7.8`; the relay issues its own `…@smtp-brevo.com` login), while Gmail's app password authenticates fine | `.env` is now plainly `MAIL_MAILER=smtp` on Gmail for local dev, with a correctly-labelled **commented** Brevo block (`MAIL_MAILER` + `MAILER_DSN`) documenting that Render supplies both itself. Production is untouched — `render.yaml` still sets `MAIL_MAILER=brevo` + `MAILER_DSN`. `AppServiceProvider` keeps the null-DSN guard so `MAIL_MAILER=brevo` without a DSN raises a `RuntimeException` naming the variable, the "this is the production transport" rule, and the `xkeysib-`/`xsmtpsib-` distinction — instead of the bare `TypeError` from inside Symfony. `.env.example` and `php artisan mail:test` both describe `MAILER_DSN` only, matching `config/services.php` (which wires just `MAILER_DSN`, no `BREVO_API_KEY`). Verified all three: local smtp → `smtp://smtp.gmail.com:587` and a real send; brevo + DSN → `brevo+api://api.brevo.com`; brevo with an empty DSN → the clear error |
| **(v6.6)** Every email in the system failed, but only "forgot password" was noticed | `MAILER_DSN` on Render held **just the API key** (`xkeysib-…`) instead of the full DSN. `brevo+api` is a single Symfony scheme (`<transport>+<protocol>://`), not two separate settings, so `Dsn::fromString()` threw `InvalidArgumentException: The mailer DSN must contain a scheme` before any network call. That exception surfaces inside `Mail::extend('brevo', …)`, where every mail call site's try/catch swallowed it — so the user saw only the generic *"cannot be sent right now"* and the real reason existed solely in Render's log stream. The v6.0 guard added for this case only catches a **blank** DSN, not a malformed one | Corrected to `MAILER_DSN=brevo+api://<xkeysib-KEY>@default` in the Render dashboard; `@default` is a literal placeholder Symfony rewrites to `api.brevo.com`. Verified the three shapes by building the transport for real: the bare key and a bare `brevo+api` both throw "must contain a scheme", the full DSN yields `brevo+api://api.brevo.com`. **A leftover `MAIL_PASSWORD` in Render's environment was not the cause** — it belongs to the `smtp` mailer and is never read while `MAIL_MAILER=brevo` — but it should still be removed, since credentials drifting apart from the mailer that uses them is exactly the v6.0 failure |
| **(v6.6)** "Verify your email" claimed a link had just been sent, on the one path that sends nothing | The page is reached two ways. `register()` and the resend button really do send. But **every login by an unverified user lands on the same page**, and `verifyNotice()` only calls `view()` — it sends nothing at all. The copy said *"We sent a verification link to …"* unconditionally, so a user logging in weeks after registering went hunting through an inbox for a message that was never sent on that visit. Same shape as the v5.7 payment-success page, which told every visitor "Payment Successful!" including those whose payment hadn't settled | `register()` and `resendVerification()` now flash `verification_sent` (true/false), and the page renders **three** states: a link was just sent; a send was attempted and failed (with the reassurance that the account itself is fine); or nothing was sent on this visit, saying so **explicitly** before pointing at the resend button. Also removed a duplicate `session('success')` block that rendered the flash twice, since `layouts.auth` already prints it. Verified by rendering all three states for real, not with `compileString()` |
| **(v6.7)** "Proceed to Payment" did nothing on a phone, with no way to tell why | Two defects stacked into a dead end. The button was **disabled** until the policy checkbox was ticked (`submit.disabled = !check.checked`), so tapping it produced *nothing at all* — no message, no movement. And because the checkbox carries `required`, once the button was made clickable the browser's own constraint validation took over: the native *"Please check this box…"* bubble is anchored **to the checkbox**, which on a narrow screen is often scrolled out of view, so the tap still looked inert. On a laptop the consent row and the button sit on screen together and the connection is obvious; on a phone they need not, and the guest is left with a button that silently refuses | The button is **never disabled** now. An `invalid` listener on the checkbox suppresses the native bubble and shows an in-page notice instead, marks the consent row red, and **scrolls it into view** — so the tap always produces an answer next to the thing that has to be acted on. A `submit` fallback covers the case where `required` is ever removed. Server-side enforcement is unchanged (`required` on the input, `accepted` in `submitBooking()`); this was only ever a UX affordance. Verified by driving a real click at a 384px viewport: note shown, row marked, form not submitted, and everything cleared on tick |
| **(v6.7)** The booking summary card never actually stuck | `.summary-card` had `position: sticky` with `top: calc(var(--nav-h)+20px)`. **`calc()` requires whitespace around `+`** — without it the declaration is invalid, so the browser dropped the whole line and `top` computed to `auto`, which makes a sticky element behave as if it were static. Nothing reports this: invalid CSS fails silently, and the rule *looks* correct | Changed to `calc(var(--nav-h) + 20px)`. Confirmed the diagnosis rather than assuming it — `CSS.supports('top','calc(72px+20px)')` returns **false**, and the card's computed `top` went from `auto` to `92px` after the fix |
| **(v5.6)** An unexpected PayMongo payment method would have wedged the webhook in a retry loop | `payments.payment_method` was narrowed to `gcash`/`paymaya`/`cash` in v5.5. Any other source type (e.g. `card`, `grab_pay` if ever enabled) would fail the INSERT on the ENUM constraint, return non-2xx, and have PayMongo retry the same doomed event repeatedly — with the payment never recorded | Method is normalised against the allowed set before insert; the original value is preserved in `notes` and logged as a warning. Recording the payment under the closest valid method beats losing it |

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
| 46 | **(v5.2)** Render + Docker Deployment (Dockerfile, nginx/php-fpm/supervisord, `render.yaml`) | ✅ Complete |
| 47 | **(v5.2)** Aiven MySQL Production Database (schema fixed, migrated, seeded) | ✅ Complete |
| 48 | **(v5.2)** Cloudinary Image Storage (production disk switch, view/model URL fixes) | ✅ Complete |
| 49 | **(v5.2)** Pusher Realtime on Production | ✅ Complete |
| 50 | **(v5.2)** Transactional Email on Production (Brevo API, replacing SMTP) | ✅ Complete |
| 51 | **(v5.2)** Free-Tier Cron Workaround (`/cron/run-schedule` route) | ✅ Complete |
| 52 | **(v5.2)** Mail/Broadcast Failure Isolation (auth flows no longer 500 on transport errors) | ✅ Complete |
| 53 | **(v5.3)** Cloudinary Image URL Accessors Hardened (try/catch on `PropertyImage`/`User`/`Package`) | ✅ Complete |
| 54 | **(v5.3)** Local `.env` Cleanup (removed drifted-in production credentials) | ✅ Complete |
| 55 | **(v5.3)** Local Docker Parity (`Dockerfile`, `.dockerignore`, Compose Watch, scoped `villa_docker` MySQL user) | ✅ Complete |
| 56 | **(v5.3)** Local Redis Cache Wired Into Dockerized `app` Service | ✅ Complete |
| 57 | **(v5.4)** Optional Walk-in Guest Login Accounts (guest record no longer requires an account) | ✅ Complete |
| 58 | **(v5.4)** `FrontDeskController` Broadcast Failure Isolation (7 unprotected `event()` calls) | ✅ Complete |
| 59 | **(v5.4)** App-wide Broadcast Failure Isolation (remaining 10 unprotected `event()` calls: Admin Booking/Calendar/Payment, Customer, Portal) | ✅ Complete |
| 60 | **(v5.5)** Payment Method / Type ENUM Consolidation (e-wallets + cash; `deposit` merged into `partial`) | ✅ Complete |
| 61 | **(v5.5)** Reschedule Limits — 7-day cutoff + max 2 (closes the cancellation-policy loophole) | ✅ Complete |
| 62 | **(v5.5)** Centralized `Booking::recalculateFinancials()` (replaced 7 diverging inline copies) | ✅ Complete |
| 63 | **(v5.5)** Refund Payout Tracking (pending → Mark Paid Out, awaiting-payout banner/filter) | ✅ Complete |
| 64 | **(v5.5)** Payment Audit Trail Restored (`reference_number` / `received_by` added to `$fillable`) | ✅ Complete |
| 65 | **(v5.5)** Staff Availability Page — 14-day Day/Night slot grid with walk-in prefill | ✅ Complete |
| 66 | **(v5.5)** Frontdesk Single-Villa Rework (Villa status strip; removed misleading unit counters) | ✅ Complete |
| 67 | **(v5.5)** Housekeeping Deadlines + Cleaning Banner + Backlog Cleared (13 → 5) | ✅ Complete |
| 68 | **(v5.5)** Staff Portal Theme Aligned to Admin (earth palette, cream sidebar) | ✅ Complete |
| 69 | **(v5.5)** Public Portal Polish (social contact rows, reviewer avatars, dashboard CTA fix) | ✅ Complete |
| 70 | **(v5.6)** Refund Lifecycle Notifications (guest cancel, refund approved, refund sent) | ✅ Complete |
| 71 | **(v5.6)** PayMongo Webhook Signature Verification Fixed | ✅ Complete |
| 72 | **(v5.6)** Webhook Records Payments (shared `recordPaymongoPayment()`, idempotent) | ✅ Complete |
| 73 | **(v5.7)** QR Ph Payment Migration (ENUM, checkout, dropdowns, badges, 41 rows relabeled) | ✅ Complete |
| 74 | **(v5.7)** Async-Safe Payment Confirmation (authoritative paid check + waiting state) | ✅ Complete |
| 75 | **(v5.7)** Webhook Endpoint Made Actually Reachable (CSRF exemption + `bootstrap/app.php` Compose Watch) | ✅ Complete |
| 76 | **(v5.7)** Paid-Booking Auto-Cancellation Fixed (`confirmOnFirstPayment()` + sweeper guard) | ✅ Complete |
| 77 | **(v5.7)** QR Ph Verified on Live Keys (2 real ₱2 payments, webhook-delivered) | ✅ Complete |
| 78 | **(v5.7)** Payment Label + Ordering Cleanup (`type_label`/`method_label`, date-tie fix) | ✅ Complete |
| 79 | **(v5.7)** Stale-Balance Notification Fixed + Webhook Rejection Diagnostics | ✅ Complete |
| 80 | **(v6.3)** Prescriptive Engine — `DemandModel`, `PrescriptiveEngine`, `recommendations` table, `prescriptive:generate` (nightly) | ✅ Complete |
| 81 | **(v6.3)** Idle-Date Promo Advisor (expected-revenue optimization over candidate discounts) | ✅ Complete |
| 82 | **(v6.3)** Maintenance Window Advisor (minimizes revenue at risk across candidate windows) | ✅ Complete |
| 83 | **(v6.3)** Recommendations Action Center — evidence, confirmation modal, Apply → real `Discount`/`AvailabilityBlock`, dismiss w/ reason, decision history | ✅ Complete |
| 84 | **(v6.3)** Prescriptive assumptions exposed in Settings (`prescriptive_*`) + `config/prescriptive.php` | ✅ Complete |
| 85 | **(v6.3)** Forecast prompt stripped of AI-invented recommendations (single source of advice) | ✅ Complete |
| 86 | **(v6.4)** Peak Rate Advisor — recommends *raising* rates on strong dates (separate peak elasticity, forced `fixed` rule) | ✅ Complete |
| 87 | **(v6.4)** What-If Simulator (`/admin/prescriptive/simulate`) — read-only, admin-driven price scenarios | ✅ Complete |
| 88 | **(v6.4)** Dashboard "Recommended Actions" widget (top 3, outside the KPI cache) | ✅ Complete |
| 89 | **(v6.4)** AI morning briefing — one Groq call per run, prose only, fails open by clearing | ✅ Complete |
| 90 | **(v6.4)** 🔴 Pricing-rule boundary bug fixed — DATE vs DATETIME meant a rule never applied on its last day, and a one-day rule never applied at all | ✅ Complete |
| 91 | **(v6.4)** Engine hardening — expiry scoped to advisors that finished; `expired` cards revive when the opportunity returns | ✅ Complete |
| 92 | **(v6.5)** Outcome tracking — `baseline_projection` frozen at forecast time, `actual_revenue`/`realized_impact` filled by `OutcomeTracker` after each window closes | ✅ Complete |
| 93 | **(v6.5)** Accuracy page (`/admin/prescriptive/accuracy`) — model calibration and action outcomes measured separately, with sample-size warnings | ✅ Complete |

### Pending / Optional

Re-verified directly against the live code/database (not just re-stated from memory) on 2026-08-08; v5.5 rows added 2026-08-15.

| Module | Status | Notes |
|---|---|---|
| ~~AI features — needs `GROQ_API_KEY`~~ | ✅ Resolved | `GROQ_API_KEY` is set in `.env` — confirmed live. The blocker this item was tracking is gone (Insights/Chatbot can call the API); Forecast still uses dummy data regardless — see next row |
| ~~PayMongo Webhook (code side)~~ | ✅ **Resolved (v5.6)** | Superseded — the handler was a no-op and signature verification was broken; both fixed in v5.6. What remains is only the external dashboard registration, tracked in the v5.2 row below |
| ~~AI Forecasting real data~~ | ✅ **Resolved (2026-08-11)** | `Admin\ForecastController::index()` used to run entirely on hardcoded dummy arrays (`$dummyBookings`, `$dummyRevenue`, `$totalProperties = 4; // dummy`) — replaced with live `Booking`/`Payment` queries per month over a real trailing 6-month window. Also removed the "occupancy rate"/"total properties" framing from both this and `Admin\InsightsController` (meaningless for a single-exclusive-villa resort — there's only ever one bookable unit, so "% of properties occupied" doesn't mean anything); Insights' KPI card was swapped for a plain Villa Status (Occupied/Available). Separately, switched the AI's raw Markdown forecast response to render as real HTML (`league/commonmark`) instead of literal `**`/`#` characters showing up on the page |
| ~~Reviews on property public page~~ | ✅ **Resolved (2026-08-08)** | Added a "Guest Reviews" section to `portal/property.blade.php`, scoped to that specific property's own **approved** reviews (average rating + count, individual review cards, admin replies shown inline, empty state if none yet). `Portal\PortalController::propertyDetail()` now queries `Review::where('property_id', $property->id)->where('status', 'approved')` and passes `reviews`/`avgRating`/`totalReviews` to the view |
| ~~Staff walk-in booking form~~ | ✅ **Confirmed done** | Re-checked `Staff\FrontDeskController` — it already validates `check_in_time`/`check_out_time` and prices via `$property->getPackagePrice($checkin)`. Not touched this session, but it's already correct — no longer pending |
| ~~AI Chatbot context refresh~~ | ✅ **Confirmed done** | Re-checked `Portal\ChatbotController` — the prompt is explicitly single-villa-aware ("SINGLE-VILLA private resort... only ONE bookable villa"), pulls the real master Villa record, real `getPackagePrice()`/`hasConflict()` results. No stale multi-villa references found |
| ~~Villa Elena base/weekend price values~~ | ✅ Resolved | Confirmed correctly set to ₱4,000 / ₱6,000 on the master Villa record |
| **(v5.0)** Admin account password | 🔲 **Still action required** | Re-checked — still the temporary `AdminTest123!` set during this session's testing. Not reset. |
| ~~(v5.0) Staff account password~~ | ✅ Resolved | Re-checked — no longer matches the temporary `StaffTest123!` this session set, so it's been changed to something else already |
| ~~(v5.0) 52 orphaned notifications with no click-through link~~ | ✅ **Superseded (2026-08-08)** | Moot after the v5.1 data reset wiped all pre-v5.1 bookings/notifications — see the next row |
| ~~(v5.0) Amenities list needs the admin's real content~~ | ✅ **Tooling fixed (2026-08-08)** | Curating the actual amenity names is a self-service admin task (Settings → Amenities, already built in v5.0) — not something that gets "completed" in code. What WAS a real bug: the master list said `"Swimming Pool"` while the Villa's actual saved amenity was `"Private Pool"`, silently orphaning that checkbox on the Property edit form (it couldn't render as checked, so re-saving the property from that form would have dropped it) — fixed in both the live `property_amenities` setting and the `PropertyController::DEFAULT_AMENITIES` seed constant. Also added **one-way sync** in `SettingsController::update()`: removing an amenity from Settings → Amenities now also strips it from every property that had it selected (previously the two lists could silently drift apart, which is exactly how the Swimming Pool/Private Pool mismatch happened in the first place) |
| **(v5.1)** Data reset — all pre-v5.1 bookings wiped | ✅ **Done (2026-08-08)** | 31 bookings (30 live + 1 already-trashed) hard-deleted, cascading 24 payments + 7 reviews; 14 housekeeping tasks unlinked (`booking_id` → null, not deleted); Villa property status reset to `available`; 97 stale notifications referencing the deleted bookings/reviews removed (kept 12 real "New Guest Registered" notifications). Clean slate for testing the new fixed-slot booking flow — no leftover free-time-era data anywhere |
| ~~(v6.0-planned) Permanent scheduler for `php artisan schedule:run`~~ | ✅ **Resolved (v5.2)** | Now hosted on Render, whose free tier has no Cron Jobs feature ($1/mo minimum). Workaround: a token-gated `GET /cron/run-schedule/{CRON_SECRET}` route runs `schedule:run` on demand, hit periodically by a free external pinger (e.g. cron-job.org). Local dev is unaffected — still `php artisan schedule:work` in an open terminal there |
| **(v5.2 → v5.7)** PayMongo Webhook registration | 🔴 **PREREQUISITE — must be done before QR Ph goes live** | Register `https://villa-elena.onrender.com/webhooks/paymongo` on the PayMongo dashboard and set `PAYMONGO_WEBHOOK_SECRET` (still the placeholder `whsk_xxx` in `.env` as of this writing, so signature verification currently rejects everything). **No live account needed** — webhooks fire in test mode against the test secret key; it just needs a publicly reachable URL (Render or ngrok), never `localhost`. This was merely nice-to-have while GCash was the rail, because the redirect back to `success_url` reliably recorded the payment. **Under QR Ph it is the primary recording path** — the guest scans on a separate device and the browser tab may never return — so without it, real payments will be taken and silently not recorded |
| **(v5.7)** Confirm what QR Ph reports as its source type | 🔲 Verify on the first real payment | The code stores `source.type` / `payment_method_used` and normalises anything unrecognised to `qrph`, keeping the original string in `notes` and logging a warning — so an unexpected value degrades safely instead of failing the INSERT. Check `storage/logs` after the first live QR Ph payment; if a warning appears, add the real value to the `$known` set in `PaymentController::recordPaymongoPayment()` |
| **(v5.7)** Whether QR Ph payments can be completed in PayMongo **test** mode | 🔲 Verify before relying on sandbox testing | The API accepts `payment_method_types: ['qrph']` with a test key and returns a checkout URL, but that only proves the session is created — not that the sandbox can simulate a scan-and-pay. If test mode can't complete a QR Ph payment end to end, the remaining verification has to happen on live keys with a small real amount. The webhook path itself is already proven by replaying signed `payment.paid` events locally |
| ~~(v5.2) Other unprotected Pusher broadcast calls~~ | ✅ **Fully resolved (2026-08-13)** | The same "broadcasts synchronously, no try/catch" shape that caused the registration 500 (see Known Issues) existed in every remaining `event(new ...)` call site across the app: `Admin\BookingController` (x4: `BookingCreated`, `BookingUpdated`, `PropertyAvailabilityChanged` x2), `Admin\CalendarController` (x1: `BookingUpdated` on drag-move), `Customer\HomeController` (x1: `PropertyAvailabilityChanged` on self-cancel), `Portal\PortalController` (x2: `BookingCreated`, `PropertyAvailabilityChanged` on the **public online booking submit** — the highest-impact one, since it's guest-facing not just staff-facing), and `Staff\FrontDeskController` (x7, fixed earlier the same day — see above). A 10th, previously-uncounted pair was also found and fixed in `Admin\PaymentController` (`PaymentReceived` on manual payment record + refund) — **the refund one was the worst of all of them**, since it sat inside a `DB::transaction()` closure: an uncaught Pusher failure there would have silently rolled back the whole refund payment record, even though the refund was otherwise entirely valid. Confirmed this wasn't hypothetical (it actually threw with the current local Pusher credentials) and verified the fix with a live test: the refund payment now persists correctly even when the broadcast fails. All 18 call sites across the whole app now wrapped in try/catch + `Log::error()`, matching the pattern `NotificationHelper::create()` already used since v5.2 |
| **(v5.2)** Admin/Staff seeded account passwords | 🔲 **Change before real use** | `AdminSeeder` sets `admin@villaelenareosrt.com` / `Admin@1234` and `staff@villaelenareosrt.com` / `Staff@1234` on the live Aiven database — fine for solo testing, must be changed before anyone else gets the link |
| ~~(v5.6) Automated refunds via the PayMongo Refunds API~~ | ❌ **Impossible — closed (v5.7)** | Verified against live payments: `POST /v1/refunds` returns `400 parameter_invalid — "Refunds are not allowed for payments with source type qrph."` Tried on both payments, full and partial amounts, before and after settlement. Not a timing or balance issue; QR Ph simply cannot be refunded through PayMongo at all, by API or dashboard. Refunds must be sent out-of-band via the resort's own GCash/Maya. This is now a permanent property of the design, not a backlog item |
| **(v5.7)** Safeguards on "Mark Paid Out" | 🔲 **Recommended — the flow is now unguarded** | Since no automation is possible, the honour-system button is the only control on real money leaving. It was skipped during live testing by someone who knew the process, and the app then told the guest "Refund Sent" while holding the cash. Three fixes proposed: (1) state plainly in the Issue Refund UI that PayMongo cannot refund QR Ph and the transfer must be made by hand; (2) surface the guest's payout destination — `users.phone` is populated for all 10 users and is the GCash number — in the Mark Paid Out confirmation; (3) require the GCash/Maya transfer reference, stored in the refund row's `reference_number`. (3) is the substantive one: a reference cannot be supplied if the transfer never happened, which turns a checkbox into evidence and creates a trail reconcilable against PayMongo |
| **(v5.5)** Booking `VE-OLRWMOGX` — ₱3,999.96 recorded as paid with no `Payment` row | 🔲 **Needs a human decision** | Found while scanning all 45 bookings for stored-vs-computed drift. The code path that caused it is fixed (walk-in now rejects an amount with no payment method), but this existing row is **real business data** and was deliberately left untouched. Someone has to establish whether ₱4,000 was actually received, then either create a matching cash `Payment` row or reset the booking to unpaid |
| **(v5.5)** Housekeeping — 3 dead `task_type` values, and no way to create a task manually | 🔲 **Open** | All 53 tasks are `checkout_clean`; `daily_clean`, `maintenance`, and `inspection` exist in the ENUM but no code ever creates them. The same gap explains why: tasks are only ever generated automatically at check-in, so staff can't log e.g. a broken aircon. Either build manual task creation (which makes the three values usable) or drop them from the ENUM |
| **(v5.5)** Only 5 housekeeping tasks remain open, all with real deadlines | ✅ **Resolved (2026-08-15)** | Was 13 open with 11 overdue (worst 48 days) and 6 stuck in `in_progress`. Backlog closed by migration; recurrence prevented by the deadline display + one-click banner. Kept here as the record of what the numbers were before |
| **(v5.5)** Docker container can silently run stale code against a migrated database | 🔲 **Workflow discipline, not a code fix** | `resources/`, `app/`, `routes/`, `config/`, `database/` only sync into the container under `docker compose up --watch`. A plain `docker compose up` freezes them at image-build time — but migrations run from the host still hit the same MySQL, producing old code against a new schema (observed: container writing `payment_type='deposit'` into an ENUM that no longer had it → `Data truncated`). Restart the container after any migration if Watch isn't running; diagnose by grepping the file **inside** the container rather than assuming a cache |

---

## 15. Deployment

### 15.1 Stack

| Concern | Service | Free tier used |
|---|---|---|
| Hosting | Render (Web Service, Docker runtime) | Yes — spins down after 15 min idle, 30–60s cold start, no persistent disk, 750 instance-hrs/month pooled |
| Database | Aiven (MySQL, "Developer Tier") | Yes — always-free, no card, no expiry; 1GB storage/RAM, 1 CPU |
| Image storage | Cloudinary | Yes — 25GB storage/bandwidth |
| Realtime | Pusher (Channels) | Yes — 200 concurrent connections, 200K msgs/day |
| Email | Brevo (HTTPS API, not SMTP) | Yes — 300 emails/day |
| Payments | PayMongo | Test-mode keys |
| AI chatbot | Groq | Free tier |

### 15.2 Required environment variables

See `.env.example` for the full, commented list — every variable there has a note on where to get its value. Render-specific ones (set in the dashboard's Environment tab, not committed):

- `APP_KEY` — let Render auto-generate (`render.yaml` has `generateValue: true`); don't reuse the local one
- `APP_URL` — the Render service URL
- `DB_CONNECTION=mysql`, `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` — from Aiven's console
- `MYSQL_ATTR_SSL_CA=/etc/ssl/certs/aiven-ca.pem` + `AIVEN_CA_CERT` (the full `.pem` contents) — Aiven requires TLS; `docker/start.sh` writes the cert file from this env var at container start
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync` — no persistent disk for file-based drivers; sync queue avoids needing a separate worker service (fine at this traffic level)
- `LOG_CHANNEL=stderr` — a log file inside the container is invisible in Render's dashboard; stderr streams there directly
- `CLOUDINARY_URL=cloudinary://<key>:<secret>@<cloud_name>` — from the Cloudinary dashboard's home page
- `BROADCAST_CONNECTION=pusher` + `PUSHER_APP_ID`/`PUSHER_APP_KEY`/`PUSHER_APP_SECRET`/`PUSHER_APP_CLUSTER=ap1`
- `MAIL_MAILER=brevo` + `MAILER_DSN=brevo+api://<API_KEY>@default` — **must be the API key** (`xkeysib-...`) from Brevo's Settings → SMTP & API → **API Keys** tab, not the SMTP key (`xsmtpsib-...`) from the SMTP tab; using the wrong one fails with "Key not found (401)"
- `MAIL_FROM_ADDRESS` — must be a verified sender in Brevo (Settings → Senders & IP)
- `CRON_SECRET` — let Render auto-generate; guards the `/cron/run-schedule/{token}` route
- `RUN_MIGRATIONS` — `true` only for a deploy that needs to run pending migrations, then back to `false`

### 15.3 Why Render blocks SMTP (and why Brevo is used via API, not SMTP)

Since September 2025, Render's free web services block **all** outbound traffic on SMTP ports 25, 465, and 587 — for any host, not just specific providers. Gmail SMTP (what local dev still uses) and Brevo's own SMTP relay would both fail identically here; this isn't a Brevo-specific limitation, it's a Render network-level block that only lifts on a paid instance. The fix used instead: `symfony/brevo-mailer` + `symfony/http-client`, wired up as a custom `brevo` Laravel mailer in `AppServiceProvider::boot()` (`Mail::extend('brevo', ...)` using `BrevoTransportFactory` + a `brevo+api://` DSN), which talks to Brevo over HTTPS — not blocked.

### 15.4 Running one-off commands against the production database

Render's free plan has no Shell tab, so there's no way to run `php artisan migrate` or similar directly against the live container. Instead, `config/database.php` has a separate `aiven` connection (reads `AIVEN_DB_HOST`/`AIVEN_DB_PORT`/`AIVEN_DB_DATABASE`/`AIVEN_DB_USERNAME`/`AIVEN_DB_PASSWORD`/`AIVEN_DB_SSL_CA` — a **different** set of env vars from the ones Render uses, added to the local `.env` only) that lets any artisan command target Aiven directly from a developer machine, e.g.:

```
php artisan migrate --force --database=aiven
php artisan migrate:fresh --seed --force --database=aiven   # full reset + reseed
```

`AIVEN_DB_SSL_CA` points at a local copy of the same Aiven CA cert (`storage/aiven-ca.pem`, gitignored). Local MySQL connections needed `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false` added alongside the CA option to actually connect (plain `MYSQL_ATTR_SSL_CA` alone wasn't enough from this machine).

### 15.5 Seeded production accounts

`AdminSeeder` (run via `--seed` during the initial `migrate:fresh`) created:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@villaelenareosrt.com` | `Admin@1234` |
| Staff | `staff@villaelenareosrt.com` | `Staff@1234` |
| Customer (sample) | `guest@example.com` | `Guest@1234` |

**Change these before sharing the live link with anyone else** — see Pending/Optional.

### 15.6 Deploying changes

Push to `main` on GitHub (`RexYep/Villa_Elena`) — Render auto-deploys from there. `render.yaml` is a Blueprint; if the Render service was created manually rather than via Blueprint sync, new env vars added to `render.yaml` need to also be added by hand in the dashboard the first time.

#### 🔴 Check the production schema BEFORE pushing, every time

```bash
php artisan migrate:status --database=aiven | grep -c Pending
```

**The number that matters is 0.** On 2026-09-04 it was **16** — Aiven was still on the 2026-08-12 schema while local had moved five doc versions past it. Pushing without noticing would have 500'd the live site instantly: no `refund_transfers`, no seasonal columns on `discounts`, no `qrph` in the `payments.payment_method` ENUM, no `recommendations` table.

Nothing warns about this. `RUN_MIGRATIONS` defaults to `false`, which is correct (migrations should be a decision, not a side effect of every deploy) — but the consequence is that the schema drifts silently for as long as nobody looks, and a green deploy proves only that the container started.

Order of operations, and why it is this way:

1. **Commit first**, but don't push.
2. **Back up** what production actually holds — a few `DB::connection('aiven')->table(...)->get()` calls dumped to JSON is enough at this data volume, and takes seconds.
3. **`php artisan migrate --force --database=aiven`** from a dev machine. Preferred over `RUN_MIGRATIONS=true` because the output is in front of you: if migration 9 of 16 fails you see which and why, whereas inside `start.sh` a failure just aborts container start (`set -e`) with the reason buried in Render's log stream.
4. **Push immediately after.** Between step 3 and the deploy landing, production runs *old code against the new schema* — the exact hazard the v5.5 stale-Docker row describes. It is a small window at this traffic level, not a safe one: old code writing `payment_method = 'gcash'` into an ENUM that now only holds `qrph`/`cash` is a `Data truncated` error.
5. **Verify the routes, not the build.** A build that goes green says nothing about whether the app works. Curl the new routes: `404` means the old code is still being served, `302` (redirect to login) means the new routes are registered. The landing page returning `200` is a real schema check too — it renders the promo banner through `Discount::publicActive()`, which reads the seasonal columns.

Verified this way on 2026-09-04: 16 migrations applied to Aiven with all data intact (6 users, 1 booking, 1 payment), `payment_method` confirmed as `enum('qrph','cash')`, then pushed; `/admin/prescriptive` went `404 → 302` in about two minutes.

#### Env vars that were missing from `render.yaml` (fixed 2026-09-04)

Found by diffing `render.yaml` against every `env()` call in `config/`. All four fail **silently** — nothing errors, the behaviour is just quietly wrong, which is why they survived several deploys:

| Var | Was | Now | Silent consequence |
|---|---|---|---|
| `BROADCAST_CONNECTION` | `log` | `pusher` | The Pusher keys were filled in and the bell still did nothing — events went to the log channel and no browser ever heard them |
| `MAIL_FROM_NAME` | absent | `Villa Elena Resort` | `config/mail.php`'s default applies, so every booking confirmation was sent from a sender named **`Example`** |
| `GROQ_MODEL` | absent | `qwen/qwen3.6-27b` | Production fell back to `config/services.php`'s default and ran a *different model* from the one local dev was tested against |
| `APP_NAME` | `Villa Elena` | `Villa Elena Resort` | Cosmetic — page titles and email footers disagreed with local |

`REDIS_*`, `AIVEN_DB_*`, and the `MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD` SMTP block are all deliberately **not** on Render: local-only cache, a dev-machine-only connection, and a transport Render blocks outright.

### 15.7 Running the same stack locally (Docker, NEW v5.3)

`docker compose up --watch` runs the **same `Dockerfile`** Render builds from — same PHP 8.2-fpm-alpine + nginx + supervisord — so changes that might behave differently under that stack can be caught locally instead of only after a deploy. `php artisan serve` still works exactly as before and isn't affected by any of this; Docker is an additional option, not a replacement.

**Setup, one-time:**
- Requires Docker Desktop (already installed here — no separate Linux/WSL setup needed beyond what Docker Desktop sets up itself).
- The container reaches the local MySQL server via `host.docker.internal`, using a scoped MySQL user (not `root`) created once:
  ```sql
  CREATE USER 'villa_docker'@'%' IDENTIFIED WITH mysql_native_password BY '<password>';
  GRANT ALL PRIVILEGES ON villa_elena_db.* TO 'villa_docker'@'%';
  FLUSH PRIVILEGES;
  ```
  The password is stored in `.env` as `DOCKER_DB_PASSWORD` (used only by `docker-compose.yml`, not read by the app itself — kept separate from the app's real `DB_PASSWORD` so `root`'s network exposure never has to change).

**Day to day:**
```bash
docker compose up --watch     # start app + redis, keep this running while you work
docker compose down           # stop
docker compose logs -f app    # tail logs (same as Render's dashboard Logs tab)
```

**Why not a plain bind mount:** the first working version bind-mounted the whole repo into the container — simple, but every `vendor/` file read crosses the Windows↔WSL2 filesystem boundary on every request, which made page loads take multiple seconds (see Known Issues Fixed). `docker-compose.yml` instead bind-mounts only `storage/` (needs to persist uploads/sessions/cache across restarts) and uses **Compose Watch** to sync `app/`, `resources/`, `routes/`, `config/`, `database/` into the container as they change — edits still show up in a second or two, but requests read from the container's own fast filesystem. Editing `composer.json`, `package.json`, the `Dockerfile`, or anything under `docker/` triggers an automatic image rebuild instead of a sync (declared under `develop.watch` in `docker-compose.yml`).

**`bootstrap/cache/` is deliberately not bind-mounted or synced** — it holds Laravel's compiled package-manifest cache. The host's version reflects a full `composer install` (dev packages included); mounting it over the container's clean `--no-dev` build reproduces the same "class not found" crash that motivated adding `.dockerignore` in the first place (see Known Issues Fixed). Let the container regenerate its own.

**`.env` is shared as-is** (`env_file: .env` in `docker-compose.yml`) between `php artisan serve` and Docker — only `DB_HOST`/`DB_USERNAME`/`DB_PASSWORD` and `REDIS_HOST` are overridden per-service in `docker-compose.yml`, since those are genuine connection details (how to reach services from inside vs. outside a container), not application behavior. Everything else — mail driver, filesystem disk, broadcast connection — stays identical between the two ways of running the app locally, which is the point: nothing about *how the app behaves* should depend on whether you're running it via `php artisan serve` or Docker.

---

*Documentation updated for Villa Elena Resort Management System — Capstone Project 2026*
*Version 5.7 — Updated August 2026 (QR Ph Payment Migration, Verified on Live Keys, Webhook Made Reachable, QR Ph Refunds Confirmed Impossible)*
