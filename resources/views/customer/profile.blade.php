@extends('layouts.customer')

@section('title', 'My Profile — Villa Elena')

@push('styles')
    <style>
        .main {
            max-width: 640px;
        }

        .page-title {
            font-weight: 700;
        }

        .form-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .form-card-head {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card-head h3 {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            font-weight: 600;
        }

        .form-card-body {
            padding: 22px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            display: block;
            margin-bottom: 8px;
            letter-spacing: .2px;
        }

        .form-control,
        .form-select {
            padding: 11px 14px;
        }

        .mb-16 {
            margin-bottom: 16px;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .is-invalid {
            border-color: #dc2626 !important;
        }

        .field-error {
            font-size: 13px;
            color: #dc2626;
            margin-top: 4px;
        }

        .hint {
            font-size: 14px;
            color: var(--muted);
        }

        .avatar-row {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 20px;
        }

        .avatar-preview {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            font-weight: 600;
            color: var(--stone);
            flex-shrink: 0;
        }

        .btn-submit {
            background: var(--stone);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Jost', sans-serif;
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--gold);
            color: var(--stone);
        }

        .danger-card {
            border: 1px solid #fecaca;
            background: #fef2f2;
            border-radius: 16px;
            overflow: hidden;
        }

        .danger-card .form-card-head {
            border-bottom: 1px solid #fecaca;
        }

        .btn-danger {
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Jost', sans-serif;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .toggle-switch {
            position: relative;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: #d1d5db;
            border-radius: 24px;
            cursor: pointer;
            transition: .2s;
        }

        .toggle-slider:before {
            content: "";
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            top: 3px;
            background: #fff;
            border-radius: 50%;
            transition: .2s;
        }

        .toggle-switch input:checked+.toggle-slider {
            background: var(--stone);
        }

        .toggle-switch input:checked+.toggle-slider:before {
            transform: translateX(20px);
        }

        .device-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f4efe6;
        }

        .device-row:last-child {
            border-bottom: none;
        }

        .device-name {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .device-meta {
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px;
        }

        .this-device-tag {
            background: #dcfce7;
            color: #15803d;
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 8px;
            margin-left: 6px;
        }

        .btn-remove-device {
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            padding: 4px 8px;
        }

        .btn-remove-device:hover {
            color: #dc2626;
        }

        .empty-hint {
            font-size: 14px;
            color: var(--muted);
            padding: 8px 0;
        }

        .profile-tabs {
            display: flex;
            gap: 6px;
            background: var(--sand);
            border-radius: 12px;
            padding: 5px;
            margin-bottom: 24px;
        }

        .profile-tab-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px 12px;
            border: none;
            border-radius: 9px;
            background: none;
            color: var(--muted);
            font-family: 'Jost', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap;
        }

        .profile-tab-btn.active {
            background: #fff;
            color: var(--stone);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
        }

        .profile-tab {
            display: none;
        }

        .profile-tab.active {
            display: block;
        }

        @media (max-width:560px) {
            .profile-tab-btn span {
                display: none;
            }

            .two-col {
                grid-template-columns: 1fr;
            }

            .avatar-row {
                flex-wrap: wrap;
            }

            .device-row {
                flex-wrap: wrap;
            }

            .toggle-row {
                flex-wrap: wrap;
            }
        }
    </style>
@endpush

