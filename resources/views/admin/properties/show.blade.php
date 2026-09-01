@extends('layouts.admin')

@section('title', $property->property_name . ' — Villa Elena Admin')
@section('page-title', $property->property_name)
@section('page-subtitle', 'Property Details')

@section('topbar-right')
    <a href="{{ route('admin.properties.index') }}" class="btn-outline">
        <i class="bi bi-arrow-left"></i> Back
    </a>
    <a href="{{ route('admin.properties.edit', $property) }}" class="btn-navy">
        <i class="bi bi-pencil"></i> Edit Property
    </a>
    <form method="POST" action="{{ route('logout') }}" class="m-0">
        @csrf
        <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
@endsection

@push('styles')
    <style>
        .btn-navy {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .2s;
            text-decoration: none;
        }

        .btn-navy:hover {
            background: var(--gold);
            color: #fff;
        }

        .btn-outline {
            background: #fff;
            color: var(--text-main);
            border: 1.5px solid var(--border);
            border-radius: 9px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all .2s;
        }

        .btn-outline:hover {
            border-color: var(--terracotta);
            color: var(--terracotta);
        }

        /* STATUS BADGE — semantic, unchanged */
        .status-pill {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .status-available {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .status-occupied {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-maintenance {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        /* CARDS */
        .panel {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .panel-head {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-head h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 17px;
            font-weight: 600;
            color: var(--stone);
        }

        .panel-body {
            padding: 22px;
        }

        /* STAT MINI CARDS */
        .mini-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .mini-card {
            background: var(--cream);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 18px 20px;
        }

        .mini-card .val {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--stone);
            line-height: 1;
        }

        .mini-card .lbl {
            font-size: 13px;
            color: var(--muted);
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .mini-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            margin-bottom: 12px;
            background: var(--gold-dim);
            color: var(--gold);
        }

        /* GALLERY */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px;
        }

        .gallery-img {
            width: 100%;
            height: 130px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid var(--border);
        }

        .gallery-primary {
            border: 2px solid var(--gold);
        }

        .no-image {
            width: 100%;
            height: 130px;
            background: var(--sand);
            border-radius: 10px;
            border: 1px dashed var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 13px;
            flex-direction: column;
            gap: 6px;
        }

        /* INFO GRID */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .info-item label {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            display: block;
            margin-bottom: 4px;
        }

        .info-item .val {
            font-size: 14px;
            color: var(--text-main);
            font-weight: 500;
        }

        /* AMENITIES */
        .amenity-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--sand);
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 14px;
            color: var(--text-main);
            margin: 3px;
        }

        /* Booking status — semantic, unchanged */
        .booking-status.confirmed {
            background: #dcfce7;
            color: #15803d;
        }

        .booking-status.pending {
            background: #fef9c3;
            color: #a16207;
        }

        .booking-status.cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        .booking-status.checked_in {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .booking-status.checked_out {
            background: #f1f5f9;
            color: #475569;
        }

        .booking-status.completed {
            background: #f0fdf4;
            color: #16a34a;
        }

        /* REVIEW STARS */
        .stars {
            color: #f59e0b;
            font-size: 13px;
        }

        /* DANGER ZONE — semantic, unchanged */
        .danger-zone {
            background: #fff5f5;
            border: 1px solid #fecaca;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-danger {
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .2s;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .two-col {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        @media(max-width:900px) {
            .two-col {
                grid-template-columns: minmax(0, 1fr);
            }

            .mini-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
@endpush

@section('content')

    {{-- BREADCRUMB --}}
    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.properties.index') }}">Properties</a>
        <span class="sep">›</span>
        <span class="current">{{ $property->property_name }}</span>
    </div>

    @if (session('success'))
        <div
            style="background:#dcfce7; color:#15803d; border-radius:10px; padding:12px 16px; margin-bottom:18px; font-size:13px;">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if ($property->type === 'villa')
        <div class="tag-green" style="border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;">
            <i class="bi bi-shield-check me-2"></i>
            Ito ang <strong>master Villa record</strong> — ang tanging bookable na listing sa customer-facing site. Lahat ng
            booking, kita, at review ay naka-tally dito.
        </div>
    @elseif($property->type === 'room')
        <div
            style="background:#f1f5f9;color:#475569;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:13px;">
            <i class="bi bi-info-circle me-2"></i>
            Ito ay isa sa mga <strong>kwarto ng Villa Elena</strong> — info/reference lang ito (status, litrato,
            housekeeping). Hindi ito hiwalay na binebentang listing, kaya inaasahang <strong>0 ang bookings/reviews</strong>
            dito — nasa master Villa record ang mga iyon.
        </div>
    @endif

    {{-- MINI STAT CARDS --}}
    <div class="mini-stats">
        <div class="mini-card">
            <div class="mini-icon" style="background:#dcfce7; color:#15803d;"><i class="bi bi-cash-stack"></i></div>
            <div class="val">₱{{ number_format($property->base_price, 0) }}</div>
            <div class="lbl">Base Price / Night</div>
        </div>
        <div class="mini-card">
            <div class="mini-icon" style="background:#dbeafe; color:#1d4ed8;"><i class="bi bi-people"></i></div>
            <div class="val">{{ $property->max_capacity }}</div>
            <div class="lbl">Max Capacity</div>
        </div>
        <div class="mini-card">
            <div class="mini-icon" style="background:#fef9c3; color:#a16207;"><i class="bi bi-calendar-check"></i></div>
            <div class="val">{{ $property->bookings->count() }}</div>
            <div class="lbl">Total Bookings</div>
        </div>
        <div class="mini-card">
            <div class="mini-icon" style="background:#f3e8ff; color:#7c3aed;"><i class="bi bi-star"></i></div>
            <div class="val">
                {{ $property->reviews->count() > 0 ? number_format($property->reviews->avg('rating'), 1) : '—' }}</div>
            <div class="lbl">Avg Rating</div>
        </div>
    </div>

    <div class="two-col">
        {{-- LEFT COLUMN --}}
        <div>

            {{-- PROPERTY INFO --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Property Information</h3>
                    <span class="status-pill status-{{ $property->status }}">{{ ucfirst($property->status) }}</span>
                </div>
                <div class="panel-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Property Name</label>
                            <div class="val">{{ $property->property_name }}</div>
                        </div>
                        <div class="info-item">
                            <label>Type</label>
                            <div class="val">{{ ucfirst($property->type) }}</div>
                        </div>
                        <div class="info-item">
                            <label>Base Price</label>
                            <div class="val">₱{{ number_format($property->base_price, 2) }}</div>
                        </div>
                        <div class="info-item">
                            <label>Weekend Price</label>
                            <div class="val">
                                {{ $property->weekend_price ? '₱' . number_format($property->weekend_price, 2) : '—' }}</div>
                        </div>
                        <div class="info-item">
                            <label>Floor Area</label>
                            <div class="val">{{ $property->floor_area_sqm ? $property->floor_area_sqm . ' sqm' : '—' }}
                            </div>
                        </div>
                        <div class="info-item">
                            <label>Max Capacity</label>
                            <div class="val">{{ $property->max_capacity }} guests</div>
                        </div>
                        <div class="info-item">
                            <label>Featured</label>
                            <div class="val">
                                @if ($property->is_featured)
                                    <span style="color:#d97706;"><i class="bi bi-star-fill"></i> Yes</span>
                                @else
                                    <span class="text-muted-theme">No</span>
                                @endif
                            </div>
                        </div>
                        <div class="info-item">
                            <label>Sort Order</label>
                            <div class="val">{{ $property->sort_order }}</div>
                        </div>
                    </div>

                    @if ($property->description)
                        <div style="margin-top:18px; padding-top:18px; border-top:1px solid var(--border);">
                            <label class="text-muted-theme section-label"
                                style="font-size: 13px; display:block; margin-bottom:8px;">Description</label>
                            <p style="font-size:14px; color:var(--text-main); line-height:1.7;">
                                {{ $property->description }}</p>
                        </div>
                    @endif

                    @if (!empty($property->amenities))
                        <div style="margin-top:18px; padding-top:18px; border-top:1px solid var(--border);">
                            <label class="text-muted-theme section-label"
                                style="font-size: 13px; display:block; margin-bottom:8px;">Amenities</label>
                            <div>
                                @foreach ($property->amenities as $amenity)
                                    <span class="amenity-tag"><i class="bi bi-check-circle-fill"
                                            style="color:#16a34a; font-size: 13px;"></i> {{ $amenity }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- IMAGES --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Photos</h3>
                    <a href="{{ route('admin.properties.edit', $property) }}"
                        style="font-size: 14px; color:var(--gold); text-decoration:none; font-weight:600;">
                        <i class="bi bi-plus-lg"></i> Add Photos
                    </a>
                </div>
                <div class="panel-body">
                    @if ($property->images->count() > 0)
                        <div class="gallery-grid">
                            @foreach ($property->images as $image)
                                <div style="position:relative;">
                                    <img src="{{ $image->url }}" alt="{{ $image->alt_text }}"
                                        class="gallery-img {{ $image->is_primary ? 'gallery-primary' : '' }}">
                                    @if ($image->is_primary)
                                        <span
                                            style="position:absolute; top:6px; left:6px; background:var(--gold); color:#fff; font-size: 11px; font-weight:700; padding:2px 7px; border-radius:10px; text-transform:uppercase; letter-spacing:.3px;">Primary</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="no-image">
                            <i class="bi bi-image" style="font-size:28px; opacity:.3;"></i>
                            No photos uploaded yet
                        </div>
                    @endif
                </div>
            </div>

            {{-- RECENT BOOKINGS --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Recent Bookings</h3>
                    <a href="{{ route('admin.bookings.index') }}?property={{ $property->id }}"
                        style="font-size: 14px; color:#2e5fa3; text-decoration:none; font-weight:500;">
                        View all <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                @if ($recentBookings->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-calendar-x"></i>
                        No bookings yet for this property.
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Booking Ref</th>
                                <th>Guest</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentBookings as $booking)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.bookings.show', $booking) }}"
                                            style="font-weight:600; color:var(--stone); text-decoration:none; font-size: 14px;">
                                            {{ $booking->booking_ref }}
                                        </a>
                                    </td>
                                    <td>{{ $booking->user->full_name ?? 'N/A' }}</td>
                                    <td class="text-muted-theme" style="font-size: 14px;">
                                        {{ $booking->check_in_date->format('M d, Y') }}</td>
                                    <td class="text-muted-theme" style="font-size: 14px;">
                                        {{ $booking->check_out_date->format('M d, Y') }}</td>
                                    <td>
                                        <span class="booking-status {{ $booking->status }}">
                                            {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- REVIEWS --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Reviews <span class="text-muted-theme"
                            style="font-size:13px; font-weight:400;">({{ $property->reviews->count() }})</span></h3>
                </div>
                @if ($property->reviews->isEmpty())
                    <div class="empty-state">
                        <i class="bi bi-star"></i>
                        No reviews yet.
                    </div>
                @else
                    <div class="panel-body" style="display:flex; flex-direction:column; gap:14px;">
                        @foreach ($property->reviews->take(5) as $review)
                            <div
                                style="padding:14px; background:#f8fafc; border-radius:10px; border:1px solid var(--border);">
                                <div
                                    style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                                    <span
                                        style="font-weight:600; font-size:13px;">{{ $review->user->full_name ?? 'Guest' }}</span>
                                    <div class="stars">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"></i>
                                        @endfor
                                    </div>
                                </div>
                                @if ($review->comment)
                                    <p class="text-muted-theme" style="font-size:13px; line-height:1.5;">
                                        {{ $review->comment }}</p>
                                @endif
                                <div style="font-size: 13px; color:#94a3b8; margin-top:6px;">
                                    {{ $review->created_at->format('M d, Y') }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        {{-- RIGHT COLUMN --}}
        <div>

            {{-- QUICK ACTIONS --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Quick Actions</h3>
                </div>
                <div class="panel-body" style="display:flex; flex-direction:column; gap:8px;">
                    <a href="{{ route('admin.properties.edit', $property) }}" class="btn-navy"
                        style="justify-content:center;">
                        <i class="bi bi-pencil"></i> Edit Property
                    </a>
                    <a href="{{ route('admin.bookings.create') }}?property_id={{ $property->id }}" class="btn-outline"
                        style="justify-content:center;">
                        <i class="bi bi-plus-circle"></i> New Booking
                    </a>
                    <button class="btn-outline" style="justify-content:center; width:100%;"
                        onclick="document.getElementById('blockDatesPanel').scrollIntoView({behavior:'smooth'})">
                        <i class="bi bi-calendar-x"></i> Block Dates
                    </button>
                </div>
            </div>

            {{-- PRICING RULES --}}
            <div class="panel">
                <div class="panel-head">
                    <h3>Pricing Rules</h3>
                </div>
                @if ($property->pricingRules->isEmpty())
                    <div class="empty-state" style="padding:24px;">
                        <i class="bi bi-tag" style="font-size:24px;"></i>
                        <p style="font-size: 14px; margin-top:6px;">No special pricing rules</p>
                    </div>
                @else
                    <div class="panel-body" style="display:flex; flex-direction:column; gap:8px;">
                        @foreach ($property->pricingRules as $rule)
                            <div
                                style="background:#f8fafc; border-radius:9px; padding:10px 14px; border:1px solid var(--border); font-size:13px;">
                                <div style="font-weight:600;">{{ $rule->name ?? ucfirst($rule->type) }}</div>
                                <div class="text-muted-theme" style="font-size: 14px; margin-top:2px;">
                                    ₱{{ number_format($rule->price ?? $rule->amount, 2) }}
                                    @if (isset($rule->start_date))
                                        · {{ \Carbon\Carbon::parse($rule->start_date)->format('M d') }} –
                                        {{ \Carbon\Carbon::parse($rule->end_date)->format('M d, Y') }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- AVAILABILITY BLOCKS --}}
            <div class="panel" id="blockDatesPanel">
                <div class="panel-head">
                    <h3>Block Dates</h3>
                </div>
                <div class="panel-body">
                    @if ($property->type === 'villa')
                        <p class="text-muted-theme mb-12" style="font-size: 14px;">
                            Blocking dates here will directly affect the availability visible to customers (since this is
                            the master, bookable Villa).
                        </p>
                    @else
                        <p class="text-muted-theme mb-12" style="font-size: 14px;">
                            <i class="bi bi-info-circle"></i> Note: this is for internal tracking/reference only for this
                            room. It does not directly affect the whole Villa's availability on the customer-facing site —
                            to block actual booking dates, block them on the master Villa record.
                        </p>
                    @endif
                    <form method="POST" action="{{ route('admin.properties.block', $property) }}" @csrf <div
                        class="mb-12">
                        <label
                            style="font-size: 13px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">Start
                            Date</label>
                        <input type="date" name="start_date" required min="{{ date('Y-m-d') }}"
                            style="width:100%; border:1.5px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; font-family:'DM Sans',sans-serif;">
                </div>
                <div class="mb-12">
                    <label style="font-size: 13px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">End
                        Date</label>
                    <input type="date" name="end_date" required min="{{ date('Y-m-d') }}"
                        style="width:100%; border:1.5px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; font-family:'DM Sans',sans-serif;">
                </div>
                <div class="mb-12">
                    <label
                        style="font-size: 13px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">Reason</label>
                    <select name="reason" required
                        style="width:100%; border:1.5px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; font-family:'DM Sans',sans-serif; background:#fff;">
                        <option value="maintenance">Maintenance</option>
                        <option value="owner_use">Owner Use</option>
                        <option value="private_event">Private Event</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-size: 13px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">Notes
                        <span class="text-muted-theme" style="font-weight:400;">(optional)</span></label>
                    <input type="text" name="notes" placeholder="e.g. Repainting the walls"
                        style="width:100%; border:1.5px solid var(--border); border-radius:8px; padding:9px 12px; font-size:13px; font-family:'DM Sans',sans-serif;">
                </div>
                <button type="submit" class="btn-navy" style="width:100%; justify-content:center;">
                    <i class="bi bi-calendar-x"></i> Block These Dates
                </button>
                </form>

                {{-- Existing blocks --}}
                @if ($property->availabilityBlocks->count() > 0)
                    <div style="margin-top:18px; padding-top:18px; border-top:1px solid var(--border);">
                        <div class="text-muted-theme section-label" style="font-size: 13px; margin-bottom:10px;">Blocked
                            Periods</div>
                        @foreach ($property->availabilityBlocks->sortByDesc('start_date')->take(5) as $block)
                            <div
                                style="background:#fff5f5; border-radius:8px; padding:9px 12px; margin-bottom:6px; border:1px solid #fecaca; font-size: 14px;">
                                <div style="font-weight:600; color:#dc2626;">
                                    {{ \Carbon\Carbon::parse($block->start_date)->format('M d') }} –
                                    {{ \Carbon\Carbon::parse($block->end_date)->format('M d, Y') }}
                                </div>
                                <div style="color:#6b7a8d; margin-top:2px;">
                                    {{ ucfirst(str_replace('_', ' ', $block->reason)) }}{{ $block->notes ? ' · ' . $block->notes : '' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- DANGER ZONE --}}
        <div class="danger-zone">
            <div>
                <div style="font-weight:600; color:#dc2626; font-size:14px;"><i
                        class="bi bi-exclamation-triangle me-2"></i>Delete Property</div>
                <div style="font-size: 14px; color:#6b7a8d; margin-top:2px;">This will permanently delete this property and
                    all its data.</div>
            </div>
            <button class="btn-danger" onclick="confirmDelete()">
                <i class="bi bi-trash"></i> Delete
            </button>
        </div>

        <form id="deleteForm" method="POST" action="{{ route('admin.properties.destroy', $property) }}"
            style="display:none;">
            @csrf
            @method('DELETE')
        </form>

    </div>
    </div>

@endsection

@push('scripts')
    <script>
        function confirmDelete() {
            if (confirm(
                    'Are you sure you want to delete "{{ addslashes($property->property_name) }}"? This cannot be undone.'
                    )) {
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
@endpush

