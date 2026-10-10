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
 * @property string|null $refund_kind
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
     * Live na Revenue Today / Revenue This Month sa admin dashboard.
     *
     * Dating tanging ang Admin\PaymentController ang nagpapadala ng
     * PaymentReceived — ang cash na itinala mula sa booking page o sa
     * front desk ay hindi kailanman umabot sa dashboard nang live. Dito,
     * sakop ang bawat daanan. Ang touch() ay iisang broadcast kada request.
     */
    protected static function booted(): void
    {
        static::saved(function ($payment) {
            if ($payment->wasRecentlyCreated || $payment->wasChanged(['amount', 'payment_date', 'payment_type'])) {
                \App\Services\DashboardStats::touch();
            }
        });

        static::deleted(fn () => \App\Services\DashboardStats::touch());

        // `refund_kind` (v7.64) ay maaaring wala pa kapag nauna ang code
        // sa migration — sa production ay tumatakbo lang ang migration
        // kapag RUN_MIGRATIONS=true. Kung hindi ito aalisin dito, ang
        // bawat pag-issue ng refund ay magiging SQL error hanggang doon;
        // sa ganito, ang tanging nawawala ay ang dahilan sa panel ng guest.
        static::saving(function ($payment) {
            if ($payment->isDirty('refund_kind') && ! static::refundKindColumnExists()) {
                unset($payment->refund_kind);
            }
        });
    }

    protected static ?bool $refundKindColumnExists = null;

    /**
     * Pampubliko: binabasa rin ito ng Booking::recalculateFinancials(),
     * na nagfi-filter sa column na ito sa BAWAT pagtutuos ng bayad. Kung
     * wala ang tsekeng ito roon, ang code na nauna sa migration ay
     * magiging SQL error sa bawat bayad — hindi lang sa mga refund.
     */
    public static function refundKindColumnExists(): bool
    {
        if (static::$refundKindColumnExists === null) {
            try {
                static::$refundKindColumnExists = \Illuminate\Support\Facades\Schema::hasColumn('payments', 'refund_kind');
            } catch (\Throwable $e) {
                static::$refundKindColumnExists = false;
            }
        }

        return static::$refundKindColumnExists;
    }

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
        'refund_kind',
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

    // ── Bakit ibinabalik ang pera (v7.63) ──────────────────────────
    /**
     * Ang mga uri ng refund na inilalabas mula sa Payments page, at ang
     * label na nakikita ng admin sa Issue Refund na form.
     *
     * Pinipili ito ng admin dahil hindi ito mahuhulaan mula sa booking,
     * at ito ang nagpapasya kung ANO ANG SASABIHIN SA GUEST
     * (`NotificationHelper::refundComingForGuest()`). Dating iisang
     * "Refund Approved" ang natatanggap ng lahat — kasama ang guest na
     * kakasabihan pa lang na non-refundable ang bayad niya, at hindi
     * naman humiling ng anuman.
     *
     * `label` ang nakikita ng admin; `guest` ang pangungusap na
     * nababasa ng guest. Iisa ang `guest` para sa abiso AT sa refund
     * panel ng booking details (v7.64) — kaya wala itong halaga o
     * booking reference: idinadagdag iyon ng tumatawag. Huwag itong
     * i-type muli sa isang view o sa helper.
     */
    public const REFUND_KINDS = [
        'overpayment' => [
            'label' => 'Double charge or overpayment',
            'guest' => 'You paid more than this booking costs, so we are returning the extra.',
        ],
        'late_payment' => [
            'label' => 'Payment arrived after the booking was cancelled',
            'guest' => 'Your payment arrived after this booking was cancelled, so we are returning it.',
        ],
        'resort_cancelled' => [
            'label' => 'The resort cancelled the booking',
            'guest' => 'We had to cancel this booking on our side, so we are returning what you paid.',
        ],
        'goodwill' => [
            'label' => 'Goodwill — the resort chose to return it',
            'guest' => 'Payments are normally non-refundable, but the resort has decided to return this to you.',
        ],
    ];

    public static function refundKindLabelFor(?string $kind): string
    {
        return self::REFUND_KINDS[$kind]['label'] ?? 'Refund';
    }

    /**
     * NULL kapag hindi kilala ang uri — kasama ang bawat refund na
     * ginawa bago nagkaroon ng `refund_kind`. Walang hinuhulaang dahilan.
     */
    public static function refundReasonForGuest(?string $kind): ?string
    {
        return self::REFUND_KINDS[$kind]['guest'] ?? null;
    }

    // ── Ang refund sa mata ng guest (v7.64) ────────────────────────
    //
    // Iba ang tanong dito sa `Booking::refundStage()`, na para sa admin
    // at sa badge ng buong booking. Ito ay kada refund, at ang sagot ay
    // kung ano ang dapat MALAMAN O GAWIN ng guest — kaya walang
    // "failed" rito: ang bumagsak na transfer ay problema ng resort,
    // maliban kung ang account na ibinigay niya ang tinanggihan.

    /**
     * @return string  none | needs_details | details_rejected | not_sent
     *                 | on_its_way | cash_due | received | sent | paid_cash
     */
    public function guestRefundState(): string
    {
        if (! $this->isRefund()) {
            return 'none';
        }

        $cash = $this->payment_method === 'cash';

        if ($this->isPaidOut()) {
            return match (true) {
                $cash                              => 'paid_cash',
                $this->succeededTransfer() !== null => 'received',
                default                            => 'sent',
            };
        }

        if ($cash) {
            return 'cash_due';
        }

        if ($this->hasTransferInFlight()) {
            return 'on_its_way';
        }

        $destination = $this->refundDestination;

        if ($destination === null) {
            return 'needs_details';
        }

        // Tinanggihan ang ACCOUNT, hindi lang ang pagsubok. Ang lahat ng
        // iba pang pagkabigo (AC06 ng GCash, kulang na laman ng wallet,
        // `error`) ay hindi maaayos ng guest, kaya "not sent yet" lang
        // ang mga iyon sa kanya. Kapag in-update na niya ang detalye
        // matapos ang pagtanggi, tapos na ang bahagi niya.
        return $this->rejectedDetailsTransfer() ? 'details_rejected' : 'not_sent';
    }

    /**
     * Ang pagtatangkang tinanggihan dahil sa KASALUKUYANG detalye ng
     * guest, kung mayroon.
     *
     * Hindi lang ang pinakahuling pagtatangka ang tinitingnan: kung
     * tinanggihan ang account at saka bumagsak ulit sa ibang dahilan
     * (hal. kulang ang laman ng wallet), mali pa rin ang account.
     */
    public function rejectedDetailsTransfer(): ?RefundTransfer
    {
        $since = $this->refundDestination?->provided_at;

        return $this->refundTransfers->first(fn ($transfer) => $transfer->isAccountDetailProblem()
            && ! ($since && $transfer->created_at && $since->gt($transfer->created_at)));
    }

    // ── Magkano pa ang puwedeng i-refund (v7.66) ───────────────────
    /**
     * Ang natitirang maire-refund kada booking: lahat ng bayad na hindi
     * refund, bawas ang lahat ng refund (naipadala man o hindi pa — utang
     * na iyon ng resort mula nang i-issue).
     *
     * IISA ang kahulugang ito para sa tseke ng server
     * (`Admin\PaymentController::refund()`, na may `$lock`) at sa "Up to
     * ₱X" ng Issue Refund na form. Noong ang form ay may sarili nitong
     * bilang — ang halaga ng iisang bayad — nag-aalok ito ng refund na
     * tinatanggihan ng server matapos ang isang naunang refund.
     *
     * Iisang query para sa buong pahina ng listahan, hindi isa kada hanay.
     *
     * @param  iterable<int>  $bookingIds
     * @return array<int, float>  booking_id => halaga (hindi bababa sa 0)
     */
    public static function refundableByBooking(iterable $bookingIds, bool $lock = false): array
    {
        $ids = collect($bookingIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return static::query()
            ->whereIn('booking_id', $ids)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['booking_id', 'payment_type', 'amount'])
            ->groupBy('booking_id')
            ->map(fn ($rows) => max(0, round(
                (float) $rows->where('payment_type', '!=', 'refund')->sum('amount')
                - (float) $rows->where('payment_type', 'refund')->sum('amount'),
                2
            )))
            ->all();
    }

    /**
     * Ang pinakamalaking refund na maiaalok mula sa bayad na ITO: hindi
     * hihigit sa sarili nitong halaga, at hindi hihigit sa natitira sa
     * booking.
     */
    public function refundCeiling(float $bookingRefundable): float
    {
        if ($this->isRefund()) {
            return 0.0;
        }

        return max(0, round(min((float) $this->amount, $bookingRefundable), 2));
    }

    /**
     * Ang maikling katayuan — pareho sa refund panel at sa hanay ng
     * Payment History, kaya dito ito nakatira at hindi sa view.
     */
    public function getGuestRefundStatusLabelAttribute(): string
    {
        return match ($this->guestRefundState()) {
            'needs_details'    => 'Needs your account details',
            'details_rejected' => 'Could not be delivered',
            'not_sent'         => 'Not sent yet',
            'on_its_way'       => 'On its way',
            'cash_due'         => 'To be paid in cash',
            'received'         => 'Received',
            'sent'             => 'Sent',
            'paid_cash'        => 'Paid in cash',
            default            => '',
        };
    }

    /**
     * Ang kulay ng katayuan: done | moving | problem | waiting.
     *
     * Iisa para sa refund panel at sa My Payments, para hindi maging
     * berde sa isang pahina at dilaw sa isa ang iisang refund.
     */
    public function guestStatusTone(): string
    {
        if (! $this->isRefund()) {
            return match ($this->status) {
                'success', 'refunded' => 'done',
                'failed'              => 'problem',
                default               => 'waiting',
            };
        }

        return match ($this->guestRefundState()) {
            'received', 'sent', 'paid_cash' => 'done',
            'on_its_way'                    => 'moving',
            'details_rejected'              => 'problem',
            default                         => 'waiting',
        };
    }

    /**
     * Ang katayuan ng ANUMANG bayad sa My Payments (v7.67).
     *
     * Dating `ucfirst($payment->status)` — ang hilaw na enum. "Success"
     * ang lumalabas sa isang bayad, at "Pending" sa isang refund na
     * hinihintay pala ang account ng guest, nang walang sinasabing
     * siya ang hinihintay.
     */
    public function getGuestStatusLabelAttribute(): string
    {
        if ($this->isRefund()) {
            return $this->guest_refund_status_label;
        }

        return match ($this->status) {
            'success'  => 'Paid',
            'pending'  => 'Pending',
            'failed'   => 'Failed',
            'refunded' => 'Refunded',
            default    => ucfirst((string) $this->status),
        };
    }

    /**
     * Paano binayaran — o, para sa refund, saan ito ibinabalik.
     *
     * Ang refund row ay kumokopya ng `payment_method` ng ORIHINAL na
     * bayad, kaya "QR Ph" ang lumalabas sa isang refund na ipinadala sa
     * GCash account ng guest. Hindi naipapadala ang refund sa QR Ph.
     */
    public function getGuestMethodLabelAttribute(): string
    {
        if (! $this->isRefund() || $this->payment_method === 'cash') {
            return $this->method_label;
        }

        $account = $this->succeededTransfer()
            ?? $this->refundTransfers->firstWhere('status', 'pending')
            ?? $this->refundDestination;

        return $account->institution_name ?? 'Bank or e-wallet';
    }

    /**
     * Ang bahaging pareho sa abiso at sa refund panel kapag tinanggihan
     * ang account na ibinigay ng guest. Ang unahan ("We tried to send …
     * to your …,") ay sa tumatawag, dahil magkaiba ang alam na ng
     * mambabasa sa bawat lugar.
     */
    public const DETAILS_REJECTED_NOTE = "but it was not accepted — the account number or name may be wrong. "
        . "Nothing was lost. Please check the details and we'll send it again.";

    public function getGuestRefundReasonAttribute(): ?string
    {
        return self::refundReasonForGuest($this->refund_kind);
    }

    public function succeededTransfer(): ?RefundTransfer
    {
        return $this->refundTransfers->firstWhere('status', 'succeeded');
    }

    /**
     * Saan pumunta (o pupunta) ang refund, sa pananalitang ligtas
     * ipakita: "{institusyon} account ending in 4567".
     *
     * Ang transfer ang inuuna kapag may nasa daan o dumating na — iyon
     * ang hindi nababagong tala ng aktwal na pinuntahan. Huling 4 na
     * digit lang; financial account data ito.
     */
    public function refundAccountPhrase(): ?string
    {
        $account = $this->succeededTransfer()
            ?? $this->refundTransfers->firstWhere('status', 'pending')
            ?? $this->refundDestination;

        return self::accountPhrase($account);
    }

    /**
     * @param  RefundTransfer|RefundDestination|null  $account
     */
    public static function accountPhrase($account): ?string
    {
        if (! $account) {
            return null;
        }

        return "{$account->institution_name} account ending in " . substr((string) $account->account_number, -4);
    }

    /**
     * Kailan nagsara ang refund. Ang `settled_at` ng transfer kapag
     * awtomatiko; kung hindi, ang huling galaw ng row — na ang pagmamarka
     * mismo, dahil wala nang nagbabago sa isang refund pagkatapos niyon.
     */
    public function refundClosedAt(): ?\Illuminate\Support\Carbon
    {
        if (! $this->isPaidOut()) {
            return null;
        }

        return $this->succeededTransfer()?->settled_at ?? $this->updated_at;
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