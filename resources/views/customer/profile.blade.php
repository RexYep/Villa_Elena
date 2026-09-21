@extends('layouts.customer')

@section('title', 'Settings — Villa Elena')

@push('styles')
    <style>
        .main {
            max-width: 1000px;
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

        .avatar-preview-wrap {
            flex-shrink: 0;
        }

        .avatar-controls {
            min-width: 0;
        }

        .avatar-title {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        /* Nakatago ang tunay na input pero hindi `display:none`: kailangan pa
               rin siyang maabot ng keyboard at ng screen reader sa pamamagitan ng
               <label for>. */
        .avatar-input {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .avatar-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        .btn-choose,
        .btn-upload {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 10px;
            padding: 9px 14px;
            font-family: 'Jost', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-choose {
            background: var(--sand);
            border: 1px solid var(--border);
            color: var(--stone);
        }

        .btn-choose:hover {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--stone);
        }

        /* Lumilitaw lang ito kapag may napiling larawan — doon lang naman ito
               may kahulugan, at doon ito hinahanap ng mata: katabi ng larawan,
               hindi sa dulo ng form. */
        .btn-upload {
            background: var(--stone);
            border: 1px solid var(--stone);
            color: #fff;
        }

        .btn-upload:hover {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--stone);
        }

        .btn-delete-avatar {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 10px;
            padding: 9px 14px;
            font-family: 'Jost', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            background: #fff;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        .btn-delete-avatar:hover {
            background: #fef2f2;
            border-color: #f87171;
            color: #b91c1c;
        }

        .btn-link-cancel {
            background: none;
            border: none;
            color: var(--muted);
            font-family: 'Jost', sans-serif;
            font-size: 13px;
            cursor: pointer;
            text-decoration: underline;
            padding: 4px;
        }

        .avatar-chosen {
            font-size: 13px;
            color: #15803d;
            margin-top: 8px;
            overflow-wrap: anywhere;
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

        /* ── Settings shell ───────────────────────────────────────────
           Ang listahan sa kaliwa ang buong mapa ng pahina: nakikita
           agad ang bawat bagay na pwedeng baguhin, naka-pangkat, nang
           hindi kailangang buksan ang tatlong tab para malaman kung
           ano ang nasa loob. Isang bagay lang ang nasa kanan. */
        .settings-shell {
            display: grid;
            grid-template-columns: 264px minmax(0, 1fr);
            gap: 28px;
            align-items: start;
        }

        .settings-rail {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            position: sticky;
            top: calc(var(--nav-h) + 24px);
        }

        .rail-head {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
        }

        .rail-head h3 {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 600;
        }

        .rail-group {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }

        .rail-group:last-child {
            border-bottom: none;
        }

        .rail-group-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .9px;
            text-transform: uppercase;
            color: var(--muted);
            padding: 4px 20px 6px;
        }

        .rail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 20px;
            background: none;
            border: none;
            border-left: 3px solid transparent;
            font-family: 'Jost', sans-serif;
            font-size: 14px;
            color: #374151;
            text-align: left;
            cursor: pointer;
            transition: background .15s, color .15s;
        }

        .rail-item:hover {
            background: var(--sand);
            color: var(--stone);
        }

        .rail-item:focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: -2px;
        }

        .rail-item.active {
            background: #faf5ea;
            color: var(--stone);
            font-weight: 600;
            border-left-color: var(--terracotta);
        }

        .rail-item i {
            width: 18px;
            font-size: 15px;
            text-align: center;
            color: var(--muted);
        }

        .rail-item.active i {
            color: var(--terracotta);
        }

        .rail-item .chev {
            margin-left: auto;
            font-size: 12px;
            color: var(--muted);
        }

        .rail-item.is-danger i {
            color: #dc2626;
        }

        /* Ang mga bagay na iisang pindot lang — ang switch ay nasa
           mismong listahan, hindi kailangang buksan ang panel. Ang
           pangalan ay pindutan pa rin: doon nakatira ang paliwanag. */
        .rail-row {
            display: flex;
            align-items: center;
        }

        .rail-row .rail-item {
            flex: 1;
            min-width: 0;
        }

        .rail-row .rail-item span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .rail-row-switch {
            display: flex;
            align-items: center;
            padding: 0 18px 0 6px;
        }

        .settings-panel {
            display: none;
            max-width: 660px;
        }

        .settings-panel.active {
            display: block;
        }

        /* Ang huling card sa loob ng panel ay walang kasunod — ang
           24px na puwang sa ilalim nito ay nagpapalayo lang sa dulo
           ng pahina. */
        .settings-panel> :last-child {
            margin-bottom: 0;
        }

        /* Nakikita lang sa telepono, kung saan pinapalitan ng panel
           ang listahan. Sa desktop ay magkatabi silang dalawa, kaya
           walang babalikan. */
        .panel-back {
            display: none;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            padding: 6px 2px;
            margin-bottom: 12px;
            font-family: 'Jost', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
        }

        .panel-back:hover {
            color: var(--stone);
        }

        /* Isang bagay lang sa screen sa telepono. Walang lugar para sa
           dalawang hanay, at ang salansanin sila ay nangangahulugang
           lampasan ang buong listahan bago marating ang form na
           binuksan mo. Kaya ang panel ang pumapalit sa listahan, at
           ang "Settings" ang nagbabalik. */
        @media (max-width:900px) {
            .settings-shell {
                grid-template-columns: minmax(0, 1fr);
                gap: 0;
            }

            .settings-rail {
                position: static;
            }

            .settings-shell[data-view="panel"] .settings-rail {
                display: none;
            }

            .settings-shell[data-view="list"] .settings-panels {
                display: none;
            }

            .panel-back {
                display: inline-flex;
            }

            .settings-panel {
                max-width: none;
            }

            /* 44px ang pinakamaliit na target na kayang tamaan ng daliri
               nang hindi nagkakamali. */
            .rail-item {
                padding: 12px 20px;
            }
        }

        @media (max-width:560px) {

            .two-col {
                grid-template-columns: 1fr;
            }

            .avatar-row {
                gap: 14px;
            }

            /* Dating `flex-wrap: wrap` ang dalawang ito. Ang switch at ang
                   pindutang "Remove" ay bumabagsak sa ilalim ng talata at
                   naiiwang mag-isa — malayo sa bagay na kinokontrol nila. Ang
                   grid ay pumipigil sa pagbagsak nang hindi nag-o-overflow. */
            .toggle-row,
            .device-row {
                display: grid;
                grid-template-columns: 1fr auto;
                align-items: start;
                gap: 12px;
            }

            .toggle-switch {
                margin-top: 2px;
            }

            /* Ang switch sa listahan ay laging katabi ng pangalan; ang
               `margin-top` sa itaas ay para sa mga nasa loob ng panel,
               kung saan may paliwanag sa ibaba ng pangalan. */
            .rail-row-switch .toggle-switch {
                margin-top: 0;
            }
        }
    </style>
