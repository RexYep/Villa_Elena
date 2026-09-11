<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $booking_id
 * @property numeric $amount
 * @property string $payment_method
 * @property string $payment_type
 * @property string|null $transaction_ref
 * @property array<array-key, mixed>|null $gateway_response
 * @property string $status
 * @property int|null $processed_by
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $payment_date
 * @property string|null $reference_number
 * @property int|null $received_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Booking|null $booking
 * @property-read string $method_label
 * @property-read string $type_label
 * @property-read \App\Models\User|null $processedBy
 * @property-read \App\Models\User|null $receivedBy
 * @property-read \App\Models\RefundDestination|null $refundDestination
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RefundTransfer> $refundTransfers
 * @property-read int|null $refund_transfers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment awaitingPayout()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereBookingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereGatewayResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereProcessedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereReceivedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereReferenceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereTransactionRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Payment extends Model
{
    use HasFactory;

    /**
     * Ilang araw bago pukawin ang isang refund na hindi pa naipapadala.
     *
     * Sinabihan na ang guest na *"we'll notify you again once it's on
     * its way."* Ang bilang na ito ang nagtatakda kung gaano katagal
     * bago maging kasinungalingan ang pangakong iyon.
     */
    public const PAYOUT_NUDGE_DAYS = 3;

    /**
     * TANDAAN: `reference_number` at `received_by` ay matagal nang
     * naipapasa ng halos lahat ng Payment::create() call sites, pero
     * hindi sila nakalista dito — kaya tahimik lang silang binabalewala
     * ng mass assignment. Resulta: NULL ang dalawang ito sa lahat ng 46
     * na payment rows. Dahil doon, walang audit trail kung sinong staff
     * ang tumanggap ng cash, at hindi rin maikumpara ang mga online
     * payment sa aktwal na PayMongo transactions. Nasira rin nito ang
     * duplicate-payment guard sa PaymentController::success(), na
     * naghahanap ng dating row batay sa `reference_number` — hindi ito
     * kailanman tumutugma kapag laging NULL ang hinahanap.
     */
    protected $fillable = [
        'booking_id',
        'amount',
        'payment_method',
        'payment_type',
        'transaction_ref',
        'reference_number',
        'gateway_response',
        'status',
        'processed_by',
        'received_by',
        'notes',
        'payment_date',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'gateway_response' => 'array',
        'payment_date'     => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Saan ipapadala ang refund na ito. Umiiral lang ito sa mga refund
     * row, at saka pa lang kapag naibigay na ng guest (o ng admin para
     * sa kanya) ang detalye.
     */
    public function refundDestination()
    {
        return $this->hasOne(RefundDestination::class);
    }

    /**
     * Bawat pagtatangkang ipadala ang refund na ito, kasama ang mga
     * bigo. Ang mga bigong transfer ay libre, kaya normal ang marami.
     */
    public function refundTransfers()
    {
        return $this->hasMany(RefundTransfer::class)->latest('id');
    }

    /**
     * Ang pinakahuling pagtatangka, kung mayroon man.
     *
     * Umaasa sa na-load nang `refundTransfers` kapag mayroon, kaya
     * hindi ito nagdaragdag ng query kada row sa isang listahan.
     */
    public function latestRefundTransfer(): ?RefundTransfer
    {
        return $this->refundTransfers->first();
    }

    // ── Helper Methods ─────────────────────────────────────────────

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isRefund(): bool
    {
        return $this->payment_type === 'refund';
    }

    /**
     * Naibigay na ba talaga ang perang ito sa guest?
     *
     * Walang refund API ang sistema (at hindi rin naman kayang i-refund
     * ang cash sa pamamagitan ng API) — manu-manong ipinapadala ng admin
     * ang pera. Kaya ang isang bagong refund row ay nagsisimula bilang
     * 'pending': inaprubahan na, pero hindi pa nailalabas. Dati, agad
     * itong minamarkahang 'success', kaya walang paraan para malaman
     * kung sino ang may hindi pa natatanggap na refund — puwedeng
     * tuluyan na lang itong makalimutan nang walang anumang senyales.
     */
    public function isPaidOut(): bool
    {
        return $this->isRefund() && $this->status === 'success';
    }

    public function isAwaitingPayout(): bool
    {
        return $this->isRefund() && $this->status === 'pending';
    }

    public function scopeAwaitingPayout($query)
    {
        return $query->where('payment_type', 'refund')->where('status', 'pending');
    }

    /**
     * Hinihintay pa ba nito ang detalye kung saan ipapadala?
     *
     * MAHALAGA: hindi ito nagpapabago ng anuman sa pananalapi. Ang
     * refund ay nabawas na sa booking noong ma-approve pa lang ito
     * (tingnan ang `Booking::recalculateFinancials()`) — utang ito ng
     * resort sa guest kahit walang ibinigay na detalye kailanman.
     * Ang tanging epekto nito ay kung ano ang ipinapakitang estado sa
     * admin: "hinihintay ang guest" kumpara sa "handa nang ipadala".
     *
     * Ginagamit ang relation ACCESSOR (`$this->refundDestination`), hindi
     * ang `->exists()` na query — ang accessor ay gumagamit ng na-eager
     * load nang relasyon kapag mayroon, at nagka-cache sa model kapag
     * wala. Sa listahan ng payments, ang `->exists()` ay isang query
     * kada row.
     */
    public function needsRefundDestination(): bool
    {
        return $this->isAwaitingPayout()
            && $this->payment_method !== 'cash'
            && $this->refundDestination === null;
    }

    /**
     * May detalye na at hindi pa naipapadala — kaya nang ipadala ngayon.
     */
    public function isReadyToSend(): bool
    {
        return $this->isAwaitingPayout()
            && $this->refundDestination !== null;
    }

    /**
     * Puwede bang ipadala ito ng sistema mismo sa PayMongo?
     *
     * Hindi lang ito tanong kung "handa na ba" — tinitingnan din nito
     * kung may nakabinbin nang pagtatangka. Ang isang `pending` na
     * transfer ay maaaring nasa kalagitnaan ng InstaPay pa; ang
     * magpadala ulit ay magpapadala ng pera nang dalawang beses.
     *
     * Ang cash ay wala rito magpakailanman — inaabot iyon nang
     * personal, at walang account na mapagpapadalhan.
     */
    public function canSendTransfer(): bool
    {
        if (! $this->isReadyToSend() || $this->payment_method === 'cash') {
            return false;
        }

        // May mga institusyong tinatanggihan ang bawat transfer mula sa
        // PayMongo Wallet. Ang pagpapakita ng button na garantisadong
        // babagsak ay pagsasayang ng oras ng admin at pagtuturo sa
        // kanya na balewalain ang mga error.
        if (! $this->refundDestination->canReceiveTransfer()) {
            return false;
        }

        // Tinatanggihan ng PayMongo ang mga halagang wala pang ₱5, at
        // ang isinasagot nito ay mukhang tungkol sa account — kaya mas
        // mabuti nang huwag na itong ialok kaysa hayaang bumagsak nang
        // may nakalilitong dahilan.
        if ((float) $this->amount <= RefundTransfer::MINIMUM_AMOUNT) {
            return false;
        }

        return ! $this->refundTransfers
            ->contains(fn ($t) => in_array($t->status, ['pending', 'succeeded'], true));
    }

    /**
     * May transfer bang kasalukuyang nasa daan?
     */
    public function hasTransferInFlight(): bool
    {
        return $this->refundTransfers->contains(fn ($t) => $t->status === 'pending');
    }

    /**
     * Ilang araw nang naghihintay ang refund na ito.
     *
     * Ginagamit ang `created_at`, hindi `payment_date` — petsa lang ang
     * `payment_date` (walang oras), at ang tanong dito ay "gaano na
     * katagal", hindi "anong petsa". Ang paggamit ng date-only na
     * column para sa aging ay lumilikha ng mga off-by-one na araw.
     */
    public function daysAwaitingPayout(): int
    {
        if (! $this->isAwaitingPayout() || ! $this->created_at) {
            return 0;
        }

        return (int) $this->created_at->diffInDays(now());
    }

    public function isOverduePayout(): bool
    {
        return $this->isAwaitingPayout()
            && $this->daysAwaitingPayout() >= self::PAYOUT_NUDGE_DAYS;
    }

    /**
     * Mga label na nakikita ng tao.
     *
     * Static ang mga ito para magamit din ng NotificationHelper, na may
     * hawak lang na string (hindi buong Payment model). Dati, ginagawa
     * ng bawat lugar ang sariling `ucfirst(str_replace('_',' ', ...))`
     * — kaya lumalabas ang "Full_payment", "Qrph", "FULL PAYMENT" at
     * "Full payment" sa apat na magkakaibang pahina para sa iisang
     * halaga.
     */
    // ── Manual-entry guards (v7.1) ─────────────────────────────────
    //
    // Ang online path ay protektado ng idempotency sa `reference_number`
    // at ng unique index sa ilalim nito. Ang manwal na pagtatala ay
    // walang katumbas na proteksiyon: `min:1` lang ang validation, kaya
    // ang isang staff na nagtala ng bayad na naibayad na online — o
    // dalawang staff na sabay nagtala ng iisang cash — ay lumilikha ng
    // pangalawang row nang walang anumang babala.
    //
    // Dalawang magkahiwalay na tanong ang mga ito, at magkaiba ang
    // tamang sagot sa bawat isa: ang lumampas sa balanse ay MALI
    // (hinaharangan), samantalang ang kamukhang bayad ay
    // KAHINA-HINALA lang (kailangan ng kumpirmasyon — totoong
    // nangyayari na dalawang beses magbayad ng magkaparehong halaga ang
    // isang guest sa iisang araw).

    /**
     * Lalampas ba sa natitirang balanse ang halagang ito?
     */
    public static function exceedsBalance(Booking $booking, float $amount): bool
    {
        return round($amount, 2) > round((float) $booking->balance_due, 2);
    }

    /**
     * May naitala na bang kamukhang bayad para sa booking na ito —
     * parehong halaga, parehong paraan, parehong araw?
     */
    public static function looksLikeDuplicate(Booking $booking, float $amount, string $method, ?string $date = null): bool
    {
        return static::where('booking_id', $booking->id)
            ->where('amount', round($amount, 2))
            ->where('payment_method', $method)
            ->whereDate('payment_date', $date ? \Carbon\Carbon::parse($date)->toDateString() : today())
            ->where('payment_type', '!=', 'refund')
            ->exists();
    }

    /**
     * Ang dalawang tsek sa itaas, pinagsama sa anyong maibabalik agad
     * ng controller bilang validation errors — o `null` kung malinis.
     *
     * Iisang kopya nito ang umiiral dahil TATLONG manwal na path ang
     * gumagamit (admin booking page, admin payments page, staff front
     * desk). Tatlong kopya ng parehong lohika ay siguradong maglalayo,
     * at ang naglalayong kopya ng isang guard ay katumbas ng walang
     * guard sa path na naiwan.
     *
     * @return array<string, string>|null
     */
    public static function manualEntryProblem(
        Booking $booking,
        float $amount,
        string $method,
        bool $confirmedDuplicate = false,
        ?string $date = null
    ): ?array {
        // Tapos na ang booking — wala nang sisingilin. Nauuna ito sa
        // balance check dahil mali ang payo ng mensahe roon ("already
        // fully paid — check whether the guest paid online") para sa isang
        // cancelled na booking, at dahil naka-store na column lang ang
        // `balance_due` na puwedeng luma. Ang status ang totoo.
        //
        // Ito rin ang daan na ginamit para "i-mark as refunded" ang isang
        // refund — na nagtala lang ng bagong bayad sa booking na wala nang
        // utang.
        if (in_array($booking->status, ['cancelled', 'no_show'], true)) {
            return ['amount' =>
                $booking->booking_ref.' is '.($booking->status === 'no_show' ? 'marked as a no-show' : 'cancelled')
                .' — there is nothing left to collect on it, so nothing was recorded. '
                .'To close a refund you already sent, open that refund on the Payments page and use "Mark Paid Out".',
            ];
        }

        if (static::exceedsBalance($booking, $amount)) {
            return ['amount' =>
                'This payment of ₱'.number_format($amount, 2).' is more than the remaining balance of ₱'
                .number_format((float) $booking->balance_due, 2).' on '.$booking->booking_ref.'. '
                .((float) $booking->balance_due <= 0
                    ? 'This booking is already fully paid — check whether the guest already paid online before recording this again.'
                    : 'Record only what is actually owed; if the guest handed over more, give the change rather than recording it.'),
            ];
        }

        if (! $confirmedDuplicate && static::looksLikeDuplicate($booking, $amount, $method, $date)) {
            return ['amount' =>
                'A payment of ₱'.number_format($amount, 2).' by '.static::methodLabelFor($method)
                .' is already recorded for '.$booking->booking_ref.' on this date. If this is a second, separate '
                .'payment, tick "This is a separate payment" to confirm. If not, the guest has already paid.',
            ];
        }

        return null;
    }

    public static function methodLabelFor(?string $method): string
    {
        return match($method) {
            'qrph'  => 'QR Ph',
            'cash'  => 'Cash',
            default => ucfirst(str_replace('_', ' ', (string) $method)),
        };
    }

    public static function typeLabelFor(?string $type): string
    {
        return match($type) {
            'full_payment' => 'Full Payment',
            'partial'      => 'Partial',
            'balance'      => 'Balance',
            'extra'        => 'Extra',
            'refund'       => 'Refund',
            default        => ucfirst(str_replace('_', ' ', (string) $type)),
        };
    }

    public function getMethodLabelAttribute(): string
    {
        return self::methodLabelFor($this->payment_method);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabelFor($this->payment_type);
    }
}