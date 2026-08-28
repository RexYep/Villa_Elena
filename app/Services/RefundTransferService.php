<?php

namespace App\Services;

use App\Helpers\NotificationHelper;
use App\Models\Payment;
use App\Models\RefundDestination;
use App\Models\RefundTransfer;
use App\Models\StaffLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ang paglalabas ng refund sa pamamagitan ng PayMongo Send Money.
 *
 * Nakatira ito sa sarili nitong serbisyo dahil ito ang tanging code sa
 * buong sistema na naglalabas ng tunay na pera palabas. Ang lohika ay
 * itinayo sa paligid ng tatlong bagay na napatunayan sa live noong
 * 2026-08-23 sa dalawang tunay na ₱1 na transfer:
 *
 *   1. Ang `201` ay HINDI nangangahulugang dumating ang pera. Isa sa
 *      dalawa ay tinanggap bilang `pending` at tinanggihan makalipas
 *      ang 2 segundo (`AC06 BlockedAccount`).
 *   2. Ang bigong transfer ay LIBRE — ibinabalik sa 0 ang ₱10 fee —
 *      kaya ang muling pagsubok ay ligtas.
 *   3. Nasa loob ng ~2.25 segundo ang settlement, kaya makatuwirang
 *      maghintay nang saglit at bigyan ng tiyak na sagot ang admin.
 */
class RefundTransferService
{
    public function __construct(private PayMongoService $paymongo)
    {
    }

    /**
     * Gaano katagal maghihintay ng settlement bago sumuko at iwang
     * `pending`. Ang naobserbahan ay ~2.25s; ang 8s ay maluwag na
     * puwang nang hindi pinapatagal nang husto ang request ng admin.
     */
    private const SETTLE_TIMEOUT_SECONDS = 8;

    private const POLL_INTERVAL_MICROSECONDS = 1_000_000;

