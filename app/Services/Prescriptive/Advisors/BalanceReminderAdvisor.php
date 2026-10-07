<?php

namespace App\Services\Prescriptive\Advisors;

use App\Models\Booking;
use App\Models\Recommendation;
use App\Services\Prescriptive\OccupancyCalendar;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * BOOKING MANAGEMENT — "this guest arrives soon and still owes a balance."
 *
 *   IF   a confirmed booking checks in within the owner's reminder days
 *   AND  it still has a balance due
 *   THEN send that guest an in-app reminder with the link to pay.
 *
 * The number of days is `prescriptive_reminder_days` (default 3).
 *
 * One reminder per booking, ever: the card's identity is the booking, so
 * once it is sent or dismissed it does not come back tomorrow and nag.
 */
class BalanceReminderAdvisor extends Advisor
{
    public function type(): string
    {
        return Recommendation::TYPE_BALANCE;
    }

    public function generate(OccupancyCalendar $calendar): array
    {
        $days = max(0, (int) OccupancyCalendar::setting('prescriptive_reminder_days', 3));

        return Booking::where('property_id', $calendar->villa()->id)
            ->where('status', 'confirmed')
            ->where('balance_due', '>', 0)
            ->whereNotNull('user_id')
            ->whereBetween('check_in_date', [
                Carbon::today()->toDateString(),
                Carbon::today()->addDays($days)->toDateString(),
            ])
            ->with('user:id,full_name')
            ->orderBy('check_in_date')
            ->orderBy('id')
            ->get()
            // A booking whose check-in time has already passed today is the
            // front desk's conversation now, not a reminder.
            ->filter(fn (Booking $booking) => $booking->user && $booking->checkInDateTime()->isFuture())
            ->map(fn (Booking $booking) => $this->card($booking))
            ->values()
            ->all();
    }

    private function card(Booking $booking): array
    {
        $guest = Str::limit($booking->user->full_name, 40);
        $checkIn = $booking->checkInDateTime();
        $due = $this->peso((float) $booking->balance_due);

        $daysAway = (int) Carbon::today()->diffInDays($checkIn->copy()->startOfDay());

        $when = match ($daysAway) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$daysAway} days",
        };

        return [
            'type' => Recommendation::TYPE_BALANCE,
            'title' => "Remind {$guest} about the {$due} balance",
            'summary' => sprintf(
                'Booking %s checks in %s (%s) and %s is still unpaid. The balance is due before or at check-in.',
                $booking->booking_ref,
                $when,
                $checkIn->format('D, M j'),
                $due
            ),
            'evidence' => [
                sprintf(
                    'Paid so far: %s of %s.',
                    $this->peso((float) $booking->amount_paid),
                    $this->peso((float) $booking->total_amount)
                ),
                sprintf(
                    'Check-in: %s at %s%s.',
                    $checkIn->format('D, M j'),
                    $checkIn->format('g:i A'),
                    $booking->slot_name ? " ({$booking->slot_name})" : ''
                ),
                'No reminder has been sent for this booking.',
            ],
            'target_start' => $checkIn->toDateString(),
            'target_end' => $checkIn->toDateString(),
            'slot' => null,
            'action_type' => Recommendation::ACTION_SEND_REMINDER,
            'action_payload' => [
                'booking_id' => $booking->id,
                'booking_ref' => $booking->booking_ref,
                'guest_name' => $guest,
            ],
            'fingerprint' => $this->fingerprint([
                Recommendation::TYPE_BALANCE,
                $booking->id,
            ]),
        ];
    }
}
