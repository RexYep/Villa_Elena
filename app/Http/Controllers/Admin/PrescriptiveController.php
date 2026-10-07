<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Concerns\ThrottlesAiRefresh;
use App\Http\Controllers\Controller;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Discount;
use App\Models\HousekeepingTask;
use App\Models\PricingRule;
use App\Models\Property;
use App\Models\Recommendation;
use App\Models\Setting;
use App\Models\StaffLog;
use App\Services\FrontdeskBroadcast;
use App\Services\Prescriptive\BriefingWriter;
use App\Services\Prescriptive\OccupancyCalendar;
use App\Services\Prescriptive\PrescriptiveEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Recommendations — ADMIN ONLY, like Promotions.
 *
 * A promo lowers revenue and a rate change raises what guests pay, so one
 * role decides both. The cards also show internal business data (which
 * dates are quiet, who still owes a balance) that is not for the frontdesk.
 *
 * THE POINT OF THIS CONTROLLER: this is where a suggestion becomes REAL.
 * Until the button is pressed a `Recommendation` is text in a table — no
 * price has changed, no date is closed, no task or reminder has been sent.
 */
class PrescriptiveController extends Controller
{
    use ThrottlesAiRefresh;

    public function index()
    {
        $open = Recommendation::open()
            // Soonest first: the card about this weekend matters before the
            // one about next month.
            ->orderBy('target_start')
            ->orderBy('id')
            ->get()
            // A card whose date has passed must not offer its button even
            // while its status is still `new` — the daily run just has not
            // expired it yet.
            ->reject(fn (Recommendation $r) => $r->isExpired())
            ->values();

        $history = Recommendation::actedOn()
            ->orderByDesc('updated_at')
            ->limit(15)
            ->with('appliedBy:id,full_name')
            ->get();

        return view('admin.prescriptive.index', [
            'open' => $open,
            'history' => $history,
            'results' => $history->mapWithKeys(fn (Recommendation $r) => [$r->id => $this->resultOf($r)]),
            'rules' => $this->rules(),
            'historyDays' => (int) config('prescriptive.history_days', 180),
            'lastGenerated' => Recommendation::max('generated_at'),
            // No AI call here — this only reads what the last run wrote.
            'briefing' => Setting::get(BriefingWriter::SETTING_TEXT, ''),
        ]);
    }

    /**
     * Runs the rules now, instead of waiting for the nightly run.
     *
     * On a cooldown like Insights and Forecast: `BriefingWriter::write()` is
     * an AI call, on the same budget as the guest chatbot and review
     * moderation.
     */
    public function regenerate(PrescriptiveEngine $engine, BriefingWriter $briefing)
    {
        if ($wait = $this->aiRefreshCooldown('prescriptive')) {
            return back()->with('error', $this->aiRefreshCooldownMessage('recommendations', $wait));
        }

        $stats = $engine->run();

        // The briefing names specific cards; left alone it would talk about
        // cards that just left the list.
        $briefing->write();

        // This rewrites the card set and expires rows in bulk
        // (PrescriptiveEngine::expireStale()), so a card that vanished
        // before anyone acted on it must leave a trace of who refreshed.
        StaffLog::record(
            'regenerated_recommendations',
            'recommendations',
            null,
            sprintf(
                'Regenerated recommendations — %d new, %d updated, %d expired',
                $stats['created'],
                $stats['refreshed'],
                $stats['expired']
            )
        );

        return back()->with('success', sprintf(
            'Recommendations refreshed — %d new, %d updated, %d no longer apply.',
            $stats['created'],
            $stats['refreshed'],
            $stats['expired']
        ));
    }