    /**
     * Ilang beses susubukan bago sumuko.
     *
     * Ang `3` ay hindi galing sa isang sinukat na success rate — wala
     * pa tayo niyon. Ito ay kung gaano katagal makatuwirang paghintayin
     * ang admin sa harap ng isang button: humigit-kumulang 10 segundo
     * kada pagsubok. Kapag nasukat na ang tunay na rate ng GCash,
     * dito ito iaayos.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Ipinapadala ang refund. Ibinabalik ang talaan ng pagtatangka.
     *
     * @throws \RuntimeException kapag hindi dapat ipadala ang refund
     */
    public function send(Payment $refund, ?int $userId = null): RefundTransfer
    {
        $destination = $refund->refundDestination;

        if ($destination === null) {
            throw new \RuntimeException('There is no destination on file for this refund.');
        }

        // Ang hadlang ay nakatira RIN dito, hindi lang sa controller.
        // Alam nating tinatanggihan ng ilang institusyon ang bawat
        // transfer mula sa Wallet; walang saysay na magpadala ng utos
        // na sigurado nang babagsak, saanman ito manggaling — sa
        // button ng admin, sa isang retry, o sa isang command.
        if (! $destination->canReceiveTransfer()) {
            throw new \RuntimeException(
                $destination->transferBlockedReason()
                . ' Send it by hand and record it with "Mark Paid Out".'
            );
        }

        // WALANG napatunayang minimum — `0.00` ang `MINIMUM_AMOUNT`,
        // kaya ang tseke na ito ay laban sa zero o negatibong refund
        // lamang. Ang lumang komentaryo dito ay nagsasabing ₱5 ang
        // minimum ng PayMongo; galing iyon sa support at pinabulaanan
        // ng sarili nating ₱1 at ₱2 na dumating sa Maya. Huwag itong
        // ibalik nang walang transfer na magpapatunay.
        if ((float) $refund->amount <= RefundTransfer::MINIMUM_AMOUNT) {
            throw new \RuntimeException(
                'PayMongo will not transfer ₱' . number_format((float) $refund->amount, 2)
                . '. Send this one by hand and record it with "Mark Paid Out".'
            );
        }

        // Walang wallet ang test mode — `{"data":[]}`, hindi error.
        // Sinasalo ito BAGO ang pag-angkin para walang naiiwang `error`
        // na row at walang abiso sa admin para sa isang bagay na
        // hindi naman kailanman posible sa mode na ito.
        if ($this->paymongo->isTestMode()) {
            throw new \RuntimeException(
                'PayMongo is in test mode, and test mode has no wallet — Send Money only works with '
                . 'live keys. Record this refund with "Mark Paid Out" instead.'
            );
        }

        $this->assertBalanceCovers((float) $refund->amount);

        // ── 1. Subukan — at ULITIN kapag bumagsak ──────────────────
        //
        // Ang `AC06` mula sa GCash ay HINDI matatag. Ang eksaktong
        // parehong payload ay bumagsak nang 16:48 at dumating nang
        // 16:49 noong 2026-08-26 (`tr_2125e015…` laban sa
        // `tr_9bc989fa…`), at may parehong dalawang mukha rin ang
        // dashboard. Kaya ang tamang sagot sa isang pagtanggi ay hindi
        // ang sumuko kundi ang ulitin.
        //
        // Libre ang bigong transfer, kaya walang halaga ang pag-ulit
        // maliban sa ilang segundo. Isang bagong row kada pagsubok,
        // para buo ang kasaysayan, at isang bagong `reference_number`
        // kada pagsubok, dahil hinihingi iyon ng PayMongo.
        $attempts = 0;

        do {
            $attempts++;
            $transfer = $this->attempt($refund, $destination, $userId);
        } while ($transfer->isRetryable() && $attempts < self::MAX_ATTEMPTS);

        // Isang abiso lang sa dulo, hindi isa kada pagsubok. Walang
        // pakialam ang admin sa mga panandaliang pagtanggi — ang
        // mahalaga sa kanya ay kung dumating ba o hindi.
        if ($transfer->hasFailed()) {
            $this->recordFailure($refund, $transfer, $attempts);
        }

        // Kung nasa daan pa rin ito, sabihin sa guest — kung hindi,
        // katahimikan lang ang mararanasan niya hanggang dumating.
        // Ang mga naipadala at dumating na agad ay hindi dinadaan
        // dito: ang `recordSuccess()` na ang nag-abiso.
        if ($transfer->isPending()) {
            NotificationHelper::refundOnTheWay($refund, $transfer);
        }

        return $transfer;
    }

