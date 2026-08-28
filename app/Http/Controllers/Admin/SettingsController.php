<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Setting;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('setting_value', 'setting_key');
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'resort_name'          => 'required|string|max:150',
            'resort_email'         => 'required|email',
            'resort_phone'         => 'required|string|max:30',
            'resort_address'       => 'nullable|string',
            'resort_description'   => 'nullable|string',
            'currency'             => 'required|string|max:10',
            'deposit_percentage'   => 'required|numeric|min:0|max:100',
            'cancellation_hours'   => 'required|integer|min:0',
            'booking_hold_minutes' => 'required|integer|min:1',
            'booking_cooldown_threshold'   => 'required|integer|min:1',
            'booking_cooldown_window_days' => 'required|integer|min:1',
            'booking_cooldown_hours'       => 'required|integer|min:1',
            'check_in_time'        => 'required|string',
            'check_out_time'       => 'required|string',
            'max_advance_days'     => 'required|integer|min:1',
            'min_stay_nights'      => 'required|integer|min:1',
            'tax_percentage'       => 'nullable|numeric|min:0|max:100',
            'facebook_url'         => 'nullable|url',
            'tiktok_url'           => 'nullable|url',
            'google_maps_url'      => 'nullable|url',
        ]);

        $keys = [
            'resort_name', 'resort_email', 'resort_phone', 'resort_address',
            'resort_description', 'currency', 'deposit_percentage',
            'cancellation_hours', 'booking_hold_minutes', 'check_in_time',
            'booking_cooldown_threshold', 'booking_cooldown_window_days', 'booking_cooldown_hours',
            'check_out_time', 'max_advance_days', 'min_stay_nights',
            'tax_percentage', 'facebook_url', 'tiktok_url', 'google_maps_url',
        ];

        foreach ($keys as $key) {
            Setting::set($key, $request->input($key, ''));
        }

        // Boolean toggles
        $toggles = ['maintenance_mode', 'allow_online_booking', 'require_id_upload', 'send_email_notifications'];
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
        if (!empty($removed)) {
            Property::whereNotNull('amenities')->get(['id', 'amenities'])->each(function ($property) use ($removed) {
                $current = $property->amenities ?? [];
                $filtered = array_values(array_diff($current, $removed));
                if ($filtered !== $current) {
                    $property->update(['amenities' => $filtered]);
                }
            });
        }

        StaffLog::record('updated_settings', 'settings', null, 'Resort settings updated');

        return back()->with('success', 'Settings saved successfully.');
    }
}