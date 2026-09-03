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