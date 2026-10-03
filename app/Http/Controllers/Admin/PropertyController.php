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
        // Dalawang zona, hindi isang grid ng magkakapantay.
        //
        // Isang villa at N room ay HINDI magkapantay: ang villa ang tanging
        // naibe-book at dito nakasalalay ang lahat ng kita, samantalang ang
        // room ay sanggunian lamang. Ang dating `ORDER BY sort_order,
        // property_name` — na may sort_order na 0,0,0,67 at NULL na pangalan —
        // ang naglagay sa villa sa PANGATLO sa apat, sa pagitan ng dalawang
        // blangkong card.
        $properties = Property::withCount('bookings')
            ->with('primaryImage')
            ->orderBy('id')
            ->get();

        $villa = $properties->firstWhere('type', 'villa');

        // Ayon sa `id`, hindi sa `sort_order`. Wala nang kahulugan ang
        // sort_order sa datos na ito (0, 0, 0, 67 — mukhang aksidente ang 67),
        // at `id` ang pagkakasunod na ginagamit ng ConvertVillasToRoomsSeeder
        // sa pagbibigay ng titik: Room A, Room B, Room C.
        $rooms = $properties->filter(fn ($p) => $p->type !== 'villa')->values();

        return view('admin.properties.index', compact('villa', 'rooms'));
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
            // 22-Hours: NULL = hindi pa ipinepresyo, kaya hindi inaalok ang
            // slot (Property::isSlotPriced()). Villa lang — walang slot ang
            // mga informational na room.
            'base_price_22h' => 'nullable|numeric|min:0',
            'weekend_price_22h' => 'nullable|numeric|min:0',
            'floor_area_sqm'=> 'nullable|numeric|min:0',
            'amenities'     => 'nullable|array',
            // 10, not 20, and the number is not arbitrary: 10 x 3 MB is 30 MB,
            // which fits inside PHP post_max_size (32M) and nginx
            // client_max_body_size (32M). Past post_max_size PHP throws the
            // WHOLE body away, `_token` included, and the admin gets a 419 that
            // reads like a session problem. See docker/php.ini before changing
            // this — all four numbers move together.
            'images'   => 'nullable|array|max:10',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3048',
        ]);

        $property = Property::create([
            'property_name'  => $request->property_name,
            'type'           => $request->type,
            'description'    => $isVilla ? $request->description : null,
            'max_capacity'   => $request->max_capacity,
            'base_price'     => $isVilla ? $request->base_price : 0,
            'weekend_price'  => $isVilla ? $request->weekend_price : null,
            // Blangko = NULL, hindi 0. Ang NULL ang nagpapasara sa 22-Hours
            // na slot; ang 0 ay magbubunga ng libreng booking, at hindi
            // iyon kailanman ang ibig sabihin ng isang blangkong field.
            'base_price_22h' => $isVilla && $request->filled('base_price_22h')
                ? $request->base_price_22h
                : null,
            'weekend_price_22h' => $isVilla && $request->filled('weekend_price_22h')
                ? $request->weekend_price_22h
                : null,
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

        // The image count is part of the same event rather than a row per
        // file: a ten-image upload is one admin action, and ten near-identical
        // audit rows would bury it.
        $uploaded = $property->images()->count();

        StaffLog::record('created_property', 'properties', $property->id,
            "Created property: {$property->property_name}"
                .($uploaded ? " (with {$uploaded} image".($uploaded === 1 ? '' : 's').')' : ''));

        return redirect()->route('admin.properties.index')
            ->with('success', "Property \"{$property->property_name}\" created successfully.");
    }

    // ── Show Single Property ───────────────────────────────────────
    public function show(Property $property)
    {
        $property->load(['images', 'bookings.user', 'reviews', 'pricingRules', 'availabilityBlocks']);
        $recentBookings = $property->bookings()->with('user')->latest()->take(5)->get();

        // Para sa "Block dates on the Villa" na link sa pahina ng isang room.
        // Nullable — hindi dapat mag-500 ang pahina ng room kung wala pang
        // villa row (mangyayari iyon sa isang bagong-seed na DB).
        $villaId = $property->type === 'villa'
            ? $property->id
            : Property::where('type', 'villa')->value('id');

        return view('admin.properties.show', compact('property', 'recentBookings', 'villaId'));
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
            // 22-Hours: NULL = hindi pa ipinepresyo, kaya hindi inaalok ang
            // slot (Property::isSlotPriced()). Villa lang — walang slot ang
            // mga informational na room.
            'base_price_22h' => 'nullable|numeric|min:0',
            'weekend_price_22h' => 'nullable|numeric|min:0',
            // 10, not 20, and the number is not arbitrary: 10 x 3 MB is 30 MB,
            // which fits inside PHP post_max_size (32M) and nginx
            // client_max_body_size (32M). Past post_max_size PHP throws the
            // WHOLE body away, `_token` included, and the admin gets a 419 that
            // reads like a session problem. See docker/php.ini before changing
            // this — all four numbers move together.
            'images'   => 'nullable|array|max:10',
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
            // Blangko = NULL, hindi 0. Ang NULL ang nagpapasara sa 22-Hours
            // na slot; ang 0 ay magbubunga ng libreng booking, at hindi
            // iyon kailanman ang ibig sabihin ng isang blangkong field.
            'base_price_22h' => $isVilla && $request->filled('base_price_22h')
                ? $request->base_price_22h
                : null,
            'weekend_price_22h' => $isVilla && $request->filled('weekend_price_22h')
                ? $request->weekend_price_22h
                : null,
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

        $added = $request->hasFile('images') ? count($request->file('images')) : 0;

        StaffLog::record('updated_property', 'properties', $property->id,
            "Updated property: {$property->property_name}"
                .($added ? " — added {$added} image".($added === 1 ? '' : 's') : ''),
            $oldData, $property->fresh()->toArray());

        return redirect()->route('admin.properties.index')
            ->with('success', "Property \"{$property->property_name}\" updated successfully.");
    }

    // ── Delete Property ────────────────────────────────────────────
    /**
     * ANG PINAKAMAPANIRANG PINDUTAN SA BUONG ADMIN PANEL, at wala itong bantay.
     *
     * CASCADE ang bawat foreign key papunta sa `properties`, at may sarili pang
     * cascade ang `bookings`:
     *
     *   properties → bookings → payments, booking_extras, reviews
     *              → property_images, pricing_rules, availability_blocks
     *              → housekeeping_tasks, issue_reports
     *
     * Kaya ang pagpindot ng 🗑 sa card ng villa at pagkumpirma ay bumubura ng
     * 74 booking, 95 payment, 4 review at 63 housekeeping task — ang buong
     * kasaysayan ng negosyo — sa isang kisap. Walang ibinababalang naiiba ito
     * sa pagbura ng isang walang lamang room, at "and all its images will be
     * permanently deleted" ang sinasabi ng modal.
     *
     * Dalawang bantay, sinasadyang magkapatong:
     *   1. Hindi kailanman puwedeng burahin ang villa. Ito ang tanging bookable
     *      na listing; kung mawala ito ay wala nang sistema.
     *   2. Hindi rin puwedeng burahin ang ANUMANG property na may booking. Wala
     *      pang room na may booking ngayon, pero kung magkaroon man, hindi na
     *      ang uri ang tanong kundi ang datos na kasama nitong mawawala.
     */
    public function destroy(Property $property)
    {
        if ($property->type === 'villa') {
            return back()->with('error', 'The master Villa record cannot be deleted — it is the only bookable listing, and every booking, payment and review in the system is attached to it.');
        }

        $bookingCount = $property->bookings()->count();

        if ($bookingCount > 0) {
            return back()->with('error', "This property cannot be deleted: {$bookingCount} booking(s) are attached to it, and deleting it would delete them and their payments too.");
        }

        // Delete associated images from storage
        foreach ($property->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $name = $property->property_name;
        $deletedId = $property->id;

        $property->delete();

        StaffLog::record('deleted_property', 'properties', $deletedId,
            "Deleted property: {$name} (property #{$deletedId})");

        return redirect()->route('admin.properties.index')
            ->with('success', "Property \"{$name}\" has been deleted.");
    }

    // ── Block Dates ────────────────────────────────────────────────
    /**
     * Villa lamang — at ito ang tseke na mahalaga, hindi ang pagtatago ng form.
     *
     * Isang property_id lang ang kinokonsulta ng `Booking::blockOn()` at ng
     * availability grid ng frontdesk: ang sa villa. Kaya ang block sa isang
     * room ay isang row na walang ginagawa — at "Dates blocked successfully"
     * pa rin ang sagot. Inalis na ang form sa mga pahina ng room, pero ang
     * ruta ay nananatiling POST-able nang diretso, at ang pagtatago ng isang
     * kontrol ay hindi pagbabawal.
     *
     * Ibinabalik bilang `error` flash, na hindi pa naipapakita ng pahinang ito
     * noon — `session('success')` lang ang inilalabas nito, kaya tahimik
     * sanang nalulunok ang anumang pagtanggi.
     */
    public function blockDates(Request $request, Property $property)
    {
        if ($property->type !== 'villa') {
            return back()->with('error', 'Dates can only be blocked on the master Villa record — it is the only listing guests can book, so a block on an individual room would have no effect on availability.');
        }

        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|in:maintenance,owner_use,private_event,other',
            'notes'      => 'nullable|string|max:255',
        ]);

        $block = AvailabilityBlock::create([
            'property_id' => $property->id,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'reason'      => $request->reason,
            'notes'       => $request->notes,
            'created_by'  => Auth::id(),
        ]);

        StaffLog::record('created_availability_block', 'availability_blocks', $block->id,
            "Blocked {$request->start_date} to {$request->end_date} on {$property->property_name} ({$request->reason})");

        return back()->with('success', 'Dates blocked successfully.');
    }

    // ── Delete Image ───────────────────────────────────────────────
    public function deleteImage(Request $request, PropertyImage $image)
    {
        // F8 — this endpoint destroys a file in object storage AND a database
        // row AND silently reassigns which photo represents the villa, and it
        // recorded none of it. On production the file lives in Cloudinary, so
        // the delete is not recoverable from a database backup: without this
        // row there is no record that the image ever existed, let alone who
        // removed it.
        //
        // Everything needed for the description is read up front. Unlike the
        // trusted-device case (see Customer\ProfileController, where Eloquent
        // keeps attributes in memory after a hard delete), `$promoted` below
        // genuinely cannot be known after the fact — it is a side effect.
        $imageId = $image->id;
        $path = $image->image_path;
        $wasPrimary = (bool) $image->is_primary;
        $propertyId = $image->property_id;
        $promoted = null;

        Storage::disk('public')->delete($image->image_path);

        // If deleting primary, make next image primary.
        //
        // "Next" now means next IN DISPLAY ORDER, matching Property::images().
        // The bare `first()` this replaces had no ORDER BY, so it promoted
        // whatever the database returned first — the lowest id in practice,
        // and formally undefined. Promoting an image the gallery does not
        // show first is a silent, invisible-to-anyone choice; that it was
        // happening at all only became apparent once the delete was logged.
        if ($image->is_primary) {
            $next = PropertyImage::where('property_id', $image->property_id)
                ->where('id', '!=', $image->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($next) $next->update(['is_primary' => 1]);

            $promoted = $next?->id;
        }

        $image->delete();

        StaffLog::record('deleted_property_image', 'property_images', $imageId,
            sprintf(
                'Deleted %simage "%s" from property #%d%s',
                $wasPrimary ? 'PRIMARY ' : '',
                $path,
                $propertyId,
                $wasPrimary
                    ? ($promoted
                        ? " — image #{$promoted} promoted to primary"
                        : ' — the property now has no primary image')
                    : ''
            ));

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