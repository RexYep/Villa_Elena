<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-level na proteksiyon laban sa double-booking.
 *
 * Ang `Booking::hasConflict()` ay tama naman — pero SELECT lang siya.
 * Sa pagitan ng check na iyon at ng INSERT, walang humahawak ng
 * kahit ano, kaya dalawang request na sabay dumating ay parehong
 * nakakakita ng "bakante" bago pa man may naipasok na row. Nangyari
 * ito nang totoo sa produksiyon: VE-4C7INQOG at VE-YHLBMLUU, parehong
 * 2026-09-15 Day slot, parehong `created_at` na 16:33:05.
 *
 * Ang `Booking::reserveSlot()` ang unang linya ng depensa (transaction
 * + row lock sa property). Ito naman ang huli: kahit may bagong code
 * path na makalimot dumaan doon, hindi na kayang mag-imbak ang MySQL
 * ng dalawang buhay na booking para sa iisang slot.
 *
 * Bakit hiwalay na column at hindi na lang composite unique index sa
 * (property_id, check_in_date, check_in_time)? Dahil ang isang
 * cancelled/no_show/soft-deleted na booking ay nananatiling row —
 * haharangan nito ang muling pag-book sa slot na pinalaya na nito.
 * Ang `slot_hold` ay NULL para sa mga ganoong booking, at maraming
 * NULL ang pinapayagan ng MySQL sa isang UNIQUE index. Ang Booking
 * model mismo ang nagpapanatili ng value nito (`computeSlotHold()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('slot_hold', 64)->nullable()->after('check_out_time');
        });

        $this->backfill();

        Schema::table('bookings', function (Blueprint $table) {
            $table->unique('slot_hold');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_slot_hold_unique');
            $table->dropColumn('slot_hold');
        });
    }

    /**
     * Pinupunan ang `slot_hold` ng mga existing na booking. Sinasadyang
     * PHP ang gumagawa nito sa halip na isang CONCAT() sa SQL — iisa
     * ang lohika nito at ng `Booking::computeSlotHold()`, at tumatakbo
     * ito kahit sa SQLite (ang test suite).
     */
    private function backfill(): void
    {
        $seen = [];
        $duplicates = [];

        DB::table('bookings')
            ->select('id', 'booking_ref', 'property_id', 'check_in_date', 'check_in_time', 'status', 'deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$seen, &$duplicates) {
                foreach ($rows as $row) {
                    $hold = $this->holdFor($row);

                    // Isang buhay na double-booking na naipasok na bago
                    // pa umiral ang index na ito. HINDI natin ito
                    // kinakansela — pera ng totoong guest ang nakataya,
                    // at desisyon ng admin kung sino ang mananatili.
                    // Ang mas huling row na lang ang inaalisan ng
                    // `slot_hold` para makagawa ang index; nananatiling
                    // buo at nakikita sa admin panel ang booking mismo.
                    if ($hold !== null && isset($seen[$hold])) {
                        $duplicates[] = "{$row->booking_ref} (id {$row->id}) collides with {$seen[$hold]}";
                        $hold = null;
                    } elseif ($hold !== null) {
                        $seen[$hold] = "{$row->booking_ref} (id {$row->id})";
                    }

                    DB::table('bookings')->where('id', $row->id)->update(['slot_hold' => $hold]);
                }
            });

        if ($duplicates !== []) {
            $message = 'Pre-existing double-bookings found while adding the slot_hold unique index. '
                .'These rows were left untouched but excluded from the index — resolve them manually: '
                .implode('; ', $duplicates);

            echo "\n  ⚠️  {$message}\n\n";
            \Illuminate\Support\Facades\Log::warning($message);
        }
    }

    private function holdFor(object $row): ?string
    {
        if ($row->deleted_at !== null) {
            return null;
        }

        if (in_array($row->status, ['cancelled', 'no_show'], true)) {
            return null;
        }

        if (empty($row->property_id) || empty($row->check_in_date) || empty($row->check_in_time)) {
            return null;
        }

        return $row->property_id
            .':'.\Carbon\Carbon::parse($row->check_in_date)->format('Y-m-d')
            .':'.\Carbon\Carbon::parse($row->check_in_time)->format('H:i:s');
    }
};
