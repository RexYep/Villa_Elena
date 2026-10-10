<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RefundDestination;
use App\Models\StaffLog;
use App\Services\PayMongoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Tinatanong ang guest kung saan niya gustong tanggapin ang refund.
 *
 * Kailangan ito dahil ANG QR Ph PAYMENT AY WALANG DALANG DETALYE NG
 * NAGBAYAD — walang account number, at ang `billing.name` ay ang mismong
 * ipinadala ng app noong ginawa ang checkout session, hindi ang
 * pangalang nakarehistro sa GCash ng guest. Tingnan ang migration ng
 * `refund_destinations` at ang §v5.9 ng project.md.
 *
 * MALAYANG PUMIPILI ANG GUEST ng destinasyon — hindi ito naka-lock sa
 * account na ginamit niyang pambayad. Sinasadyang desisyon iyon: ang
 * Send Money ay isang plain na transfer, hindi reversal, kaya kaya
 * nitong pumunta kahit saan; at may mga guest na nagbabayad gamit ang
 * wallet ng iba. Ang proteksyon ay hindi nasa form kundi sa dulo —
 * may admin na tumitingin bago lumabas ang pera, at nakatakda sa
 * halaga ng refund ang maaaring maipadala.
 */
class RefundDestinationController extends Controller
{
    public function __construct(private PayMongoService $paymongo)
    {
    }

    // ── Form ───────────────────────────────────────────────────────
    public function edit(Payment $payment)
    {
        $this->authorizeRefund($payment);

        if ($locked = $this->lockedRedirect($payment)) {
            return $locked;
        }

        $institutions = $this->paymongo->receivingInstitutions();

        return view('customer.refund_destination', [
            'payment'      => $payment,
            'booking'      => $payment->booking,
            'destination'  => $payment->refundDestination,
            'institutions' => $institutions,
            // Ang numero sa profile ang pinaka-malamang na sagot, pero
            // CONTACT number iyon ng booking — hindi kinakailangang ang
            // e-wallet number niya. Iminumungkahi, hindi ipinapalagay:
            // kailangan pa rin niyang tahasang kumpirmahin o palitan.
            'suggestedNumber' => Auth::user()->phone,
        ]);
    }

    // ── Save ───────────────────────────────────────────────────────
    public function update(Request $request, Payment $payment)
    {
        $this->authorizeRefund($payment);

        if ($locked = $this->lockedRedirect($payment)) {
            return $locked;
        }

        // Ang BIC ay dapat galing sa buhay na listahan ng PayMongo —
        // hindi malayang text. Dito napipigilan ang isang bumagsak na
        // transfer bago pa ito subukan: kung hindi kilala ng PayMongo
        // ang institusyon, walang saysay ang pagpapadala.
        $institutions = $this->paymongo->receivingInstitutions();
        $validBics    = array_column($institutions, 'bic');

        if (empty($validBics)) {
            // Hindi maabot ang PayMongo at wala ring stale na kopya.
            // Mas mabuting sabihin ito nang tahasan kaysa tumanggap ng
            // BIC na hindi natin mapapatunayan.
            return back()->with('error',
                'We could not load the list of banks and e-wallets right now. Please try again in a few minutes.');
        }

        $validated = $request->validate(
            RefundDestination::rules($validBics),
            RefundDestination::messages()
        );

        $institution = collect($institutions)
            ->firstWhere('bic', $validated['institution_bic']);

        if ($error = RefundDestination::mobileNumberError($institution['bic'], $institution['name'], $validated['account_number'])) {
            return back()->withErrors(['account_number' => $error])->withInput();
        }

        $destination = RefundDestination::updateOrCreate(
            ['payment_id' => $payment->id],
            [
                'institution_name' => $institution['name'],
                'institution_bic'  => $validated['institution_bic'],
                'account_number'   => $validated['account_number'],
                'account_name'     => trim($validated['account_name']),
                'provided_by'      => Auth::id(),
                'provided_at'      => now(),
            ]
        );

        // SINASADYANG hindi isinusulat ang account number o pangalan sa
        // StaffLog. Financial account data ito; ang layunin ng log ay
        // ipakita na may naganap, hindi ulitin ang laman.
        StaffLog::record('refund_destination_set', 'payments', $payment->id,
            "Refund destination provided for booking "
            . ($payment->booking->booking_ref ?? '#' . $payment->booking_id)
            . " ({$institution['name']})");

        Log::info("Refund destination set for payment {$payment->id} by user " . Auth::id());

        // Sabihin sa mga admin na puwede na itong ipadala — pero kapag
        // may NAGBAGO lang. Ang `provided_at` ay laging bago, kaya ang
        // tatlong field ng account mismo ang tinitingnan; kung hindi, ang
        // bawat muling pag-save ng parehong detalye ay isang abiso.
        if ($destination->wasRecentlyCreated
            || $destination->wasChanged(['institution_bic', 'account_number', 'account_name'])
        ) {
            NotificationHelper::refundDetailsProvided(
                $payment->setRelation('refundDestination', $destination),
                $destination
            );
        }

        return redirect()
            ->route('customer.bookings.show', $payment->booking_id)
            // Walang "shortly": walang gumagalaw hangga't hindi pinipindot
            // ng admin ang Send Refund, kaya iyon ay pangakong hindi kayang
            // tuparin ng sistema. Ang kaya nitong ipangako ay ang abiso —
            // kapareho ng sinasabi ng refund panel sa pahinang pupuntahan.
            ->with('success', 'Thank you — we have your refund details. It will go to your '
                . Payment::accountPhrase($destination) . ", and we'll notify you when it's sent.");
    }

