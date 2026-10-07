<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Setting;
use App\Models\StaffLog;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('setting_value', 'setting_key');

        // Ang totoong huling pagbabago. Dating `now()` ang ipinapakita ng
        // "Last saved" — ang oras ng pagbukas ng pahina. Hindi ginagalaw ng
        // updateOrCreate() ang `updated_at` ng row na walang nagbago, kaya
        // ito ang huling pagkakataong may value na talagang nabago.
        $lastSavedAt = Setting::max('updated_at');

        return view('admin.settings.index', compact('settings', 'lastSavedAt'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'resort_name' => 'required|string|max:150',
            'resort_email' => 'required|email',
            'resort_phone' => 'required|string|max:30',
            'resort_address' => 'nullable|string',
            'resort_description' => 'nullable|string',
            'deposit_percentage' => 'required|numeric|min:0|max:100',
            'booking_hold_minutes' => 'required|integer|min:1',
            'booking_cooldown_threshold' => 'required|integer|min:1',
            'booking_cooldown_window_days' => 'required|integer|min:1',
            'booking_cooldown_hours' => 'required|integer|min:1',
            'max_advance_days' => 'required|integer|min:1',
            'facebook_url' => 'nullable|url',
            'tiktok_url' => 'nullable|url',
            'google_maps_url' => 'nullable|url',

            // Recommendation rules — ang PATAKARAN ng may-ari, hindi mga
            // pagpapalagay ng isang modelo. Ang unang itatanong tungkol sa
            // isang rekomendasyon ay "saan galing ang 10% na iyan?", at ang
            // sagot ay isa sa anim na field na ito.
            //
            // `gt:` sa busy threshold: kapag pantay o mas mababa ito sa
            // quiet threshold, iisang petsa ang sabay na "mahina" at
            // "malakas" at dalawang magkasalungat na card ang lalabas.
            'prescriptive_quiet_threshold' => 'required|numeric|min:1|max:99',
            'prescriptive_promo_percent' => 'required|numeric|min:1|max:50',
            'prescriptive_busy_threshold' => 'required|numeric|min:2|max:100|gt:prescriptive_quiet_threshold',
            'prescriptive_increase_percent' => 'required|numeric|min:1|max:50',
            'prescriptive_maintenance_days' => 'required|integer|min:1|max:14',
            'prescriptive_reminder_days' => 'required|integer|min:0|max:14',
        ], [
            'prescriptive_busy_threshold.gt' => 'The busy threshold must be higher than the quiet threshold.',
        ]);

        // Taken before anything is written; compared against a fresh read at
        // the end of the method. Reading the whole table is fine here —
        // Setting::get() caches, but pluck() goes to the database, which is
        // what we want for a before/after pair.
        $settingsBefore = Setting::pluck('setting_value', 'setting_key')->all();

        // Wala na rito ang `check_in_time`, `check_out_time`,
        // `min_stay_nights`, `currency` at `tax_percentage`: naisusulat
        // sila pero walang bumabasa. Ang oras ay galing sa Booking::SLOTS,
        // at PHP lang ang sinisingil ng PayMongo. Huwag silang ibalik
        // hangga't walang code na gumagamit.
        $keys = [
            'resort_name', 'resort_email', 'resort_phone', 'resort_address',
            'resort_description', 'deposit_percentage',
            'booking_hold_minutes',
            'booking_cooldown_threshold', 'booking_cooldown_window_days', 'booking_cooldown_hours',
            'max_advance_days',
            'facebook_url', 'tiktok_url', 'google_maps_url',
            'prescriptive_quiet_threshold', 'prescriptive_promo_percent',
            'prescriptive_busy_threshold', 'prescriptive_increase_percent',
            'prescriptive_maintenance_days', 'prescriptive_reminder_days',
        ];

        foreach ($keys as $key) {
            Setting::set($key, $request->input($key, ''));
        }

        // Boolean toggles
        // "send_email_notifications" ay hindi na kasama dito simula
        // v6.x — inilipat na ito sa customer (My Account →
        // Notifications), dahil sa guest mismo dapat manggaling ang
        // desisyon kung tatanggap sila ng booking confirmation emails,
        // hindi sa isang resort-wide na admin switch.
        $toggles = ['maintenance_mode', 'allow_online_booking'];
        foreach ($toggles as $toggle) {
            Setting::set($toggle, $request->has($toggle) ? '1' : '0');
        }

        // Shared, admin-managed property amenities list (used by the
        // Villa create/edit form instead of a hardcoded array).
        $oldAmenities = json_decode(Setting::get('property_amenities', '[]'), true) ?: [];
        $amenities = array_values(array_filter(array_map('trim', $request->input('amenities', []))));
        Setting::set('property_amenities', json_encode($amenities));

        // Kapag inalis ang isang amenity sa master list, tanggalin din ito
        // sa lahat ng property na may naka-save nito, para hindi mag-desync
        // ang aktwal na ipinapakita sa portal (property.blade.php / home.blade.php)
        // sa listahan ng mga opsyon sa Settings.
        $removed = array_diff($oldAmenities, $amenities);
        if (! empty($removed)) {
            Property::whereNotNull('amenities')->get(['id', 'amenities'])->each(function ($property) use ($removed) {
                $current = $property->amenities ?? [];
                $filtered = array_values(array_diff($current, $removed));
                if ($filtered !== $current) {
                    $property->update(['amenities' => $filtered]);
                }
            });
        }

        // Every one of the 44 `updated_settings` rows in the live table reads
        // "Resort settings updated", with no target and no values. Deposit
        // percentage, cancellation window, booking hold minutes and the
        // maintenance-mode switch all live behind this one form, and none of
        // them left a fingerprint.
        //
        // `$settingsBefore` is captured at the top of this method, before the
        // Setting::set() loop — by here every value has already been written.
        StaffLog::recordChange('updated_settings', 'settings', null,
            'Resort settings updated',
            $settingsBefore,
            Setting::pluck('setting_value', 'setting_key')->all());

        return back()->with('success', 'Settings saved successfully.');
    }
}