    /**
     * Carries out a recommendation.
     *
     * The card is claimed inside a locked transaction BEFORE the record is
     * made, so a double click cannot create two promos or send two
     * reminders.
     */
    public function apply(Recommendation $recommendation)
    {
        if (! $recommendation->isActionable()) {
            return back()->with('error', 'That recommendation is no longer open — it may have been applied, dismissed, or its date has passed.');
        }

        // Has the world changed since the card was worked out? It may have
        // been sitting in an open tab for hours.
        if ($problem = $this->staleReason($recommendation)) {
            return back()->with('error', $problem.' Press Refresh to recompute the recommendations.');
        }

        try {
            $result = DB::transaction(function () use ($recommendation) {
                $fresh = Recommendation::whereKey($recommendation->id)->lockForUpdate()->first();

                if (! $fresh || $fresh->status !== 'new') {
                    return null;
                }

                $record = match ($fresh->action_type) {
                    Recommendation::ACTION_CREATE_PROMO => $this->createPromo($fresh),
                    Recommendation::ACTION_CREATE_PRICING_RULE => $this->createPricingRule($fresh),
                    Recommendation::ACTION_CREATE_BLOCK => $this->createBlock($fresh),
                    Recommendation::ACTION_CREATE_TASK => $this->createTask($fresh),
                    Recommendation::ACTION_SEND_REMINDER => $this->sendReminder($fresh),
                    default => throw new \RuntimeException("Unknown action type: {$fresh->action_type}"),
                };

                $fresh->update([
                    'status' => 'applied',
                    'applied_at' => now(),
                    'applied_by' => Auth::id(),
                    'applied_record_type' => $record->getTable(),
                    'applied_record_id' => $record->getKey(),
                ]);

                return [$fresh, $record];
            });
        } catch (\Throwable $e) {
            // Never `$e->getMessage()` here: everything inside the
            // transaction is DB work, so the only thing it could show the
            // admin is internals (a failed INSERT once printed its whole SQL
            // with bound values into this flash message).
            report($e);

            return back()->with('error', 'Could not apply that recommendation — nothing was created, and the error has been logged.');
        }

        if ($result === null) {
            return back()->with('error', 'That recommendation was already acted on.');
        }

        [$applied, $record] = $result;

        StaffLog::record(
            'applied_recommendation',
            'recommendations',
            $applied->id,
            "Applied recommendation '{$applied->title}'"
        );

        // A second row, using the SAME action name the manual path uses, so
        // filtering the audit log by `created_promo` (or a block, a rule, a
        // task) does not silently leave out the ones made from this page.
        // Two rows for one click answer two questions: "who applied
        // recommendation #12" and "where did promo #34 come from".
        StaffLog::record(
            $this->creationAction($applied->action_type),
            $applied->applied_record_type,
            $record->getKey(),
            $this->creationDescription($applied, $record)
        );

        // After the commit, never inside it: the staff frontdesk should only
        // hear about a task that really exists.
        if ($applied->action_type === Recommendation::ACTION_CREATE_TASK) {
            FrontdeskBroadcast::send(
                'task_assigned',
                "URGENT task from admin: \"{$record->headline}\" — due {$record->due_label}.",
                taskId: $record->id,
                actor: Auth::user()->full_name,
            );
        }

        return back()->with('success', $this->successMessage($applied, $record));
    }

    public function dismiss(Request $request, Recommendation $recommendation)
    {
        if ($recommendation->status !== 'new') {
            return back()->with('error', 'That recommendation is no longer open.');
        }

        $data = $request->validate([
            'dismiss_reason' => 'nullable|string|max:255',
        ]);

        $recommendation->update([
            'status' => 'dismissed',
            'dismissed_at' => now(),
            'dismiss_reason' => $data['dismiss_reason'] ?? null,
        ]);

        StaffLog::record(
            'dismissed_recommendation',
            'recommendations',
            $recommendation->id,
            "Dismissed recommendation '{$recommendation->title}'"
        );

        return back()->with('success', 'Recommendation dismissed. It will not be suggested again.');
    }

    /**
     * Turns off the rate a peak-pricing card created.
     *
     * It lives here because a pricing rule has no page of its own: a promo
     * can be switched off under Promotions and a block removed from the
     * Calendar, but without this a raised rate could only be undone in the
     * database.
     */
    public function turnOffRate(Recommendation $recommendation)
    {
        $rule = $recommendation->status === 'applied'
            && $recommendation->action_type === Recommendation::ACTION_CREATE_PRICING_RULE
                ? PricingRule::find($recommendation->applied_record_id)
                : null;

        if (! $rule || ! $rule->is_active) {
            return back()->with('error', 'That rate is not active any more.');
        }

        $rule->update(['is_active' => 0]);

        StaffLog::record(
            'deactivated_pricing_rule',
            'pricing_rules',
            $rule->id,
            "Turned off pricing rule '{$rule->label}' (from recommendation #{$recommendation->id})"
        );

        return back()->with('success', "The rate '{$rule->label}' is turned off — those dates are back to the normal price. Bookings already made keep what they were charged.");
    }

