<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Services\Prescriptive\HolidayCalendar;
use App\Services\Prescriptive\OccupancyCalendar;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fills the LOCAL database with a year of made-up bookings, so the
 * Recommendations page has history to measure "usual" against.
 *
 * Everything it writes can be found again and removed with --clear:
 * bookings are `DEMO-…`, guests are `…@demo.villa-elena.test`.
 *
 * The pattern is deliberately seasonal (busy summer and December, quiet
 * rainy months, busier weekends), because the page's history filter can
 * only show a difference if the months differ.
 *
 * It only fills slots that are free and offered, so existing bookings are
 * never overlapped. It sends no email and no notification.
 */
class DemoHistory extends Command
{
    protected $signature = 'demo:history
        {--clear : Remove every DEMO booking and demo guest instead}
        {--months= : How many months back to fill (default: from six months before last year began)}
        {--ahead=60 : How many days ahead to fill (as "booked so far")}
        {--seed=2026 : Random seed, so the same data comes out each time}';

    protected $description = 'Add (or --clear) made-up booking history on the LOCAL database, for the Recommendations demo';

    public const REF_PREFIX = 'DEMO-';

    public const EMAIL_DOMAIN = 'demo.villa-elena.test';

    /** Chance a slot is booked, by day of week (0 = Sunday). */
    private const WEEKDAY_FILL = [
        0 => ['day' => 0.45, 'night' => 0.15],
        1 => ['day' => 0.12, 'night' => 0.15],
        2 => ['day' => 0.12, 'night' => 0.18],
        3 => ['day' => 0.12, 'night' => 0.18],
        4 => ['day' => 0.15, 'night' => 0.22],
        5 => ['day' => 0.30, 'night' => 0.55],
        6 => ['day' => 0.55, 'night' => 0.60],
    ];

    /** Season multiplier by month: summer and December busy, rainy months quiet. */
    private const MONTH_FACTOR = [
        1 => 1.0, 2 => 0.9, 3 => 1.5, 4 => 1.6, 5 => 1.5, 6 => 1.0,
        7 => 0.45, 8 => 0.45, 9 => 0.5, 10 => 0.7, 11 => 0.9, 12 => 1.5,
    ];

    private const NAMES = [
        'Ana Reyes', 'Mark Santos', 'Joy Dela Cruz', 'Paolo Garcia', 'Kim Mendoza',
        'Carlo Bautista', 'Liza Ramos', 'Jun Villanueva', 'Rica Aquino', 'Miguel Torres',
        'Bea Castillo', 'Rafael Navarro', 'Trisha Flores', 'Enzo Gonzales', 'Mae Fernandez',
        'Jomar Lopez', 'Camille Rivera', 'Noel Pascual', 'Faye Domingo', 'Ivan Salazar',
        'Grace Manalo', 'Ken Ocampo', 'Shiela Cruz', 'Arvin Dizon', 'Lea Morales',
    ];

    public function handle(): int
    {
        if (! app()->environment('local') || config('database.default') === 'aiven') {
            $this->error('Refused: demo data is for the local database only (APP_ENV=local, not the aiven connection).');

            return self::FAILURE;
        }

        return $this->option('clear') ? $this->clear() : $this->fill();
    }