@section('content')
    <div class="page-title">My Profile</div>
    <div class="page-sub" style="margin-bottom:20px;">Manage your account information and security</div>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    @php $securityTabActive = $errors->twoFactor->any(); @endphp

    <div class="profile-tabs">
        <button type="button" class="profile-tab-btn {{ $securityTabActive ? '' : 'active' }}"
            onclick="showProfileTab('account', this)">
            <i class="bi bi-person"></i> <span>Account</span>
        </button>
        <button type="button" class="profile-tab-btn {{ $securityTabActive ? 'active' : '' }}"
            onclick="showProfileTab('security', this)">
            <i class="bi bi-shield-lock"></i> <span>Security</span>
        </button>
        <button type="button" class="profile-tab-btn" onclick="showProfileTab('notifications', this)">
            <i class="bi bi-bell"></i> <span>Notifications</span>
        </button>
    </div>

    {{-- ═══════════════════════ ACCOUNT TAB ═══════════════════════ --}}
    <div class="profile-tab {{ $securityTabActive ? '' : 'active' }}" id="ptab-account">

        {{-- ── Profile Info ─────────────────────────────────────────── --}}
        <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="form-card">
                <div class="form-card-head">
                    <i class="bi bi-person"></i>
                    <h3>Profile Information</h3>
                </div>
                <div class="form-card-body">

                    <div class="avatar-row">
                        @if ($user->profile_image)
                            <img src="{{ $user->profile_image_url }}" alt="Avatar" class="avatar-preview">
                        @else
                            <div class="avatar-preview">{{ strtoupper(substr($user->full_name, 0, 1)) }}</div>
                        @endif
                        <div>
                            <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp"
                                class="form-control @error('avatar') is-invalid @enderror">
                            <div class="hint" style="margin-top:6px;">JPG, PNG or WEBP. Max 3MB.</div>
                            @error('avatar')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-16">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror"
                            value="{{ old('full_name', $user->full_name) }}" required>
                        @error('full_name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-16">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" value="{{ $user->email }}" disabled>
                        <div class="hint" style="margin-top:6px;">Contact the resort to change your email address.</div>
                    </div>

                    <div class="two-col mb-16">
                        <div>
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                value="{{ old('phone', $user->phone) }}" placeholder="09XX-XXX-XXXX" required>
                            @error('phone')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label">ID Type</label>
                            <select name="id_type" class="form-select">
                                <option value="">None provided</option>
                                @foreach (["Driver's License", 'Passport', 'SSS ID', 'PhilHealth ID', "Voter's ID", 'National ID', 'PRC ID', 'Postal ID'] as $idType)
                                    <option value="{{ $idType }}"
                                        {{ old('id_type', $user->id_type) == $idType ? 'selected' : '' }}>
                                        {{ $idType }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-16">
                        <label class="form-label">ID Number</label>
                        <input type="text" name="id_number" class="form-control"
                            value="{{ old('id_number', $user->id_number) }}">
                    </div>

                    <div class="mb-16">
                        <label class="form-label">Home Address</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $user->address) }}</textarea>
                    </div>

                    <button type="submit" class="btn-submit"><i class="bi bi-check-circle"></i> Save Changes</button>
                </div>
            </div>
        </form>

        {{-- ── Change Password ──────────────────────────────────────── --}}
        <form method="POST" action="{{ route('customer.profile.password') }}">
            @csrf
            @method('PUT')
            <div class="form-card">
                <div class="form-card-head">
                    <i class="bi bi-shield-lock"></i>
                    <h3>Change Password</h3>
                </div>
                <div class="form-card-body">
                    <div class="mb-16">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password"
                            class="form-control @error('current_password', 'updatePassword') is-invalid @enderror">
                        @error('current_password', 'updatePassword')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="two-col mb-16">
                        <div>
                            <label class="form-label">New Password</label>
                            <input type="password" name="password"
                                class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                                placeholder="Min. 8 characters">
                            @error('password', 'updatePassword')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="password_confirmation" class="form-control"
                                placeholder="Repeat password">
                        </div>
                    </div>
                    <button type="submit" class="btn-submit"><i class="bi bi-shield-check"></i> Update Password</button>
                </div>
            </div>
        </form>

        {{-- ── Deactivate Account ───────────────────────────────────── --}}
        <form method="POST" action="{{ route('customer.profile.deactivate') }}"
            onsubmit="return confirm('Are you sure you want to deactivate your account? You will be logged out and unable to log back in until an admin reactivates it.');">
            @csrf
            @method('DELETE')
            <div class="danger-card">
                <div class="form-card-head">
                    <i class="bi bi-exclamation-triangle" style="color:#dc2626;"></i>
                    <h3 style="color:#dc2626;">Deactivate Account</h3>
                </div>
                <div class="form-card-body">
                    <p class="hint" style="margin-bottom:16px;">
                        Deactivating logs you out immediately and blocks further logins. Your existing bookings,
                        payments, and reviews are kept — contact the resort to reactivate your account.
                    </p>
                    <div class="mb-16">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password"
                            class="form-control @error('password', 'deactivate') is-invalid @enderror">
                        @error('password', 'deactivate')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn-danger"><i class="bi bi-power"></i> Deactivate My Account</button>
                </div>
            </div>
        </form>

    </div>
    {{-- ═══════════════════════ END ACCOUNT TAB ═══════════════════════ --}}

    {{-- ═══════════════════════ NOTIFICATIONS TAB ═══════════════════════ --}}
    <div class="profile-tab" id="ptab-notifications">

        {{-- ── Notifications ─────────────────────────────────────────── --}}
        <div class="form-card">
            <div class="form-card-head">
                <i class="bi bi-envelope"></i>
                <h3>Notifications</h3>
            </div>
            <div class="form-card-body">
                <form method="POST" action="{{ route('customer.profile.email-notifications') }}">
                    @csrf @method('PUT')
                    <div class="toggle-row" style="margin-bottom:0;">
                        <div>
                            <div class="device-name">Booking confirmation emails</div>
                            <div class="hint" style="margin-top:3px;">
                                You will receive an email booking confirmation and a promo offer.
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications_enabled" value="1"
                                {{ $user->email_notifications_enabled ? 'checked' : '' }} onchange="this.form.submit()">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </form>
            </div>
        </div>

    </div>
    {{-- ═══════════════════════ END NOTIFICATIONS TAB ═══════════════════════ --}}

    {{-- ═══════════════════════ SECURITY TAB ═══════════════════════ --}}
    <div class="profile-tab {{ $securityTabActive ? 'active' : '' }}" id="ptab-security">

        {{-- ── Security: 2FA + Trusted Devices + Login Activity ────────── --}}
        <div class="form-card">
            <div class="form-card-head">
                <i class="bi bi-fingerprint"></i>
                <h3>Two-Factor Authentication</h3>
            </div>
            <div class="form-card-body">
                <div class="toggle-row" style="margin-bottom:{{ $user->two_factor_enabled ? '16px' : '0' }};">
                    <div>
                        <div class="device-name">Verify new devices by email</div>
                        <div class="hint" style="margin-top:3px;">
                            When enabled, you will first need to verify the new device using a code sent to your email
                            before you can log in on it. You won't need to do this again on the same device.
                        </div>
                    </div>

                    @if ($user->two_factor_enabled)
                        <label class="toggle-switch">
                            <input type="checkbox" checked
                                onclick="event.preventDefault(); document.getElementById('disable2faForm').style.display='block';">
                            <span class="toggle-slider"></span>
                        </label>
                    @else
                        <form method="POST" action="{{ route('customer.profile.2fa.toggle') }}">
                            @csrf @method('PUT')
                            <label class="toggle-switch">
                                <input type="checkbox" onchange="this.form.submit()">
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    @endif
                </div>

                @if ($user->two_factor_enabled)
                    <form method="POST" action="{{ route('customer.profile.2fa.toggle') }}" id="disable2faForm"
                        style="{{ $errors->twoFactor->any() ? '' : 'display:none;' }}background:#f9f5ee;border-radius:10px;padding:14px;">
                        @csrf @method('PUT')
                        <label class="form-label">Enter your password to turn this off</label>
                        <input type="password" name="password"
                            class="form-control @error('password', 'twoFactor') is-invalid @enderror"
                            style="margin-bottom:10px;">
                        @error('password', 'twoFactor')
                            <div class="field-error" style="margin-bottom:8px;">{{ $message }}</div>
                        @enderror
                        <button type="submit" class="btn-danger" style="padding:9px 16px;font-size:13px;">Turn
                            Off</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($user->two_factor_enabled)
            <div class="form-card">
                <div class="form-card-head">
                    <i class="bi bi-laptop"></i>
                    <h3>Trusted Devices</h3>
                </div>
                <div class="form-card-body">
                    @forelse($trustedDevices as $device)
                        <div class="device-row">
                            <div>
                                <div class="device-name">
                                    {{ $device->device_label ?: 'Unknown device' }}
                                    @if ($device->token === $currentDeviceToken)
                                        <span class="this-device-tag">This device</span>
                                    @endif
                                </div>
                                <div class="device-meta">
                                    {{ $device->ip_address }} · Last used {{ $device->last_used_at?->diffForHumans() }}
                                </div>
                            </div>
                            <form method="POST" action="{{ route('customer.profile.devices.destroy', $device) }}"
                                onsubmit="return confirm('Remove this device? It will need to verify again next time it logs in.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-remove-device"><i class="bi bi-trash"></i>
                                    Remove</button>
                            </form>
                        </div>
                    @empty
                        <div class="empty-hint">No trusted devices yet.</div>
                    @endforelse
                </div>
            </div>
        @endif

        <div class="form-card">
            <div class="form-card-head">
                <i class="bi bi-clock-history"></i>
                <h3>Recent Login Activity</h3>
            </div>
            <div class="form-card-body">
                @forelse($loginActivities as $activity)
                    <div class="device-row">
                        <div>
                            <div class="device-name">{{ $activity->device_label ?: 'Unknown device' }}</div>
                            <div class="device-meta">
                                {{ $activity->ip_address }} · {{ $activity->created_at->diffForHumans() }}
                                @if ($activity->via_new_device_otp)
                                    · verified via email code
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="empty-hint">No login activity recorded yet.</div>
                @endforelse
            </div>
        </div>

    </div>
    {{-- ═══════════════════════ END SECURITY TAB ═══════════════════════ --}}
@endsection

@push('scripts')
    <script>
        function showProfileTab(name, btn) {
            document.querySelectorAll('.profile-tab').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.profile-tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('ptab-' + name).classList.add('active');
            btn.classList.add('active');
        }
    </script>
@endpush

