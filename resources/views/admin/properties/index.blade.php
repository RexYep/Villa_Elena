@extends('layouts.admin')

@section('title', 'Properties — Villa Elena Admin')
@section('page-title', 'Properties')
@section('page-subtitle', 'Manage the villa and its rooms')

@push('styles')
    <style>
        /* WALA nang stats row dito.
           Apat na stat card sa ibabaw ng apat na item, at "Total Properties: 4"
           ay hindi isang KPI. Mas masama pa: pinaghahalo ng
           Available/Occupied/Maintenance ang status ng villa — na siyang
           nakakaapekto sa benta — at ang status ng mga room, na sa housekeeping
           lang mahalaga. Walang tanong na sinasagot ng pinagsamang bilang.
           Ang status ng villa ay nasa villa panel na; ang buod ng mga room ay
           nasa pamagat ng seksiyong Rooms. */

        /* ── Zone 1: ang villa ─────────────────────────────────────────
           Isang larawan sa kaliwa at isang hanay ng datos sa kanan, buong
           lapad. Sinasadyang HINDI ito kaparehong card ng mga room: ang villa
           ang may dalang pera, kaya ito ang may bigat. */
        .villa-panel {
            display: grid;
            grid-template-columns: minmax(0, 320px) minmax(0, 1fr);
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 36px;
        }

        .villa-photo {
            background: var(--sand);
            min-height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 40px;
        }

        .villa-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .villa-body {
            padding: 24px 28px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .villa-eyebrow {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--gold-text);
        }

        .villa-name {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.1;
            margin: 0;
        }

        /* Ang mga halaga ng rate ang tanging nakatakda sa serif display face sa
           pahinang ito — ito ang numerong tinitingnan ng admin. */
        .villa-rates {
            display: flex;
            flex-wrap: wrap;
            gap: 28px;
        }

        .villa-rate-val {
            font-family: var(--font-display);
            font-size: 23px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }

        .villa-rate-lbl {
            font-size: 13px;
            color: var(--muted);
            margin-top: 4px;
        }

        .villa-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 20px;
            font-size: 14px;
            color: var(--muted);
        }

        .villa-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .villa-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: auto;
            padding-top: 4px;
        }

        /* ── Zone 2: ang mga room ───────────────────────────────────────
           Tahimik na hanay. Mas maliit na radius, walang hover-lift — hindi
           ito kapantay ng villa panel at hindi dapat magmukhang ganoon. */
        .rooms-head {
            display: flex;
            align-items: baseline;
            gap: 10px;
            margin-bottom: 14px;
        }

        .rooms-head h2 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 600;
            color: var(--text-main);
            margin: 0;
        }

        .rooms-head .count {
            font-size: 14px;
            color: var(--muted);
        }

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .room-card {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .room-photo {
            height: 120px;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 28px;
        }

        .room-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .room-body {
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .room-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-main);
        }

        .room-cap {
            font-size: 13px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .room-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 14px;
            border-top: 1px solid var(--border);
            background: var(--sand);
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
            background: var(--btn-primary);
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
            background: var(--btn-primary-hover);
            color: #fff;
        }


        /* Wala nang .properties-grid at .property-* dito: pinalitan sila ng
           .villa-panel at ng .room-card sa itaas. Ang .status-* sa ibaba ay
           ginagamit pa rin ng dalawang zona. */

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


        /* No 900px override any more — both grids above size themselves by
           content now, so the column count follows the width on its own. */

        @media (max-width: 560px) {
            /* Ang .villa-panel at .rooms-grid ang humahawak sa sarili nilang
               pagliit sa ibaba — tingnan ang media query sa dulo. */

            /* Isang hanay ang villa panel sa telepono, at hindi na kailangang
               i-stack ang toolbar — isang pindutan na lang ang laman nito. */
            .villa-panel {
                grid-template-columns: minmax(0, 1fr);
            }

            .villa-body {
                padding: 18px 20px;
            }

            .villa-name {
                font-size: 25px;
            }

            .rooms-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

    {{-- Toolbar
         Walang search box, walang Status filter, walang Type filter.
         Apat na item ang nasa pahina, tatlo rito ay room. Ang paghahanap sa
         apat na card ay mas mahal kaysa sa natitipid nito, at ang Type filter
         ay ganap nang kalabisan kapag ang pahina mismo ay nakahati sa Villa at
         Rooms. Pangalawa na lang din ang "Add New Property": hinaharangan na ng
         store() ang pangalawang villa, kaya room lang ang tunay nitong
         naidadagdag, at bihira iyon. --}}
    <div class="toolbar" style="justify-content:flex-end;">
        <a href="{{ route('admin.properties.create') }}" class="btn-add"
            style="background:transparent; color:var(--text-main); border:1.5px solid var(--border);">
            <i class="bi bi-plus-lg"></i> Add room
        </a>
    </div>

    {{-- ── ZONE 1: THE VILLA ──────────────────────────────────────── --}}
    @if ($villa)
        <div class="villa-panel">
            <div class="villa-photo">
                @if ($villa->primaryImage)
                    <img src="{{ $villa->primaryImage->url }}" alt="{{ $villa->property_name }}">
                @else
                    <i class="bi bi-image"></i>
                @endif
            </div>
            <div class="villa-body">
                <div>
                    <div class="villa-eyebrow">The bookable listing</div>
                    <h2 class="villa-name">{{ $villa->property_name ?: 'Untitled villa' }}</h2>
                </div>

                <div class="villa-rates">
                    <div>
                        <div class="villa-rate-val">₱{{ number_format($villa->base_price, 2) }}</div>
                        <div class="villa-rate-lbl">Regular · Mon–Thu, Sun after 6PM</div>
                    </div>
                    @if ($villa->weekend_price)
                        <div>
                            <div class="villa-rate-val">₱{{ number_format($villa->weekend_price, 2) }}</div>
                            <div class="villa-rate-lbl">Peak · Fri/Sat, Sun before 6PM</div>
                        </div>
                    @endif
                </div>

                <div class="villa-meta">
                    <span><i class="bi bi-people"></i> {{ $villa->max_capacity }} guests</span>
                    @if ($villa->floor_area_sqm)
                        <span><i class="bi bi-rulers"></i> {{ $villa->floor_area_sqm }} sqm</span>
                    @endif
                    <span><i class="bi bi-calendar-check"></i> {{ $villa->bookings_count }} bookings</span>
                    <span class="status-badge status-{{ $villa->status }}">{{ ucfirst($villa->status) }}</span>
                </div>

                {{-- Walang Delete. Tinatanggihan ito ng server, at ito ang
                     pindutang bubura sana ng bawat booking at payment. --}}
                <div class="villa-actions">
                    <a href="{{ route('admin.properties.show', $villa) }}" class="btn-add"
                        style="background:transparent; color:var(--text-main); border:1.5px solid var(--border);">
                        <i class="bi bi-eye"></i> View
                    </a>
                    <a href="{{ route('admin.properties.edit', $villa) }}" class="btn-add">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                </div>
            </div>
        </div>
    @else
        <div class="empty-state">
            <i class="bi bi-house-slash"></i>
            <h3>No villa record</h3>
            <p>Nothing can be booked until a property of type "villa" exists.</p>
            <a href="{{ route('admin.properties.create') }}" class="btn-add" style="display:inline-flex; margin-top:16px;">
                <i class="bi bi-plus-lg"></i> Add the villa
            </a>
        </div>
    @endif

    {{-- ── ZONE 2: THE ROOMS ──────────────────────────────────────── --}}
    @if ($rooms->isNotEmpty())
        <div class="rooms-head">
            <h2>Rooms</h2>
            <span class="count">
                {{ $rooms->count() }} {{ Str::plural('room', $rooms->count()) }} · not separately bookable
            </span>
        </div>

        <div class="rooms-grid">
            @foreach ($rooms as $room)
                <div class="room-card">
                    <div class="room-photo">
                        @if ($room->primaryImage)
                            <img src="{{ $room->primaryImage->url }}" alt="{{ $room->property_name }}">
                        @else
                            <i class="bi bi-image"></i>
                        @endif
                    </div>
                    <div class="room-body">
                        {{-- Walang presyo at walang bilang ng booking dito:
                             0.00 at 0 ang laman ng mga iyon magpakailanman. --}}
                        <div class="room-name">{{ $room->property_name ?: 'Untitled room' }}</div>
                        <div class="room-cap"><i class="bi bi-people"></i> {{ $room->max_capacity }} guests</div>
                    </div>
                    <div class="room-foot">
                        <span class="status-badge status-{{ $room->status }}">{{ ucfirst($room->status) }}</span>
                        <div class="action-btns">
                            <a href="{{ route('admin.properties.show', $room) }}" class="btn-icon" title="View"><i
                                    class="bi bi-eye"></i></a>
                            <a href="{{ route('admin.properties.edit', $room) }}" class="btn-icon" title="Edit"><i
                                    class="bi bi-pencil"></i></a>
                            {{-- Js::from(), never '{{ $x }}': the HTML parser decodes entities in an attribute
                                 before the JS parser runs, so `{{ }}` does not keep a value inside a JS string.
                                 Measured exploitable on this shape; see staff/partials/_today_list.blade.php. --}}
                            <button
                                onclick="confirmDelete({{ $room->id }}, {{ Illuminate\Support\Js::from($room->property_name ?: 'Untitled room') }}, {{ (int) $room->bookings_count }})"
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
            <div class="modal-content modal-soft">
                <div class="modal-body text-center p-4">
                    <div
                        style="width:56px;height:56px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:24px;color:#b91c1c;">
                        <i class="bi bi-trash"></i>
                    </div>
                    <h5 style="font-family: var(--font-display);font-size: 18px;margin-bottom:8px;">Delete Property?
                    </h5>
                    <p style="font-size:13px;color:var(--muted);margin-bottom:20px;" id="deleteMsg"></p>
                    <form id="deleteForm" method="POST">
                        @csrf @method('DELETE')
                        <div class="flex-gap-8">
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

        // Sinasabi na ng mensahe kung ano talaga ang mawawala. Dati ay "and all
        // its images will be permanently deleted" — samantalang CASCADE ang
        // bawat foreign key, kaya kasama ring nabubura ang mga booking nito at
        // ang mga bayad sa mga booking na iyon.
        function confirmDelete(id, name, bookingCount) {
            const msg = bookingCount > 0 ?
                `"${name}" has ${bookingCount} booking(s). Deleting it also deletes those bookings and their payments. This cannot be undone.` :
                `"${name}" and its images will be permanently deleted. This cannot be undone.`;

            document.getElementById('deleteMsg').textContent = msg;
            document.getElementById('deleteForm').action = deletePropertyUrlTemplate.replace('__ID__', id);
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Wala nang filterCards(): kasama ng search box at ng dalawang dropdown
        // na tumatawag dito ang naalis. Ang `.property-card` na hinahanap nito
        // ay wala na ring umiiral.
    </script>
@endpush

