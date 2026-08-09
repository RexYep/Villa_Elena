@extends('layouts.admin')

@section('title', 'Settings — Villa Elena Admin')
@section('page-title', 'Settings')
@section('page-subtitle', 'Configure your resort system')

@push('styles')
<style>
/* Layout */
.settings-layout{display:grid;grid-template-columns:220px 1fr;gap:24px;align-items:start;}

/* Tab Nav */
.tab-nav{background:var(--cream);border-radius:14px;border:1px solid var(--border);overflow:hidden;position:sticky;top:calc(var(--topbar-h) + 24px);}
.tab-nav-header{padding:16px 18px;border-bottom:1px solid var(--border);}
.tab-nav-header h3{font-family:'Cormorant Garamond',serif;font-size:16px;font-weight:600;color:var(--text-main);}
.tab-link{display:flex;align-items:center;gap:10px;padding:11px 18px;font-size:13px;color:var(--muted);cursor:pointer;border:none;background:none;width:100%;text-align:left;transition:all .2s;border-left:3px solid transparent;}
.tab-link:hover{background:var(--sand);color:var(--text-main);}
.tab-link.active{background:var(--gold-dim);color:var(--text-main);font-weight:600;border-left-color:var(--terracotta);}
.tab-link i{font-size:15px;width:18px;text-align:center;}

/* Cards */
.settings-section{display:none;}
.settings-section.active{display:block;}
.settings-card{background:var(--cream);border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
.settings-card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
.settings-card-header .icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;background:var(--gold-dim);color:var(--gold);}
.settings-card-header h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;color:var(--text-main);}
.settings-card-header p{font-size:12px;color:var(--muted);margin-top:2px;}
.settings-card-body{padding:24px;}

/* Form */
.three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
.mb-16{margin-bottom:16px;}

/* Toggle Switch */
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);}
.toggle-row:last-child{border-bottom:none;}
.toggle-info .toggle-title{font-size:14px;font-weight:500;color:var(--text-main);}
.toggle-info .toggle-desc{font-size:12px;color:var(--muted);margin-top:2px;}
.toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0;}
.toggle-switch input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;cursor:pointer;inset:0;background:#d1d5db;border-radius:100px;transition:.3s;}
.toggle-slider:before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;}
.toggle-switch input:checked + .toggle-slider{background:var(--terracotta);}
.toggle-switch input:checked + .toggle-slider:before{transform:translateX(20px);}

