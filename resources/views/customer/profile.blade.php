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

        .form-card-head h2 {
            margin: 0;
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 600;
            line-height: 1.3;
        }

        .form-card-body {
            padding: 22px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: var(--stone);
            display: block;
            margin-bottom: 8px;
            letter-spacing: .2px;
        }

        .form-control,
        .form-select {
            padding: 11px 14px;
        }

        /* Bootstrap paints a disabled field cool grey, off the palette. */
        .form-control:disabled {
            background: var(--sand);
            color: var(--muted);
            opacity: 1;
        }

        /* !important like the base.css spacing helpers: `.hint` below resets
           its own margin and would otherwise win. */
        .mb-16 {
            margin-bottom: 16px !important;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .is-invalid {
            border-color: var(--tag-red-fg) !important;
        }

        .hint {
            font-size: 14px;
            color: var(--muted);
            margin: 0;
        }

        .hint a {
            color: var(--stone);
            font-weight: 600;
        }

        .hint-below {
            margin-top: 6px;
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
            color: var(--stone);
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

        /* The input above is 1px and invisible, so its keyboard focus is
           drawn on the button that stands in for it. */
        .avatar-input:focus-visible~.avatar-actions .btn-choose {
            outline: 2px solid var(--stone);
            outline-offset: 2px;
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
            font-family: var(--font-body);
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
            background: var(--btn-primary);
            border: 1px solid var(--stone);
            color: #fff;
        }

        .btn-upload:hover {
            background: var(--btn-primary-hover);
            border-color: var(--btn-primary-hover);
            color: #fff;
        }

        .btn-delete-avatar {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 10px;
            padding: 9px 14px;
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            background: #fff;
            border: 1px solid var(--state-red-line);
            color: var(--tag-red-fg);
        }

        .btn-delete-avatar:hover {
            background: var(--state-red-soft);
            border-color: var(--state-red-edge);
            color: var(--tag-red-fg);
        }

        .btn-link-cancel {
            background: none;
            border: none;
            color: var(--muted);
            font-family: var(--font-body);
            font-size: 13px;
            cursor: pointer;
            text-decoration: underline;
            padding: 4px;
        }

        .avatar-chosen {
            font-size: 13px;
            color: var(--tag-green-fg);
            margin-top: 8px;
            overflow-wrap: anywhere;
        }

        .btn-submit {
            background: var(--btn-primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-body);
            transition: all .2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--btn-primary-hover);
            color: #fff;
        }

        .danger-card {
            border: 1px solid var(--state-red-line);
            background: var(--state-red-soft);
            border-radius: 16px;
            overflow: hidden;
        }

        .danger-card .form-card-head {
            border-bottom: 1px solid var(--state-red-line);
        }

        .danger-card a {
            color: var(--stone);
            font-weight: 600;
        }

        .btn-danger {
            background: var(--state-red);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-body);
        }

        .btn-danger:hover {
            background: var(--tag-red-fg);
            color: #fff;
        }

        .btn-danger.is-small {
            padding: 9px 16px;
            font-size: 13px;
        }

        .delete-facts {
            margin: 0 0 16px;
            padding-left: 20px;
            font-size: 14px;
            color: var(--muted);
        }

        .delete-facts li+li {
            margin-top: 6px;
        }

        .delete-blockers {
            background: var(--tag-red-bg);
            color: var(--tag-red-fg);
            border: 1px solid var(--state-red-line);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .delete-blockers ul {
            margin: 6px 0 0;
            padding-left: 20px;
        }

        .delete-blockers li+li {
            margin-top: 6px;
        }

        .delete-blockers a {
            color: inherit;
            font-weight: 600;
            margin-left: 4px;
        }

        .delete-blockers-title {
            font-weight: 600;
        }

        .btn-code {
            background: #fff;
            color: var(--stone);
            border: 1px solid var(--border-strong);
            border-radius: 10px;
            padding: 9px 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-body);
            margin-top: 6px;
        }

        .btn-code:hover {
            border-color: var(--stone);
        }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .toggle-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--stone);
            margin-bottom: 3px;
        }

        /* The checkbox covers the whole switch: invisible, but it is what
           the finger, the mouse and the Tab key reach. It used to be 0×0,
           so a keyboard user landed on it and saw nothing at all. Its
           focus ring is drawn on the track. */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            opacity: 0;
            cursor: pointer;
            z-index: 1;
        }

        /* Off is --border-strong, 3:1 on white. The old --border was
           1.3:1: an off switch was close to invisible. */
        .toggle-slider {
            position: absolute;
            inset: 0;
            background: var(--border-strong);
            border-radius: 24px;
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

        .toggle-switch input:focus-visible+.toggle-slider {
            outline: 2px solid var(--stone);
            outline-offset: 2px;
        }

        /* Turning two-factor login off asks for the password first. */
        .confirm-box {
            margin-top: 16px;
            background: var(--sand);
            border-radius: 10px;
            padding: 14px;
        }

        .confirm-box .pw-field {
            margin-bottom: 10px;
        }

        .confirm-box .field-error {
            margin-bottom: 8px !important;
        }

        .device-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .device-row:last-child {
            border-bottom: none;
        }

        .device-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--stone);
        }

        .device-meta {
            font-size: 13px;
            color: var(--muted);
            margin-top: 2px;
        }

        .this-device-tag {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
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
            padding: 8px;
            min-height: 44px;
        }

        .btn-remove-device:hover {
            color: var(--tag-red-fg);
        }

        .empty-hint {
            font-size: 14px;
            color: var(--muted);
            padding: 8px 0;
        }

        .panel-intro {
            margin-bottom: 8px;
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

        .rail-group {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }

        .rail-group:last-child {
            border-bottom: none;
        }

        .rail-group-label {
            font-size: 12px;
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
            font-family: var(--font-body);
            font-size: 14px;
            color: var(--stone);
            text-align: left;
            cursor: pointer;
            transition: background .15s, color .15s;
        }

        .rail-item:hover {
            background: var(--sand);
            color: var(--stone);
        }

        /* Stone, not gold: a focus ring needs 3:1 against white, and gold
           is about 2.2:1. */
        .rail-item:focus-visible {
            outline: 2px solid var(--stone);
            outline-offset: -2px;
        }

        .rail-item.active {
            background: color-mix(in srgb, var(--gold) 12%, #fff);
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

        .rail-item.is-danger,
        .rail-item.is-danger i {
            color: var(--tag-red-fg);
        }

        /* A setting's current state, in words, where the list used to carry
           a second switch for it. The switch lives in the section, next to
           the sentence that explains it. */
        .rail-state {
            margin-left: auto;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: var(--sand);
            color: var(--muted);
        }

        .rail-state.is-on {
            background: var(--tag-green-bg);
            color: var(--tag-green-fg);
        }

        .rail-state~.chev {
            margin-left: 8px;
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
            min-height: 44px;
            font-family: var(--font-body);
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

            /* The list is the whole screen here and nothing is open, so
               nothing in it is marked as the open section. */
            .settings-shell[data-view="list"] .rail-item.active {
                background: none;
                font-weight: 400;
                border-left-color: transparent;
            }

            .settings-shell[data-view="list"] .rail-item.active i {
                color: var(--muted);
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
        }
    </style>
@endpush

@section('content')
    <h1 class="page-title">Settings</h1>
    <p class="page-sub mb-20">Manage your account information and security</p>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}</div>
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
        } elseif ($errors->signOutOthers->any()) {
            $openPanel = 'activity';
        } elseif ($errors->deleteAccount->any() || session('delete_code_sent')) {
            $openPanel = 'delete';
        } elseif ($errors->getBag('default')->any()) {
            $openPanel = 'profile';
        }

        $trustedDays = \App\Http\Controllers\AuthController::TRUSTED_DEVICE_DAYS;
    @endphp

    <div class="settings-shell" id="settingsShell" data-view="list" data-open="{{ $openPanel }}">

        {{-- ── The list ──────────────────────────────────────────────
             Every row opens a section. A setting that is a switch shows
             its state here in words; the switch itself is in the section,
             beside the sentence that says what it does. --}}
        <nav class="settings-rail" aria-label="Settings sections">
            <div class="rail-group">
                <div class="rail-group-label">Account</div>
                <button type="button" class="rail-item" data-panel="profile">
                    <i class="bi bi-person" aria-hidden="true"></i> <span>Profile</span>
                    <span class="chev" aria-hidden="true">›</span>
                </button>
                <button type="button" class="rail-item" data-panel="password">
                    <i class="bi bi-key" aria-hidden="true"></i> <span>Change password</span>
                    <span class="chev" aria-hidden="true">›</span>
                </button>
            </div>

            <div class="rail-group">
                <div class="rail-group-label">Security</div>
                <button type="button" class="rail-item" data-panel="two-factor">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i> <span>Two-factor login</span>
                    <span class="rail-state {{ $user->two_factor_enabled ? 'is-on' : '' }}">{{ $user->two_factor_enabled ? 'On' : 'Off' }}</span>
                    <span class="chev" aria-hidden="true">›</span>
                </button>
                @if ($user->two_factor_enabled)
                    <button type="button" class="rail-item" data-panel="devices">
                        <i class="bi bi-laptop" aria-hidden="true"></i> <span>Trusted devices</span>
                        <span class="chev" aria-hidden="true">›</span>
                    </button>
                @endif
                <button type="button" class="rail-item" data-panel="activity">
                    <i class="bi bi-clock-history" aria-hidden="true"></i> <span>Login activity</span>
                    <span class="chev" aria-hidden="true">›</span>
                </button>
            </div>

            <div class="rail-group">
                <div class="rail-group-label">Notifications</div>
                <button type="button" class="rail-item" data-panel="notifications">
                    <i class="bi bi-envelope" aria-hidden="true"></i> <span>Booking emails</span>
                    <span class="rail-state {{ $user->email_notifications_enabled ? 'is-on' : '' }}">{{ $user->email_notifications_enabled ? 'On' : 'Off' }}</span>
                    <span class="chev" aria-hidden="true">›</span>
                </button>
            </div>

            {{-- Last and on its own: the one row that cannot be undone. --}}
            <div class="rail-group">
                <button type="button" class="rail-item is-danger" data-panel="delete">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <span>Delete account</span>
                    <span class="chev" aria-hidden="true">›</span>
                </button>
            </div>
        </nav>

        {{-- ── The open section ──────────────────────────────────────── --}}
        <div class="settings-panels">
            <button type="button" class="panel-back" id="panelBack">‹ Settings</button>

            {{-- ── Profile ──────────────────────────────────────────── --}}
            <div class="settings-panel" id="panel-profile">
                {{-- The photo has its own two forms, so uploading it does not
                     also submit the name and phone (and lose the photo when
                     one of them fails). The file input sits in the profile
                     card but belongs to avatarForm through `form=`. --}}
                <form id="avatarForm" method="POST" action="{{ route('customer.profile.avatar.update') }}"
                    enctype="multipart/form-data" hidden>
                    @csrf
                </form>
                <form id="deleteAvatarForm" method="POST" data-confirm="Delete your profile photo?"
                    data-confirm-label="Delete" action="{{ route('customer.profile.avatar.destroy') }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>
                @php $contact = \App\Support\GuestEmail::contact(); @endphp
                <form method="POST" action="{{ route('customer.profile.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="form-card">
                        <div class="form-card-head">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <h2>Profile</h2>
                        </div>
                        <div class="form-card-body">

                            {{-- Dating isang hubad na `<input type="file">` lang ito:
                                 "Choose File / No file chosen", walang label kung para
                                 saan, walang nakikitang pagbabago pagkatapos pumili, at
                                 ang tanging pindutan ay ang "Save Changes" sa dulo ng
                                 mahabang form. Ngayon: may pangalan ang kontrol, agad
                                 nakikita ang piniling larawan sa mismong bilog, at
                                 lumilitaw ang "Upload photo" katabi mismo nito. --}}
                            <div class="avatar-row">
                                <div class="avatar-preview-wrap">
                                    <img id="avatarImg" class="avatar-preview" alt="Profile photo"
                                        src="{{ $user->profile_image ? $user->profile_image_url : '' }}"
                                        data-original="{{ $user->profile_image ? $user->profile_image_url : '' }}"
                                        @unless ($user->profile_image) hidden @endunless>
                                    <div id="avatarInitial" class="avatar-preview" aria-hidden="true"
                                        @if ($user->profile_image) hidden @endif>
                                        {{ strtoupper(substr($user->full_name, 0, 1)) }}
                                    </div>
                                </div>
                                <div class="avatar-controls">
                                    <div class="avatar-title">Profile photo</div>
                                    <p class="hint">JPG, PNG or WEBP, up to 3 MB</p>

                                    <input type="file" name="avatar" id="avatarInput" form="avatarForm"
                                        accept="image/png,image/jpeg,image/webp" class="avatar-input">

                                    <div class="avatar-actions">
                                        <label for="avatarInput" class="btn-choose">
                                            <i class="bi bi-image" aria-hidden="true"></i>
                                            {{ $user->profile_image ? 'Change photo' : 'Choose photo' }}
                                        </label>
                                        <button type="submit" form="avatarForm" class="btn-upload" id="avatarUploadBtn" hidden>
                                            <i class="bi bi-upload" aria-hidden="true"></i> Upload photo
                                        </button>
                                        <button type="button" class="btn-link-cancel" id="avatarCancelBtn" hidden>Cancel</button>
                                        @if ($user->profile_image)
                                            <button type="submit" form="deleteAvatarForm" class="btn-delete-avatar"
                                                id="avatarDeleteBtn">
                                                <i class="bi bi-trash3" aria-hidden="true"></i> Delete photo
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
                                <label for="f_full_name" class="form-label">Full name</label>
                                <input id="f_full_name" type="text" name="full_name" autocomplete="name"
                                    class="form-control @error('full_name') is-invalid @enderror"
                                    value="{{ old('full_name', $user->full_name) }}" required>
                                @error('full_name')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-16">
                                <label for="f_email" class="form-label">Email address</label>
                                <input id="f_email" type="email" class="form-control" value="{{ $user->email }}"
                                    aria-describedby="f_email_hint" disabled>
                                {{-- Says how, not only who: the resort's own
                                     phone and email from Settings. --}}
                                @php
                                    // Each one a link; "or" between them, and a full stop
                                    // straight after the last, whichever are set.
                                    $contactLinks = array_filter([
                                        $contact['phone'] ? '<a href="tel:'.e(preg_replace('/[^0-9+]/', '', $contact['phone'])).'">'.e($contact['phone']).'</a>' : null,
                                        $contact['email'] ? '<a href="mailto:'.e($contact['email']).'">'.e($contact['email']).'</a>' : null,
                                    ]);
                                @endphp
                                <p class="hint hint-below" id="f_email_hint">
                                    To change it, contact the resort{!! $contactLinks ? ' at '.implode(' or ', $contactLinks) : '' !!}.
                                </p>
                            </div>

                            <div class="mb-16">
                                <label for="f_phone" class="form-label">Phone number</label>
                                <input id="f_phone" type="tel" name="phone" autocomplete="tel" inputmode="tel"
                                    class="form-control @error('phone') is-invalid @enderror"
                                    value="{{ old('phone', $user->phone) }}" placeholder="09XX-XXX-XXXX" required>
                                @error('phone')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-16">
                                <label for="f_address" class="form-label">Home address</label>
                                <textarea id="f_address" name="address" class="form-control" rows="2" autocomplete="street-address">{{ old('address', $user->address) }}</textarea>
                            </div>

                            <button type="submit" class="btn-submit"><i class="bi bi-check-circle" aria-hidden="true"></i>
                                Save changes</button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- ── Change password ──────────────────────────────────── --}}
            <div class="settings-panel" id="panel-password">
                <form method="POST" action="{{ route('customer.profile.password') }}">
                    @csrf
                    @method('PUT')
                    <div class="form-card">
                        <div class="form-card-head">
                            <i class="bi bi-key" aria-hidden="true"></i>
                            <h2>Change password</h2>
                        </div>
                        <div class="form-card-body">
                            <div class="mb-16">
                                <label for="f_current_password" class="form-label">Current password</label>
                                <div class="pw-field">
                                    <input id="f_current_password" type="password" autocomplete="current-password"
                                        name="current_password"
                                        class="form-control @error('current_password', 'updatePassword') is-invalid @enderror">
                                    <button type="button" class="pw-toggle" data-pw-toggle="f_current_password"
                                        aria-label="Show password">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                @error('current_password', 'updatePassword')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    <label for="f_password" class="form-label">New password</label>
                                    <div class="pw-field">
                                        <input id="f_password" type="password" name="password"
                                            class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                                            autocomplete="new-password">
                                        <button type="button" class="pw-toggle" data-pw-toggle="f_password"
                                            aria-label="Show password">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    @error('password', 'updatePassword')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                    @include('partials.password_rules', ['for' => 'f_password'])
                                </div>
                                <div>
                                    <label for="f_password_confirmation" class="form-label">Confirm new password</label>
                                    <div class="pw-field">
                                        <input id="f_password_confirmation" type="password" name="password_confirmation"
                                            class="form-control" autocomplete="new-password">
                                        <button type="button" class="pw-toggle" data-pw-toggle="f_password_confirmation"
                                            aria-label="Show password">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p class="hint mb-16">Changing it signs you out on every other device.</p>
                            <button type="submit" class="btn-submit"><i class="bi bi-shield-check" aria-hidden="true"></i>
                                Update password</button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- ── Two-factor login ─────────────────────────────────── --}}
            <div class="settings-panel" id="panel-two-factor">
                <div class="form-card">
                    <div class="form-card-head">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        <h2>Two-factor login</h2>
                    </div>
                    <div class="form-card-body">
                        <div class="toggle-row">
                            <div>
                                <div class="toggle-title" id="tfTitle">Email me a code when I sign in on a new device</div>
                                <p class="hint" id="tfHint">
                                    You enter the code once on each device. After that the device is trusted for
                                    {{ $trustedDays }} days.
                                </p>
                            </div>

                            @if ($user->two_factor_enabled)
                                {{-- Turning it off asks for the password, so this
                                     switch only opens the form below; the script
                                     stops it from flipping on its own. --}}
                                <label class="toggle-switch">
                                    <input type="checkbox" role="switch" checked data-2fa-off
                                        aria-labelledby="tfTitle" aria-describedby="tfHint"
                                        aria-controls="disable2faForm">
                                    <span class="toggle-slider"></span>
                                </label>
                            @else
                                <form method="POST" action="{{ route('customer.profile.2fa.toggle') }}">
                                    @csrf @method('PUT')
                                    <label class="toggle-switch">
                                        <input type="checkbox" role="switch" data-autosubmit
                                            aria-labelledby="tfTitle" aria-describedby="tfHint">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </form>
                            @endif
                        </div>

                        @if ($user->two_factor_enabled)
                            <form method="POST" action="{{ route('customer.profile.2fa.toggle') }}" id="disable2faForm"
                                class="confirm-box" @unless ($errors->twoFactor->any()) hidden @endunless>
                                @csrf @method('PUT')
                                <label for="f_password_3" class="form-label">Enter your password to turn this off</label>
                                <div class="pw-field">
                                    <input id="f_password_3" type="password" autocomplete="current-password" name="password"
                                        class="form-control @error('password', 'twoFactor') is-invalid @enderror">
                                    <button type="button" class="pw-toggle" data-pw-toggle="f_password_3"
                                        aria-label="Show password">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                @error('password', 'twoFactor')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                                <button type="submit" class="btn-danger is-small">Turn off</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── Trusted devices ──────────────────────────────────── --}}
            @if ($user->two_factor_enabled)
                <div class="settings-panel" id="panel-devices">
                    <div class="form-card">
                        <div class="form-card-head">
                            <i class="bi bi-laptop" aria-hidden="true"></i>
                            <h2>Trusted devices</h2>
                        </div>
                        <div class="form-card-body">
                            <p class="hint panel-intro">
                                These devices sign in without an email code, each for {{ $trustedDays }} days from
                                when it was verified. Remove one you no longer use or don't recognise; it will need a
                                code next time.
                            </p>
                            @forelse($trustedDevices as $device)
                                <div class="device-row">
                                    <div>
                                        <div class="device-name">
                                            {{ $device->device_label ?: 'Unknown device' }}
                                            {{-- The row stores a hash, so this can't be a
                                                 string comparison any more — the model
                                                 hashes the cookie value to compare. --}}
                                            @if ($device->matchesToken($currentDeviceToken))
                                                <span class="this-device-tag">This device</span>
                                            @endif
                                        </div>
                                        <div class="device-meta">
                                            Last used {{ $device->last_used_at?->diffForHumans() ?? 'never' }}
                                            · trusted until {{ $device->expires_at?->format('M j, Y') }}
                                            · {{ $device->ip_address }}
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('customer.profile.devices.destroy', $device) }}"
                                        data-confirm="Remove this device? It will need to verify again next time it logs in."
                                        data-confirm-label="Remove">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-remove-device"
                                            aria-label="Remove {{ $device->device_label ?: 'this device' }}">
                                            <i class="bi bi-trash" aria-hidden="true"></i> Remove</button>
                                    </form>
                                </div>
                            @empty
                                <div class="empty-hint">No trusted devices yet. A device is added here the first time you
                                    sign in on it with an email code.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            {{-- ── Login activity ───────────────────────────────────── --}}
            <div class="settings-panel" id="panel-activity">
                <div class="form-card">
                    <div class="form-card-head">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        <h2>Login activity</h2>
                    </div>
                    <div class="form-card-body">
                        <p class="hint panel-intro">
                            Your last {{ $loginActivities->count() ?: 10 }} sign-ins. Don't recognise one?
                            <a href="#password">Change your password</a>, which also signs you out everywhere else.
                        </p>
                        @forelse($loginActivities as $activity)
                            <div class="device-row">
                                <div>
                                    <div class="device-name">{{ $activity->device_label ?: 'Unknown device' }}</div>
                                    <div class="device-meta">
                                        {{-- The date and time first: four rows of
                                             "5 days ago" told the guest nothing. --}}
                                        <time datetime="{{ $activity->created_at->toIso8601String() }}">
                                            {{ $activity->created_at->timezone(config('app.timezone'))->format('M j, Y, g:i A') }}
                                        </time>
                                        ({{ $activity->created_at->diffForHumans() }})
                                        · {{ $activity->ip_address }}
                                        @if ($activity->via_new_device_otp)
                                            · verified with an email code
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-hint">No sign-ins recorded yet.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Only where the server can end other sessions: with file
                     sessions (local development) nothing would be signed
                     out, and the button would say it had been. --}}
                @if ($canSignOutOthers)
                    <form method="POST" action="{{ route('customer.profile.sessions.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <div class="form-card">
                            <div class="form-card-head">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                <h2>Sign out everywhere else</h2>
                            </div>
                            <div class="form-card-body">
                                <p class="hint mb-16">
                                    Ends your sign-ins on every other phone and computer. You stay signed in here.
                                    @if ($user->two_factor_enabled)
                                        Every trusted device, this one included, will need an email code again.
                                    @endif
                                </p>
                                <div class="mb-16">
                                    <label for="f_password_4" class="form-label">Your password</label>
                                    <div class="pw-field">
                                        <input id="f_password_4" type="password" autocomplete="current-password"
                                            name="password"
                                            class="form-control @error('password', 'signOutOthers') is-invalid @enderror">
                                        <button type="button" class="pw-toggle" data-pw-toggle="f_password_4"
                                            aria-label="Show password">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    @error('password', 'signOutOthers')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn-submit"><i class="bi bi-box-arrow-right"
                                        aria-hidden="true"></i> Sign out other devices</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>

            {{-- ── Booking emails ───────────────────────────────────── --}}
            <div class="settings-panel" id="panel-notifications">
                <div class="form-card">
                    <div class="form-card-head">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <h2>Booking emails</h2>
                    </div>
                    <div class="form-card-body">
                        <form method="POST" action="{{ route('customer.profile.email-notifications') }}">
                            @csrf @method('PUT')
                            <div class="toggle-row">
                                <div>
                                    <div class="toggle-title" id="emTitle">Email me about my bookings and refunds</div>
                                    <p class="hint" id="emHint">
                                        A confirmation when a payment is received, and an update when a booking is
                                        cancelled or moved, or when a refund is issued, needs your account details, or is
                                        paid out. Everything also appears in your notifications here, whether this is on
                                        or off.
                                    </p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" role="switch" name="email_notifications_enabled" value="1"
                                        aria-labelledby="emTitle" aria-describedby="emHint" data-autosubmit
                                        {{ $user->email_notifications_enabled ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ── Delete account ───────────────────────────────────── --}}
            {{-- Erases the account's personal details and keeps its booking
                 and payment records (App\Services\AccountErasure). The
                 reasons it is refused are listed here BEFORE the password
                 is typed; the server asks the same question again. --}}
            @php
                // Upcoming stays are summarised past a few: one line per
                // booking turned a guest with many into a page of red.
                $upcomingProblems = collect($deletionProblems)->where('upcoming', true)->values();
                $otherProblems = collect($deletionProblems)->where('upcoming', false)->values();
                $summariseUpcoming = $upcomingProblems->count() > 3;
            @endphp
            <div class="settings-panel" id="panel-delete">
                <div class="danger-card">
                    <div class="form-card-head">
                        <i class="bi bi-exclamation-triangle text-red" aria-hidden="true"></i>
                        <h2 class="text-red">Delete account</h2>
                    </div>
                    <div class="form-card-body">
                        <p class="hint mb-16">
                            Deleting your account removes your name, email address, phone number, address,
                            profile photo and sign-in history. <strong>It cannot be undone.</strong>
                        </p>

                        {{-- The refusal comes first when there is one: it is the
                             first thing the guest needs to know. The submitted
                             form's own refusal is shown only when this live list
                             is empty (something changed since the page loaded);
                             otherwise it would repeat the list below. --}}
                        @if ($errors->deleteAccount->has('account') && ! count($deletionProblems))
                            <div class="delete-blockers" role="alert">
                                @foreach ($errors->deleteAccount->get('account') as $message)
                                    <div>{{ $message }}</div>
                                @endforeach
                            </div>
                        @endif

                        @if (count($deletionProblems))
                            <div class="delete-blockers" @if ($errors->deleteAccount->has('account')) role="alert" @endif>
                                <div class="delete-blockers-title">Your account can't be deleted yet:</div>
                                <ul>
                                    @if ($summariseUpcoming)
                                        <li>
                                            You have {{ $upcomingProblems->count() }} upcoming bookings, the first on
                                            {{ $upcomingProblems->first()['booking']->stay_phrase }}. Cancel them, or wait
                                            until the stays are over.
                                            <a href="{{ route('customer.bookings') }}">See my bookings</a>
                                        </li>
                                    @else
                                        @foreach ($upcomingProblems as $problem)
                                            <li>
                                                {{ $problem['message'] }}
                                                <a href="{{ route('customer.bookings.show', $problem['booking']) }}">View booking</a>
                                            </li>
                                        @endforeach
                                    @endif
                                    @foreach ($otherProblems as $problem)
                                        <li>
                                            {{ $problem['message'] }}
                                            @if ($problem['booking'])
                                                <a href="{{ route('customer.bookings.show', $problem['booking']) }}">View booking</a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <ul class="delete-facts">
                            <li>Records of your bookings and payments are kept without your name, because the law
                                requires us to keep proof of every transaction.</li>
                            <li>Your reviews stay up under “{{ \App\Models\User::ANONYMIZED_NAME }}”. To remove one,
                                delete it from <a href="{{ route('customer.reviews.index') }}">My Reviews</a> first.</li>
                            <li>You can sign up again with the same email address, but the new account starts fresh:
                                your past bookings and completed stays do not carry over, including toward
                                returning-guest promos.</li>
                        </ul>

                        @unless (count($deletionProblems))
                            @if ($holdsToCancel->isNotEmpty())
                                <p class="hint mb-16">
                                    Your unpaid {{ Str::plural('booking', $holdsToCancel->count()) }}
                                    {{ $holdsToCancel->pluck('booking_ref')->implode(', ') }}
                                    will be cancelled.
                                </p>
                            @endif

                            @if ($user->two_factor_enabled)
                                {{-- Its own form: a form cannot sit inside the delete form. --}}
                                <form method="POST" action="{{ route('customer.profile.delete-code') }}" class="mb-16">
                                    @csrf
                                    <div class="field-label">Code from your email</div>
                                    <p class="hint">Two-factor login is on, so we email you a code to confirm this.</p>
                                    <button type="submit" class="btn-code"><i class="bi bi-envelope" aria-hidden="true"></i>
                                        {{ session('delete_code_sent') ? 'Send another code' : 'Email me a code' }}</button>
                                    @if (session('delete_code_sent'))
                                        <p class="hint mt-8">Code sent. It works for
                                            {{ \App\Support\AccountDeletionCode::TTL_MINUTES }} minutes.</p>
                                    @endif
                                </form>
                            @endif

                            <form method="POST" action="{{ route('customer.profile.destroy') }}"
                                data-confirm="Delete your account? This cannot be undone."
                                data-confirm-label="Delete account">
                                @csrf
                                @method('DELETE')

                                @if ($user->two_factor_enabled)
                                    <div class="mb-16">
                                        <label for="f_delete_code" class="form-label">Code</label>
                                        <input id="f_delete_code" type="text" name="code" inputmode="numeric"
                                            autocomplete="one-time-code" maxlength="6"
                                            class="form-control @error('code', 'deleteAccount') is-invalid @enderror">
                                        @error('code', 'deleteAccount')
                                            <div class="field-error">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif

                                <div class="mb-16">
                                    <label for="f_password_2" class="form-label">Your password</label>
                                    <div class="pw-field">
                                        <input id="f_password_2" type="password" autocomplete="current-password"
                                            name="password"
                                            class="form-control @error('password', 'deleteAccount') is-invalid @enderror">
                                        <button type="button" class="pw-toggle" data-pw-toggle="f_password_2"
                                            aria-label="Show password">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                    @error('password', 'deleteAccount')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-16">
                                    <label for="f_confirm_text" class="form-label">Type DELETE to confirm</label>
                                    <input id="f_confirm_text" type="text" name="confirm_text" autocomplete="off"
                                        class="form-control @error('confirm_text', 'deleteAccount') is-invalid @enderror">
                                    @error('confirm_text', 'deleteAccount')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>

                                <button type="submit" class="btn-danger"><i class="bi bi-trash" aria-hidden="true"></i>
                                    Delete my account</button>
                            </form>
                        @endunless
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
            const narrow = window.matchMedia('(max-width: 900px)');

            // Ang sagot ng server ay isang redirect na hindi nagdadala ng
            // `#hash`, kaya inaalala kung saang panel nanggaling ang form, at
            // minsan lang itong binabasa.
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
                    // ang nasa harap, at walang nakabukas doon.
                    show(FALLBACK, false);
                    shell.dataset.view = 'list';
                    if (narrow.matches) items.forEach(el => el.removeAttribute('aria-current'));
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

            // Turning two-factor login off asks for the password, so the
            // switch opens the form instead of flipping.
            const offSwitch = shell.querySelector('[data-2fa-off]');
            if (offSwitch) offSwitch.addEventListener('click', function(e) {
                e.preventDefault();
                const form = document.getElementById('disable2faForm');
                if (!form) return;
                form.hidden = false;
                const field = form.querySelector('input[name="password"]');
                if (field) field.focus();
            });

            // A switch saves as soon as it is flipped. requestSubmit(), not
            // submit(): only the first fires the submit event below, which
            // is what brings the guest back to this section afterwards.
            shell.querySelectorAll('[data-autosubmit]').forEach(input => {
                input.addEventListener('change', () => {
                    if (input.form.requestSubmit) {
                        input.form.requestSubmit();
                    } else {
                        input.form.submit();
                    }
                });
            });

            shell.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', () => {
                    const panel = form.closest('.settings-panel');
                    if (panel) remember(panel.id.replace('panel-', ''));
                });
            });

            window.addEventListener('hashchange', fromHash);

            const forced = shell.dataset.open;
            const recalled = recall();

            if (!location.hash && forced) {
                show(forced, true); // may error — dapat itong makita agad
            } else if (!location.hash && recalled) {
                show(recalled, true);
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

                chosen.textContent = 'Ready to upload: ' + file.name;
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
