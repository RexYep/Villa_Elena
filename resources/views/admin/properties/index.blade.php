@extends('layouts.admin')

@section('title', 'Properties — Villa Elena Admin')
@section('page-title', 'Properties')
@section('page-subtitle', 'Manage all resort rooms, villas, cottages, and halls')

@push('styles')
    <style>
        /* Stats row — auto-fit, not four fixed tracks. `repeat(4, 1fr)` is
           `repeat(4, minmax(auto, 1fr))`, so each track's floor is its own
           min-content: icon (42px) + gap + the longest label word + padding is
           about 171px, but at a 1000px viewport there are only ~157px to go
           round, so the row overflowed its container by a few pixels — invisible,
           because admin.css clips it with `body { overflow-x: hidden }`. */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--cream);
            border-radius: 12px;
            padding: 18px 20px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
            background: var(--gold-dim);
            color: var(--gold);
        }

        .stat-val {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }

        .stat-lbl {
            font-size: 14px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-add {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-add:hover {
            background: var(--gold);
            color: #fff;
        }

        /* flex-wrap, because this row cannot shrink past roughly 400px: a text
           input has an intrinsic minimum around 170px and each select cannot go
           narrower than its longest option ("Maintenance", "All Types"). Without
           it the row stays one line and runs off a phone screen — and admin.css's
           `body { overflow-x: hidden }` means that shows up as the filters being
           cut off, not as a scrollbar. */
        .search-box {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .search-box input {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 13px;
            flex: 1 1 200px;
            min-width: 0;
            max-width: 240px;
            font-family: 'DM Sans', sans-serif;
            background: #fff;
            color: var(--text-main);
        }

        /* flex: 0 1 auto — shrink when the row is tight, but never GROW. With
           `1 1 auto` a select expands to fill whatever is left on its line, and
           because the row wraps at desktop widths the lone "All Types" dropdown
           was stretching to 415px for its two options. */
        .search-box .filter-select {
            flex: 0 1 auto;
            min-width: 0;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--terracotta);
        }

        .filter-select {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            background: #fff;
            color: var(--text-main);
        }

        /* Property Cards Grid — minmax(0, 1fr) rather than 1fr. A bare `1fr` is
           minmax(auto, 1fr), so a single wide item inside a card (a long property
           name, the meta row above, an amenity chip) can widen its track and take
           the grid past the screen. minmax(0, …) lets the track shrink and the
           content wrap instead. */
        /* Three fixed columns held even where they don't fit. Between 993px —
           where the admin's own 260px sidebar reappears — and about 1150px the
           content area is only ~676px, giving 207px cards; the meta row inside
           them went to three lines and the price and amenity chips had nowhere to
           go. Sizing by content lets the column count follow the space: two in
           that band, three once there is room. */
        .properties-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }

        .property-card {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
        }

        .property-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(44, 36, 22, 0.12);
        }

        .property-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 40px;
        }

        .property-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-body {
            padding: 18px 20px;
        }

        .property-type {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--gold);
            margin-bottom: 6px;
        }

        .property-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 19px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        /* Three nowrap items on one line ("20 guests", "340 sqm", "1248
           bookings") need roughly 260px, plus the card's 40px of padding. In the
           two-column band the cards are narrower than that, and since a grid
           `1fr` track's floor is its min-content width, the track widens rather
           than the text wrapping — pushing the whole grid past its container. */
        .property-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .property-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .property-price {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 14px;
        }

        .property-price small {
            font-size: 14px;
            font-weight: 400;
            color: var(--muted);
        }

        .property-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            background: var(--sand);
        }

        /* Status badges — semantic, unchanged */
        .status-available {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .status-occupied {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-maintenance {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        /* Empty state */
        .empty-state h3 {
            font-size: 18px;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .property-card {
            animation: fadeUp .35s ease both;
        }

        /* No 900px override any more — both grids above size themselves by
           content now, so the column count follows the width on its own. */

        @media (max-width: 560px) {
            /* Two columns, not one. These cards are an icon plus two short lines,
               so full width wastes most of the row and turns four of them into a
               long scroll before the properties themselves start. */
            .stats-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 14px;
                gap: 10px;
            }

            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }

            .stat-val {
                font-size: 22px;
            }

            .stat-lbl {
                font-size: 12px;
            }

            /* One property card per row on a phone; auto-fill would still fit two
               260px tracks at the wider end of this range and they would be too
               cramped for the image, meta row, price and amenity chips. */
            .properties-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            /* The toolbar is space-between, so on a phone the Add button and the
               filters sit on separate lines with the button stranded; stacking
               them deliberately keeps the filters full width. */
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .toolbar .btn-add {
                justify-content: center;
            }

            .search-box input {
                max-width: none;
            }
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Stats --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e0f2fe; color:#0369a1;"><i class="bi bi-houses"></i></div>
            <div>
                <div class="stat-val">{{ $stats['total'] }}</div>
                <div class="stat-lbl">Total Properties</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7; color:#15803d;"><i class="bi bi-house-check"></i></div>
            <div>
                <div class="stat-val">{{ $stats['available'] }}</div>
                <div class="stat-lbl">Available</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe; color:#1d4ed8;"><i class="bi bi-house-fill"></i></div>
            <div>
                <div class="stat-val">{{ $stats['occupied'] }}</div>
                <div class="stat-lbl">Occupied</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3; color:#a16207;"><i class="bi bi-tools"></i></div>
            <div>
                <div class="stat-val">{{ $stats['maintenance'] }}</div>
                <div class="stat-lbl">Maintenance</div>
            </div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="toolbar">
        <a href="{{ route('admin.properties.create') }}" class="btn-add">
            <i class="bi bi-plus-lg"></i> Add New Property
        </a>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search properties..." onkeyup="filterCards()">
            <select class="filter-select" id="filterStatus" onchange="filterCards()">
                <option value="">All Status</option>
                <option value="available">Available</option>
                <option value="occupied">Occupied</option>
                <option value="maintenance">Maintenance</option>
            </select>
            <select class="filter-select" id="filterType" onchange="filterCards()">
                <option value="">All Types</option>
                <option value="villa">Villa</option>
                <option value="room">Room</option>
            </select>
        </div>
    </div>

    {{-- Properties Grid --}}
    @if ($properties->isEmpty())
        <div class="empty-state">
            <i class="bi bi-house-slash"></i>
            <h3>No properties yet</h3>
            <p>Add your first property to get started.</p>
            <a href="{{ route('admin.properties.create') }}" class="btn-add" style="display:inline-flex; margin-top:16px;">
                <i class="bi bi-plus-lg"></i> Add First Property
            </a>
        </div>
    @else
        <div class="properties-grid" id="propertiesGrid">
            @foreach ($properties as $property)
                <div class="property-card" data-name="{{ strtolower($property->property_name) }}"
                    data-status="{{ $property->status }}" data-type="{{ $property->type }}">

                    {{-- Image --}}
                    <div class="property-img">
                        @if ($property->primaryImage)
                            <img src="{{ $property->primaryImage->url }}" alt="{{ $property->property_name }}">
                        @else
                            <i class="bi bi-image"></i>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="property-body">
                        <div class="property-type">
                            {{ ucfirst($property->type) }}
                            @if ($property->type === 'villa')
                                <span class="tag-green"
                                    style="font-size: 11px;font-weight:700;padding:2px 7px;border-radius:10px;margin-left:6px;letter-spacing:.3px;">MASTER
                                    · BOOKABLE</span>
                            @elseif($property->type === 'room')
                                <span
                                    style="background:#f1f5f9;color:#64748b;font-size: 11px;font-weight:700;padding:2px 7px;border-radius:10px;margin-left:6px;letter-spacing:.3px;">PART
                                    OF VILLA</span>
                            @endif
                        </div>
                        <div class="property-name">{{ $property->property_name }}</div>
                        <div class="property-meta">
                            <span><i class="bi bi-people"></i> {{ $property->max_capacity }} guests</span>
                            @if ($property->floor_area_sqm)
                                <span><i class="bi bi-rulers"></i> {{ $property->floor_area_sqm }} sqm</span>
                            @endif
                            <span><i class="bi bi-calendar-check"></i> {{ $property->bookings_count }} bookings</span>
                        </div>
                        <div class="property-price">
                            ₱{{ number_format($property->base_price, 2) }}
                            <small>/ night</small>
                            @if ($property->weekend_price)
                                <small
                                    style="color:#c9a84c; margin-left:6px;">₱{{ number_format($property->weekend_price, 2) }}
                                    wknd</small>
                            @endif
                        </div>

                        {{-- Amenities --}}
                        @if ($property->amenities)
                            <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                @foreach (array_slice($property->amenities, 0, 4) as $amenity)
                                    <span
                                        style="background:#f1f5f9; color:#64748b; font-size: 12px; padding:2px 8px; border-radius:10px;">
                                        {{ $amenity }}
                                    </span>
                                @endforeach
                                @if (count($property->amenities) > 4)
                                    <span
                                        style="background:#f1f5f9; color:#64748b; font-size: 12px; padding:2px 8px; border-radius:10px;">
                                        +{{ count($property->amenities) - 4 }} more
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="property-footer">
                        <span class="status-badge status-{{ $property->status }}">
                            {{ ucfirst($property->status) }}
                        </span>
                        <div class="action-btns">
                            <a href="{{ route('admin.properties.show', $property) }}" class="btn-icon" title="View"><i
                                    class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.properties.edit', $property) }}" class="btn-icon" title="Edit"><i
                                    class="bi bi-pencil"></i></a>
                            <button onclick="confirmDelete({{ $property->id }}, '{{ $property->property_name }}')"
                                class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

@endsection

@section('modals')
    {{-- Delete Confirmation Modal --}}
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content" style="border-radius:14px; border:none;">
                <div class="modal-body text-center p-4">
                    <div
                        style="width:56px;height:56px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:24px;color:#ef4444;">
                        <i class="bi bi-trash"></i>
                    </div>
                    <h5 style="font-family:'Cormorant Garamond',serif;font-size:20px;margin-bottom:8px;">Delete Property?
                    </h5>
                    <p style="font-size:13px;color:#64748b;margin-bottom:20px;" id="deleteMsg"></p>
                    <form id="deleteForm" method="POST">
                        @csrf @method('DELETE')
                        <div style="display:flex;gap:8px;">
                            <button type="button" class="btn btn-light w-50" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger w-50">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const deletePropertyUrlTemplate = '{{ route('admin.properties.destroy', ['property' => '__ID__']) }}';

        function confirmDelete(id, name) {
            document.getElementById('deleteMsg').textContent = `"${name}" and all its images will be permanently deleted.`;
            document.getElementById('deleteForm').action = deletePropertyUrlTemplate.replace('__ID__', id);
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function filterCards() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('filterStatus').value;
            const type = document.getElementById('filterType').value;

            document.querySelectorAll('.property-card').forEach(card => {
                const matchName = card.dataset.name.includes(search);
                const matchStatus = !status || card.dataset.status === status;
                const matchType = !type || card.dataset.type === type;
                card.style.display = (matchName && matchStatus && matchType) ? '' : 'none';
            });
        }
    </script>
@endpush