    private function fill(): int
    {
        if (Booking::withTrashed()->where('booking_ref', 'like', self::REF_PREFIX.'%')->exists()) {
            $this->error('Demo bookings already exist. Run `php artisan demo:history --clear` first.');

            return self::FAILURE;
        }

        mt_srand((int) $this->option('seed'));

        $villa = Property::where('type', 'villa')->firstOrFail();
        $guests = $this->makeGuests();
        $holidays = new HolidayCalendar;

        $today = Carbon::today();
        // The what-if views cover this year and last year, and January's
        // "usual" is the 180 days before it — so by default the history
        // starts six months before LAST year (Jul 1, 2024 when run in 2026).
        // Those six months only feed January's history: that year itself
        // has no month with 180 days behind it, so it is not offered.
        $from = $this->option('months') !== null
            ? $today->copy()->subMonthsNoOverflow((int) $this->option('months'))
            : $today->copy()->startOfYear()->subYearNoOverflow()->subMonthsNoOverflow(6);
        $to = $today->copy()->addDays((int) $this->option('ahead'));

        // Reads what is already booked and offered, once, with the same
        // overlap and block rules the Recommendations page uses.
        $calendar = OccupancyCalendar::covering($from, $to, $villa);

        $made = ['checked_out' => 0, 'confirmed' => 0, 'cancelled' => 0, 'skipped' => 0];
        $byMonth = [];

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            // Today is left alone: a booking on it would be checked in or
            // out by the scheduler within minutes.
            if ($date->isSameDay($today)) {
                continue;
            }

            foreach ($calendar->sellableSlots($date) as $slot) {
                if (! isset(self::WEEKDAY_FILL[$date->dayOfWeek][$slot]) || $calendar->isTaken($date, $slot)) {
                    continue;   // a 22-hour date, or already booked
                }

                if ($this->roll() >= $this->chance($date, $slot, $today, $holidays)) {
                    continue;
                }

                $status = $this->create($villa, $guests[array_rand($guests)], $date, $slot, $today);

                $made[$status]++;

                if ($status !== 'skipped' && $status !== 'cancelled') {
                    $month = $date->format('Y-m');
                    $byMonth[$month] = ($byMonth[$month] ?? 0) + 1;
                }
            }
        }

        $this->info(sprintf(
            'Added %d past stays (checked out), %d upcoming (confirmed) and %d cancelled holds for %d demo guests.',
            $made['checked_out'],
            $made['confirmed'],
            $made['cancelled'],
            count($guests)
        ));

        if ($made['skipped'] > 0) {
            $this->line("Skipped {$made['skipped']} slot(s) the database refused (already held).");
        }

        $this->table(['Month', 'Live demo bookings'], collect($byMonth)->map(fn ($n, $m) => [$m, $n])->values()->all());

