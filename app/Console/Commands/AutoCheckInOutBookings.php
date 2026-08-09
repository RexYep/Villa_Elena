<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Notification;
use App\Models\StaffLog;
use App\Helpers\NotificationHelper;
use Illuminate\Console\Command;

class AutoCheckInOutBookings extends Command
{
    /**
     * Ang name at signature ng Artisan command.
     */
    protected $signature = 'bookings:auto-checkinout';

    /**
     * Paglalarawan ng command.
     */
    protected $description = 'Awtomatikong nagcha-check-in/check-out ng mga booking base sa naka-schedule na petsa/oras, nang hindi na kailangan pang mag-click si staff.';

    public function handle(): int
    {
        $this->autoCheckIns();
        $this->autoCheckOuts();
        $this->cancelStalePendingBookings();

        return self::SUCCESS;
    }

    // ── Auto Check-in ────────────────────────────────────────────────
    // Anumang "confirmed" na booking na dumating na ang naka-schedule
    // na check-in datetime ay awtomatikong magiging "checked_in" —
    // hindi na kailangan pang mano-manong i-click ni staff, at hindi
    // na hinihintay ang pisikal na pagdating ng guest.
    private function autoCheckIns(): void
    {
        $bookings = Booking::where('status', 'confirmed')
            ->with(['user', 'property'])
            ->get();

        foreach ($bookings as $booking) {
            if ($booking->checkInDateTime()->gt(now())) {
                continue; // hindi pa oras
            }

            // Kung may natitirang balance, HINDI ito dapat i-auto-check-in.
            // Ang buong punto ng "Remaining Balance" modal sa Frontdesk ay
            // kailangan ng explicit, pinangalanang staff member na mag-
            // authorize ng deferred check-in (checkbox + StaffLog entry na
            // nagpapakita kung sino ang nag-decide). Walang taong physically
            // nag-de-decide dito sa automated na command, kaya sa halip na
            // tahimik na i-deferred ito, iiwan na lang natin itong "confirmed"
            // at aalertuhan si admin/staff na dumating na ang oras ng check-in
            // pero may balance pa — sila na ang bahalang mag-desisyon nang
            // personal sa Frontdesk.
            if ($booking->balance_due > 0) {
                // Ang command na ito ay tumatakbo every minute (see
                // routes/console.php). Kung wala tayong dedup guard dito,
                // mag-a-alert ito ng BAGONG notification + StaffLog entry
                // KADA MINUTO habang naka-"confirmed" pa ang booking na
                // ito at may balance pa — spinaspam ang admin notification
                // bell. Sapat na ang ISANG beses na alert kada booking;
                // titigil na ito hanggang ma-resolve ni staff sa Frontdesk
                // (deferred check-in o bayad), dahil sa punto na iyon
                // aalis na rin ang booking sa "confirmed" status na ito
                // hinahanap ng query sa itaas.
                $alreadyAlerted = StaffLog::where('target_table', 'bookings')
                    ->where('target_id', $booking->id)
                    ->where('action', 'auto_checkin_skipped_balance')
                    ->exists();

                if (!$alreadyAlerted) {
                    NotificationHelper::notifyAdmin(
                        'Guest Arrived — May Balance Pa',
                        "Nag-dating na ang check-in time ni {$booking->user->full_name} para sa {$booking->booking_ref}, pero may natitirang balance na ₱" . number_format($booking->balance_due, 2) . ". Hindi ito awtomatikong na-check-in — kailangan ng staff na mag-decide sa Frontdesk (bayaran ngayon o i-confirm ang deferred check-in).",
                        route('admin.bookings.show', $booking, false)
                    );

                    StaffLog::record('auto_checkin_skipped_balance', 'bookings', $booking->id,
                        "System hindi awtomatikong ni-check-in si {$booking->user->full_name} para sa {$booking->booking_ref} (scheduled: {$booking->checkInDateTime()->format('M d, Y g:i A')}) dahil may balance na ₱" . number_format($booking->balance_due, 2) . " — kailangan ng manual na staff decision sa Frontdesk.");

                    $this->warn("⚠️  Hindi na-auto-check-in (may balance): {$booking->booking_ref} ({$booking->user->full_name})");
                }

                continue;
            }

            $booking->update([
                'status'          => 'checked_in',
                'actual_check_in' => now(),
            ]);
            $booking->property->update(['status' => 'occupied']);

            NotificationHelper::guestCheckedIn($booking);

            // Kapareho ng ginagawa sa manual check-in — gumawa (o gamitin
            // na existing) ng checkout-cleaning housekeeping task.
            HousekeepingTask::firstOrCreate(
                ['booking_id' => $booking->id, 'task_type' => 'checkout_clean'],
                [
                    'property_id'    => $booking->property_id,
                    'due_date'       => $booking->check_out_date,
                    'scheduled_date' => $booking->check_out_date,
                    'status'         => 'pending',
                    'notes'          => "Post-checkout cleaning for booking {$booking->booking_ref}",
                ]
            );

            Notification::create([
                'user_id' => $booking->user_id,
                'type'    => 'in_app',
                'title'   => 'Welcome to Villa Elena!',
                'message' => "Awtomatiko kang na-check-in sa {$booking->property->property_name}. Enjoy your stay! Check-out: {$booking->check_out_date->format('F d, Y')}.",
                'link'    => route('customer.bookings.show', $booking, false),
                'is_read' => 0,
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            StaffLog::record('auto_check_in', 'bookings', $booking->id,
                "System auto-checked-in {$booking->user->full_name} for {$booking->booking_ref} (scheduled: {$booking->checkInDateTime()->format('M d, Y g:i A')}).");

            $this->info("✅ Auto checked-in: {$booking->booking_ref} ({$booking->user->full_name})");
        }
    }

    // ── Auto Check-out ───────────────────────────────────────────────
    // Anumang "checked_in" na booking na dumating na ang naka-schedule
    // na check-out datetime (walang extension) ay awtomatikong
    // magiging "checked_out".
    private function autoCheckOuts(): void
    {
        $bookings = Booking::where('status', 'checked_in')
            ->with(['user', 'property'])
            ->get();

        foreach ($bookings as $booking) {
            if ($booking->checkOutDateTime()->gt(now())) {
                continue; // hindi pa oras
            }

            $booking->update([
                'status'           => 'checked_out',
                'actual_check_out' => now(),
            ]);
            $booking->property->update(['status' => 'available']);

            NotificationHelper::guestCheckedOut($booking);

            HousekeepingTask::where('booking_id', $booking->id)
                ->where('task_type', 'checkout_clean')
                ->update(['status' => 'in_progress']);

            Notification::create([
                'user_id' => $booking->user_id,
                'type'    => 'in_app',
                'title'   => 'Check-out Complete',
                'message' => "Thank you for staying at Villa Elena! Booking {$booking->booking_ref} is now complete. We hope to see you again!",
                'link'    => route('customer.bookings.show', $booking, false),
                'is_read' => 0,
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            StaffLog::record('auto_check_out', 'bookings', $booking->id,
                "System auto-checked-out {$booking->user->full_name} for {$booking->booking_ref} (scheduled: {$booking->checkOutDateTime()->format('M d, Y g:i A')}).");

            $this->info("✅ Auto checked-out: {$booking->booking_ref} ({$booking->user->full_name})");
        }
    }

    // ── Bonus: Cancel stale unpaid "pending" bookings ─────────────────
    // Kaugnay ng anti-abuse feature (#3) — mga "pending" na booking na
    // lumagpas na sa "Booking Hold" window (Settings → Booking Rules,
    // Booking::pendingHoldMinutes()) ay itinuturing nang abandoned. Ang
    // Booking::hasConflict() ay hindi na sila binibilang sa slot-
    // blocking simula pa noong idinagdag natin ito, pero dito naman
    // natin sila i-formally cancel sa DB para malinis ang records at
    // makita ni staff kung bakit na-cancel.
    private function cancelStalePendingBookings(): void
    {
        $holdMinutes = Booking::pendingHoldMinutes();

        $stale = Booking::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($holdMinutes))
            ->with(['user', 'property'])
            ->get();

        $holdLabel = $holdMinutes % 60 === 0
            ? ($holdMinutes / 60) . '-oras'
            : $holdMinutes . '-minuto';

        foreach ($stale as $booking) {
            $booking->update([
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancellation_reason' => "Auto-cancelled ng system — hindi natapos ang bayad sa loob ng {$holdLabel} na grace period.",
                'balance_due'         => 0,
            ]);

            NotificationHelper::notifyGuest(
                $booking->user_id,
                'Booking Auto-Cancelled — Grace Period Expired',
                "Your booking {$booking->booking_ref} for {$booking->property->property_name} was automatically cancelled because the required 50% downpayment wasn't completed within the {$holdLabel} grace period. Feel free to book again if the dates are still available.",
                route('customer.bookings.show', $booking, false)
            );

            StaffLog::record('auto_cancelled_stale_booking', 'bookings', $booking->id,
                "System auto-cancelled unpaid pending booking {$booking->booking_ref} ({$holdMinutes}+ minutes since created, no payment received).");

            $this->info("🗑️ Auto-cancelled stale pending: {$booking->booking_ref}");
        }
    }
}