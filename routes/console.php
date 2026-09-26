<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:auto-checkinout')->everyMinute();

// Prescriptive engine — isang beses sa isang araw, bago magising ang admin.
// Hindi ito kailangang mas madalas: ang pinagbabatayan nito ay buwan-buwang
// pattern, hindi minutong palitan, at ang mga mungkahing nagbabago tuwing
// nagre-refresh ang page ay hindi mapagkakatiwalaan. May "Refresh" na buton
// naman sa page para sa mga sandaling gustong makita agad ang epekto ng
// katatapos lang na booking.
Schedule::command('prescriptive:generate')->dailyAt('01:30');

// staff_logs retention (v7.42). Nothing pruned this table until now, and v7.41
// made it the security-event store as well, so it grows with rejected requests
// too. Policy and the exempt list are in config/audit.php.
//
// 03:20 rather than on the hour: `schedule:run` is driven by an external cron
// pinger (see routes/cron.php), and the top of the hour is when every other
// free-tier cron service fires. The offset is only about the pinger's own
// reliability, not about load here — the delete is chunked and the table is small.
//
// `withoutOverlapping()` because this is driven by an HTTP ping: a slow run and
// the next ping arriving would otherwise have two pruners deleting the same rows.
Schedule::command('staff-logs:prune')->dailyAt('03:20')->withoutOverlapping();