    // ── The rules, in words ────────────────────────────────────────

    /**
     * The five decision rules with the owner's current numbers filled in —
     * shown on the page so nobody has to open the code to learn why a card
     * appeared.
     *
     * @return array<int, array{decision: string, when: string, then: string}>
     */
    private function rules(): array
    {
        $number = fn (string $key, float $default) => rtrim(rtrim(
            number_format(OccupancyCalendar::setting($key, $default), 2, '.', ''), '0'), '.');

        $quiet = $number('prescriptive_quiet_threshold', 30);
        $promo = $number('prescriptive_promo_percent', 10);
        $busy = $number('prescriptive_busy_threshold', 70);
        $increase = $number('prescriptive_increase_percent', 10);
        $maintenance = (int) OccupancyCalendar::setting('prescriptive_maintenance_days', 2);
        $reminder = (int) OccupancyCalendar::setting('prescriptive_reminder_days', 3);
        $gap = rtrim(rtrim(number_format((float) config('prescriptive.turnover_gap_hours', 3), 1), '0'), '.');

        return [
            [
                'decision' => 'Promotion',
                'when' => "Predicted occupancy is below {$quiet}%, with no holiday and no promo already running",
                'then' => "Offer a {$promo}% promo on those dates",
            ],
            [
                'decision' => 'Peak pricing',
                'when' => "Predicted occupancy is {$busy}% or more — or the date is a public holiday",
                'then' => "Raise the rate {$increase}% on those dates",
            ],
            [
                'decision' => 'Maintenance',
                'when' => 'No maintenance is scheduled yet',
                'then' => "Close the villa on the free {$maintenance}-day gap whose weekdays are usually quietest",
            ],
            [
                'decision' => 'Housekeeping',
                'when' => "One booking checks out and the next checks in within {$gap} hours",
                'then' => 'Send the frontdesk a turnover-cleaning task',
            ],
            [
                'decision' => 'Booking follow-up',
                'when' => "A confirmed booking checks in within {$reminder} day".($reminder === 1 ? '' : 's').' and still has a balance due',
                'then' => 'Send the guest a reminder with the link to pay',
            ],
        ];
    }

    // ── Internals ──────────────────────────────────────────────────

    /**
     * Why this can no longer be applied — null when it still can.
     *
     * The card was worked out last night and the click is now. In between a
     * guest may have booked those dates, paid the balance, or cancelled —
     * and closing the villa over a real booking is far worse than having no
     * recommendation at all.
     */
    private function staleReason(Recommendation $rec): ?string
    {
        $p = $rec->action_payload ?? [];

        switch ($rec->action_type) {
            case Recommendation::ACTION_CREATE_BLOCK:
                $clash = Booking::where('property_id', $this->villa()->id)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->whereDate('check_in_date', '<=', $rec->target_end)
                    ->whereDate('check_out_date', '>=', $rec->target_start)
                    ->exists();

                return $clash ? 'A booking now falls on those dates, so the villa cannot be blocked.' : null;

            case Recommendation::ACTION_CREATE_PRICING_RULE:
                $taken = PricingRule::where('property_id', $this->villa()->id)
                    ->where('is_active', 1)
                    ->whereDate('start_date', '<=', $rec->target_end)
                    ->whereDate('end_date', '>=', $rec->target_start)
                    ->exists();

                return $taken ? 'A rate is already set for some of those dates.' : null;

            case Recommendation::ACTION_CREATE_TASK:
                $live = Booking::whereIn('id', [$p['out_booking_id'] ?? 0, $p['in_booking_id'] ?? 0])
                    ->whereIn('status', ['confirmed', 'checked_in'])
                    ->count();

                if ($live < 2) {
                    return 'One of those two bookings has changed, so this turnover no longer applies.';
                }

                $sent = HousekeepingTask::where('booking_id', $p['out_booking_id'])
                    ->where('task_type', 'checkout_clean')
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                return $sent ? 'A cleaning task has already been sent for that checkout.' : null;

            case Recommendation::ACTION_SEND_REMINDER:
                $booking = Booking::find($p['booking_id'] ?? 0);

                if (! $booking || $booking->status !== 'confirmed' || (float) $booking->balance_due <= 0) {
                    return 'That booking no longer has a balance to remind the guest about.';
                }

                return null;
        }

        return null;
    }

