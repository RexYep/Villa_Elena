<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itinatala kung BAKIT ibinalik ang isang refund.
 *
 * Mula v7.63 ay pinipili ito ng admin sa Issue Refund na form
 * (`Payment::REFUND_KINDS`) at ito ang nagpapasya sa pangungusap ng
 * abiso sa guest — pero binabasa lang ito nang minsan at hindi
 * iniimbak. Kailangan na itong maiwan: ipinapakita rin ng refund panel
 * sa booking details ng guest ang dahilan, at hindi iyon mababasa mula
 * sa abisong naipadala na.
 *
 * `string`, hindi `enum`: ang listahan ng mga uri ay nasa
 * `Payment::REFUND_KINDS`, at ang isang ENUM ay mangangailangan ng
 * panibagong `MODIFY` sa bawat uring idaragdag doon.
 *
 * NULL para sa lahat ng hindi refund, at para sa mga refund na ginawa
 * bago ang migration na ito — hindi mahuhulaan ang dahilan ng mga iyon,
 * kaya walang ipinapakitang dahilan ang panel para sa kanila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('refund_kind', 32)
                ->nullable()
                ->after('payment_type')
                ->comment('Payment::REFUND_KINDS — para lang sa payment_type = refund');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('refund_kind');
        });
    }
};
