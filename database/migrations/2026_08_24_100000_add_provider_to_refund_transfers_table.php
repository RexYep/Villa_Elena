<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aling riles ang dinaanan ng transfer na ito.
 *
 * Hanggang ngayon ay laging InstaPay. Kinailangan itong gawing tahasan
 * nang matuklasang TINATANGGIHAN ng GCash (`GXCHPHM2XXX`) ang bawat
 * InstaPay na papasok mula sa PayMongo Wallet — `AC06 BlockedAccount`,
 * tatlong beses, sa dalawang magkaibang account — samantalang gumagana
 * naman ang Maya sa parehong code.
 *
 * Dalawang magkaibang pag-uugali ang riles, kaya kailangang malaman
 * kung alin ang ginamit para maintindihan ang isang row:
 *
 *   - InstaPay: real-time, ~2 segundo, hanggang ₱50,000.
 *   - PESONet:  batch, sa mga banking day lang, tatlong cycle kada
 *               araw (11:00 / 14:00 / 17:00), kaya ORAS ang tagal —
 *               hindi segundo. Ang isang PESONet na `pending` ay
 *               normal, hindi tanda ng problema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_transfers', function (Blueprint $table) {
            $table->string('provider', 16)->default('instapay')->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('refund_transfers', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
