<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Properties — Villa Elena Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --navy: #0d1b2a; --navy-mid: #1a2f45; --gold: #c9a84c;
            --gold-light: #e8c97a; --gold-dim: rgba(201,168,76,0.15);
            --off-white: #f4f6f9; --border: #e2e8f0;
            --text-main: #1a2f45; --text-muted: #6b7a8d;
            --sidebar-w: 260px; --topbar-h: 68px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--off-white); color: var(--text-main); }

        /* Sidebar — same as dashboard */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: var(--navy); display: flex;
            flex-direction: column; z-index: 1000; overflow-y: auto;
        }
        .sidebar-brand { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.07); }
        .sidebar-brand h1 { font-family: 'Cormorant Garamond', serif; color: var(--gold-light); font-size: 22px; font-weight: 700; }
        .sidebar-brand p { color: rgba(255,255,255,0.35); font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; margin-top: 3px; }
        .sidebar-section { padding: 20px 16px 8px; }
        .sidebar-section-label { font-size: 10px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: rgba(255,255,255,0.25); padding: 0 8px; margin-bottom: 6px; }
        .nav-item-custom { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 14px; transition: all .2s; margin-bottom: 2px; }
        .nav-item-custom:hover { background: rgba(255,255,255,0.07); color: #fff; }
        .nav-item-custom.active { background: var(--gold-dim); color: var(--gold-light); font-weight: 500; }
        .nav-icon { width: 32px; height: 32px; border-radius: 7px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; background: rgba(255,255,255,0.05); }
        .nav-item-custom.active .nav-icon { background: var(--gold-dim); color: var(--gold); }
        .sidebar-footer { margin-top: auto; padding: 16px; border-top: 1px solid rgba(255,255,255,0.07); }
        .user-card { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; background: rgba(255,255,255,0.05); }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--gold-dim); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 600; }
        .user-info .name { color: #fff; font-size: 13px; font-weight: 500; }
        .user-info .role-badge { font-size: 10px; color: var(--gold); letter-spacing: 0.5px; text-transform: uppercase; }

        /* Topbar */
        .topbar { position: fixed; top: 0; left: var(--sidebar-w); right: 0; height: var(--topbar-h); background: #fff; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; z-index: 900; }
        .topbar-left h2 { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 600; }
        .topbar-left p { font-size: 12px; color: var(--text-muted); margin-top: 1px; }
        .logout-btn { display: flex; align-items: center; gap: 7px; background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; border-radius: 9px; padding: 7px 14px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all .2s; text-decoration: none; }
        .logout-btn:hover { background: #ef4444; color: white; border-color: #ef4444; }

        /* Main */
        .main-content { margin-left: var(--sidebar-w); margin-top: var(--topbar-h); padding: 32px; }

        /* Stats row */
        .stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: #fff; border-radius: 12px; padding: 18px 20px; border: 1px solid var(--border); display: flex; align-items: center; gap: 14px; }
        .stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .stat-val { font-family: 'Cormorant Garamond', serif; font-size: 28px; font-weight: 700; color: var(--text-main); line-height: 1; }
        .stat-lbl { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* Toolbar */
        .toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
        .btn-add { display: flex; align-items: center; gap: 8px; background: var(--navy); color: #fff; border: none; border-radius: 9px; padding: 10px 20px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; transition: opacity .2s; }
        .btn-add:hover { opacity: 0.88; color: #fff; }
        .search-box { display: flex; gap: 8px; align-items: center; }
        .search-box input { border: 1.5px solid var(--border); border-radius: 8px; padding: 9px 14px; font-size: 13px; width: 240px; font-family: 'DM Sans', sans-serif; }
        .search-box input:focus { outline: none; border-color: var(--navy-mid); }
        .filter-select { border: 1.5px solid var(--border); border-radius: 8px; padding: 9px 14px; font-size: 13px; font-family: 'DM Sans', sans-serif; background: #fff; }

        /* Property Cards Grid */
        .properties-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; }

        .property-card { background: #fff; border-radius: 14px; border: 1px solid var(--border); overflow: hidden; transition: transform .2s, box-shadow .2s; }
        .property-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.09); }

        .property-img { width: 100%; height: 180px; object-fit: cover; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 40px; }
        .property-img img { width: 100%; height: 100%; object-fit: cover; }

        .property-body { padding: 18px 20px; }
        .property-type { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.2px; color: var(--gold); margin-bottom: 6px; }
        .property-name { font-family: 'Cormorant Garamond', serif; font-size: 19px; font-weight: 600; color: var(--text-main); margin-bottom: 8px; }
        .property-meta { display: flex; gap: 14px; font-size: 12px; color: var(--text-muted); margin-bottom: 12px; }
        .property-meta span { display: flex; align-items: center; gap: 4px; }
        .property-price { font-size: 18px; font-weight: 600; color: var(--text-main); margin-bottom: 14px; }
        .property-price small { font-size: 12px; font-weight: 400; color: var(--text-muted); }

        .property-footer { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-top: 1px solid var(--border); background: #fafbfc; }

        .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-available    { background: #dcfce7; color: #15803d; }
        .status-occupied     { background: #dbeafe; color: #1d4ed8; }
        .status-maintenance  { background: #fef9c3; color: #a16207; }

        .action-btns { display: flex; gap: 6px; }
        .btn-icon { width: 32px; height: 32px; border-radius: 7px; border: 1px solid var(--border); background: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; cursor: pointer; transition: all .2s; text-decoration: none; color: var(--text-muted); }
        .btn-icon:hover { background: var(--navy); color: #fff; border-color: var(--navy); }
        .btn-icon.danger:hover { background: #ef4444; border-color: #ef4444; color: #fff; }

        /* Empty state */
        .empty-state { text-align: center; padding: 80px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 52px; display: block; margin-bottom: 12px; opacity: .4; }
        .empty-state h3 { font-size: 18px; color: var(--text-main); margin-bottom: 6px; }

        /* Alert */
        .alert { border-radius: 10px; font-size: 13px; padding: 12px 16px; margin-bottom: 20px; border: none; }
        .alert-success { background: #dcfce7; color: #15803d; }
        .alert-danger  { background: #fee2e2; color: #dc2626; }

        @keyframes fadeUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
        .property-card { animation: fadeUp .35s ease both; }
    </style>
</head>
<body>

@include('admin.partials.sidebar')


{{-- TOPBAR --}}
<header class="topbar">
    <div class="topbar-left">
        <h2>Properties</h2>
        <p>Manage all resort rooms, villas, cottages, and halls</p>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
        </form>
    </div>
</header>

{{-- MAIN --}}
<main class="main-content">

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Stats --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e0f2fe; color:#0369a1;"><i class="bi bi-houses"></i></div>
            <div><div class="stat-val">{{ $stats['total'] }}</div><div class="stat-lbl">Total Properties</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7; color:#15803d;"><i class="bi bi-house-check"></i></div>
            <div><div class="stat-val">{{ $stats['available'] }}</div><div class="stat-lbl">Available</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe; color:#1d4ed8;"><i class="bi bi-house-fill"></i></div>
            <div><div class="stat-val">{{ $stats['occupied'] }}</div><div class="stat-lbl">Occupied</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3; color:#a16207;"><i class="bi bi-tools"></i></div>
            <div><div class="stat-val">{{ $stats['maintenance'] }}</div><div class="stat-lbl">Maintenance</div></div>
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
                <option value="cottage">Cottage</option>
                <option value="room">Room</option>
                <option value="hall">Hall</option>
            </select>
        </div>
    </div>

    {{-- Properties Grid --}}
    @if($properties->isEmpty())
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
            @foreach($properties as $property)
            <div class="property-card"
                data-name="{{ strtolower($property->property_name) }}"
                data-status="{{ $property->status }}"
                data-type="{{ $property->type }}">

                {{-- Image --}}
                <div class="property-img">
                    @if($property->primaryImage)
                        <img src="{{ asset('storage/' . $property->primaryImage->image_path) }}"
                             alt="{{ $property->property_name }}">
                    @else
                        <i class="bi bi-image"></i>
                    @endif
                </div>

                {{-- Body --}}
                <div class="property-body">
                    <div class="property-type">{{ ucfirst($property->type) }}</div>
                    <div class="property-name">{{ $property->property_name }}</div>
                    <div class="property-meta">
                        <span><i class="bi bi-people"></i> {{ $property->max_capacity }} guests</span>
                        @if($property->floor_area_sqm)
                        <span><i class="bi bi-rulers"></i> {{ $property->floor_area_sqm }} sqm</span>
                        @endif
                        <span><i class="bi bi-calendar-check"></i> {{ $property->bookings_count }} bookings</span>
                    </div>
                    <div class="property-price">
                        ₱{{ number_format($property->base_price, 2) }}
                        <small>/ night</small>
                        @if($property->weekend_price)
                            <small style="color:#c9a84c; margin-left:6px;">₱{{ number_format($property->weekend_price,2) }} wknd</small>
                        @endif
                    </div>

                    {{-- Amenities --}}
                    @if($property->amenities)
                        <div style="display:flex; flex-wrap:wrap; gap:5px;">
                            @foreach(array_slice($property->amenities, 0, 4) as $amenity)
                                <span style="background:#f1f5f9; color:#64748b; font-size:10px; padding:2px 8px; border-radius:10px;">
                                    {{ $amenity }}
                                </span>
                            @endforeach
                            @if(count($property->amenities) > 4)
                                <span style="background:#f1f5f9; color:#64748b; font-size:10px; padding:2px 8px; border-radius:10px;">
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
                        <a href="{{ route('admin.properties.show', $property) }}"
                           class="btn-icon" title="View"><i class="bi bi-eye"></i></a>
                        <a href="{{ route('admin.properties.edit', $property) }}"
                           class="btn-icon" title="Edit"><i class="bi bi-pencil"></i></a>
                        <button onclick="confirmDelete({{ $property->id }}, '{{ $property->property_name }}')"
                                class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                </div>

            </div>
            @endforeach
        </div>
    @endif

</main>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px; border:none;">
            <div class="modal-body text-center p-4">
                <div style="width:56px;height:56px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:24px;color:#ef4444;">
                    <i class="bi bi-trash"></i>
                </div>
                <h5 style="font-family:'Cormorant Garamond',serif;font-size:20px;margin-bottom:8px;">Delete Property?</h5>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmDelete(id, name) {
    document.getElementById('deleteMsg').textContent = `"${name}" and all its images will be permanently deleted.`;
    document.getElementById('deleteForm').action = `/admin/properties/${id}`;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function filterCards() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const type   = document.getElementById('filterType').value;

    document.querySelectorAll('.property-card').forEach(card => {
        const matchName   = card.dataset.name.includes(search);
        const matchStatus = !status || card.dataset.status === status;
        const matchType   = !type   || card.dataset.type   === type;
        card.style.display = (matchName && matchStatus && matchType) ? '' : 'none';
    });
}
</script>
@include('admin.partials.realtime') 
</body>
</html>