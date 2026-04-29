<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — Villa Elena Admin</title>
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

        /* Layout */
        .settings-layout{display:grid;grid-template-columns:220px 1fr;gap:24px;align-items:start;}

        /* Tab Nav */
        .tab-nav{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;position:sticky;top:calc(var(--topbar-h) + 24px);}
        .tab-nav-header{padding:16px 18px;border-bottom:1px solid var(--border);}
        .tab-nav-header h3{font-family:'Cormorant Garamond',serif;font-size:16px;font-weight:600;}
        .tab-link{display:flex;align-items:center;gap:10px;padding:11px 18px;font-size:13px;color:var(--text-muted);cursor:pointer;border:none;background:none;width:100%;text-align:left;transition:all .2s;border-left:3px solid transparent;}
        .tab-link:hover{background:#f8fafc;color:var(--text-main);}
        .tab-link.active{background:var(--gold-dim);color:var(--text-main);font-weight:600;border-left-color:var(--gold);}
        .tab-link i{font-size:15px;width:18px;text-align:center;}

        /* Cards */
        .settings-section{display:none;}
        .settings-section.active{display:block;}
        .settings-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .settings-card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
        .settings-card-header .icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
        .settings-card-header h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;}
        .settings-card-header p{font-size:12px;color:var(--text-muted);margin-top:2px;}
        .settings-card-body{padding:24px;}

        /* Form */
        .form-label{font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;display:block;}
        .hint{font-size:11px;color:#94a3b8;margin-top:4px;display:block;}
        .req{color:#ef4444;}
        .form-control,.form-select{border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;font-size:14px;font-family:'DM Sans',sans-serif;width:100%;transition:border-color .2s;background:#fff;}
        .form-control:focus,.form-select:focus{outline:none;border-color:var(--navy-mid);box-shadow:0 0 0 3px rgba(26,47,69,0.08);}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
        .mb-16{margin-bottom:16px;}

        /* Toggle Switch */
        .toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid #f1f5f9;}
        .toggle-row:last-child{border-bottom:none;}
        .toggle-info .toggle-title{font-size:14px;font-weight:500;}
        .toggle-info .toggle-desc{font-size:12px;color:var(--text-muted);margin-top:2px;}
        .toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0;}
        .toggle-switch input{opacity:0;width:0;height:0;}
        .toggle-slider{position:absolute;cursor:pointer;inset:0;background:#d1d5db;border-radius:100px;transition:.3s;}
        .toggle-slider:before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;}
        .toggle-switch input:checked + .toggle-slider{background:var(--navy);}
        .toggle-switch input:checked + .toggle-slider:before{transform:translateX(20px);}

        /* Submit */
        .submit-bar{background:#fff;border-radius:12px;border:1px solid var(--border);padding:16px 24px;display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;position:sticky;bottom:24px;box-shadow:0 4px 20px rgba(0,0,0,0.08);}
        .btn-save{background:var(--navy);color:#fff;border:none;border-radius:9px;padding:11px 28px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:8px;transition:opacity .2s;}
        .btn-save:hover{opacity:.88;}
        .submit-info{font-size:12px;color:var(--text-muted);}

        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-success{background:#dcfce7;color:#15803d;}

        /* Maintenance banner */
        .maintenance-banner{background:#fef9c3;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;font-size:13px;color:#a16207;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
    </style>
</head>
<body>

@include('admin.partials.sidebar')


<header class="topbar">
    <div class="topbar-left">
        <h2>Settings</h2>
        <p>Configure your resort system</p>
    </div>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
</header>

<main class="main-content">

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
                            <div class="icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-building"></i></div>
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
                            <div class="icon" style="background:#f3e8ff;color:#7c3aed;"><i class="bi bi-toggles"></i></div>
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
                            <div class="icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-credit-card"></i></div>
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
                            <div class="icon" style="background:#e0f2fe;color:#0369a1;"><i class="bi bi-bell"></i></div>
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
                                <label class="form-label"><i class="bi bi-instagram text-danger me-1"></i> Instagram</label>
                                <input type="url" name="instagram_url" class="form-control"
                                    value="{{ $settings['instagram_url'] ?? '' }}"
                                    placeholder="https://instagram.com/villaelenaresort">
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
                            <div class="icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-shield-gear"></i></div>
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
                                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:3px;">Laravel Version</div>
                                    <div style="font-weight:500;">{{ app()->version() }}</div>
                                </div>
                                <div>
                                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:3px;">PHP Version</div>
                                    <div style="font-weight:500;">{{ phpversion() }}</div>
                                </div>
                                <div>
                                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:3px;">Environment</div>
                                    <div style="font-weight:500;">{{ app()->environment() }}</div>
                                </div>
                                <div>
                                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:3px;">Server Time</div>
                                    <div style="font-weight:500;">{{ now()->format('M d, Y h:i A') }}</div>
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

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showTab(name) {
    document.querySelectorAll('.settings-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-link').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.currentTarget.classList.add('active');
}
</script>
@include('admin.partials.realtime') 
</body>
</html>