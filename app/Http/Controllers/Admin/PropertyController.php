<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\AvailabilityBlock;
use App\Models\StaffLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Events\PropertyStatusChanged;

class PropertyController extends Controller
{
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
        return view('admin.properties.create');
    }

    // ── Store New Property ─────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'property_name' => 'required|string|max:150',
            'type'          => 'required|in:villa,cottage,room,hall',
            'description'   => 'nullable|string',
            'max_capacity'  => 'required|integer|min:1',
            'base_price'    => 'required|numeric|min:0',
            'weekend_price' => 'nullable|numeric|min:0',
            'floor_area_sqm'=> 'nullable|numeric|min:0',
            'amenities'     => 'nullable|array',
            'status'        => 'required|in:available,occupied,maintenance',
            'images.*'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3048',
        ]);

        $property = Property::create([
            'property_name'  => $request->property_name,
            'type'           => $request->type,
            'description'    => $request->description,
            'max_capacity'   => $request->max_capacity,
            'base_price'     => $request->base_price,
            'weekend_price'  => $request->weekend_price,
            'floor_area_sqm' => $request->floor_area_sqm,
            'amenities'      => $request->amenities ?? [],
            'status'         => $request->status,
            'is_featured'    => $request->boolean('is_featured'),
            'sort_order'     => $request->sort_order ?? 0,
            'created_by'     => auth()->id(),
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
        return view('admin.properties.edit', compact('property'));
    }

    // ── Update Property ────────────────────────────────────────────
    public function update(Request $request, Property $property)
    {
        $request->validate([
            'property_name' => 'required|string|max:150',
            'type'          => 'required|in:villa,cottage,room,hall',
            'max_capacity'  => 'required|integer|min:1',
            'base_price'    => 'required|numeric|min:0',
            'weekend_price' => 'nullable|numeric|min:0',
            'status'        => 'required|in:available,occupied,maintenance',
            'images.*'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3048',
        ]);

        $oldStatus = $property->status;

        $property->update([
            'property_name'  => $request->property_name,
            'type'           => $request->type,
            'description'    => $request->description,
            'max_capacity'   => $request->max_capacity,
            'base_price'     => $request->base_price,
            'weekend_price'  => $request->weekend_price,
            'floor_area_sqm' => $request->floor_area_sqm,
            'amenities'      => $request->amenities ?? [],
            'status'         => $request->status,
            'is_featured'    => $request->boolean('is_featured'),
            'sort_order'     => $request->sort_order ?? 0,
        ]);

       if ($oldStatus !== $property->fresh()->status) {
    event(new PropertyStatusChanged($property->fresh(), $oldStatus));
    }

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
            "Updated property: {$property->property_name}", $old, $property->fresh()->toArray());

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
            'created_by'  => auth()->id(),
        ]);

        return back()->with('success', 'Dates blocked successfully.');
    }

    // ── Delete Image ───────────────────────────────────────────────
    public function deleteImage(PropertyImage $image)
    {
        Storage::disk('public')->delete($image->image_path);

        // If deleting primary, make next image primary
        if ($image->is_primary) {
            $next = PropertyImage::where('property_id', $image->property_id)
                ->where('id', '!=', $image->id)->first();
            if ($next) $next->update(['is_primary' => 1]);
        }

        $image->delete();

        return back()->with('success', 'Image deleted.');
    }
}