        return self::SUCCESS;
    }

    /** Chance this slot gets a booking: weekday × season, more on holidays, less the further ahead. */
    private function chance(Carbon $date, string $slot, Carbon $today, HolidayCalendar $holidays): float
    {
        $p = self::WEEKDAY_FILL[$date->dayOfWeek][$slot] * self::MONTH_FACTOR[$date->month];

        if ($holidays->name($date) !== null) {
            $p *= 1.8;
        }

        // Upcoming dates are only partly booked yet: guests keep booking
        // until close to the date.
        if ($date->gt($today)) {
            $p *= max(0.15, 1 - $today->diffInDays($date) / 50);
        }

        return min(0.95, $p);
    }

    /** @return 'checked_out'|'confirmed'|'cancelled'|'skipped' */
    private function create(Property $villa, User $guest, Carbon $date, string $slot, Carbon $today): string
    {
        [$checkIn, $checkOut] = Booking::slotDateTimes($slot, $date->format('Y-m-d'));
        $past = $checkOut->lt(Carbon::now());

        // About one in ten becomes an unpaid hold that ran out. It frees
        // the slot again, as a real one does.
        $status = $this->roll() < 0.10 ? 'cancelled' : ($past ? 'checked_out' : 'confirmed');

        $price = $villa->getPackagePrice($checkIn, $slot);
        $createdAt = $checkIn->copy()->subDays(mt_rand(2, 40))->setTime(mt_rand(8, 21), mt_rand(0, 59));
        if ($createdAt->gt(Carbon::now())) {
            $createdAt = Carbon::now()->subMinutes(mt_rand(30, 600));
        }

        $source = $this->roll() < 0.7 ? 'online' : ($this->roll() < 0.67 ? 'walk_in' : 'phone');

        try {
            DB::transaction(function () use ($villa, $guest, $checkIn, $checkOut, $price, $status, $createdAt, $source, $past) {
                $booking = new Booking([
                    'booking_ref' => self::REF_PREFIX.strtoupper(Str::random(8)),
                    'user_id' => $guest->id,
                    'property_id' => $villa->id,
                    'check_in_date' => $checkIn->toDateString(),
                    'check_in_time' => $checkIn->format('H:i:s'),
                    'check_out_date' => $checkOut->toDateString(),
                    'check_out_time' => $checkOut->format('H:i:s'),
                    'num_nights' => 1,
                    'num_guests' => mt_rand(2, 12),
                    'base_amount' => $price,
                    'extras_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => $price,
                    'amount_paid' => 0,
                    'balance_due' => $price,
                    'status' => $status,
                    'payment_status' => 'unpaid',
                    'source' => $source,
                    'cancelled_at' => $status === 'cancelled' ? $createdAt->copy()->addMinutes(20) : null,
                    'cancelled_by' => $status === 'cancelled' ? 'system' : null,
                    'cancellation_reason' => $status === 'cancelled' ? 'Demo data: unpaid hold expired.' : null,
                ]);

                if ($status === 'checked_out') {
                    $booking->actual_check_in = $checkIn->copy()->addMinutes(mt_rand(0, 45));
                    $booking->actual_check_out = $checkOut->copy()->subMinutes(mt_rand(0, 30));
                }

                $booking->created_at = $createdAt;
                $booking->updated_at = $past ? $checkOut : $createdAt;
                $booking->save();

                if ($status === 'cancelled') {
                    $booking->recalculateFinancials();

                    return;
                }

                // A past stay was paid in full; an upcoming one has its deposit.
                $amount = $status === 'checked_out' ? $price : round($price / 2, 2);

                Payment::create([
                    'booking_id' => $booking->id,
                    'amount' => $amount,
                    'payment_method' => 'cash',
                    'payment_type' => $status === 'checked_out' ? 'full_payment' : 'partial',
                    'status' => 'success',
                    'notes' => 'Demo data payment.',
                    'payment_date' => $status === 'checked_out' ? $checkIn->copy()->startOfDay() : $createdAt,
                ]);

                $booking->recalculateFinancials();
            });
        } catch (UniqueConstraintViolationException) {
            return 'skipped';
        }

        return $status;
    }

    /** @return array<int, User> */
    private function makeGuests(): array
    {
        $password = Hash::make(Str::random(40));   // nobody can sign in as a demo guest

        return collect(self::NAMES)->map(function (string $name, int $i) use ($password) {
            // Reused if a run stopped half-way and left the guests behind.
            $user = User::firstOrNew(['email' => Str::slug($name, '.').'@'.self::EMAIL_DOMAIN]);
            $user->fill([
                'full_name' => $name,
                'password' => $password,
                'phone' => '0917'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                'role' => 'customer',
            ]);
            $user->forceFill(['status' => 1, 'email_verified_at' => now()])->save();

            return $user;
        })->all();
    }

    private function clear(): int
    {
        $guestIds = User::where('email', 'like', '%@'.self::EMAIL_DOMAIN)->pluck('id');

        $bookingIds = Booking::withTrashed()
            ->where(fn ($q) => $q->where('booking_ref', 'like', self::REF_PREFIX.'%')->orWhereIn('user_id', $guestIds))
            ->pluck('id');

        DB::transaction(function () use ($guestIds, $bookingIds) {
            Payment::whereIn('booking_id', $bookingIds)->delete();
            DB::table('notifications')->whereIn('user_id', $guestIds)->delete();
            Booking::withTrashed()->whereIn('id', $bookingIds)->forceDelete();
            User::whereIn('id', $guestIds)->delete();
        });

        $this->info(sprintf('Removed %d demo bookings and %d demo guests.', $bookingIds->count(), $guestIds->count()));

        return self::SUCCESS;
    }

    private function roll(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}