/* Submit */
.submit-bar{background:var(--cream);border-radius:12px;border:1px solid var(--border);padding:16px 24px;display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;position:sticky;bottom:24px;box-shadow:0 4px 20px rgba(44,36,22,0.1);}
.btn-save{background:var(--terracotta);color:#fff;border:none;border-radius:9px;padding:11px 28px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:8px;transition:background .2s;}
.btn-save:hover{background:var(--gold);}
.submit-info{font-size:12px;color:var(--muted);}

/* Maintenance banner — semantic warning, unchanged */
.maintenance-banner{background:#fef9c3;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;font-size:13px;color:#a16207;margin-bottom:16px;display:flex;align-items:center;gap:8px;}

@media (max-width: 900px) {
    .settings-layout{grid-template-columns:1fr;}
    .tab-nav{position:static;}
    .three-col{grid-template-columns:1fr 1fr;}
}
@media (max-width: 560px) {
    .three-col{grid-template-columns:1fr;}
    .submit-bar{flex-direction:column;align-items:stretch;gap:10px;position:static;}
}
</style>
@endpush

@section('content')

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    @if(($settings['maintenance_mode'] ?? '0') === '1')
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
                <div class="tab-nav-header"><h3>Settings</h3></div>
                <button type="button" class="tab-link active" onclick="showTab('resort')">
                    <i class="bi bi-building"></i> Resort Info
                </button>
                <button type="button" class="tab-link" onclick="showTab('booking')">
                    <i class="bi bi-calendar-check"></i> Booking Rules
                </button>
                <button type="button" class="tab-link" onclick="showTab('payments')">
                    <i class="bi bi-credit-card"></i> Payments
                </button>
                <button type="button" class="tab-link" onclick="showTab('notifications')">
                    <i class="bi bi-bell"></i> Notifications
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
                                <label class="form-label">Resort Name <span class="req">*</span></label>
                                <input type="text" name="resort_name" class="form-control"
                                    value="{{ $settings['resort_name'] ?? 'Villa Elena Private Rental Resort' }}" required>
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    <label class="form-label">Email Address <span class="req">*</span></label>
                                    <input type="email" name="resort_email" class="form-control"
                                        value="{{ $settings['resort_email'] ?? '' }}" required>
                                </div>
                                <div>
                                    <label class="form-label">Phone Number <span class="req">*</span></label>
                                    <input type="text" name="resort_phone" class="form-control"
                                        value="{{ $settings['resort_phone'] ?? '' }}" required>
                                </div>
                            </div>
                            <div class="mb-16">
                                <label class="form-label">Full Address</label>
                                <input type="text" name="resort_address" class="form-control"
                                    value="{{ $settings['resort_address'] ?? '' }}"
                                    placeholder="e.g. Calamba, Laguna, Philippines">
                            </div>
                            <div>
                                <label class="form-label">Resort Description</label>
                                <textarea name="resort_description" class="form-control" rows="4"
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
                            <div class="icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-calendar-check"></i></div>
                            <div>
                                <h3>Booking Rules</h3>
                                <p>Check-in/out times, stay limits, and hold settings</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="two-col mb-16">
                                <div>
                                    <label class="form-label">Check-in Time</label>
                                    <input type="time" name="check_in_time" class="form-control"
                                        value="{{ $settings['check_in_time'] ?? '14:00' }}">
                                </div>
                                <div>
                                    <label class="form-label">Check-out Time</label>
                                    <input type="time" name="check_out_time" class="form-control"
                                        value="{{ $settings['check_out_time'] ?? '12:00' }}">
                                </div>
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    <label class="form-label">Minimum Stay (nights)</label>
                                    <input type="number" name="min_stay_nights" class="form-control"
                                        value="{{ $settings['min_stay_nights'] ?? '1' }}" min="1">
                                    <span class="hint">Guests cannot book fewer nights than this.</span>
                                </div>
                                <div>
                                    <label class="form-label">Max Advance Booking (days)</label>
                                    <input type="number" name="max_advance_days" class="form-control"
                                        value="{{ $settings['max_advance_days'] ?? '365' }}" min="1">
                                    <span class="hint">How far ahead guests can make reservations.</span>
                                </div>
                            </div>
                            <div class="two-col mb-16">
                                <div>
                                    <label class="form-label">Booking Hold (minutes)</label>
                                    <input type="number" name="booking_hold_minutes" class="form-control"
                                        value="{{ $settings['booking_hold_minutes'] ?? '15' }}" min="1">
                                    <span class="hint">Time a pending booking reserves the property before expiring.</span>
                                </div>
                                <div>
                                    <label class="form-label">Cancellation Window (hours)</label>
                                    <input type="number" name="cancellation_hours" class="form-control"
                                        value="{{ $settings['cancellation_hours'] ?? '48' }}" min="0">
                                    <span class="hint">Guests can cancel free of charge within this window.</span>
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
                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <div class="toggle-title">Require ID Upload</div>
                                    <div class="toggle-desc">Guests must upload a valid ID during booking</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="require_id_upload" value="1"
                                        {{ ($settings['require_id_upload'] ?? '0') === '1' ? 'checked' : '' }}>
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
                                <p>Currency, deposit, and tax configuration</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="three-col mb-16">
                                <div>
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-select">
                                        <option value="PHP" {{ ($settings['currency'] ?? 'PHP') === 'PHP' ? 'selected' : '' }}>PHP — Philippine Peso</option>
                                        <option value="USD" {{ ($settings['currency'] ?? '') === 'USD' ? 'selected' : '' }}>USD — US Dollar</option>
                                        <option value="EUR" {{ ($settings['currency'] ?? '') === 'EUR' ? 'selected' : '' }}>EUR — Euro</option>
                                        <option value="SGD" {{ ($settings['currency'] ?? '') === 'SGD' ? 'selected' : '' }}>SGD — Singapore Dollar</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Deposit Percentage (%)</label>
                                    <input type="number" name="deposit_percentage" class="form-control"
                                        value="{{ $settings['deposit_percentage'] ?? '30' }}" min="0" max="100" step="1">
                                    <span class="hint">% of total required to confirm booking.</span>
                                </div>
                                <div>
                                    <label class="form-label">Tax Percentage (%)</label>
                                    <input type="number" name="tax_percentage" class="form-control"
                                        value="{{ $settings['tax_percentage'] ?? '0' }}" min="0" max="100" step="0.01">
                                    <span class="hint">Leave at 0 if tax is already included.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notifications --}}
                <div class="settings-section" id="tab-notifications">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon tag-cyan"><i class="bi bi-bell"></i></div>
                            <div>
                                <h3>Notification Preferences</h3>
                                <p>Control when and how notifications are sent</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <div class="toggle-title">Email Notifications</div>
                                    <div class="toggle-desc">Send booking confirmations, reminders, and updates via email</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="send_email_notifications" value="1"
                                        {{ ($settings['send_email_notifications'] ?? '1') === '1' ? 'checked' : '' }}>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Amenities --}}
                <div class="settings-section" id="tab-amenities">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon" style="background:#dcfce7;color:#15803d;"><i class="bi bi-stars"></i></div>
                            <div>
                                <h3>Property Amenities</h3>
                                <p>Manage the amenity options offered when adding/editing the Villa</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div id="amenitiesRepeater">
                                @php
                                    $currentAmenities = json_decode($settings['property_amenities'] ?? '[]', true) ?: [];
                                @endphp
                                @forelse($currentAmenities as $amenity)
                                    <div class="amenity-row" style="display:flex;gap:8px;margin-bottom:10px;">
                                        <input type="text" name="amenities[]" class="form-control" value="{{ $amenity }}">
                                        <button type="button" class="btn-remove-amenity" onclick="this.parentElement.remove()"
                                            style="background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:0 14px;cursor:pointer;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                @empty
                                    <div class="amenity-row" style="display:flex;gap:8px;margin-bottom:10px;">
                                        <input type="text" name="amenities[]" class="form-control" placeholder="e.g. WiFi">
                                        <button type="button" class="btn-remove-amenity" onclick="this.parentElement.remove()"
                                            style="background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:0 14px;cursor:pointer;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                            <button type="button" onclick="addAmenityRow()"
                                style="background:var(--sand);color:var(--text-main);border:none;border-radius:8px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;margin-top:6px;">
                                <i class="bi bi-plus-circle me-1"></i> Add Amenity
                            </button>
                            <span class="hint" style="display:block;margin-top:10px;">These appear as checkboxes when creating/editing the Villa property.</span>
                        </div>
                    </div>
                </div>

                {{-- Social --}}
                <div class="settings-section" id="tab-social">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="icon" style="background:#fce7f3;color:#be185d;"><i class="bi bi-share"></i></div>
                            <div>
                                <h3>Social Media & Links</h3>
                                <p>External links shown on the booking portal</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="mb-16">
                                <label class="form-label"><i class="bi bi-facebook text-primary me-1"></i> Facebook Page</label>
                                <input type="url" name="facebook_url" class="form-control"
                                    value="{{ $settings['facebook_url'] ?? '' }}"
                                    placeholder="https://facebook.com/villaelenaresort">
                            </div>
                            <div class="mb-16">
                                <label class="form-label"><i class="bi bi-tiktok me-1"></i> TikTok</label>
                                <input type="url" name="tiktok_url" class="form-control"
                                    value="{{ $settings['tiktok_url'] ?? '' }}"
                                    placeholder="https://tiktok.com/@villaelenaresort">
                            </div>
                            <div>
                                <label class="form-label"><i class="bi bi-geo-alt text-success me-1"></i> Google Maps Link</label>
                                <input type="url" name="google_maps_url" class="form-control"
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
                            <div class="icon tag-red"><i class="bi bi-shield-gear"></i></div>
                            <div>
                                <h3>System Settings</h3>
                                <p>Maintenance mode and advanced controls</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="toggle-row">
                                <div class="toggle-info">
                                    <div class="toggle-title" style="color:#dc2626;">⚠️ Maintenance Mode</div>
                                    <div class="toggle-desc">Hides the guest-facing portal and shows a maintenance page to visitors. Admin access still works.</div>
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
                            <div class="icon" style="background:#f1f5f9;color:#475569;"><i class="bi bi-info-circle"></i></div>
                            <div>
                                <h3>System Info</h3>
                                <p>Current environment details</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="two-col">
                                <div>
                                    <div class="text-muted-theme" style="font-size:12px;margin-bottom:3px;">Laravel Version</div>
                                    <div class="fw-medium">{{ app()->version() }}</div>
                                </div>
                                <div>
                                    <div class="text-muted-theme" style="font-size:12px;margin-bottom:3px;">PHP Version</div>
                                    <div class="fw-medium">{{ phpversion() }}</div>
                                </div>
                                <div>
                                    <div class="text-muted-theme" style="font-size:12px;margin-bottom:3px;">Environment</div>
                                    <div class="fw-medium">{{ app()->environment() }}</div>
                                </div>
                                <div>
                                    <div class="text-muted-theme" style="font-size:12px;margin-bottom:3px;">Server Time</div>
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
                        Last saved: {{ now()->format('M d, Y h:i A') }}
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
            style="background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:0 14px;cursor:pointer;">
            <i class="bi bi-trash"></i>
        </button>
    `;
    document.getElementById('amenitiesRepeater').appendChild(wrap);
    wrap.querySelector('input').focus();
}
</script>
@endpush
