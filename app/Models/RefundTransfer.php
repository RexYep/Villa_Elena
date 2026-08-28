<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Isang pagtatangkang ipadala ang isang refund sa pamamagitan ng
 * PayMongo Send Money (`POST /v2/batch_transfers`).
 *
 * Tingnan ang migration para sa dahilan kung bakit hiwalay ito sa
 * `refund_destinations` at bakit marami ito kada refund.
 *
 * @property int $id
 * @property int $payment_id
 * @property string|null $transfer_id
 * @property string|null $batch_transfer_id
 * @property string $reference_number
 * @property string $status
 * @property numeric $amount
 * @property numeric $fee
 * @property string $provider
 * @property string|null $provider_reference_number
 * @property string|null $provider_error_code
 * @property string|null $provider_error_message
 * @property string|null $sub_code
 * @property string $institution_name
 * @property string $institution_bic
 * @property string $account_number
 * @property string $account_name
 * @property int|null $initiated_by
 * @property \Illuminate\Support\Carbon|null $settled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $masked_account_number
 * @property-read string $receipt_reference
 * @property-read \App\Models\User|null $initiatedBy
 * @property-read \App\Models\Payment $payment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereAccountName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereBatchTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereInitiatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereInstitutionBic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereInstitutionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereProviderErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereProviderErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereProviderReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereSettledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereSubCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RefundTransfer whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RefundTransfer extends Model
{
    /**
     * Ang bayad kada transfer, sa piso.
     *
     * Wala itong fee table kahit saan sa docs ng PayMongo — nalaman
     * lang ito sa isang tunay na ₱1 na transfer noong 2026-08-23
     * (`fee: 1000` centavos). Sinisingil LAMANG ito kapag dumating
     * talaga ang pera; ibinabalik sa 0 kapag bumagsak.
     *
     * Ginagamit ito bilang pagtatantiya sa tseke ng balanse bago
     * magpadala. Hindi ito pinagkakatiwalaan bilang katotohanan —
     * ang aktwal na `fee` mula sa sagot ang isinusulat sa row.
     */
    public const ESTIMATED_FEE = 10.00;

    /**
     * WALANG minimum na napatunayan.
     *
     * Sinabi ng PayMongo support na kailangang higit sa ₱5 — at
     * pinabulaanan iyon ng sarili nating datos: ang ₱1 at ₱2 papuntang
     * Maya ay parehong DUMATING. Ang ₱6 papuntang GCash ay bumagsak pa
     * rin nang may `AC06`, kaya hindi rin nito naipapaliwanag ang mga
     * pagkabigo doon.
     *
     * Nakatakda sa `0` — walang hadlang. Iniiwan ang constant dahil
     * kapag may lumitaw na TUNAY na minimum, dito ito nakatira; pero
     * huwag itong ibalik sa 5 nang walang matibay na patunay. Ang
     * hindi umiiral na hadlang ay pumipigil sa mga tunay na refund.
     */
    public const MINIMUM_AMOUNT = 0.00;

    /**
     * Ang `purpose` na isinasama sa bawat transfer.
     *
     * HINDI ITO ANG DAHILAN NG `AC06`. Nagmukha itong ganoon nang ilang
     * oras: ang tanging tatlong transfer na dumating sa GCash ay
     * pawang `own-account`, at wala ni isa sa mga bumagsak ang may
     * ganoong tag. Pinatay iyon ng dalawang probe na 69 segundo lang
     * ang pagitan noong 2026-08-26:
     *
     *   tr_2125e015…  purpose=own-account, description=own-account  -> AC06
     *   tr_9bc989fa…  purpose=own-account, description="Villa Elena…" -> succeeded
     *
     * at ng `tr_2125e015…` mismo, na kapareho sa BAWAT field na
     * kontrolado natin ng dalawang matagumpay na dashboard transfer at
     * bumagsak pa rin. Ang parehong payload ay dumating AT bumagsak,
     * kaya hindi ang payload ang nagpapasya. Tingnan ang
     * `isRetryable()` at ang `project.md` para sa buong talaan.
     *
     * Ipinapadala pa rin ito dahil ipinapadala ito ng dashboard, wala
     * itong halaga, at ang blangkong field ay hindi kailanman mas mabuti
     * kaysa sa isang tapat na field. Wala lang itong lunas na dala.
     *
     * `PAYMONGO_TRANSFER_PURPOSE` ang nagpapalit nito nang walang
     * deploy. Kung may susukatin muli rito: isang variable lang sa
     * bawat pagkakataon, at isulat ang resulta sa `project.md`.
     */
    public const PURPOSE = 'customer-refund';

    /**
     * Ang aktwal na gagamiting `purpose`, matapos ang env override.
     */
    public static function purpose(): string
    {
        return (string) config('services.paymongo.transfer_purpose', self::PURPOSE);
    }

    protected $fillable = [
        'payment_id',
        'transfer_id',
        'batch_transfer_id',
        'reference_number',
        'status',
        'amount',
        'fee',
        'provider',
        'provider_reference_number',
        'provider_error_code',
        'provider_error_message',
        'sub_code',
        'institution_name',
        'institution_bic',
        'account_number',
        'account_name',
        'initiated_by',
        'settled_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'fee'        => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    // ── Estado ─────────────────────────────────────────────────────

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Tapos na ba ito at hindi nagtagumpay?
     *
     * Pinagsasama ang `failed` (tinanggihan ng bangko) at `error`
     * (hindi man lang nakaabot sa PayMongo) — sa mata ng admin ay
     * pareho lang ang ibig sabihin: hindi dumating ang pera, at
     * puwedeng subukan muli.
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, ['failed', 'error'], true);
    }

    /**
     * Mga code na nangangahulugang "wala kang ginawang mali — ulitin mo."
     *
     * Ang `AC06` ay nandito dahil sa sinukat, hindi sa pangalan nito.
     * Ang ibig sabihin nito ay "BlockedAccount", na parang isang
     * permanenteng katotohanan tungkol sa account — pero ang parehong
     * account, sa parehong payload, ay tumanggap ng pera makalipas ang
     * 69 segundo. Anuman ang ibig sabihin ng `AC06` sa loob ng GCash,
     * hindi ito nangangahulugang "tumigil ka na".
     *
     * Sinasadyang HINDI kasama rito ang `AC01`/`AC02`/`AC03`/`AC04`/
     * `BE01` at ang mga kauri: tungkol ang mga iyon sa maling numero o
     * pangalan, at ang pag-ulit sa mga iyon ay pag-ulit ng parehong
     * mali habang pinapaghintay ang admin.
     */
    public const RETRYABLE_ERROR_CODES = [
        'AC06',   // BlockedAccount — napatunayang panandalian sa GCash
        'AB08',   // offline ang tumatanggap na institusyon
        '9910',   // InstaPay: naka-log off ang tumatanggap na bangko
        '91',
    ];

    /**
     * Dapat bang subukan itong muli?
     *
     * Ang `error` ay sinasadyang wala rito: ibig sabihin niyon ay hindi
     * man lang umabot sa PayMongo ang utos, o tinanggihan nito ang
     * mismong hugis ng request. Ang pag-ulit doon ay pag-ulit ng
     * parehong sirang request.
     */
    public function isRetryable(): bool
    {
        return $this->status === 'failed'
            && in_array(strtoupper((string) $this->provider_error_code), self::RETRYABLE_ERROR_CODES, true);
    }

    // ── Mensaheng nauunawaan ng tao ────────────────────────────────

    /**
     * Mga ISO 20022 na rejection code, isinalin sa bagay na kayang
     * pagpasyahan ng admin.
     *
     * Ang mga ito ay galing sa InstaPay, HINDI sa listahan ng
     * `account_not_found` / `account_not_active` na nasa test-case
     * docs ng PayMongo — mga simulator string lang iyon, at hindi
     * kailanman lumalabas sa live. Napatunayan sa live: `AC06` /
     * `BlockedAccount` (2026-08-23).
     *
     * Ang hindi kilalang code ay ibinabalik nang hilaw sa halip na
     * itago sa likod ng malabong "may naganap na error" — ang code
     * mismo ang tanging bagay na magagamit ng admin kapag tumawag
     * siya sa support.
     */
    public function failureReason(): ?string
    {
        if (! $this->hasFailed()) {
            return null;
        }

        $known = [
            'AC01' => 'the account number is not valid for this bank or e-wallet',
            'AC02' => 'the account number is not valid',
            'AC03' => 'the account number is not valid for receiving transfers',
            'AC04' => 'the account is closed',
            'AC06' => 'the account is blocked and cannot receive money right now',
            'AC07' => 'the account is closed',
            'AC13' => 'the account type cannot receive this kind of transfer',
            'AC14' => 'the account type cannot receive this kind of transfer',
            'AG01' => 'this bank or e-wallet does not allow this transfer',
            'AG03' => 'this bank or e-wallet does not support this transfer type',
            'AM04' => 'there was not enough balance to send it',
            'AM05' => 'this looks like a duplicate of a transfer already sent',
            'BE01' => 'the account name does not match the account number',
            'MD07' => 'the account holder is recorded as deceased',
            'RR04' => 'the receiving bank or e-wallet rejected it for regulatory reasons',
        ];

        $code = strtoupper((string) $this->provider_error_code);

        if (isset($known[$code])) {
            return "{$this->institution_name} did not accept the transfer — {$known[$code]}.";
        }

        if ($this->status === 'error') {
            return $this->provider_error_message
                ?: 'The request never reached PayMongo. Nothing was sent.';
        }

        $detail = $this->provider_error_message ? " ({$this->provider_error_message})" : '';

        return "{$this->institution_name} rejected the transfer"
            . ($code !== '' ? " with code {$code}{$detail}" : $detail) . '.';
    }

    /**
     * Naka-mask na account number, tulad ng sa `RefundDestination`.
     */
    public function getMaskedAccountNumberAttribute(): string
    {
        $number = (string) $this->account_number;

        if (strlen($number) <= 4) {
            return $number;
        }

        return str_repeat('•', strlen($number) - 4) . substr($number, -4);
    }

    /**
     * Ang reference na ipapakita bilang katibayan.
     *
     * Mas mainam ang trace ng provider kaysa sa sarili nating
     * reference — iyon ang hahanapin ng bangko kapag may usapin.
     */
    public function getReceiptReferenceAttribute(): string
    {
        return $this->provider_reference_number ?: $this->reference_number;
    }
}
