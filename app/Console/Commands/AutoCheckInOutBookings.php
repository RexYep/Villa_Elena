<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RefundTransfer;
use App\Models\StaffLog;
use App\Helpers\NotificationHelper;
use App\Services\RefundTransferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCheckInOutBookings extends Command
{
    /**
     * Ang name at signature ng Artisan command.
     */
    protected $signature = 'bookings:auto-checkinout';

    /**
     * Paglalarawan ng command.
     */
    protected $description = 'Automatically checks in/out bookings based on their scheduled date/time, without requiring staff to manually click.';

    public function handle(): int
    {
        $this->autoCheckIns();
        $this->autoCheckOuts();
        $this->cancelStalePendingBookings();
        // Bago mangulit tungkol sa mga refund, alamin muna kung dumating
        // na pala ang ilan — kung hindi, mapapaalalahanan ang admin
        // tungkol sa isang refund na naipadala na kanina.
        $this->syncPendingTransfers();
        $this->nudgeStaleRefunds();

        return self::SUCCESS;
    }

    // ── Mga transfer na hindi pa na-settle ──────────────────────────
    /**
     * Hinahabol ang bawat transfer na naiwang `pending`.
     *
     * Kailangan ito dahil sa PESONet: batch-cleared ito tuwing 11:00 /
     * 14:00 / 17:00 sa mga banking day lang, kaya oras — hindi
     * segundo — bago malaman ang kapalaran nito. Matagal nang sumuko
     * ang panandaliang paghihintay sa request ng admin bago pa iyon
     * matapos.
     *
     * Hindi rin puwedeng umasa lang sa `callback_url`: hindi ito
     * ipinapadala kapag hindi maabot mula sa labas ang app (lokal na
     * pag-develop), at walang garantiya ang PayMongo na darating ito.
     * Ito ang huling panangga — kung wala nito, ang isang refund na
     * dumating naman ay mananatiling `pending` magpakailanman at
     * mananatiling nakabukas ang utang sa mga talaan.
     */
    private function syncPendingTransfers(): void
    {
        $pending = RefundTransfer::where('status', 'pending')
            ->whereNotNull('transfer_id')
            ->get();

        if ($pending->isEmpty()) {
            return;
        }

        $service = app(RefundTransferService::class);
        $settled = 0;

        foreach ($pending as $transfer) {
            try {
                if (! $service->syncStatus($transfer)->isPending()) {
                    $settled++;
                }
            } catch (\Throwable $e) {
                // Ang isang hindi maabot na PayMongo ay hindi dapat
                // magpabagsak sa buong scheduled run — may check-in at
                // check-out pa itong ginagawa.
                Log::error("Could not sync refund transfer {$transfer->id}: " . $e->getMessage());
            }
        }

        $this->info("Refund transfers checked: {$pending->count()}, settled: {$settled}");
    }

    // ── Mga refund na matagal nang nakaupo ───────────────────────────
    /**
     * Sinabihan ang guest na *"we'll notify you again once it's on its
     * way."* Kung walang pumupukaw, ang pangakong iyon ay tumatahimik
     * na lang — at ang refund ay maaaring tuluyang makalimutan nang
     * walang anumang senyales, na siya mismong pagkabigong nag-udyok
     * sa buong pending/paid-out na paghihiwalay noong v5.5.
     *
     * DALAWANG MAGKAIBANG PAGKAKAPAKO ang tinutugunan nito, at sadyang
     * magkaiba ang tinatawagan:
     *
     *   - Naghihintay ng detalye ng guest → ANG GUEST ang pinupukaw.
     *     Wala tayong magagawa hangga't hindi niya sinasabi kung saan.
     *   - Kumpleto na ang detalye pero hindi pa naipapadala → ANG ADMIN
     *     ang pinupukaw. Nasa kanya na ang lahat ng kailangan.
     *
     * Ang paghahalo ng dalawa ay magpapadala ng "may kailangan kang
     * gawin" sa taong walang magagawa.
     *
     * Umaasa ang dedupe sa umiiral nang notification rows sa halip na
     * sa bagong column: kung may naipadala nang paalala para sa refund
     * na ito sa loob ng nakaraang PAYOUT_NUDGE_DAYS, laktawan. Araw-araw
     * tumatakbo ang command na ito, at ang paulit-ulit na paalala ay
     * hindi na binabasa.
     */
    private function nudgeStaleRefunds(): void
    {
        $days = Payment::PAYOUT_NUDGE_DAYS;

        $stale = Payment::awaitingPayout()
            ->where('created_at', '<=', now()->subDays($days))
            ->with(['booking.user', 'refundDestination'])
            ->get();

        foreach ($stale as $refund) {
            $booking = $refund->booking;

            if (! $booking) {
                continue;
            }

            $waiting = $refund->daysAwaitingPayout();
            $amount  = number_format($refund->amount, 2);

            if ($refund->needsRefundDestination()) {
                $link = route('customer.refunds.destination', $refund, false);

                if ($this->alreadyNudged($link, $days)) {
                    continue;
                }

                NotificationHelper::notifyGuest(
                    $booking->user_id,
                    "We still need your refund details — {$booking->booking_ref}",
                    "Your ₱{$amount} refund for booking {$booking->booking_ref} has been waiting {$waiting} days. "
                    . "We can't send it until you tell us which bank or e-wallet account should receive it. "
                    . 'It only takes a moment.',
                    $link
                );

                $this->info("📨 Nudged guest for refund details: {$booking->booking_ref} ({$waiting}d)");
                continue;
            }

            // May detalye na — ang resort na ang napapako rito.
            $link = route('admin.payments.show', $refund, false);

            if ($this->alreadyNudged($link, $days)) {
                continue;
            }

            NotificationHelper::notifyAdmin(
                "Refund still unsent after {$waiting} days — {$booking->booking_ref}",
                "₱{$amount} for booking {$booking->booking_ref} has been approved and we have the guest's "
                . 'account details, but the money still has not been sent. The guest was told they would '
                . 'hear back from us.',
                $link
            );

            $this->warn("⏰ Refund unsent for {$waiting}d: {$booking->booking_ref} (₱{$amount})");
        }
    }

    /**
     * May naipadala na bang paalala para sa refund na ito kamakailan?
     *
     * Ang `link` ang ginagamit na susi dahil natatangi ito kada refund
     * (naglalaman ito ng payment id) at naitatala na — walang bagong
     * column na kailangan para lang dito.
     */
    private function alreadyNudged(string $link, int $withinDays): bool
    {
        return Notification::where('link', $link)
            ->where('created_at', '>=', now()->subDays($withinDays))
            ->exists();
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
                        'Guest Arrived — Outstanding Balance',
                        "Check-in time has arrived for {$booking->user->full_name} ({$booking->booking_ref}), but there is an outstanding balance of ₱" . number_format($booking->balance_due, 2) . ". The booking was not automatically checked in — a staff member must decide at the Front Desk (collect payment now or confirm a deferred check-in).",
                        route('admin.bookings.show', $booking, false)
                    );

                    StaffLog::record('auto_checkin_skipped_balance', 'bookings', $booking->id,
                        "System did not auto-check-in {$booking->user->full_name} for {$booking->booking_ref} (scheduled: {$booking->checkInDateTime()->format('M d, Y g:i A')}) due to outstanding balance of ₱" . number_format($booking->balance_due, 2) . " — manual staff decision required at the Front Desk.");

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
                'message' => "You have been automatically checked in to {$booking->property->property_name}. Enjoy your stay! Check-out: {$booking->check_out_date->format('F d, Y')}.",
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

        // Ang `amount_paid` na tseke ay SADYANG dobleng proteksyon.
        //
        // Ang layunin ng sweeper na ito ay palayain ang mga slot na
        // hawak ng hindi nagbabayad — hindi ang kanselahin ang mga
        // nagbayad na. Dati, `status` at `created_at` lang ang sinasala
        // nito, kaya sapat nang maiwang 'pending' ang isang booking
        // (halimbawa, manwal na naitala ang bayad ng staff bago pa
        // naidagdag ang Booking::confirmOnFirstPayment()) para
        // makanselang may hawak nang pera ng guest — at masabihan pa
        // siyang "hindi nakumpleto ang downpayment".
        //
        // Naayos na ang ugat sa mga record-payment path, pero nananatili
        // ang tsekeng ito: kung may makalimot sa hinaharap na i-promote
        // ang status, mas mabuting maiwang 'pending' ang isang bayad na
        // booking kaysa makanselang may hawak na pera.
        $stale = Booking::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($holdMinutes))
            ->where(function ($q) {
                $q->whereNull('amount_paid')->orWhere('amount_paid', '<=', 0);
            })
            ->with(['user', 'property'])
            ->get();

        $holdLabel = $holdMinutes % 60 === 0
            ? ($holdMinutes / 60) . '-hour'
            : $holdMinutes . '-minute';

        foreach ($stale as $booking) {
            $booking->update([
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancellation_reason' => "Auto-cancelled by the system — payment was not completed within the {$holdLabel} grace period.",
                'cancelled_by'        => 'system',
                'balance_due'         => 0,
            ]);

            NotificationHelper::notifyGuest(
                $booking->user_id,
                'Booking Auto-Cancelled — Grace Period Expired',
                "Your booking {$booking->booking_ref} for {$booking->property->property_name} was automatically cancelled because the required 50% downpayment wasn't completed within the {$holdLabel} grace period. Feel free to book again if the dates are still available.",
                route('customer.bookings.show', $booking, false)
            );

            // Dating wala nito — puro notifyGuest() lang, kaya ang admin
            // ay walang malay na may na-cancel na booking hangga't hindi
            // niya binuksan mismo ang bookings list. Sinasadyang ibang
            // preset ito sa bookingCancelled() (na nagsasabing "guest
            // cancelled their booking") — mali iyon dito, ang system ang
            // nag-cancel, hindi ang guest.
            NotificationHelper::bookingAutoCancelled($booking, $holdLabel);

            StaffLog::record('auto_cancelled_stale_booking', 'bookings', $booking->id,
                "System auto-cancelled unpaid pending booking {$booking->booking_ref} ({$holdMinutes}+ minutes since created, no payment received).");

            $this->info("🗑️ Auto-cancelled stale pending: {$booking->booking_ref}");
        }
    }
}