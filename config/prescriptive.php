<?php

/**
 * Recommendations (prescriptive analytics) — the values a developer sets.
 *
 * The numbers the OWNER decides — how quiet is "quiet", how big the promo
 * is, how busy is "busy", how much to raise the rate, how many days
 * maintenance needs, how early to remind a guest — are in the `settings`
 * table and edited under Admin → Settings → Booking Rules → Recommendation Rules.
 */
return [

    // How far back "usual" occupancy is measured.
    'history_days' => 180,

    // How far ahead the promo and peak-pricing rules look. A stretch that
    // starts inside this range is considered whole.
    'lookahead_days' => 30,

    // Dates closer than this are left alone: a promo or a new rate needs a
    // little time to be seen before it can change anything.
    'min_lead_days' => 2,

    // Maintenance needs preparation, so the first candidate gap is this far
    // out, and the search stops at the horizon.
    'maintenance_lead_days' => 7,
    'maintenance_horizon_days' => 60,

    // Two bookings this close together need a turnover clean in between.
    // The fixed slots leave exactly 2 hours (5PM→7PM, 6AM→8AM).
    'turnover_gap_hours' => 3,

    // How far ahead turnover cleans are suggested. Kept short on purpose:
    // a task sent three weeks early is noise on the frontdesk screen.
    'turnover_lookahead_days' => 7,

    // Eid'l Fitr / Eid'l Adha and any special proclamation — add them here
    // when announced. Format: 'Y-m-d' => 'Name'.
    'extra_holidays' => [
        // '2027-03-20' => "Eid'l Fitr",
    ],
];