    /**
     * Isang pagtatangka: angkinin, ipadala, hintayin ang kapalaran.
     *
     * HINDI ito nag-aabiso ng pagkabigo — ang tumatawag ang bahala
     * doon, isang beses lang, matapos ang huling pagsubok.
     */
    private function attempt(Payment $refund, RefundDestination $destination, ?int $userId): RefundTransfer
    {
        // ── Angkinin ang refund BAGO gumalaw ang pera ──────────────
        //
        // Ang pag-angkin ay nasa loob ng maikling transaction na may
        // `lockForUpdate` para ang dobleng pindot — o dalawang admin na
        // sabay — ay hindi makapagpadala nang dalawang beses. Ang
        // hadlang ay nasa DATABASE, hindi sa view: ang pagtatago ng
        // button ay hindi hadlang, dekorasyon lang iyon.
        //
        // Isinusulat na ang row bago pa ang tawag, kaya kahit mag-crash
        // ang proseso sa gitna ng pagpapadala ay may bakas pa rin kung
        // ano ang sinubukan.
        $transfer = DB::transaction(function () use ($refund, $destination, $userId) {
            $locked = Payment::whereKey($refund->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isAwaitingPayout()) {
                throw new \RuntimeException('This refund is not awaiting payout any more.');
            }

            $blocking = RefundTransfer::where('payment_id', $refund->id)
                ->whereIn('status', ['pending', 'succeeded'])
                ->first();

            if ($blocking) {
                throw new \RuntimeException($blocking->isSucceeded()
                    ? 'This refund has already been sent.'
                    : 'A transfer for this refund is already in progress.');
            }

            return RefundTransfer::create([
                'payment_id'       => $refund->id,
                'reference_number' => $this->newReference($refund),
                'status'           => 'pending',
                'amount'           => $refund->amount,
                // Ang riles ay hindi pinipili ng admin — nakadepende
                // ito sa institusyon. InstaPay ang default; walang
                // laman ang `PESONET_ONLY_BICS`. Tumatanggap ang GCash
                // ng InstaPay — kahit mula sa API — pero hindi sa
                // bawat pagkakataon. Ang pag-ulit ang sagot doon,
                // hindi ang paglipat ng riles.
                'provider'         => $destination->preferredProvider(),
                'institution_name' => $destination->institution_name,
                'institution_bic'  => $destination->institution_bic,
                'account_number'   => $destination->account_number,
                'account_name'     => $destination->account_name,
                'initiated_by'     => $userId,
            ]);
        });

        // ── 2. Ang tawag mismo — SA LABAS ng transaction ────────────
        //
        // Kung ito ay nasa loob ng isang transaction na mag-ro-rollback
        // pagkatapos, gagalaw ang pera nang walang anumang natitirang
        // tala nito. Iyon ang isang pagkakamaling hindi na mababawi.
        try {
            $response = $this->paymongo->sendTransfer(
                amount:          (float) $refund->amount,
                destination:     $destination->toTransferAccount(),
                referenceNumber: $transfer->reference_number,
                description:     $this->describe($refund),
                callbackUrl:     $this->callbackUrl(),
                provider:        $transfer->provider,
                purpose:         RefundTransfer::purpose(),
            );
        } catch (\Throwable $e) {
            // Hindi umabot sa PayMongo, o tinanggihan nito ang mismong
            // request. Walang perang gumalaw — pero ang pag-angkin ay
            // nakasulat na, kaya kailangang buksan itong muli.
            $transfer->update([
                'status'                 => 'error',
                'provider_error_message' => Str::limit($e->getMessage(), 250),
            ]);

            Log::error("Refund transfer failed to send for payment {$refund->id}: " . $e->getMessage());

            return $transfer->fresh();
        }

        $transfer->update([
            'transfer_id'       => $response['id'] ?? null,
            'batch_transfer_id' => $response['batch_transfer_id'] ?? null,
            'fee'               => isset($response['fee']) ? $response['fee'] / 100 : 0,
        ]);

        $transfer = $transfer->fresh();

        // ── Hintayin ang tunay na kapalaran ───────────────────────
        //
        // Sa PESONet ay WALANG hinihintay: batch-cleared ito tuwing
        // 11:00 / 14:00 / 17:00 sa mga banking day lang, kaya oras ang
        // tagal. Ang paghintay ng 8 segundo doon ay walang saysay na
        // pagpapatagal sa request ng admin — hahabulin na lang ito ng
        // callback o ng susunod na sync.
        if ($transfer->provider === 'instapay') {
            // `false`: ang pagkabigo ng ISANG pagsubok ay hindi pa
            // balita. Ang tumatawag ang mag-aabiso, sa dulo.
            $transfer = $this->awaitSettlement($transfer, $refund, false);
        }

        return $transfer;
    }

    /**
     * Tinatanong nang paulit-ulit ang PayMongo hanggang matapos ang
     * transfer o maubos ang oras.
     */
    private function awaitSettlement(RefundTransfer $transfer, Payment $refund, bool $announceFailure = true): RefundTransfer
    {
        $deadline = microtime(true) + self::SETTLE_TIMEOUT_SECONDS;

        do {
            usleep(self::POLL_INTERVAL_MICROSECONDS);

            $transfer = $this->syncStatus($transfer, $refund, $announceFailure);

            if (! $transfer->isPending()) {
                return $transfer;
            }
        } while (microtime(true) < $deadline);

        // Nanatiling `pending`. Hindi ito pagkabigo — hindi pa lang
        // ito tapos. Hahabulin ito ng callback o ng susunod na sync.
        return $transfer;
    }

