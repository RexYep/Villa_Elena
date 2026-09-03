<?php

/**
 * Mga tunable ng prescriptive engine na HINDI dapat nasa Settings page.
 *
 * Ang mga pagpapalagay na binabago ng may-ari (elasticity, threshold,
 * lookahead) ay nasa `settings` table — makikita at maiaayos sa
 * Admin → Settings → Prescriptive Engine. Ang nandito ay mga bagay na
 * pagpapasya ng developer o taunang datos.
 */
return [

    // Mga diskuwentong sinusubukan ng optimizer (porsyento). Ang 0 ay
    // laging kasama bilang baseline — kung ito ang panalo, walang
    // imumungkahi, at iyon ang tamang sagot sa maraming araw.
    'discount_candidates' => [0, 5, 10, 15, 20],

    // Katapat nito para sa PeakRateAdvisor — mga pagtaas ng presyong
    // sinusubukan sa mga petsang malakas ang demand.
    'increase_candidates' => [0, 5, 10, 15, 20],

    // Pinakamataas na posibilidad na ipapalagay ng modelo kahit gaano
    // kalaki ang diskuwento. Walang presyong ginagarantiya ang booking.
    'probability_ceiling' => 0.90,

    // Hindi na gagalawin ang mga petsang mas malapit pa dito — kailangan
    // pa ng oras para maipaalam ang promo bago ito magkabisa.
    'min_lead_days' => 2,

    // Pinakamahabang tuluy-tuloy na promo na imumungkahi sa isang card.
    // Ang mas mahaba ay hinahati sa mga tipak, para ang "20% off buong
    // buwan" ay hindi maipasa bilang iisang pindot.
    'max_promo_run_days' => 14,

    // Bago ang unang posibleng maintenance window — hindi puwedeng bukas
    // agad, may kailangang ihanda.
    'maintenance_lead_days' => 7,

    // Gaano kalayo tumitingin ang maintenance advisor.
    'maintenance_horizon_days' => 60,

    // Eid'l Fitr / Eid'l Adha at anumang espesyal na proklamasyon —
    // idagdag dito kapag inanunsyo. Format: 'Y-m-d' => 'Pangalan'.
    'extra_holidays' => [
        // '2027-03-20' => "Eid'l Fitr",
    ],
];
