<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Saan ipapadala ang isang refund — bangko/e-wallet, account number, at
 * ang pangalang nakarehistro doon.
 *
 * Tingnan ang migration para sa buong dahilan kung bakit hiwalay na
 * table ito at bakit hindi makukuha ang datos na ito sa QR Ph payment.
 *
 * @property int $id
 * @property int $payment_id
 * @property string $institution_name
 * @property string $institution_bic
 * @property string $account_number
 * @property string $account_name
 * @property int|null $provided_by
 * @property \Illuminate\Support\Carbon $provided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $masked_account_number
 * @property-read \App\Models\Payment $payment
 * @property-read \App\Models\User|null $providedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination whereAccountName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination whereInstitutionBic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination whereInstitutionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination whereProvidedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination whereProvidedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundDestination whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RefundDestination extends Model
{
    /**
     * Ang mga institusyong ang "account number" ay mobile number.
     *
     * Nakalista ang mga ito sa pamamagitan ng `provider_code` mula sa
     * `GET /v1/wallets/receiving_institutions` — hindi sa pangalan.
     */
    public const MOBILE_WALLET_BICS = [
        'GXCHPHM2XXX',   // G-Xchange, Inc. — ang GCash
        'PAPHPHM1XXX',   // Maya Philippines, Inc. — ang Maya wallet
        // Sinasadyang WALA rito ang MYDBPHM2XXX (MAYA BANK, INC):
        // tunay na bangko iyon, may ordinaryong account number.
    ];

    /**
     * Mga institusyong hindi tumatanggap ng InstaPay mula sa Wallet.
     *
     * WALANG LAMAN. Nandito noon ang GCash — dahil sa riles daw, tapos
     * dahil sa halaga daw. Parehong mali: tumatanggap ang GCash ng
     * InstaPay mula sa wallet na ito, mula rin sa API. Hindi lang ito
     * palagi — tingnan ang `RefundTransfer::isRetryable()`.
     */
    public const PESONET_ONLY_BICS = [];

    /**
     * Mga institusyong HINDI kayang abutin ng awtomatikong transfer.
     *
     * Ang paghaharang dito ay huling pagpipilian: pumipigil ito sa
     * tunay na refund, at dalawang beses na itong nailagay batay sa
     * maling pagbabasa ng ebidensiya. Maglagay lang ng BIC dito kung
     * may institusyong TUNAY na hindi kayang abutin.
     */
    public const NO_AUTO_TRANSFER_BICS = [
        // Walang laman. Nandito noon ang GCash, dalawang beses, sa
        // dalawang magkaibang maling dahilan ("riles", tapos
        // "halaga"). Ang tunay na sagot ay ang `purpose`, at hindi
        // iyon dahilan para humarang — dahilan iyon para itama ang
        // ipinapadala natin. Tingnan ang `RefundTransfer::PURPOSE`.
    ];

    protected $fillable = [
        'payment_id',
        'institution_name',
        'institution_bic',
        'account_number',
        'account_name',
        'provided_by',
        'provided_at',
    ];

    protected $casts = [
        'provided_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function providedBy()
    {
        return $this->belongsTo(User::class, 'provided_by');
    }

    // ── Validation (pinagsasaluhan ng guest at admin na form) ──────
    /**
     * Dalawang lugar ang naglalagay ng destinasyon: ang guest mismo, at
     * ang admin para sa kanya (madalas nakukuha sa text o tawag). Iisa
     * ang panuntunan sa dalawa — dito sila nakatira para hindi sila
     * maghiwalay, na siyang paraan kung paano tahimik na nagiging
     * mas maluwag ang isang form kaysa sa isa.
     *
     * Ang `$validBics` ay galing sa BUHAY na listahan ng PayMongo
     * (`PayMongoService::receivingInstitutions()`), hindi sa hardcode.
     */
    public static function rules(array $validBics): array
    {
        return [
            'institution_bic' => 'required|string|in:' . implode(',', $validBics),
            'account_number'  => 'required|string|max:64|regex:/^[0-9]+$/',
            'account_name'    => 'required|string|min:2|max:255',
        ];
    }

    public static function messages(): array
    {
        return [
            'institution_bic.in'    => 'Please choose a bank or e-wallet from the list.',
            'account_number.regex'  => 'Account numbers can only contain digits — no spaces or dashes.',
            'account_name.required' => 'Enter the account name exactly as it appears on the account.',
        ];
    }

    /**
     * Para sa GCash at Maya, ang account number ay mobile number — at
     * ang pangkalahatang digits-only na tuntunin sa itaas ay tatanggap
     * ng "12345". Sa mga tunay na bangko, iba-iba ang haba ng account
     * number, kaya doon sinasadyang walang ipinapataw.
     *
     * Ibinabalik ang mensahe ng error, o `null` kung walang problema.
     */
    public static function mobileNumberError(string $bic, string $institutionName, string $number): ?string
    {
        if (! self::isMobileWallet($bic)) {
            return null;
        }

        if (preg_match('/^09[0-9]{9}$/', $number)) {
            return null;
        }

        return "For {$institutionName}, enter the 11-digit mobile number starting with 09.";
    }

    /**
     * Sinusuri ang BIC, hindi ang pangalan.
     *
     * Ang pagtutugma sa pangalan ay mapanganib dito: may dalawang
     * magkaibang Maya sa listahan ng InstaPay — ang wallet
     * ("Maya Philippines, Inc.") at ang bangko ("MAYA BANK, INC").
     * Ang huli ay tumatanggap ng ordinaryong account number, kaya ang
     * isang `str_contains($name, 'maya')` ay maling pipilitin ang may
     * hawak ng Maya Bank na mag-type ng mobile number na wala naman
     * sila — at hindi na tatanggapin ang refund nila kailanman.
     */
    public static function isMobileWallet(string $bic): bool
    {
        return in_array(strtoupper($bic), self::MOBILE_WALLET_BICS, true);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Ang eksaktong hugis na hinihingi ng PayMongo Send Money para sa
     * `destination_account` sa `POST /v2/batch_transfers`.
     *
     * Dito nakatira ang mapping para iisa lang ang lugar na kailangang
     * baguhin kung magbago man ang API — hindi ito kakalat sa controller.
     */
    public function toTransferAccount(): array
    {
        return [
            'number' => $this->account_number,
            'name'   => $this->account_name,
            'bic'    => $this->institution_bic,
        ];
    }

    /**
     * Kaya bang abutin ang destinasyong ito ng awtomatikong transfer?
     */
    public function canReceiveTransfer(): bool
    {
        return ! array_key_exists(strtoupper((string) $this->institution_bic), self::NO_AUTO_TRANSFER_BICS);
    }

    /**
     * Bakit hindi — sa wikang maiintindihan ng admin.
     */
    public function transferBlockedReason(): ?string
    {
        return self::NO_AUTO_TRANSFER_BICS[strtoupper((string) $this->institution_bic)] ?? null;
    }

    /**
     * Mga institusyong kilalang bumabagsak, PERO hindi hinaharangan.
     *
     * Iba ito sa `NO_AUTO_TRANSFER_BICS`. Ang paghaharang ay sinasabing
     * "alam nating imposible ito"; ang babalang ito ay nagsasabing
     * "minsan bumabagsak — inuulit ito ng app, at libre ang bigo."
     *
     * Nandito ang GCash dahil pabago-bago ito, hindi dahil sarado.
     */
    public const KNOWN_TRANSFER_ISSUES = [
        'GXCHPHM2XXX' => 'GCash rejects a share of incoming transfers at random with AC06 '
            . '(BlockedAccount) — the same payload has both failed and, a minute later, landed. '
            . 'The app retries automatically up to 3 times before giving up, and a failed attempt '
            . 'costs nothing. If all three fail, wait a moment and press Send again, or send it '
            . 'by hand and record it with "Mark Paid Out".',
    ];

    /**
     * Babala tungkol sa destinasyong ito, kung mayroon.
     */
    public function transferWarning(): ?string
    {
        return self::KNOWN_TRANSFER_ISSUES[strtoupper((string) $this->institution_bic)] ?? null;
    }

    public function preferredProvider(): string
    {
        return in_array(strtoupper((string) $this->institution_bic), self::PESONET_ONLY_BICS, true)
            ? 'pesonet'
            : 'instapay';
    }

    /**
     * Dumarating ba agad ang pera sa destinasyong ito?
     *
     * Kailangan ito ng UI: ang isang PESONet na transfer ay mananatiling
     * `pending` nang ilang oras, at kung hindi ito sasabihin nang maaga
     * ay mukhang sira ang sistema gayong maayos naman.
     */
    public function isInstant(): bool
    {
        return $this->preferredProvider() === 'instapay';
    }

    /**
     * Naka-mask na account number para sa pangkaraniwang pagpapakita.
     *
     * Financial account data ito. Sapat ang huling 4 na digit para
     * makilala ito ng admin sa isang listahan; ang buong numero ay
     * kailangan lang sa mismong sandali ng pagpapadala, at may
     * hiwalay na "reveal" doon.
     */
    public function getMaskedAccountNumberAttribute(): string
    {
        $number = (string) $this->account_number;

        if (strlen($number) <= 4) {
            return $number;
        }

        return str_repeat('•', strlen($number) - 4) . substr($number, -4);
    }
}