    /**
     * Isinasabay ang isang transfer sa kung ano ang sinasabi ng
     * PayMongo, at inaayos ang refund kapag natapos na ito.
     *
     * Ito ang IISANG lugar kung saan nagiging `paid out` ang isang
     * refund dahil sa isang transfer — tinatawag ito ng poll, ng
     * callback, at ng anumang manu-manong pag-refresh.
     */
    public function syncStatus(RefundTransfer $transfer, ?Payment $refund = null, bool $announceFailure = true): RefundTransfer
    {
        if (! $transfer->isPending() || ! $transfer->transfer_id) {
            return $transfer;
        }

        $data = $this->paymongo->getTransfer($transfer->transfer_id);

        if ($data === null) {
            return $transfer;
        }

        $status = $data['status'] ?? 'pending';

        if ($status === 'pending') {
            return $transfer;
        }

        $transfer->update([
            'status' => $status === 'succeeded' ? 'succeeded' : 'failed',
            // Ngayon lang ito nagiging tunay na trace ng bangko — sa
            // unang sagot ay echo lamang ito ng reference natin.
            'provider_reference_number' => $data['provider_reference_number'] ?? null,
            'provider_error_code'       => $data['provider_error_code'] ?? null,
            'provider_error_message'    => Str::limit((string) ($data['provider_error_message'] ?? ''), 250) ?: null,
            'sub_code'                  => $data['metadata']['sub_code'] ?? null,
            // Sinisingil lang ang fee kapag dumating talaga ang pera.
            //
            // Ang bigong transfer ay LIBRE — pero ASYNCHRONOUS ang
            // pagbabalik ng fee sa PayMongo: kapag tinanong sila sa
            // mismong sandali ng pagbagsak, `1000` pa rin ang isinasagot
            // nila, at nagiging `0` lang ito makalipas ang ilang sandali.
            // Ang inline poll natin ay tumatama sa maagang bintanang
            // iyon, kaya naitatala ang ₱10 sa isang transfer na hindi
            // naman sinisingil.
            //
            // Napatunayan sa balanse ng wallet: anim na transfer, isa
            // lang ang nagtagumpay, at ₱12 lang ang nabawas (₱2 + ₱10).
            // Kaya ang katotohanan ay `0`, hindi ang sinasabi ng API.
            'fee'                       => $status === 'succeeded'
                ? (isset($data['fee']) ? $data['fee'] / 100 : 0)
                : 0,
            'settled_at'                => now(),
        ]);

        $transfer = $transfer->fresh();
        $refund   = $refund ?: $transfer->payment;

        // Ang TAGUMPAY ay laging itinatala — doon nagsasara ang refund,
        // at walang sinuman ang dapat maghintay para diyan. Ang
        // PAGKABIGO ay puwedeng ipagpaliban: sa loob ng retry loop ay
        // hindi pa ito balita, dahil baka dumating naman ang susunod.
        if ($transfer->isSucceeded()) {
            $this->recordSuccess($refund, $transfer);
        } elseif ($announceFailure) {
            $this->recordFailure($refund, $transfer);
        }

        return $transfer;
    }

    // ── Mga kahihinatnan ───────────────────────────────────────────

    /**
     * Dumating ang pera. Dito lang, at wala nang iba, nagsasara ang
     * refund dahil sa isang awtomatikong transfer.
     */
    private function recordSuccess(Payment $refund, RefundTransfer $transfer): void
    {
        if (! $refund->isAwaitingPayout()) {
            return;   // may ibang nakauna na — huwag nang ulitin
        }

        $refund->update([
            'status'          => 'success',
            'processed_by'    => $transfer->initiated_by,
            'transaction_ref' => $transfer->receipt_reference,
        ]);

        NotificationHelper::refundPaidOut($refund->fresh('booking'));

        StaffLog::record('refund_sent', 'payments', $refund->id,
            'Refund ₱' . number_format((float) $refund->amount, 2) . ' sent to '
            . $transfer->institution_name . ' via PayMongo (ref: ' . $transfer->receipt_reference . ')');
    }

