<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\AvailabilityBlock;
use App\Models\Setting;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    // Seed value only — used until the admin saves the Settings ->
    // Amenities tab for the first time (see Admin\SettingsController).
    private const DEFAULT_AMENITIES = [
        'WiFi', 'Air Conditioning', 'Private Pool', 'Hot Tub',
        'Kitchen', 'BBQ Grill', 'Parking',
        'Garden View', 'Mountain View', 'Sea View',
        'Smart TV', 'Refrigerator', 'Washing Machine',
        'Safety Deposit Box', 'Balcony / Terrace',
        'Karaoke', 'Game Room',
        'Outdoor Shower', 'Water Heater', 'Generator',
        'CCTV', 'Rice Cooker', 'Dining Area',
    ];

    private function amenityList(): array
    {
        return json_decode(
            Setting::get('property_amenities', json_encode(self::DEFAULT_AMENITIES)),
            true
        ) ?: [];
    }

    // ── List All Properties ────────────────────────────────────────
    public function index()
    {
        $properties = Property::withCount('bookings')
            ->with('primaryImage')
            ->orderBy('sort_order')
            ->orderBy('property_name')
            ->get();

        $stats = [
            'total'       => $properties->count(),
            'available'   => $properties->where('status', 'available')->count(),
            'occupied'    => $properties->where('status', 'occupied')->count(),
            'maintenance' => $properties->where('status', 'maintenance')->count(),
        ];

        return view('admin.properties.index', compact('properties', 'stats'));
    }

    // ── Show Create Form ───────────────────────────────────────────
    public function create()
    {
        // Isang Villa (master, bookable) na lang dapat mayroon.
        // Kapag meron nang Villa, hindi na dapat pagpipilian ang type —
        // Room na agad ang bagong property (ipinapasa ang variable na ito
        // para malaman ng view kung itatago ang type selector).
        $existingVilla = Property::where('type', 'villa')->first();
        $amenityList   = $this->amenityList();

        return view('admin.properties.create', compact('existingVilla', 'amenityList'));
    }

    // ── Store New Property ─────────────────────────────────────────
    public function store(Request $request)
    {
        $isVilla = $request->type === 'villa';

        $request->validate([
            'property_name' => $isVilla ? 'required|string|max:150' : 'nullable|string|max:150',
            'type'          => 'required|in:villa,room',
            'description'   => 'nullable|string',
            'max_capacity'  => 'required|integer|min:1',
            'base_price'    => $isVilla ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'weekend_price' => 'nullable|numeric|min:0',
            'floor_area_sqm'=> 'nullable|numeric|min:0',
            'amenities'     => 'nullable|array',
            'images'   => 'nullable|array|max:20',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3048',
        ]);

        $property = Property::create([
            'property_name'  => $request->property_name,
            'type'           => $request->type,
            'description'    => $isVilla ? $request->description : null,
            'max_capacity'   => $request->max_capacity,
            'base_price'     => $isVilla ? $request->base_price : 0,
            'weekend_price'  => $isVilla ? $request->weekend_price : null,
            'floor_area_sqm' => $request->floor_area_sqm,
            'amenities'      => $isVilla ? ($request->amenities ?? []) : [],
            'is_featured'    => $request->boolean('is_featured'),
            'created_by'     => Auth::id(),
        ]);

        // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('properties', 'public');
                PropertyImage::create([
                    'property_id' => $property->id,
                    'image_path'  => $path,
                    'alt_text'    => $property->property_name,
                    'is_primary'  => $index === 0 ? 1 : 0,
                    'sort_order'  => $index,
                ]);
            }
        }

        StaffLog::record('created_property', 'properties', $property->id,
            "Created property: {$property->property_name}");

        return redirect()->route('admin.properties.index')
            ->with('success', "Property \"{$property->property_name}\" created successfully.");
    }

    // ── Show Single Property ───────────────────────────────────────
    public function show(Property $property)
    {
        $property->load(['images', 'bookings.user', 'reviews', 'pricingRules', 'availabilityBlocks']);
        $recentBookings = $property->bookings()->with('user')->latest()->take(5)->get();
        return view('admin.properties.show', compact('property', 'recentBookings'));
    }

    // ── Show Edit Form ─────────────────────────────────────────────
    public function edit(Property $property)
    {
        $property->load('images');

        // Ibang Villa (maliban dito mismo) para sa parehong warning
        $existingVilla = Property::where('type', 'villa')->where('id', '!=', $property->id)->first();
        $amenityList   = $this->amenityList();

        return view('admin.properties.edit', compact('property', 'existingVilla', 'amenityList'));
    }

    // ── Update Property ────────────────────────────────────────────
    public function update(Request $request, Property $property)
    {
        $isVilla = $request->type === 'villa';

        $request->validate([
            'property_name' => $isVilla ? 'required|string|max:150' : 'nullable|string|max:150',
            'type'          => 'required|in:villa,room',
            'max_capacity'  => 'required|integer|min:1',
            'base_price'    => $isVilla ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'weekend_price' => 'nullable|numeric|min:0',
            'images'   => 'nullable|array|max:20',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3048',
        ]);

        // I-kunin ang lumang datos ng property BAGO mag-update, para
        // magamit sa StaffLog (audit trail: "ano bago, ano pagkatapos").
        // `status` ay hindi na dito na-e-edit — awtomatiko na lang itong
        // pinapalitan ng check-in/check-out flow (walang binabago dito).
        $oldData = $property->toArray();

        $property->update([
            'property_name'  => $request->property_name,
            'type'           => $request->type,
            'description'    => $isVilla ? $request->description : null,
            'max_capacity'   => $request->max_capacity,
            'base_price'     => $isVilla ? $request->base_price : 0,
            'weekend_price'  => $isVilla ? $request->weekend_price : null,
            'floor_area_sqm' => $request->floor_area_sqm,
            'amenities'      => $isVilla ? ($request->amenities ?? []) : [],
            'is_featured'    => $request->boolean('is_featured'),
        ]);

        // Handle new image uploads
        if ($request->hasFile('images')) {
            $existingCount = $property->images()->count();
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('properties', 'public');
                PropertyImage::create([
                    'property_id' => $property->id,
                    'image_path'  => $path,
                    'alt_text'    => $property->property_name,
                    'is_primary'  => ($existingCount === 0 && $index === 0) ? 1 : 0,
                    'sort_order'  => $existingCount + $index,
                ]);
            }
        }

        StaffLog::record('updated_property', 'properties', $property->id,
            "Updated property: {$property->property_name}", $oldData, $property->fresh()->toArray());

        return redirect()->route('admin.properties.index')
            ->with('success', "Property \"{$property->property_name}\" updated successfully.");
    }

    // ── Delete Property ────────────────────────────────────────────
    public function destroy(Property $property)
    {
        // Delete associated images from storage
        foreach ($property->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $name = $property->property_name;
        $property->delete();

        StaffLog::record('deleted_property', 'properties', null,
            "Deleted property: {$name}");

        return redirect()->route('admin.properties.index')
            ->with('success', "Property \"{$name}\" has been deleted.");
    }

    // ── Block Dates ────────────────────────────────────────────────
    public function blockDates(Request $request, Property $property)
    {
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|in:maintenance,owner_use,private_event,other',
            'notes'      => 'nullable|string|max:255',
        ]);

        AvailabilityBlock::create([
            'property_id' => $property->id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'reason'      => $request->reason,
            'notes'       => $request->notes,
            'created_by'  => Auth::id(),
        ]);

        return back()->with('success', 'Dates blocked successfully.');
    }

    // ── Delete Image ───────────────────────────────────────────────
    public function deleteImage(Request $request, PropertyImage $image)
    {
        Storage::disk('public')->delete($image->image_path);

        // If deleting primary, make next image primary
        if ($image->is_primary) {
            $next = PropertyImage::where('property_id', $image->property_id)
                ->where('id', '!=', $image->id)->first();
            if ($next) $next->update(['is_primary' => 1]);
        }

        $image->delete();

        // Ang delete button na ito ay AJAX-only (fetch() sa edit.blade.php)
        // — hindi ito plain form submit. Ang dating `back()` (302 redirect
        // pabalik sa edit page) ay nagpapa-follow ng redirect sa fetch(),
        // na hindi laging maasahang nagre-resolve bilang "ok" sa totoong
        // browser (kahit successful naman talaga ang delete sa DB/storage
        // — kaya lumalabas na parang nabigo kahit tapos na). Direktang
        // JSON response na lang, walang redirect chain na pag-aalinlanganan.
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Image deleted.']);
        }

        return back()->with('success', 'Image deleted.');
    }
}