    private function createPromo(Recommendation $rec): Discount
    {
        // Field by field, never the payload wholesale: a payload carrying
        // `used_count` or `code` would slip past the validation that
        // PromotionController applies and this path does not go through.
        $p = $rec->action_payload;

        return Discount::create([
            'label' => $p['label'],
            'description' => $p['description'] ?? null,
            'type' => 'percentage',
            'value' => $p['value'],
            'start_date' => $p['start_date'],
            'expiry_date' => $p['expiry_date'],
            'applies_to' => $p['applies_to'] ?? 'all',
            'is_public' => $p['is_public'] ?? 1,
            'is_active' => $p['is_active'] ?? 1,
        ]);
    }

    /**
     * A PERCENTAGE rule, which `Property::getPackagePrice()` applies on top
     * of the normal price of each date and slot — so one row is right for a
     * weekday, a weekend and a 22-hour stay alike.
     */
    private function createPricingRule(Recommendation $rec): PricingRule
    {
        $p = $rec->action_payload;

        return PricingRule::create([
            'property_id' => $this->villa()->id,
            'label' => $p['label'],
            'start_date' => $p['start_date'],
            'end_date' => $p['end_date'],
            'price' => $p['price'],
            'type' => 'percentage',
            'is_active' => 1,
        ]);
    }