@endpush

@section('content')
    <div class="page-title">Settings</div>
    <div class="page-sub" style="margin-bottom:20px;">Manage your account information and security</div>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    {{-- Kung may bumalik na error, ang panel na pinanggalingan nito ang
         dapat bumukas — kung hindi, nasa ibang panel ang mensahe at
         hindi ito makikita ng bisita. Bawat form ay may sariling error
         bag (`ProfileController`), kaya alam natin kung alin. --}}
    @php
        $openPanel = null;
        if ($errors->twoFactor->any()) {
            $openPanel = 'two-factor';
        } elseif ($errors->updatePassword->any()) {
            $openPanel = 'password';
        } elseif ($errors->deactivate->any()) {
            $openPanel = 'deactivate';
        } elseif ($errors->getBag('default')->any()) {
            $openPanel = 'profile';
        }
    @endphp

    <div class="settings-shell" id="settingsShell" data-view="list" data-open="{{ $openPanel }}">

        {{-- ── Ang listahan ─────────────────────────────────────────── --}}
        <nav class="settings-rail" aria-label="Settings sections">
            <div class="rail-head">
                <h3>Settings</h3>
            </div>

            <div class="rail-group">
                <div class="rail-group-label">Account</div>
                <button type="button" class="rail-item" data-panel="profile">
                    <i class="bi bi-person"></i> <span>Profile</span> <span class="chev">›</span>
                </button>
                <button type="button" class="rail-item" data-panel="password">
                    <i class="bi bi-key"></i> <span>Change password</span> <span class="chev">›</span>
                </button>
                <button type="button" class="rail-item is-danger" data-panel="deactivate">
                    <i class="bi bi-exclamation-triangle"></i> <span>Deactivate account</span> <span
                        class="chev">›</span>
                </button>
            </div>

            <div class="rail-group">
                <div class="rail-group-label">Security</div>
                <div class="rail-row">
                    <button type="button" class="rail-item" data-panel="two-factor">
                        <i class="bi bi-shield-lock"></i> <span>Two-factor login</span>
                    </button>
                    <div class="rail-row-switch">
                        @if ($user->two_factor_enabled)
                            {{-- Ang pagpatay nito ay humihingi ng password, kaya
                                 hindi ito kayang tapusin ng switch mag-isa: ang
                                 panel ang nagtatanong. Pinipigilan ng
                                 `preventDefault()` ang switch na magmukhang
                                 patay na gayong buhay pa. --}}
                            <label class="toggle-switch" title="Turn off two-factor login">
                                <input type="checkbox" checked aria-label="Two-factor login is on. Turn it off."
                                    data-2fa-off>
                                <span class="toggle-slider"></span>
                            </label>
                        @else
                            <form method="POST" action="{{ route('customer.profile.2fa.toggle') }}" data-keep-panel>
                                @csrf @method('PUT')
                                <label class="toggle-switch" title="Turn on two-factor login">
                                    <input type="checkbox" aria-label="Two-factor login is off. Turn it on."
                                        onchange="this.form.submit()">
                                    <span class="toggle-slider"></span>
                                </label>
                            </form>
                        @endif
                    </div>
                </div>
                @if ($user->two_factor_enabled)
                    <button type="button" class="rail-item" data-panel="devices">
                        <i class="bi bi-laptop"></i> <span>Trusted devices</span> <span class="chev">›</span>
                    </button>
                @endif
                <button type="button" class="rail-item" data-panel="activity">
                    <i class="bi bi-clock-history"></i> <span>Login activity</span> <span class="chev">›</span>
                </button>
            </div>

            <div class="rail-group">
                <div class="rail-group-label">Notifications</div>
                <div class="rail-row">
                    <button type="button" class="rail-item" data-panel="notifications">
                        <i class="bi bi-envelope"></i> <span>Booking emails</span>
                    </button>
                    <div class="rail-row-switch">
                        <form method="POST" action="{{ route('customer.profile.email-notifications') }}"
                            data-keep-panel>
                            @csrf @method('PUT')
                            <label class="toggle-switch" title="Booking confirmation emails">
                                <input type="checkbox" name="email_notifications_enabled" value="1"
                                    aria-label="Booking confirmation emails"
                                    {{ $user->email_notifications_enabled ? 'checked' : '' }}
                                    onchange="this.form.submit()">
                                <span class="toggle-slider"></span>
                            </label>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        {{-- ── Ang binuksan ─────────────────────────────────────────── --}}
        <div class="settings-panels">
            <button type="button" class="panel-back" id="panelBack">‹ Settings</button>

            {{-- ── Profile Info ─────────────────────────────────────── --}}
            <div class="settings-panel" id="panel-profile">
                <form id="deleteAvatarForm" method="POST" action="{{ route('customer.profile.avatar.destroy') }}" style="display:none;">
                    @csrf
                    @method('DELETE')
                </form>
                <form method="POST" action="{{ route('customer.profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
            <div class="form-card">
                <div class="form-card-head">
                    <i class="bi bi-person"></i>
                    <h3>Profile Information</h3>
                </div>
                <div class="form-card-body">

                    {{-- Dating isang hubad na `<input type="file">` lang ito:
                         "Choose File / No file chosen", walang label kung para
                         saan, walang nakikitang pagbabago pagkatapos pumili, at
                         ang tanging pindutan ay ang "Save Changes" sa dulo ng
                         mahabang form. Hindi alam ng bisita kung na-upload na
                         ba o hindi. Ngayon: may pangalan ang kontrol, agad
                         nakikita ang piniling larawan sa mismong bilog, at
                         lumilitaw ang "Upload Photo" katabi mismo nito. --}}
                    <div class="avatar-row">
                        <div class="avatar-preview-wrap">
                            <img id="avatarImg" class="avatar-preview" alt="Profile photo"
                                src="{{ $user->profile_image ? $user->profile_image_url : '' }}"
                                data-original="{{ $user->profile_image ? $user->profile_image_url : '' }}"
                                @unless ($user->profile_image) hidden @endunless>
                            <div id="avatarInitial" class="avatar-preview" @if ($user->profile_image) hidden @endif>
                                {{ strtoupper(substr($user->full_name, 0, 1)) }}
                            </div>
                        </div>
                        <div class="avatar-controls">
                            <div class="avatar-title">Profile photo</div>
                            <div class="hint">JPG, PNG or WEBP · Max 3MB</div>

                            {{-- Ang totoong input ay itinatago (hindi
                                 `display:none`, para maabot pa rin ito ng
                                 keyboard at ng screen reader sa pamamagitan ng
                                 label nito). --}}
                            <input type="file" name="avatar" id="avatarInput" accept="image/png,image/jpeg,image/webp"
                                class="avatar-input">

                            <div class="avatar-actions">
                                <label for="avatarInput" class="btn-choose">
                                    <i class="bi bi-image"></i> {{ $user->profile_image ? 'Change photo' : 'Choose photo' }}
                                </label>
                                <button type="submit" class="btn-upload" id="avatarUploadBtn" hidden>
                                    <i class="bi bi-upload"></i> Upload photo
                                </button>
                                <button type="button" class="btn-link-cancel" id="avatarCancelBtn" hidden>Cancel</button>
                                @if ($user->profile_image)
                                    <button type="submit" form="deleteAvatarForm" class="btn-delete-avatar" id="avatarDeleteBtn"
                                        onclick="return confirm('Are you sure you want to delete your profile photo?');">
                                        <i class="bi bi-trash3"></i> Delete photo
                                    </button>
                                @endif
                            </div>

                            <div class="avatar-chosen" id="avatarChosen" hidden></div>
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

                    <div class="mb-16">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                            value="{{ old('phone', $user->phone) }}" placeholder="09XX-XXX-XXXX" required>
                        @error('phone')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-16">
                        <label class="form-label">Home Address</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $user->address) }}</textarea>
                    </div>

                    <button type="submit" class="btn-submit"><i class="bi bi-check-circle"></i> Save Changes</button>
                </div>
            </div>
        </form>
            </div>

            {{-- ── Change Password ──────────────────────────────────── --}}
            <div class="settings-panel" id="panel-password">
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
            </div>

            {{-- ── Deactivate Account ───────────────────────────────── --}}
            <div class="settings-panel" id="panel-deactivate">
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

            {{-- ── Booking emails ───────────────────────────────────── --}}
            <div class="settings-panel" id="panel-notifications">
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

            {{-- ── Two-Factor Authentication ────────────────────────── --}}
            <div class="settings-panel" id="panel-two-factor">
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
            </div>

            {{-- ── Trusted Devices ──────────────────────────────────── --}}
            @if ($user->two_factor_enabled)
                <div class="settings-panel" id="panel-devices">
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
                </div>
            @endif

            {{-- ── Recent Login Activity ────────────────────────────── --}}
            <div class="settings-panel" id="panel-activity">
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

        </div>{{-- .settings-panels --}}
    </div>{{-- .settings-shell --}}