    // ── Guards ─────────────────────────────────────────────────────

    /**
     * Sarili lang niyang refund ang puwedeng galawin ng guest.
     */
    private function authorizeRefund(Payment $payment): void
    {
        abort_if(! $payment->isRefund(), 404);
        abort_if($payment->booking?->user_id !== Auth::id(), 403);

        // Walang saysay ang bank details para sa cash refund — inaabot
        // ito nang personal sa front desk.
        abort_if($payment->payment_method === 'cash', 404);
    }

    /**
     * Sarado na ba ang pagpapalit ng detalye? Kung oo, ibinabalik ang
     * guest sa booking na may paliwanag — HINDI 403 (v7.64).
     *
     * Ang link ng lumang abiso ("Refund Coming", "We still need your
     * refund details") ay nananatili sa kampana matapos maipadala ang
     * pera, kaya ordinaryong pangyayari ang pagbukas nito nang huli —
     * hindi isang paglabag. Ang error page doon ay nagsasabi sa guest na
     * may mali sa refund niya gayong tapos na ito. Ang booking details
     * ang nagpapakita ng `info`, at naroon din ang refund panel.
     *
     * Dalawang kandado:
     *
     *  - Naipadala na. Ang record ay talaan na ng kung saan aktwal na
     *    napunta ang pera; ang pag-edit dito ay magpapasinungaling sa
     *    audit trail.
     *
     *  - NASA DAAN NA ANG PERA. Ang `isAwaitingPayout()` ay nananatiling
     *    totoo habang naglilinaw ang isang transfer — at sa PESONet ay
     *    maaaring umabot iyon ng isang araw. Kung wala ito, mapapalitan
     *    ng guest ang account niya mula GCash tungong Maya habang
     *    papunta na ang pera sa GCash: magpapakita ang sistema ng Maya,
     *    dadating ang pera sa GCash, at magmumukhang nawala ang refund.
     *    Kung bumagsak ang transfer, muling bubukas ito — at doon nga
     *    kailangan ang pagpapalit.
     */
    private function lockedRedirect(Payment $payment): ?RedirectResponse
    {
        $message = match (true) {
            ! $payment->isAwaitingPayout()
                => 'This refund has already been sent, so its account details can no longer be changed.',
            $payment->load('refundTransfers')->hasTransferInFlight()
                => 'Your refund is already on its way to the account you gave us, so these details are '
                    . 'locked until it arrives. If it does not go through, you will be able to change them again.',
            default => null,
        };

        return $message
            ? redirect()->route('customer.bookings.show', $payment->booking_id)->with('info', $message)
            : null;
    }
}