    private function createBlock(Recommendation $rec): AvailabilityBlock
    {
        $p = $rec->action_payload;

        return AvailabilityBlock::create([
            'property_id' => $this->villa()->id,
            'start_date' => $p['start_date'],
            'end_date' => $p['end_date'],
            'reason' => $p['reason'] ?? 'maintenance',
            'notes' => $p['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    /** The same row `Admin\HousekeepingController::store()` writes by hand. */
    private function createTask(Recommendation $rec): HousekeepingTask
    {
        $p = $rec->action_payload;

        return HousekeepingTask::create([
            'property_id' => $this->villa()->id,
            'created_by' => Auth::id(),
            'booking_id' => $p['out_booking_id'],
            'task_type' => 'checkout_clean',
            'title' => $p['title'],
            'priority' => 'urgent',
            'due_date' => $p['due_date'],
            'due_time' => $p['due_time'],
            'notes' => $p['notes'] ?? null,
            'status' => 'pending',
        ]);
    }

    /**
     * In-app only, never email: the mail quota is shared with 2FA codes,
     * password resets and booking confirmations.
     *
     * The amount is read from the booking NOW, not from the card — the guest
     * may have paid part of it since the card was worked out.
     */
    private function sendReminder(Recommendation $rec): Booking
    {
        $booking = Booking::findOrFail($rec->action_payload['booking_id']);
        $checkIn = $booking->checkInDateTime();

        NotificationHelper::notifyGuest(
            $booking->user_id,
            'Balance Reminder',
            sprintf(
                'Your booking %s checks in on %s at %s. A balance of ₱%s is still due — you can pay it online now, or settle it at check-in.',
                $booking->booking_ref,
                $checkIn->format('D, M j'),
                $checkIn->format('g:i A'),
                number_format((float) $booking->balance_due, 2)
            ),
            route('payment.page', $booking, false)
        );

        return $booking;
    }

    /**
     * The action name the MANUAL path uses for the same kind of record, so
     * one audit-log filter returns both. Keep these in step with
     * PromotionController, CalendarController and HousekeepingController.
     */
    private function creationAction(string $actionType): string
    {
        return match ($actionType) {
            Recommendation::ACTION_CREATE_PROMO => 'created_promo',
            Recommendation::ACTION_CREATE_PRICING_RULE => 'created_pricing_rule',
            Recommendation::ACTION_CREATE_BLOCK => 'created_availability_block',
            Recommendation::ACTION_CREATE_TASK => 'task_created',
            Recommendation::ACTION_SEND_REMINDER => 'sent_balance_reminder',
        };
    }

    private function creationDescription(Recommendation $rec, $record): string
    {
        $origin = "from recommendation #{$rec->id} '{$rec->title}'";

        return match ($rec->action_type) {
            Recommendation::ACTION_CREATE_PROMO => "Created promo '{$record->label}' ({$record->value_label}, {$record->window_label}) {$origin}",
            Recommendation::ACTION_CREATE_PRICING_RULE => "Created pricing rule '{$record->label}' {$origin}",
            // `start_date`/`end_date` are DATE CASTS, so interpolating them
            // directly prints Carbon's full datetime.
            Recommendation::ACTION_CREATE_BLOCK => 'Blocked '.$record->start_date->format('Y-m-d')
                .' to '.$record->end_date->format('Y-m-d')
                ." ({$record->reason}) {$origin}",
            Recommendation::ACTION_CREATE_TASK => "Sent task \"{$record->headline}\" to staff (due {$record->due_label}) {$origin}",
            Recommendation::ACTION_SEND_REMINDER => "Sent a balance reminder for booking {$record->booking_ref} {$origin}",
        };
    }

    private function successMessage(Recommendation $rec, $record): string
    {
        return match ($rec->action_type) {
            Recommendation::ACTION_CREATE_PROMO => "Promo '{$record->label}' is now live — guests booking those dates see the lower price. Edit or switch it off any time under Promotions.",
            Recommendation::ACTION_CREATE_PRICING_RULE => "The rate for {$rec->window_label} is now raised. You can turn it off again in the history below.",
            Recommendation::ACTION_CREATE_BLOCK => "The villa is now blocked for {$rec->window_label}. Those dates are no longer bookable.",
            Recommendation::ACTION_CREATE_TASK => "Task \"{$record->headline}\" sent to the staff frontdesk.",
            Recommendation::ACTION_SEND_REMINDER => "Reminder sent for booking {$record->booking_ref}.",
        };
    }

    /**
     * What came of a decision, in plain words, read live from the record the
     * card created — so the history answers "did it help?" without a model.
     *
     * @return array{text: string, can_turn_off: bool}
     */
    private function resultOf(Recommendation $rec): array
    {
        $result = fn (string $text, bool $canTurnOff = false) => ['text' => $text, 'can_turn_off' => $canTurnOff];

        if ($rec->status !== 'applied') {
            return $result($rec->dismiss_reason ?: '—');
        }

        $count = fn (int $n) => $n.' booking'.($n === 1 ? '' : 's');

        switch ($rec->action_type) {
            case Recommendation::ACTION_CREATE_PROMO:
                $promo = Discount::find($rec->applied_record_id);

                if (! $promo) {
                    return $result('Promo was deleted');
                }

                $used = Booking::where('discount_id', $promo->id)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->count();

                return $result($count($used).' used this promo');

            case Recommendation::ACTION_CREATE_PRICING_RULE:
                $rule = PricingRule::find($rec->applied_record_id);

                if (! $rule) {
                    return $result('Rate was removed');
                }

                $booked = Booking::where('property_id', $rule->property_id)
                    ->whereNotIn('status', ['cancelled', 'no_show'])
                    ->whereDate('check_in_date', '>=', $rule->start_date)
                    ->whereDate('check_in_date', '<=', $rule->end_date)
                    ->where('created_at', '>=', $rec->applied_at ?? $rec->updated_at)
                    ->count();

                return $rule->is_active
                    ? $result($count($booked).' at the higher rate', canTurnOff: $rule->end_date->gte(Carbon::today()))
                    : $result('Turned off · '.$count($booked).' at the higher rate');

            case Recommendation::ACTION_CREATE_BLOCK:
                return $result(AvailabilityBlock::whereKey($rec->applied_record_id)->exists()
                    ? 'Villa closed on those dates'
                    : 'Block was removed');

            case Recommendation::ACTION_CREATE_TASK:
                $task = HousekeepingTask::find($rec->applied_record_id);

                return $result($task ? 'Task: '.$task->status_label : 'Task was deleted');

            case Recommendation::ACTION_SEND_REMINDER:
                $booking = Booking::find($rec->applied_record_id);

                return $result(match (true) {
                    ! $booking => 'Booking no longer exists',
                    $booking->status === 'cancelled' => 'Booking was cancelled',
                    (float) $booking->balance_due <= 0 => 'Balance paid',
                    default => '₱'.number_format((float) $booking->balance_due, 0).' still due',
                });
        }

        return $result('—');
    }

    private function villa(): Property
    {
        return Property::where('type', 'villa')->firstOrFail();
    }
}