@endsection

@push('scripts')
    <script>
        // ── Settings navigation ──────────────────────────────────────────
        // Ang `location.hash` ang nag-iisang nagsasabi kung ano ang nakabukas.
        // Dahil doon, ang likod na pindutan ng browser at ang pag-refresh ay
        // parehong bumabalik sa parehong lugar nang walang dagdag na code —
        // hindi kayang gawin iyon ng isang variable sa loob ng script.
        (function() {
            const shell = document.getElementById('settingsShell');
            if (!shell) return;

            const items = Array.from(shell.querySelectorAll('.rail-item[data-panel]'));
            const keys = items.map(el => el.dataset.panel);
            const FALLBACK = 'profile';
            const LIST = 'settings';
            const STORE = 've-settings-panel';

            // Hindi lahat ng form ay nasa loob ng panel (nasa listahan ang
            // dalawang switch), at ang sagot ng server ay isang redirect na
            // hindi nagdadala ng `#hash`. Kaya inaalala kung saan tayo
            // nanggaling, at minsan lang itong binabasa.
            function remember(key) {
                try {
                    sessionStorage.setItem(STORE, key);
                } catch (e) {
                    /* private mode — hindi mahalaga, babalik lang sa Profile */
                }
            }

            function recall() {
                try {
                    const key = sessionStorage.getItem(STORE);
                    sessionStorage.removeItem(STORE);
                    return key;
                } catch (e) {
                    return null;
                }
            }

            function show(key, updateHash) {
                if (keys.indexOf(key) === -1) key = FALLBACK;

                shell.querySelectorAll('.settings-panel').forEach(panel => {
                    panel.classList.toggle('active', panel.id === 'panel-' + key);
                });
                items.forEach(el => {
                    const on = el.dataset.panel === key;
                    el.classList.toggle('active', on);
                    if (on) {
                        el.setAttribute('aria-current', 'true');
                    } else {
                        el.removeAttribute('aria-current');
                    }
                });

                shell.dataset.view = 'panel';
                if (updateHash && location.hash.slice(1) !== key) location.hash = key;
            }

            function fromHash() {
                const key = decodeURIComponent(location.hash.slice(1));

                if (!key || key === LIST) {
                    // Nananatiling pili ang isang panel para sa desktop, kung
                    // saan laging may nasa kanan; sa telepono ay ang listahan
                    // ang nasa harap.
                    show(FALLBACK, false);
                    shell.dataset.view = 'list';
                    return;
                }
                show(key, false);
            }

            items.forEach(el => {
                el.addEventListener('click', () => show(el.dataset.panel, true));
            });

            const back = document.getElementById('panelBack');
            if (back) back.addEventListener('click', () => {
                location.hash = LIST;
            });

            // Ang switch ng 2FA ay hindi kayang patayin ang sarili: password
            // muna. Ang panel ang humihingi nito, kaya doon tayo dinadala —
            // at bukas na agad ang form, dahil iyon ang hiniling ng pindot.
            const offSwitch = shell.querySelector('[data-2fa-off]');
            if (offSwitch) offSwitch.addEventListener('click', function(e) {
                e.preventDefault();
                show('two-factor', true);
                const form = document.getElementById('disable2faForm');
                if (form) {
                    form.style.display = 'block';
                    const field = form.querySelector('input[type="password"]');
                    if (field) field.focus();
                }
            });

            shell.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', () => {
                    const panel = form.closest('.settings-panel');
                    if (panel) {
                        remember(panel.id.replace('panel-', ''));
                    } else if (form.hasAttribute('data-keep-panel')) {
                        // Isang switch sa listahan — doon din tayo babalik.
                        remember(LIST);
                    }
                });
            });

            window.addEventListener('hashchange', fromHash);

            const forced = shell.dataset.open;
            const recalled = recall();

            if (!location.hash && forced) {
                show(forced, true); // may error — dapat itong makita agad
            } else if (!location.hash && recalled) {
                if (recalled === LIST) {
                    fromHash();
                } else {
                    show(recalled, true);
                }
            } else {
                fromHash();
            }
        })();

        // ── Profile photo ────────────────────────────────────────────────
        // Ang pagpili ng file ay dapat may nakikitang kasagutan agad: ang
        // bagong larawan sa bilog, ang pangalan ng file, at ang pindutang
        // talagang mag-a-upload. Kung wala ang tatlong ito, ang tanging
        // senyas na may nangyari ay ang "No file chosen" na nagbago — sa
        // isang maliit na kahon na madaling hindi mapansin.
        (function() {
            const input = document.getElementById('avatarInput');
            if (!input) return;

            const img = document.getElementById('avatarImg');
            const initial = document.getElementById('avatarInitial');
            const chosen = document.getElementById('avatarChosen');
            const uploadBtn = document.getElementById('avatarUploadBtn');
            const cancelBtn = document.getElementById('avatarCancelBtn');
            const deleteBtn = document.getElementById('avatarDeleteBtn');
            const originalSrc = img.dataset.original || '';
            let objectUrl = null;

            function restore() {
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }
                if (originalSrc) {
                    img.src = originalSrc;
                    img.hidden = false;
                    initial.hidden = true;
                    if (deleteBtn) deleteBtn.hidden = false;
                } else {
                    img.removeAttribute('src');
                    img.hidden = true;
                    initial.hidden = false;
                    if (deleteBtn) deleteBtn.hidden = true;
                }
                chosen.hidden = true;
                chosen.textContent = '';
                uploadBtn.hidden = true;
                cancelBtn.hidden = true;
            }

            input.addEventListener('change', function() {
                const file = this.files && this.files[0];
                if (!file) {
                    restore();
                    return;
                }

                if (objectUrl) URL.revokeObjectURL(objectUrl);
                objectUrl = URL.createObjectURL(file);
                img.src = objectUrl;
                img.hidden = false;
                initial.hidden = true;

                chosen.textContent = 'Upload ' + file.name;
                chosen.hidden = false;
                uploadBtn.hidden = false;
                cancelBtn.hidden = false;
                if (deleteBtn) deleteBtn.hidden = true;
            });

            cancelBtn.addEventListener('click', function() {
                input.value = '';
                restore();
            });
        })();
    </script>
@endpush

