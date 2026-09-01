@extends('layouts.admin')

@section('title', 'Guests & Staff — Villa Elena Admin')
@section('page-title', 'Guests & Staff')
@section('page-subtitle', 'Manage all registered accounts and user profiles')

@push('styles')
    <style>
        .stats-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-chip {
            background: var(--cream);
            border-radius: 10px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            display: block;
        }

        .stat-chip:hover {
            border-color: var(--terracotta);
            box-shadow: 0 2px 8px rgba(44, 36, 22, .09);
        }

        .stat-chip.active {
            background: var(--terracotta);
            border-color: var(--terracotta);
        }

        .stat-chip .val {
            font-family: 'Cormorant Garamond', serif;
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }

        .stat-chip.active .val {
            color: #fff !important;
        }

        .stat-chip .lbl {
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
        }

        .stat-chip.active .lbl {
            color: rgba(255, 255, 255, 0.75);
        }

        .filters-bar {
            background: var(--cream);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 14px 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-input {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            background: #fff;
            color: var(--text-main);
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--terracotta);
        }

        .btn-filter {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: background .2s;
        }

        .btn-filter:hover {
            background: var(--gold);
        }

        .btn-clear {
            background: var(--sand);
            color: var(--muted);
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 13px;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
        }

        .btn-add {
            display: flex;
            align-items: center;
            gap: 7px;
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-add:hover {
            background: var(--gold);
            color: #fff;
        }

        .avatar-cell {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }

        /* Role / status badges — semantic, unchanged */
        .role-admin {
            background: var(--tag-amber-bg);
            color: var(--tag-amber-fg);
        }

        .role-staff {
            background: var(--tag-blue-bg);
            color: var(--tag-blue-fg);
        }

        .role-customer {
            background: var(--tag-purple-bg);
            color: var(--tag-purple-fg);
        }

        .status-active {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .status-inactive {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
        }

        .btn-icon.warning:hover {
            background: #f59e0b;
            border-color: #f59e0b;
            color: #fff;
        }

        @media (max-width: 900px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 560px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
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
        <a href="{{ route('admin.users.index') }}"
            class="stat-chip {{ !request('role') && !request()->has('status') ? 'active' : '' }}">
            <div class="val">{{ $stats['total'] }}</div>
            <div class="lbl">All Users</div>
        </a>
        <a href="{{ route('admin.users.index', ['role' => 'customer']) }}"
            class="stat-chip {{ request('role') == 'customer' ? 'active' : '' }}">
            <div class="val" style="color:#7c3aed;">{{ $stats['customers'] }}</div>
            <div class="lbl">Guests</div>
        </a>
        <a href="{{ route('admin.users.index', ['role' => 'staff']) }}"
            class="stat-chip {{ request('role') == 'staff' ? 'active' : '' }}">
            <div class="val" style="color:#1d4ed8;">{{ $stats['staff'] }}</div>
            <div class="lbl">Staff</div>
        </a>
        <a href="{{ route('admin.users.index', ['role' => 'admin']) }}"
            class="stat-chip {{ request('role') == 'admin' ? 'active' : '' }}">
            <div class="val" style="color:#a16207;">{{ $stats['admins'] }}</div>
            <div class="lbl">Admins</div>
        </a>
        <a href="{{ route('admin.users.index', ['status' => '1']) }}"
            class="stat-chip {{ request('status') === '1' ? 'active' : '' }}">
            <div class="val" style="color:#15803d;">{{ $stats['active'] }}</div>
            <div class="lbl">Active</div>
        </a>
        <a href="{{ route('admin.users.index', ['status' => '0']) }}"
            class="stat-chip {{ request('status') === '0' ? 'active' : '' }}">
            <div class="val" style="color:#dc2626;">{{ $stats['inactive'] }}</div>
            <div class="lbl">Inactive</div>
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.users.index') }}">
        <div class="filters-bar">
            <input type="text" name="search" class="filter-input" style="width:220px;"
                placeholder="Search name, email, phone..." value="{{ request('search') }}">
            <select name="role" class="filter-input">
                <option value="">All Roles</option>
                <option value="customer" {{ request('role') == 'customer' ? 'selected' : '' }}>Guest</option>
                <option value="staff" {{ request('role') == 'staff' ? 'selected' : '' }}>Staff</option>
                <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin</option>
            </select>
            <select name="status" class="filter-input">
                <option value="">All Status</option>
                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
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

        @if ($users->isEmpty())
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
                    @foreach ($users as $user)
                        @php
                            $colors = ['#1d4ed8', '#7c3aed', '#15803d', '#c9a84c', '#dc2626', '#0369a1'];
                            $color = $colors[ord(strtolower($user->full_name[0])) % count($colors)];
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex-gap-10">
                                    <div class="avatar-cell"
                                        style="background:{{ $color }}22;color:{{ $color }};">
                                        {{ strtoupper(substr($user->full_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.users.show', $user) }}"
                                            style="font-weight:600;color:var(--stone);text-decoration:none;">
                                            {{ $user->full_name }}
                                        </a>
                                        <div style="font-size: 13px;color:#94a3b8;">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-muted-theme">{{ $user->phone ?? '—' }}</td>
                            <td>
                                <span class="role-badge role-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
                            </td>
                            <td style="text-align:center;font-weight:500;">{{ $user->bookings_count }}</td>
                            <td class="text-muted-theme" style="font-size: 14px;">
                                {{ $user->last_login ? $user->last_login->diffForHumans() : 'Never' }}
                            </td>
                            <td>
                                @if ($user->status)
                                    <span class="status-active">Active</span>
                                @else
                                    <span class="status-inactive">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="{{ route('admin.users.show', $user) }}" class="btn-icon"
                                        title="View Profile"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn-icon" title="Edit"><i
                                            class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('admin.users.toggle', $user) }}"
                                        style="display:inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn-icon warning"
                                            title="{{ $user->status ? 'Deactivate' : 'Activate' }}">
                                            <i class="bi bi-{{ $user->status ? 'toggle-on' : 'toggle-off' }}"></i>
                                        </button>
                                    </form>
                                    @if ($user->id !== Auth::id())
                                        <button
                                            onclick="confirmDelete({{ $user->id }}, '{{ addslashes($user->full_name) }}')"
                                            class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($users->hasPages())
                <div class="pagination-wrap">{{ $users->links() }}</div>
            @endif
        @endif
    </div>

@endsection

@section('modals')
    {{-- Delete Modal --}}
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content" style="border-radius:14px;border:none;">
                <div class="modal-body text-center p-4">
                    <div
                        style="width:52px;height:52px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:22px;color:#ef4444;">
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
@endsection

@push('scripts')
    <script>
        const deleteUserUrlTemplate = '{{ route('admin.users.destroy', ['user' => '__ID__']) }}';

        function confirmDelete(id, name) {
            document.getElementById('deleteMsg').textContent = `"${name}" and all their data will be permanently deleted.`;
            document.getElementById('deleteForm').action = deleteUserUrlTemplate.replace('__ID__', id);
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
@endpush

