<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saan ipapadala ang isang refund.
     *
     * Hanggang ngayon, walang alam ang sistema tungkol dito. Ang refund
     * ay isang `payments` row lang na may halaga at dahilan; ang admin
     * ang bahalang alamin kung saan ipapadala ang pera — karaniwang sa
     * pamamagitan ng pagte-text sa guest. Walang naitatala kung saan
     * talaga ito napunta.
     *
     * Kinakailangan ito ng PayMongo Send Money (`/v2/batch_transfers`),
     * na humihingi ng `destination_account.{number,name,bic}`. AT HINDI
     * ITO MAKUKUHA SA QR Ph PAYMENT — napatunayan laban sa totoong paid
     * na payment (`pay_fahRhy…`): walang account number kahit saan sa
     * payment object, `null` ang `source.provider.bank_institution_code`,
     * at ang `billing.name` ay ang mismong ipinadala ng app noong ginawa
     * ang checkout session — sarili nating booking record, ibinalik sa
     * atin. Hindi iyon ang pangalang nakarehistro sa GCash ng guest.
     * Kaya kailangang itanong; walang paraan para i-derive.
     *
     * BAKIT HIWALAY NA TABLE, hindi mga column sa `payments`:
     *
     *   1. Ang destinasyon ay pag-aari ng REFUND, hindi ng guest. Kapag
     *      nakatago ito sa `users`, isang standing record lang ito na
     *      tahimik na nagbabago — hindi mo na masasabi kung saan
     *      TALAGA napunta ang refund noong Marso. Dito, permanente.
     *   2. Nahihiwalay ang PII sa financial table. Ang `cascadeOnDelete`
     *      ay nangangahulugang kasama nitong nabubura ang detalye kapag
     *      nabura ang refund — malinis na purge nang libre.
     *
     * WALANG paraan para patunayan ang `account_name` bago magpadala —
     * walang account-name-inquiry endpoint ang PayMongo. Sa submission
     * lang nagva-validate, kaya ang maling pangalan ay lumalabas bilang
     * runtime na `account_not_found`. Kaya ang eksaktong ibinigay ng
     * guest ang itinatago rito, nang hindi ginagalaw — para maikumpara
     * ito sa kung ano talaga ang ipinadala kapag may bumagsak.
     */
    public function up(): void
    {
        Schema::create('refund_destinations', function (Blueprint $table) {
            $table->id();

            // Isang destinasyon kada refund. Ang `unique` ang nagpapatupad
            // nito sa antas ng DB — hindi lang sa antas ng aplikasyon.
            $table->foreignId('payment_id')
                ->unique()
                ->constrained('payments')
                ->cascadeOnDelete();

            // Halimbawa: "G-Xchange, Inc." / "GXCHPHM2XXX" — parehong
            // itinatago dahil ang BIC ang ipinapadala sa PayMongo, pero
            // ang pangalan ang nakikita ng tao sa admin panel. Ang
            // listahan ay galing sa buhay na `receiving_institutions`
            // endpoint, hindi hardcode.
            $table->string('institution_name');
            $table->string('institution_bic', 16);

            // Para sa GCash/Maya, ang mobile number mismo ang account
            // number. Naka-string, hindi integer — mahalaga ang unang 0.
            $table->string('account_number', 64);
            $table->string('account_name');

            // Sinong naglagay. Karaniwan ang guest mismo, pero kayang
            // ipasok ito ng admin para sa kanya (madalas nakukuha sa
            // text o tawag) — kaya kailangang matukoy ang pinagmulan.
            $table->foreignId('provided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('provided_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_destinations');
    }
};
