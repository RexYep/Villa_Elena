@extends('layouts.admin')

@section('title', 'Settings — Villa Elena Admin')
@section('page-title', 'Settings')
@section('page-subtitle', 'Configure your resort system')

@push('styles')
    <style>
        /* Layout */
        .settings-layout {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* Tab Nav */
        .tab-nav {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            position: sticky;
            top: calc(var(--topbar-h) + 24px);
        }

        .tab-nav-header {
            padding: 16px 18px;
            border-bottom: 1px solid var(--border);
        }

        .tab-nav-header h3 {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
        }

        .tab-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 18px;
            font-size: 13px;
            color: var(--muted);
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            transition: all .2s;
            border-left: 3px solid transparent;
        }

        .tab-link:hover {
            background: var(--sand);
            color: var(--text-main);
        }

        .tab-link.active {
            background: var(--gold-dim);
            color: var(--text-main);
            font-weight: 600;
            border-left-color: var(--terracotta);
        }

        .tab-link i {
            font-size: 15px;
            width: 18px;
            text-align: center;
        }

        /* Cards */
        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .settings-card {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .settings-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .settings-card-header .icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
            background: var(--gold-dim);
            color: var(--gold);
        }

        .settings-card-header h3 {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
        }

        .settings-card-header p {
            font-size: 14px;
            color: var(--muted);
            margin-top: 2px;
        }

        .settings-card-body {
            padding: 24px;
        }

        /* Form */
        .three-col {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        .mb-16 {
            margin-bottom: 16px;
        }

        /* Toggle Switch */
        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .toggle-row:last-child {
            border-bottom: none;
        }

        .toggle-info .toggle-title {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
        }

        .toggle-info .toggle-desc {
            font-size: 14px;
            color: var(--muted);
            margin-top: 2px;
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
            cursor: pointer;
            inset: 0;
            background: var(--border);
            border-radius: 100px;
            transition: .3s;
        }

        .toggle-slider:before {
            content: '';
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: #fff;
            border-radius: 50%;
            transition: .3s;
        }

        .toggle-switch input:checked+.toggle-slider {
            background: var(--terracotta);
        }

        .toggle-switch input:checked+.toggle-slider:before {
            transform: translateX(20px);
        }

        /* Submit */
        .submit-bar {
            background: var(--cream);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            position: sticky;
            bottom: 24px;
            box-shadow: 0 4px 20px rgba(44, 36, 22, 0.1);
        }

        /* flex-shrink:0 because the submit bar is a flex row and the "Last saved"
           timestamp beside it takes what it needs: where the content column is
           narrowest (425px at a 993px viewport) the button was squeezed to 159px
           and "Save Settings" broke across two lines, making it 64px tall. */
        .btn-save {
            background: var(--btn-primary);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 11px 28px;
            flex-shrink: 0;
            white-space: nowrap;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-body);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background .2s;
        }

        .btn-save:hover {
            background: var(--btn-primary-hover); color: #fff;
        }

        .submit-info {
            font-size: 14px;
            color: var(--muted);
        }

        /* Maintenance banner — semantic warning, unchanged */
        .maintenance-banner {
            background: #fef9c3;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #a16207;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Used three times in the amenities tab, and by addAmenityRow() for every
           row added at runtime, but defined nowhere — it rendered as a bare
           20x43px button around a trash icon. */
        .btn-remove-amenity {
            flex-shrink: 0;
            width: 38px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            color: var(--muted);
            cursor: pointer;
            transition: all .2s;
        }

        .btn-remove-amenity:hover {
            background: #fee2e2;
            border-color: #fecaca;
            color: #b91c1c;
        }

        /* Same declarations this button carried as an inline style attribute; it
           needs a class so the coarse-pointer rule below can reach it. */
        .btn-add-amenity {
            background: var(--sand);
            color: var(--text-main);
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 6px;
        }

        /* Two-up until 1150px, not 900px. The 220px tab rail plus gap comes off
           the content column, so it is 860px wide at a 900px viewport but only
           425px at 993px — and three columns there came out 114px each for
           Currency, Deposit % and Tax %. The widest label, "Deposit Percentage
           (%)", needs 148px, so three columns need 476px inside the card (a
           1094px viewport); two need 312px, which fits from 901px up. */
        @media (max-width: 1150px) {
            .three-col {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 900px) {
            .settings-layout {
                grid-template-columns: minmax(0, 1fr);
            }

            .tab-nav {
                position: static;
            }
        }

        /* Maintenance Mode hides the entire guest-facing portal, and its switch
           is 44x24px. The pseudo-element gives every toggle a 44x44 hit area
           without changing how the switch looks. Keyed on pointer type, not
           width: only a finger needs the larger target. */
        @media (hover: none) and (pointer: coarse) {
            .toggle-switch::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 44px;
                height: 44px;
                transform: translate(-50%, -50%);
            }

            .btn-save,
            .btn-add-amenity,
            .settings-card-body .form-control,
            .settings-card-body .form-select {
                min-height: 44px;
            }

            .btn-remove-amenity {
                min-width: 44px;
                min-height: 44px;
            }
        }

        @media (max-width: 560px) {
            .three-col {
                grid-template-columns: 1fr;
            }

            .submit-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                position: static;
            }
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    @if (($settings['maintenance_mode'] ?? '0') === '1')
        <div class="maintenance-banner">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Maintenance Mode is ON</strong> — The guest-facing site is currently hidden from visitors.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')

        <div class="settings-layout">

            {{-- Tab Navigation --}}
            <div class="tab-nav">
                <div class="tab-nav-header">
                    <h3>Settings</h3>
                </div>
                <button type="button" class="tab-link active" onclick="showTab('resort')">
                    <i class="bi bi-building"></i> Resort Info
                </button>
                <button type="button" class="tab-link" onclick="showTab('booking')">
                    <i class="bi bi-calendar-check"></i> Booking Rules
                </button>
                <button type="button" class="tab-link" onclick="showTab('payments')">
                    <i class="bi bi-credit-card"></i> Payments
                </button>
                <button type="button" class="tab-link" onclick="showTab('amenities')">
                    <i class="bi bi-stars"></i> Amenities
                </button>
                <button type="button" class="tab-link" onclick="showTab('social')">
                    <i class="bi bi-share"></i> Social & Links
                </button>
                <button type="button" class="tab-link" onclick="showTab('system')">
                    <i class="bi bi-shield-gear"></i> System
                </button>
            </div>

            {{-- Settings Panels --}}
            <div>

                {{-- Resort Info --}}
                <div class="settings-section active" id="tab-resort">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon tag-blue"><i class="bi bi-building"></i></div>
                            <div>
                                <h3>Resort Information</h3>
                                <p>Basic details about Villa Elena Resort</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="mb-16">
                                <label for="f_resort_name" class="form-label">Resort Name <span class="req">*</span></label>
                                <input id="f_resort_name" type="text" name="resort_name" class="form-control"
                                    value="{{ $settings['resort_name'] ?? 'Villa Elena Private Rental Resort' }}" required>
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    <label for="f_resort_email" class="form-label">Email Address <span class="req">*</span></label>
                                    <input id="f_resort_email" type="email" name="resort_email" class="form-control"
                                        value="{{ $settings['resort_email'] ?? '' }}" required>
                                </div>
                                <div>
                                    <label for="f_resort_phone" class="form-label">Phone Number <span class="req">*</span></label>
                                    <input id="f_resort_phone" type="text" name="resort_phone" class="form-control"
                                        value="{{ $settings['resort_phone'] ?? '' }}" required>
                                </div>
                            </div>
                            <div class="mb-16">
                                <label for="f_resort_address" class="form-label">Full Address</label>
                                <input id="f_resort_address" type="text" name="resort_address" class="form-control"
                                    value="{{ $settings['resort_address'] ?? '' }}"
                                    placeholder="e.g. Calamba, Laguna, Philippines">
                            </div>
                            <div>
                                <label for="f_resort_description" class="form-label">Resort Description</label>
                                <textarea id="f_resort_description" name="resort_description" class="form-control" rows="4"
                                    placeholder="Brief description shown on the booking portal...">{{ $settings['resort_description'] ?? '' }}</textarea>
                                <span class="hint">Shown on the guest-facing booking page.</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Booking Rules --}}
                <div class="settings-section" id="tab-booking">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon tag-green"><i
                                    class="bi bi-calendar-check"></i></div>
                            <div>
                                <h3>Booking Rules</h3>
                                <p>Advance-booking limit, hold time, and cooldown settings</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            {{-- Walang Check-in/Check-out Time o Minimum Stay
                                 dito: ang oras ay nakapirmi sa mga slot
                                 (Booking::SLOTS), at walang bumabasa sa mga
                                 dating field na iyon. --}}
                            <div class="two-col mb-16">
                                <div>
                                    <label for="f_max_advance_days" class="form-label">Max Advance Booking (days)</label>
                                    <input id="f_max_advance_days" type="number" name="max_advance_days" class="form-control"
                                        value="{{ $settings['max_advance_days'] ?? '365' }}" min="1">
                                    <span class="hint">How far ahead guests can book or reschedule online. Staff
                                        and admin bookings are not limited.</span>
                                </div>
                                <div>
                                    <label for="f_booking_hold_minutes" class="form-label">Booking Hold (minutes)</label>
                                    <input id="f_booking_hold_minutes" type="number" name="booking_hold_minutes" class="form-control"
                                        value="{{ $settings['booking_hold_minutes'] ?? '15' }}" min="1">
                                    <span class="hint">Time a pending booking reserves the property before
                                        expiring.</span>
                                </div>
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    {{-- Dating may "Cancellation Window (hours)" na
                                         field dito. Walang ipinapatupad ang
                                         halagang iyon — ang chatbot lang ang
                                         bumabasa, at ipinapangako nito ang
                                         libreng cancellation na wala naman.
                                         Hindi setting ang patakaran: nakasulat
                                         ito sa Booking::CANCELLATION_POLICY. --}}
                                    <label class="form-label">Cancellation Policy</label>
                                    <div class="form-control" style="height:auto;background:var(--cream);font-size:13px;line-height:1.5;">
                                        {{ \App\Models\Booking::CANCELLATION_POLICY }}
                                    </div>
                                    <span class="hint">Fixed by the booking policy — not editable here.</span>
                                </div>
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    <label for="f_booking_cooldown_threshold" class="form-label">Auto-Cancel Threshold</label>
                                    <input id="f_booking_cooldown_threshold" type="number" name="booking_cooldown_threshold" class="form-control"
                                        value="{{ $settings['booking_cooldown_threshold'] ?? '3' }}" min="1">
                                    <span class="hint">Unpaid auto-cancelled bookings within the window below that
                                        trigger a booking cooldown.</span>
                                </div>
                                <div>
                                    <label for="f_booking_cooldown_window_days" class="form-label">Auto-Cancel Lookback (days)</label>
                                    <input id="f_booking_cooldown_window_days" type="number" name="booking_cooldown_window_days" class="form-control"
                                        value="{{ $settings['booking_cooldown_window_days'] ?? '30' }}" min="1">
                                    <span class="hint">How far back to count auto-cancelled bookings.</span>
                                </div>
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    <label for="f_booking_cooldown_hours" class="form-label">Booking Cooldown (hours)</label>
                                    <input id="f_booking_cooldown_hours" type="number" name="booking_cooldown_hours" class="form-control"
                                        value="{{ $settings['booking_cooldown_hours'] ?? '24' }}" min="1">
                                    <span class="hint">How long a guest is blocked from booking again after hitting
                                        the threshold.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon tag-amber"><i class="bi bi-lightbulb"></i></div>
                            <div>
                                <h3>Recommendation Rules</h3>
                                <p>Your policy for the Recommendations page — each card there follows one of these numbers
                                </p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="two-col mb-16">
                                <div>
                                    <label for="f_prescriptive_quiet_threshold" class="form-label">Quiet Dates — below (%)</label>
                                    <input id="f_prescriptive_quiet_threshold" type="number" name="prescriptive_quiet_threshold" class="form-control"
                                        value="{{ old('prescriptive_quiet_threshold', $settings['prescriptive_quiet_threshold'] ?? '30') }}" min="1"
                                        max="99" step="1">
                                    <span class="hint">Upcoming dates count as quiet when predicted occupancy is below
                                        this. A promo is suggested for them.</span>
                                </div>
                                <div>
                                    <label for="f_prescriptive_promo_percent" class="form-label">Promo for Quiet Dates (%)</label>
                                    <input id="f_prescriptive_promo_percent" type="number" name="prescriptive_promo_percent" class="form-control"
                                        value="{{ old('prescriptive_promo_percent', $settings['prescriptive_promo_percent'] ?? '10') }}" min="1"
                                        max="50" step="1">
                                    <span class="hint">The discount a promo card offers.</span>
                                </div>
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    <label for="f_prescriptive_busy_threshold" class="form-label">Busy Dates — at or above (%)</label>
                                    <input id="f_prescriptive_busy_threshold" type="number" name="prescriptive_busy_threshold" class="form-control"
                                        value="{{ old('prescriptive_busy_threshold', $settings['prescriptive_busy_threshold'] ?? '70') }}" min="2"
                                        max="100" step="1">
                                    <span class="hint">Upcoming dates count as busy when predicted occupancy reaches
                                        this. Public holidays always count as busy. Must be higher than the quiet
                                        number.</span>
                                    @error('prescriptive_busy_threshold')
                                        <span class="hint text-red">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div>
                                    <label for="f_prescriptive_increase_percent" class="form-label">Rate Increase for Busy Dates (%)</label>
                                    <input id="f_prescriptive_increase_percent" type="number" name="prescriptive_increase_percent" class="form-control"
                                        value="{{ old('prescriptive_increase_percent', $settings['prescriptive_increase_percent'] ?? '10') }}" min="1"
                                        max="50" step="1">
                                    <span class="hint">How much a peak-pricing card raises the rate, on top of the
                                        normal weekday or weekend price.</span>
                                </div>
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    <label for="f_prescriptive_maintenance_days" class="form-label">Maintenance Length (days)</label>
                                    <input id="f_prescriptive_maintenance_days" type="number" name="prescriptive_maintenance_days" class="form-control"
                                        value="{{ old('prescriptive_maintenance_days', $settings['prescriptive_maintenance_days'] ?? '2') }}" min="1"
                                        max="14">
                                    <span class="hint">How many days in a row the villa needs when it closes for
                                        maintenance.</span>
                                </div>
                                <div>
                                    <label for="f_prescriptive_reminder_days" class="form-label">Balance Reminder (days before check-in)</label>
                                    <input id="f_prescriptive_reminder_days" type="number" name="prescriptive_reminder_days" class="form-control"
                                        value="{{ old('prescriptive_reminder_days', $settings['prescriptive_reminder_days'] ?? '3') }}" min="0"
                                        max="14">
                                    <span class="hint">A reminder is suggested when a guest with a balance still due
                                        checks in within this many days.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon tag-purple"><i class="bi bi-toggles"></i></div>
                            <div>
                                <h3>Booking Options</h3>
                                <p>Enable or disable booking features</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <div class="toggle-title">Allow Online Booking</div>
                                    <div class="toggle-desc">Guests can submit bookings through the portal</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="allow_online_booking" value="1"
                                        {{ ($settings['allow_online_booking'] ?? '1') === '1' ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Payments --}}
                <div class="settings-section" id="tab-payments">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon tag-amber"><i class="bi bi-credit-card"></i></div>
                            <div>
                                <h3>Payment Settings</h3>
                                <p>Deposit required to confirm a booking</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            {{-- Walang Currency o Tax dito: PHP lang ang
                                 sinisingil ng PayMongo, at walang kuwentang
                                 gumagamit ng tax. --}}
                            <div class="two-col mb-16">
                                <div>
                                    <label for="f_deposit_percentage" class="form-label">Deposit Percentage (%)</label>
                                    <input id="f_deposit_percentage" type="number" name="deposit_percentage" class="form-control"
                                        value="{{ $settings['deposit_percentage'] ?? '50' }}" min="0"
                                        max="100" step="1">
                                    <span class="hint">% of total required to confirm booking.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Amenities --}}
                <div class="settings-section" id="tab-amenities">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon tag-green"><i class="bi bi-stars"></i>
                            </div>
                            <div>
                                <h3>Property Amenities</h3>
                                <p>Manage the amenity options offered when adding/editing the Villa</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div id="amenitiesRepeater">
                                @php
                                    $currentAmenities =
                                        json_decode($settings['property_amenities'] ?? '[]', true) ?: [];
                                @endphp
                                @forelse($currentAmenities as $amenity)
                                    <div class="amenity-row" style="display:flex;gap:8px;margin-bottom:10px;">
                                        <input type="text" name="amenities[]" class="form-control"
                                            value="{{ $amenity }}">
                                        <button type="button" class="btn-remove-amenity"
                                            onclick="this.parentElement.remove()"
                                            style="background:#fee2e2;color:#b91c1c;border:none;border-radius:8px;padding:0 14px;cursor:pointer;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                @empty
                                    <div class="amenity-row" style="display:flex;gap:8px;margin-bottom:10px;">
                                        <input type="text" name="amenities[]" class="form-control"
                                            placeholder="e.g. WiFi">
                                        <button type="button" class="btn-remove-amenity"
                                            onclick="this.parentElement.remove()"
                                            style="background:#fee2e2;color:#b91c1c;border:none;border-radius:8px;padding:0 14px;cursor:pointer;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                            <button type="button" onclick="addAmenityRow()" class="btn-add-amenity">
                                <i class="bi bi-plus-circle me-1"></i> Add Amenity
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Social --}}
                <div class="settings-section" id="tab-social">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon" style="background:#fce7f3;color:#be185d;"><i class="bi bi-share"></i>
                            </div>
                            <div>
                                <h3>Social Media & Links</h3>
                                <p>External links shown on the booking portal</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="mb-16">
                                <label for="f_facebook_url" class="form-label"><i class="bi bi-facebook text-primary me-1"></i> Facebook
                                    Page</label>
                                <input id="f_facebook_url" type="url" name="facebook_url" class="form-control"
                                    value="{{ $settings['facebook_url'] ?? '' }}"
                                    placeholder="https://facebook.com/villaelenaresort">
                            </div>
                            <div class="mb-16">
                                <label for="f_tiktok_url" class="form-label"><i class="bi bi-tiktok me-1"></i> TikTok</label>
                                <input id="f_tiktok_url" type="url" name="tiktok_url" class="form-control"
                                    value="{{ $settings['tiktok_url'] ?? '' }}"
                                    placeholder="https://tiktok.com/@villaelenaresort">
                            </div>
                            <div>
                                <label for="f_google_maps_url" class="form-label"><i class="bi bi-geo-alt text-success me-1"></i> Google Maps
                                    Link</label>
                                <input id="f_google_maps_url" type="url" name="google_maps_url" class="form-control"
                                    value="{{ $settings['google_maps_url'] ?? '' }}"
                                    placeholder="https://maps.google.com/?q=...">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- System --}}
                <div class="settings-section" id="tab-system">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon tag-red"><i class="bi bi-gear-wide-connected"></i></div>
                            <div>
                                <h3>System Settings</h3>
                                <p>Maintenance mode and advanced controls</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <div class="toggle-title text-red"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Maintenance Mode</div>
                                    <div class="toggle-desc">Hides the guest-facing portal and shows a maintenance page to
                                        visitors. Admin access still works.</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="maintenance_mode" value="1"
                                        {{ ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon tag-neutral"><i
                                    class="bi bi-info-circle"></i></div>
                            <div>
                                <h3>System Info</h3>
                                <p>Current environment details</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="two-col">
                                <div>
                                    <div class="text-muted-theme" style="font-size: 14px;margin-bottom:3px;">Laravel
                                        Version</div>
                                    <div class="fw-medium">{{ app()->version() }}</div>
                                </div>
                                <div>
                                    <div class="text-muted-theme" style="font-size: 14px;margin-bottom:3px;">PHP Version
                                    </div>
                                    <div class="fw-medium">{{ phpversion() }}</div>
                                </div>
                                <div>
                                    <div class="text-muted-theme" style="font-size: 14px;margin-bottom:3px;">Environment
                                    </div>
                                    <div class="fw-medium">{{ app()->environment() }}</div>
                                </div>
                                <div>
                                    <div class="text-muted-theme" style="font-size: 14px;margin-bottom:3px;">Server Time
                                    </div>
                                    <div class="fw-medium">{{ now()->format('M d, Y h:i A') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Save Bar --}}
                <div class="submit-bar">
                    <div class="submit-info">
                        <i class="bi bi-clock me-1"></i>
                        Last changed:
                        {{ $lastSavedAt ? \Carbon\Carbon::parse($lastSavedAt)->format('M d, Y h:i A') : 'never' }}
                    </div>
                    <button type="submit" class="btn-save">
                        <i class="bi bi-check-circle"></i> Save Settings
                    </button>
                </div>

            </div>
        </div>
    </form>

@endsection

@push('scripts')
    <script>
        function showTab(name) {
            document.querySelectorAll('.settings-section').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-link').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + name).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        function addAmenityRow() {
            const wrap = document.createElement('div');
            wrap.className = 'amenity-row';
            wrap.style.cssText = 'display:flex;gap:8px;margin-bottom:10px;';
            wrap.innerHTML = `
        <input type="text" name="amenities[]" class="form-control" placeholder="e.g. WiFi">
        <button type="button" class="btn-remove-amenity" onclick="this.parentElement.remove()"
            style="background:#fee2e2;color:#b91c1c;border:none;border-radius:8px;padding:0 14px;cursor:pointer;">
            <i class="bi bi-trash"></i>
        </button>
    `;
            document.getElementById('amenitiesRepeater').appendChild(wrap);
            wrap.querySelector('input').focus();
        }
    </script>
@endpush

