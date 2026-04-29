<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($user) ? 'Edit' : 'Add' }} User — Villa Elena Admin</title>
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
        .breadcrumb-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);margin-bottom:24px;}
        .breadcrumb-row a{color:var(--text-muted);text-decoration:none;}
        .breadcrumb-row a:hover{color:var(--navy);}
        .breadcrumb-row .sep{color:#cbd5e1;}
        .breadcrumb-row .current{color:var(--text-main);font-weight:500;}

        .form-wrapper{max-width:720px;}
        .form-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
        .form-card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
        .form-card-header .card-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;}
        .form-card-header h3{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;}
        .form-card-body{padding:24px;}
        .form-label{font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;display:block;}
        .req{color:#ef4444;margin-left:2px;}
        .form-control,.form-select{border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;font-size:14px;font-family:'DM Sans',sans-serif;width:100%;transition:border-color .2s;background:#fff;}
        .form-control:focus,.form-select:focus{outline:none;border-color:var(--navy-mid);box-shadow:0 0 0 3px rgba(26,47,69,0.08);}
        .is-invalid{border-color:#ef4444 !important;}
        .invalid-feedback{font-size:12px;color:#ef4444;margin-top:4px;display:block;}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .input-group .form-control{border-right:none;}
        .input-group .btn-outline-secondary{border:1.5px solid var(--border);border-left:none;border-radius:0 8px 8px 0;background:#fff;color:var(--text-muted);}
        .btn-submit{background:var(--navy);color:#fff;border:none;border-radius:9px;padding:12px 28px;font-size:14px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .2s;}
        .btn-submit:hover{opacity:.88;}
        .btn-cancel-link{color:var(--text-muted);font-size:13px;text-decoration:none;margin-left:16px;}
        .btn-cancel-link:hover{color:var(--navy);}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;margin-bottom:20px;border:none;}
        .alert-danger{background:#fee2e2;color:#dc2626;}
        .hint{font-size:11px;color:#94a3b8;margin-top:4px;display:block;}
    </style>
</head>
<body>

@include('admin.partials.sidebar')


<header class="topbar">
    <div class="topbar-left">
        <h2>{{ isset($user) ? 'Edit User' : 'Add New User' }}</h2>
        <p>{{ isset($user) ? 'Update profile for ' . $user->full_name : 'Create a new guest, staff, or admin account' }}</p>
    </div>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</button>
    </form>
</header>

<main class="main-content">

    <div class="breadcrumb-row">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <span class="sep">›</span>
        <a href="{{ route('admin.users.index') }}">Users</a>
        <span class="sep">›</span>
        <span class="current">{{ isset($user) ? 'Edit: '.$user->full_name : 'Add New' }}</span>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            @foreach($errors->all() as $error){{ $error }}. @endforeach
        </div>
    @endif

    <div class="form-wrapper">
        <form method="POST"
              action="{{ isset($user) ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if(isset($user)) @method('PUT') @endif

            {{-- Basic Info --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon" style="background:#e0f2fe;color:#0369a1;"><i class="bi bi-person"></i></div>
                    <h3>Basic Information</h3>
                </div>
                <div class="form-card-body">
                    <div style="margin-bottom:16px;">
                        <label class="form-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror"
                            value="{{ old('full_name', $user->full_name ?? '') }}"
                            placeholder="Juan dela Cruz" required>
                        @error('full_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="two-col" style="margin-bottom:16px;">
                        <div>
                            <label class="form-label">Email Address <span class="req">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $user->email ?? '') }}"
                                placeholder="guest@example.com" required>
                            @error('email')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label class="form-label">Phone Number <span class="req">*</span></label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                value="{{ old('phone', $user->phone ?? '') }}"
                                placeholder="09XX-XXX-XXXX" required>
                            @error('phone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Role <span class="req">*</span></label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="customer" {{ old('role', $user->role ?? 'customer') == 'customer' ? 'selected' : '' }}>👤 Guest (Customer)</option>
                            <option value="staff"    {{ old('role', $user->role ?? '') == 'staff'    ? 'selected' : '' }}>🏷️ Staff</option>
                            <option value="admin"    {{ old('role', $user->role ?? '') == 'admin'    ? 'selected' : '' }}>⚙️ Admin</option>
                        </select>
                        @error('role')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            {{-- ID & Address --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon" style="background:#dcfce7;color:#15803d;"><i class="bi bi-card-text"></i></div>
                    <h3>ID & Address</h3>
                </div>
                <div class="form-card-body">
                    <div class="two-col" style="margin-bottom:16px;">
                        <div>
                            <label class="form-label">ID Type</label>
                            <select name="id_type" class="form-select">
                                <option value="">None provided</option>
                                @foreach(["Driver's License","Passport","SSS ID","PhilHealth ID","Voter's ID","National ID","PRC ID","Postal ID"] as $idType)
                                    <option value="{{ $idType }}" {{ old('id_type', $user->id_type ?? '') == $idType ? 'selected' : '' }}>
                                        {{ $idType }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">ID Number</label>
                            <input type="text" name="id_number" class="form-control"
                                value="{{ old('id_number', $user->id_number ?? '') }}"
                                placeholder="ID number">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Home Address</label>
                        <textarea name="address" class="form-control" rows="2"
                            placeholder="Full home address">{{ old('address', $user->address ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Password --}}
            <div class="form-card">
                <div class="form-card-header">
                    <div class="card-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="bi bi-shield-lock"></i></div>
                    <h3>{{ isset($user) ? 'Change Password' : 'Set Password' }}</h3>
                </div>
                <div class="form-card-body">
                    @if(isset($user))
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
                                    placeholder="Min. 8 characters"
                                    {{ !isset($user) ? 'required' : '' }}>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('password',this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                                @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Confirm Password {{ !isset($user) ? '*' : '' }}</label>
                            <div class="input-group">
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                    class="form-control"
                                    placeholder="Repeat password"
                                    {{ !isset($user) ? 'required' : '' }}>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePw('password_confirmation',this)">
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

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('bi-eye');
    icon.classList.toggle('bi-eye-slash');
}
</script>
@include('admin.partials.realtime') 
</body>
</html>