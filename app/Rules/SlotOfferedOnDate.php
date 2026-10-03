<?php

namespace App\Rules;

use App\Models\Booking;
use App\Models\Property;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Inaalok ba ang piniling slot sa piniling CHECK-IN DATE?
 *
 * Bakit isang Rule at hindi isang `in:` na listahan:
 *
 *   Hindi na isang nakapirming listahan ang sagot. Ang 22-Hours ay
 *   inaalok lang sa mga petsang pinili ng may-ari (`slot_windows`), at sa
 *   mga petsang iyon ay ITO LANG ang inaalok — kaya ang tanong ay
 *   nakadepende sa IBANG field ng parehong request. Hindi kayang sabihin
 *   iyon ng `in:`.
 *
 *   Pitong validator ang nagtatanong nito (portal preview/form/submit,
 *   walk-in quote + store, admin quote + store, customer reschedule).
 *   Pitong inline na tsek ang tiyak na maghihiwalay, at ang naiwang kopya
 *   ay hindi na guard. Iisang klase, iisang mensahe.
 *
 * Ang `quoteFor()` ang huling hadlang (fail-closed, 422) — ito ang
 * MAGANDANG hadlang: nakikita ng user sa tabi ng field na mali, hindi
 * isang error page.
 */
class SlotOfferedOnDate implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    /**
     * @param  string  $dateField  Pangalan ng check-in date field sa
     *                             request na ito — magkaiba sila kada
     *                             surface (`checkin` sa portal, at
     *                             `check_in_date` sa admin/walk-in).
     */
    public function __construct(
        protected string $dateField = 'check_in_date',
        protected ?Property $property = null,
    ) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! isset(Booking::SLOTS[$value])) {
            $fail('Please choose a booking slot.');

            return;
        }

        $raw = $this->data[$this->dateField] ?? null;

        // Walang petsa, o hindi ito mabasa: ang SARILING rule ng field na
        // iyon ang mag-uulat niyon. Ang pagdagdag ng pangalawang error sa
        // slot field ay nagtuturo sa user sa maling kahon.
        if (blank($raw)) {
            return;
        }

        try {
            $date = Carbon::parse($raw);
        } catch (\Throwable $e) {
            return;
        }

        $offered = Booking::slotsOfferedOn($date, $this->property);

        if (in_array($value, $offered, true)) {
            return;
        }

        $fail($this->message($value, $offered, $date));
    }

    /**
     * Dalawang magkaibang dahilan kung bakit maaaring tumanggi, at
     * magkaibang bagay ang kailangang gawin ng user sa bawat isa —
     * kaya magkaibang mensahe. Ang isang pangkalahatang "invalid slot"
     * ay nag-iiwan sa guest na hindi alam kung aling field ang babaguhin.
     */
    protected function message(string $slot, array $offered, Carbon $date): string
    {
        $name = Booking::SLOTS[$slot]['name'] ?? $slot;
        $when = $date->format('M j, Y');

        // Isang windowed na slot na hinihingi sa petsang walang window.
        if (Booking::slotRequiresWindow($slot)) {
            return "The {$name} stay is only offered on selected dates, and {$when} is not one of them. "
                .'Please pick another date, or choose a different slot.';
        }

        // Ang kabaligtaran: ang petsa ay para sa isang windowed na slot
        // lamang, kaya nakatago ang Day/Night doon.
        $only = array_map(fn ($k) => Booking::SLOTS[$k]['name'] ?? $k, $offered);

        if ($only) {
            return "{$when} is offered as ".implode(' or ', $only).' only, so '
                ."the {$name} slot cannot be booked on that date.";
        }

        return "No booking slot is available on {$when}. Please choose another date.";
    }
}
