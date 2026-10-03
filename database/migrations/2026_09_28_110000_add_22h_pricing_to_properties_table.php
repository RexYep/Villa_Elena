<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presyo para sa 22-Hours Stay (7:00 PM – 5:00 PM kinabukasan).
 *
 * Bakit kailangan ng SARILING column at hindi na lang `base_price`:
 *
 *   Ang isang 22-oras na stay ay sumasakop sa DALAWANG slot — ang Night
 *   ng unang araw at ang Day ng kinabukasan. Kung ang presyo ng Night ang
 *   isisingil dito, ipinagbibili ang villa nang 22 oras sa halagang
 *   11 oras. Wala pang per-slot na presyo ang schema: pareho ang
 *   binabayaran ng Day at ng Night, at ang petsa lang ng check-in ang
 *   nagpapasya (`base_price` o `weekend_price`).
 *
 * ⚠️ SINASADYANG NULL ANG DEFAULT, at NULL ang ibig sabihin ng "hindi pa
 * ipinepresyo" — hindi "libre".
 *
 * Hindi pa sinasabi ng may-ari kung magkano ang 22 oras. Hangga't NULL
 * ang dalawang column na ito, ang slot ay HINDI inaalok kahit saang
 * booking form at tinatanggihan ng `Property::quoteFor()`. Hindi ito
 * pag-iingat lang sa teorya: ang mga `type = room` na row ay may
 * `base_price = 0.00` at NULL na `weekend_price`, at dahil doon ay
 * nakagawa ang `POST /book/{room}` ng tunay at slot-holding na booking na
 * ₱0 (tingnan ang `PortalController::assertBookableListing()`). Ang
 * parehong hugis ng bug ay iyon din — kaya ang tugon sa NULL ay
 * pagtanggi, hindi zero.
 *
 * Kapag sumagot na ang may-ari, itatakda ito sa Admin → Properties at
 * lilitaw ang slot sa lahat ng form nang mag-isa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('base_price_22h', 10, 2)->nullable()->after('weekend_price');
            $table->decimal('weekend_price_22h', 10, 2)->nullable()->after('base_price_22h');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['base_price_22h', 'weekend_price_22h']);
        });
    }
};