    /**
     * Hindi dumating ang pera. Ang refund ay NANANATILING bukas —
     * hindi ito nakasarado nang tahimik, dahil may utang pa rin ang
     * resort sa guest.
     */
    private function recordFailure(Payment $refund, RefundTransfer $transfer, int $attempts = 1): void
    {
        $reason = $transfer->failureReason() ?? 'The transfer did not go through.';

        // Sinasabi ang bilang ng pagsubok. Kung hindi, ang isang
        // pagtanggi na inulit nang tatlong beses ay kamukhang-kamukha
        // ng isang hindi man lang sinubukang muli, at ang admin ay
        // pipindutin lang ulit ang parehong button.
        $tries = $attempts > 1 ? " Tried {$attempts} times." : '';

        NotificationHelper::notifyAdmin(
            'Refund transfer failed',
            'The ₱' . number_format((float) $refund->amount, 2) . ' refund for booking '
            . ($refund->booking->booking_ref ?? '#' . $refund->booking_id) . ' was not delivered. '
            . $reason . $tries . ' No fee was charged. Check the details with the guest and try again.',
            route('admin.payments.show', $refund, false),
        );

        StaffLog::record('refund_transfer_failed', 'payments', $refund->id,
            'Refund transfer failed for booking '
            . ($refund->booking->booking_ref ?? '#' . $refund->booking_id)
            . ' — ' . ($transfer->provider_error_code ?: $transfer->status) . ': ' . $reason);
    }

    // ── Mga maliliit na katulong ───────────────────────────────────

    /**
     * Tumatanggi bago pa man gumalaw ang anuman kapag hindi sasapat
     * ang wallet.
     *
     * Isinasama ang tinatayang fee: ang balanseng eksaktong kasingdami
     * ng refund ay hindi sapat, dahil ang ₱10 ay hiwalay na kinukuha.
     *
     * Kapag hindi maabot ang PayMongo (`null`), tumutuloy ito. Ang
     * tseke ay panangga laban sa isang alam nang pagkabigo, hindi
     * isang gate — at ang tunay na paghatol ay nasa PayMongo pa rin.
     */
    private function assertBalanceCovers(float $amount): void
    {
        $available = $this->paymongo->walletBalance();

        if ($available === null) {
            return;
        }

        $needed = $amount + RefundTransfer::ESTIMATED_FEE;

        if ($available < $needed) {
            throw new \RuntimeException(
                'The PayMongo wallet has ₱' . number_format($available, 2)
                . ' available but ₱' . number_format($needed, 2)
                . ' is needed (₱' . number_format($amount, 2) . ' refund + ₱'
                . number_format(RefundTransfer::ESTIMATED_FEE, 2) . ' transfer fee). '
                . 'Top up the wallet, or send this refund by hand and record it with "Mark Paid Out".'
            );
        }
    }

    /**
     * Kailangang natatangi ito — ito ang matatag na susi kapag hindi
     * umabot sa atin ang sagot ng PayMongo.
     */
    private function newReference(Payment $refund): string
    {
        return 'VE-RF' . $refund->id . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4));
    }

    private function describe(Payment $refund): string
    {
        $ref = $refund->booking->booking_ref ?? ('#' . $refund->booking_id);

        return "Villa Elena refund for booking {$ref}";
    }

    /**
     * Saan ipapaalam ng PayMongo ang resulta.
     *
     * Ibinabalik ang `null` kapag hindi maaabot mula sa labas ang app
     * (lokal na pag-develop) — walang saysay ang magpadala ng callback
     * URL na `127.0.0.1`. Ang polling ang sasagip doon.
     */
    private function callbackUrl(): ?string
    {
        $url = config('app.url');

        if (! $url || Str::contains($url, ['localhost', '127.0.0.1', '::1'])) {
            return null;
        }

        return rtrim($url, '/') . '/webhooks/paymongo/transfer';
    }
}
