<?php

namespace App\Services\Prescriptive;

use Carbon\Carbon;

/**
 * Mga pistang opisyal sa Pilipinas, KINOKOMPYUT hindi hinahardcode bawat taon.
 *
 * Isang static na listahan ng petsa ay tama sa taong isinulat ito at mali
 * pagkatapos — at ang isang mungkahing "mag-maintenance sa Dis 25" ay
 * agarang nasisira ang tiwala sa buong feature. Kaya ang mga nakapirming
 * petsa (RA 9492) ay binubuo mula sa taon, at ang mga gumagalaw na
 * Kristiyanong petsa ay hinahango sa Pasko ng Pagkabuhay gamit ang
 * algoritmong Meeus/Butcher (walang `ext-calendar` na kailangan — hindi
 * ito laging naka-enable sa PHP).
 *
 * SADYANG WALA ang Eid'l Fitr at Eid'l Adha: taun-taon silang
 * ipinoproklama batay sa obserbasyon ng buwan, kaya hindi sila
 * mahuhulaan nang eksakto sa code. Idagdag sila sa
 * `config/prescriptive.php` → `extra_holidays` kapag inanunsyo na.
 *
 * Ginagamit ito bilang BANTAY, hindi bilang hula: hindi nagmumungkahi ng
 * diskuwento o maintenance ang engine sa mga petsang ito.
 */
class HolidayCalendar
{
    private array $cache = [];

    public function name(Carbon $date): ?string
    {
        $year = (int) $date->year;

        return $this->forYear($year)[$date->format('Y-m-d')] ?? null;
    }

    public function isHoliday(Carbon $date): bool
    {
        return $this->name($date) !== null;
    }

    /** @return array<string, string> 'Y-m-d' => pangalan */
    public function forYear(int $year): array
    {
        if (isset($this->cache[$year])) {
            return $this->cache[$year];
        }

        $days = [
            "{$year}-01-01" => "New Year's Day",
            "{$year}-04-09" => 'Araw ng Kagitingan',
            "{$year}-05-01" => 'Labor Day',
            "{$year}-06-12" => 'Independence Day',
            "{$year}-08-21" => 'Ninoy Aquino Day',
            "{$year}-11-01" => "All Saints' Day",
            "{$year}-11-02" => "All Souls' Day",
            "{$year}-11-30" => 'Bonifacio Day',
            "{$year}-12-08" => 'Immaculate Conception',
            "{$year}-12-24" => 'Christmas Eve',
            "{$year}-12-25" => 'Christmas Day',
            "{$year}-12-30" => 'Rizal Day',
            "{$year}-12-31" => 'Last Day of the Year',
        ];

        // Huling Lunes ng Agosto
        $days[Carbon::create($year, 8, 31)->lastOfMonth(Carbon::MONDAY)->format('Y-m-d')] = 'National Heroes Day';

        // Semana Santa — hango sa Pasko ng Pagkabuhay
        $easter = $this->easter($year);
        $days[$easter->copy()->subDays(3)->format('Y-m-d')] = 'Maundy Thursday';
        $days[$easter->copy()->subDays(2)->format('Y-m-d')] = 'Good Friday';
        $days[$easter->copy()->subDay()->format('Y-m-d')] = 'Black Saturday';

        foreach ((array) config('prescriptive.extra_holidays', []) as $date => $label) {
            $days[$date] = $label;
        }

        return $this->cache[$year] = $days;
    }

    /** Meeus/Butcher — Gregorian Easter Sunday. */
    private function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
