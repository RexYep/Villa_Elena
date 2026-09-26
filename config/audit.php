<?php

return [

    /*
    |--------------------------------------------------------------------------
    | staff_logs retention
    |--------------------------------------------------------------------------
    |
    | Nothing pruned this table until v7.42, and v7.41 made it the security-event
    | store as well, so it now grows with rejected requests rather than only with
    | staff activity. It was already the largest table in the database: 448 KB of
    | 1.63 MB, and 1,206 of 2,283 rows.
    |
    | TWO TIERS, because the rows are not worth the same. A successful login or a
    | completed housekeeping task is operational noise a fortnight later. A
    | lockout, a password change, a permission failure or a credential spray is
    | the thing you go looking for after an incident — and incidents are usually
    | discovered long after they happen, which is exactly the argument for keeping
    | the security tier much longer.
    |
    | Set either to 0 to keep that tier forever.
    |
    */

    'retention' => [
        'security_days' => (int) env('AUDIT_KEEP_SECURITY_DAYS', 365),
        'routine_days' => (int) env('AUDIT_KEEP_ROUTINE_DAYS', 90),
    ],

    /*
    | Actions that must NEVER be pruned, whatever their age.
    |
    | `auto_checkin_skipped_balance` is not history — it is APPLICATION STATE. The
    | scheduler reads it as a dedup key (AutoCheckInOutBookings, the
    | `$alreadyAlerted` check) so that a guest who arrives with an outstanding
    | balance produces one admin notification rather than one per minute. Delete
    | the row and `bookings:auto-checkinout` starts re-notifying every single
    | minute until staff resolve the booking. That is a functional bug, not a gap
    | in the record, and it is why this list exists at all rather than the policy
    | being a single date comparison.
    |
    | Anything else that starts being read as state belongs here too.
    */
    'never_prune' => [
        'auto_checkin_skipped_balance',
    ],

];
