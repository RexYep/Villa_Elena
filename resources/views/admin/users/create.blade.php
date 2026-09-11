@extends('layouts.admin')

@section('title', (isset($user) ? 'Edit' : 'Add') . ' User — Villa Elena Admin')
@section('page-title', isset($user) ? 'Edit User' : 'Add New User')
@section('page-subtitle', isset($user) ? 'Update profile for ' . $user->full_name : 'Create a new guest, staff, or admin
    account')

    @push('styles')
        <style>
            .form-wrapper {
                max-width: 720px;
            }

            /* admin.css collapses every .two-col below a 900px *viewport*, but
               this form is capped at 720px and has no sidebar to compete with —
               at 768px and 900px it was already at its full 720px width and still
               stacked every pair into one 670px column, while at 901px the same
               720px form showed them side by side at 327px. Same container, two
               layouts, 254px of extra page. Keyed on 561px like the booking,
               property and promotions forms. Two classes, so it outranks
               admin.css's single-class rule in either stylesheet order. */
            @media (min-width: 561px) {
                .form-wrapper .two-col {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            /* When editing, the breadcrumb ends in "Edit: <full name>", and a
               breadcrumb item is a flex item whose floor is its own min-content.
               A name with no break points (entered as one token, or an email typed
               into the name field) held that floor and ran off the row — a
               47-character name reached R437 against a 320px viewport, clipped by
               body{overflow-x:hidden}. Ordinary names with spaces wrap fine. */
            .breadcrumb-row .current {
                min-width: 0;
                overflow-wrap: anywhere;
            }

            /* The show/hide password button was 41x43px and Cancel was a 42x20px
               text link beside Create Account. Keyed on pointer type, not width:
               only a finger needs the larger target. */
            @media (hover: none) and (pointer: coarse) {

                .form-wrapper .form-control,
                .form-wrapper .form-select {
                    min-height: 44px;
                }

                .form-wrapper .input-group .btn {
                    min-width: 44px;
                    min-height: 44px;
                }

                .form-wrapper .btn-cancel-link {
                    display: inline-flex;
                    align-items: center;
                    min-height: 44px;
                    padding: 0 8px;
                }
            }
        </style>
    @endpush

@section('content')

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.users.index') }}">Users</a>
        <span class="sep">›</span>
        <span class="current">{{ isset($user) ? 'Edit: ' . $user->full_name : 'Add New' }}</span>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            @foreach ($errors->all() as $error)
                {{ $error }}.
            @endforeach
        </div>
    @endif

    <div class="form-wrapper">
        <form method="POST" action="{{ isset($user) ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if (isset($user))
                @method('PUT')
            @endif

            {{-- Basic Info --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon tag-cyan"><i class="bi bi-person"></i></div>
                    <h3>Basic Information</h3>
                </div>
                <div class="form-card-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror"
                            value="{{ old('full_name', $user->full_name ?? '') }}" placeholder="Juan dela Cruz" required>
                        @error('full_name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="two-col mb-3">
                        <div>
                            <label class="form-label">Email Address <span class="req">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $user->email ?? '') }}" placeholder="guest@example.com" required>
                            @error('email')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label">Phone Number <span class="req">*</span></label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                value="{{ old('phone', $user->phone ?? '') }}" placeholder="09XX-XXX-XXXX" required>
                            @error('phone')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Role <span class="req">*</span></label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="customer"
                                {{ old('role', $user->role ?? 'customer') == 'customer' ? 'selected' : '' }}>👤 Guest
                                (Customer)</option>
                            <option value="staff" {{ old('role', $user->role ?? '') == 'staff' ? 'selected' : '' }}>🏷️
                                Staff</option>
                            <option value="admin" {{ old('role', $user->role ?? '') == 'admin' ? 'selected' : '' }}>⚙️
                                Admin</option>
                        </select>
                        @error('role')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- ID & Address --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon tag-green"><i class="bi bi-card-text"></i></div>
                    <h3>ID & Address</h3>
                </div>
                <div class="form-card-body">
                    <div class="two-col mb-3">
                        <div>
                            <label class="form-label">ID Type</label>
                            <select name="id_type" class="form-select">
                                <option value="">None provided</option>
                                @foreach (["Driver's License", 'Passport', 'SSS ID', 'PhilHealth ID', "Voter's ID", 'National ID', 'PRC ID', 'Postal ID'] as $idType)
                                    <option value="{{ $idType }}"
                                        {{ old('id_type', $user->id_type ?? '') == $idType ? 'selected' : '' }}>
                                        {{ $idType }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">ID Number</label>
                            <input type="text" name="id_number" class="form-control"
                                value="{{ old('id_number', $user->id_number ?? '') }}" placeholder="ID number">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Home Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Full home address">{{ old('address', $user->address ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Password --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon tag-purple"><i class="bi bi-shield-lock"></i></div>
                    <h3>{{ isset($user) ? 'Change Password' : 'Set Password' }}</h3>
                </div>
                <div class="form-card-body">
                    @if (isset($user))
                        <span class="hint" style="margin-bottom:14px;display:block;">
                            Leave blank to keep the current password.
                        </span>
                    @endif
                    <div class="two-col">
                        <div>
                            <label class="form-label">Password {{ !isset($user) ? '*' : '' }}</label>
                            <div class="input-group">
                                <input type="password" id="password" name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Min. 8 characters" {{ !isset($user) ? 'required' : '' }}>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePw('password',this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                @error('password')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Confirm Password {{ !isset($user) ? '*' : '' }}</label>
                            <div class="input-group">
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                    class="form-control" placeholder="Repeat password"
                                    {{ !isset($user) ? 'required' : '' }}>
                                <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePw('password_confirmation',this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div style="display:flex;align-items:center;">
                <button type="submit" class="btn-submit">
                    <i class="bi bi-{{ isset($user) ? 'check-circle' : 'person-plus' }} me-2"></i>
                    {{ isset($user) ? 'Save Changes' : 'Create Account' }}
                </button>
                <a href="{{ isset($user) ? route('admin.users.show', $user) : route('admin.users.index') }}"
                    class="btn-cancel-link">Cancel</a>
            </div>

        </form>
    </div>

@endsection

@push('scripts')
    <script>
        function togglePw(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            input.type = input.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        }
    </script>
@endpush

