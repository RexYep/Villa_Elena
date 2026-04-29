<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Guests & Staff — Villa Elena Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--navy:#0d1b2a;--navy-mid:#1a2f45;--gold:#c9a84c;--gold-light:#e8c97a;--gold-dim:rgba(201,168,76,0.15);--off-white:#f4f6f9;--border:#e2e8f0;--text-main:#1a2f45;--text-muted:#6b7a8d;--sidebar-w:260px;--topbar-h:68px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--off-white);color:var(--text-main);}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;background:var(--navy);display:flex;flex-direction:column;z-index:1000;overflow-y:auto;}
        .sidebar-brand{padding:28px 24px 20px;border-bottom:1px solid rgba(255,255,255,0.07);}
        .sidebar-brand h1{font-family:'Cormorant Garamond',serif;color:var(--gold-light);font-size:22px;font-weight:700;}
        .sidebar-brand p{color:rgba(255,255,255,0.35);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;margin-top:3px;}
        .sidebar-section{padding:20px 16px 8px;}
        .sidebar-section-label{font-size:10px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.25);padding:0 8px;margin-bottom:6px;}
        .nav-item-custom{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:8px;color:rgba(255,255,255,0.6);text-decoration:none;font-size:14px;transition:all .2s;margin-bottom:2px;}
        .nav-item-custom:hover{background:rgba(255,255,255,0.07);color:#fff;}
        .nav-item-custom.active{background:var(--gold-dim);color:var(--gold-light);font-weight:500;}
        .nav-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;background:rgba(255,255,255,0.05);}
        .nav-item-custom.active .nav-icon{background:var(--gold-dim);color:var(--gold);}
        .sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,0.07);}
        .user-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;background:rgba(255,255,255,0.05);}
        .user-avatar{width:36px;height:36px;border-radius:50%;background:var(--gold-dim);color:var(--gold);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:600;}
        .user-info .name{color:#fff;font-size:13px;font-weight:500;}
        .user-info .role-badge{font-size:10px;color:var(--gold);letter-spacing:0.5px;text-transform:uppercase;}
        .topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 32px;z-index:900;}
        .topbar-left h2{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:600;}
        .topbar-left p{font-size:12px;color:var(--text-muted);margin-top:1px;}
        .logout-btn{display:flex;align-items:center;gap:7px;background:#fef2f2;color:#ef4444;border:1px solid #fecaca;border-radius:9px;padding:7px 14px;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;text-decoration:none;}
        .logout-btn:hover{background:#ef4444;color:white;border-color:#ef4444;}
        .main-content{margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:32px;}

        .stats-row{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:24px;}
        .stat-chip{background:#fff;border-radius:10px;padding:14px 16px;border:1px solid var(--border);cursor:pointer;transition:all .2s;text-decoration:none;display:block;}
        .stat-chip:hover{border-color:var(--navy);box-shadow:0 2px 8px rgba(0,0,0,0.07);}
        .stat-chip.active{background:var(--navy);border-color:var(--navy);}
        .stat-chip .val{font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:var(--text-main);line-height:1;}
        .stat-chip.active .val{color:#fff;}
        .stat-chip .lbl{font-size:11px;color:var(--text-muted);margin-top:3px;}
        .stat-chip.active .lbl{color:rgba(255,255,255,0.65);}

        .filters-bar{background:#fff;border-radius:12px;border:1px solid var(--border);padding:14px 20px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
        .filter-input{border:1.5px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;font-family:'DM Sans',sans-serif;background:#fff;}
        .filter-input:focus{outline:none;border-color:var(--navy-mid);}
        .btn-filter{background:var(--navy);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;cursor:pointer;font-family:'DM Sans',sans-serif;}
        .btn-clear{background:#f1f5f9;color:var(--text-muted);border:none;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:none;}

        .table-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;}
        .table-header{padding:16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .table-header h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;}
        .btn-add{display:flex;align-items:center;gap:7px;background:var(--navy);color:#fff;border:none;border-radius:9px;padding:9px 18px;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:opacity .2s;}
        .btn-add:hover{opacity:.88;color:#fff;}

        table{width:100%;border-collapse:collapse;font-size:13px;}
        th{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);padding:12px 20px;border-bottom:1px solid var(--border);text-align:left;}
        td{padding:13px 20px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:#fafbfc;}

        .avatar-cell{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;}
        .role-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
        .role-admin    {background:#fef9c3;color:#a16207;}
        .role-staff    {background:#dbeafe;color:#1d4ed8;}
        .role-customer {background:#f3e8ff;color:#7c3aed;}
        .status-active  {background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
        .status-inactive{background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}

        .action-btns{display:flex;gap:5px;}
        .btn-icon{width:30px;height:30px;border-radius:7px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--text-muted);}
        .btn-icon:hover{background:var(--navy);color:#fff;border-color:var(--navy);}
        .btn-icon.danger:hover{background:#ef4444;border-color:#ef4444;color:#fff;}
        .btn-icon.warning:hover{background:#f59e0b;border-color:#f59e0b;color:#fff;}

        .empty-state{text-align:center;padding:60px 20px;color:var(--text-muted);}
        .empty-state i{font-size:44px;display:block;margin-bottom:10px;opacity:.4;}
        .pagination-wrap{padding:16px 24px;border-top:1px solid var(--border);}
        .pagination .page-link{border-radius:7px;font-size:13px;color:var(--navy);border-color:var(--border);}
        .pagination .page-item.active .page-link{background:var(--navy);border-color:var(--navy);}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-success{background:#dcfce7;color:#15803d;}
        .alert-danger{background:#fee2e2;color:#dc2626;}
    </style>
</head>
<body>

@include('admin.partials.sidebar')


<header class="topbar">
    <div class="topbar-left">
        <h2>Guests & Staff</h2>
        <p>Manage all registered accounts and user profiles</p>
    </div>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
</header>

<main class="main-content">

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    {{-- Stats --}}
    <div class="stats-row">
        <a href="{{ route('admin.users.index') }}" class="stat-chip {{ !request('role') ? 'active' : '' }}">
            <div class="val">{{ $stats['total'] }}</div><div class="lbl">All Users</div>
        </a>
        <a href="{{ route('admin.users.index', ['role'=>'customer']) }}" class="stat-chip {{ request('role')=='customer' ? 'active' : '' }}">
            <div class="val" style="color:#7c3aed;">{{ $stats['customers'] }}</div><div class="lbl">Guests</div>
        </a>
        <a href="{{ route('admin.users.index', ['role'=>'staff']) }}" class="stat-chip {{ request('role')=='staff' ? 'active' : '' }}">
            <div class="val" style="color:#1d4ed8;">{{ $stats['staff'] }}</div><div class="lbl">Staff</div>
        </a>
        <a href="{{ route('admin.users.index', ['role'=>'admin']) }}" class="stat-chip {{ request('role')=='admin' ? 'active' : '' }}">
            <div class="val" style="color:#a16207;">{{ $stats['admins'] }}</div><div class="lbl">Admins</div>
        </a>
        <a href="{{ route('admin.users.index', ['status'=>'1']) }}" class="stat-chip {{ request('status')==='1' ? 'active' : '' }}">
            <div class="val" style="color:#15803d;">{{ $stats['active'] }}</div><div class="lbl">Active</div>
        </a>
        <a href="{{ route('admin.users.index', ['status'=>'0']) }}" class="stat-chip {{ request('status')==='0' ? 'active' : '' }}">
            <div class="val" style="color:#dc2626;">{{ $stats['inactive'] }}</div><div class="lbl">Inactive</div>
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.users.index') }}">
        <div class="filters-bar">
            <input type="text" name="search" class="filter-input" style="width:220px;"
                placeholder="Search name, email, phone..." value="{{ request('search') }}">
            <select name="role" class="filter-input">
                <option value="">All Roles</option>
                <option value="customer" {{ request('role')=='customer'?'selected':'' }}>Guest</option>
                <option value="staff"    {{ request('role')=='staff'   ?'selected':'' }}>Staff</option>
                <option value="admin"    {{ request('role')=='admin'   ?'selected':'' }}>Admin</option>
            </select>
            <select name="status" class="filter-input">
                <option value="">All Status</option>
                <option value="1" {{ request('status')==='1'?'selected':'' }}>Active</option>
                <option value="0" {{ request('status')==='0'?'selected':'' }}>Inactive</option>
            </select>
            <button type="submit" class="btn-filter"><i class="bi bi-search me-1"></i> Search</button>
            <a href="{{ route('admin.users.index') }}" class="btn-clear">Clear</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="table-card">
        <div class="table-header">
            <h3>{{ $users->total() }} User{{ $users->total() != 1 ? 's' : '' }} Found</h3>
            <a href="{{ route('admin.users.create') }}" class="btn-add">
                <i class="bi bi-plus-lg"></i> Add User
            </a>
        </div>

        @if($users->isEmpty())
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p>No users found matching your filters.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Bookings</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    @php
                        $colors = ['#1d4ed8','#7c3aed','#15803d','#c9a84c','#dc2626','#0369a1'];
                        $color  = $colors[ord(strtolower($user->full_name[0])) % count($colors)];
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="avatar-cell" style="background:{{ $color }}22;color:{{ $color }};">
                                    {{ strtoupper(substr($user->full_name, 0, 1)) }}
                                </div>
                                <div>
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       style="font-weight:600;color:var(--navy);text-decoration:none;">
                                        {{ $user->full_name }}
                                    </a>
                                    <div style="font-size:11px;color:#94a3b8;">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--text-muted);">{{ $user->phone ?? '—' }}</td>
                        <td>
                            <span class="role-badge role-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
                        </td>
                        <td style="text-align:center;font-weight:500;">{{ $user->bookings_count }}</td>
                        <td style="font-size:12px;color:var(--text-muted);">
                            {{ $user->last_login ? $user->last_login->diffForHumans() : 'Never' }}
                        </td>
                        <td>
                            @if($user->status)
                                <span class="status-active">Active</span>
                            @else
                                <span class="status-inactive">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="{{ route('admin.users.show', $user) }}"
                                   class="btn-icon" title="View Profile"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="btn-icon" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.users.toggle', $user) }}" style="display:inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-icon warning"
                                        title="{{ $user->status ? 'Deactivate' : 'Activate' }}">
                                        <i class="bi bi-{{ $user->status ? 'toggle-on' : 'toggle-off' }}"></i>
                                    </button>
                                </form>
                                @if($user->id !== auth()->id())
                                <button onclick="confirmDelete({{ $user->id }}, '{{ addslashes($user->full_name) }}')"
                                    class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($users->hasPages())
                <div class="pagination-wrap">{{ $users->links() }}</div>
            @endif
        @endif
    </div>

</main>

{{-- Delete Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-body text-center p-4">
                <div style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px;color:#ef4444;">
                    <i class="bi bi-person-x"></i>
                </div>
                <h5 style="font-family:'Cormorant Garamond',serif;font-size:19px;margin-bottom:8px;">Delete User?</h5>
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
    document.getElementById('deleteMsg').textContent = `"${name}" and all their data will be permanently deleted.`;
    document.getElementById('deleteForm').action = `/admin/users/${id}`;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@include('admin.partials.realtime') 
</body>
</html>