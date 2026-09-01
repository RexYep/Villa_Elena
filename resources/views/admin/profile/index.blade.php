@extends('layouts.admin')

@section('title', 'My Account — Villa Elena Admin')
@section('page-title', 'My Account')
@section('page-subtitle', 'Manage your own admin account')

@push('styles')
    <style>
        .account-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
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
            font-family: 'Cormorant Garamond', serif;
            font-size: 17px;
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

        .mb-16 {
            margin-bottom: 16px;
        }

        .btn-save {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 11px 28px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background .2s;
        }

        .btn-save:hover {
            background: var(--gold);
        }

        .account-identity {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }

        .account-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--terracotta);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 600;
            font-family: 'Cormorant Garamond', serif;
            flex-shrink: 0;
        }

        .account-identity .name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-main);
        }

        .account-identity .email {
            font-size: 14px;
            color: var(--muted);
            margin-top: 2px;
        }

        @media (max-width: 900px) {
            .account-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="settings-card">
        <div class="account-identity">
            <div class="account-avatar">{{ strtoupper(substr($user->full_name ?? 'A', 0, 1)) }}</div>
            <div>
                <div class="name">{{ $user->full_name }}</div>
                <div class="email">{{ $user->email }} · {{ ucfirst($user->role) }}</div>
            </div>
        </div>
    </div>

    <div class="account-layout">

        {{-- Account Info --}}
        <form method="POST" action="{{ route('admin.profile.update') }}">
            @csrf @method('PUT')

            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="icon"><i class="bi bi-person"></i></div>
                    <div>
                        <h3>Account Info</h3>
                        <p>Your name and contact number</p>
                    </div>
                </div>
                <div class="settings-card-body">
                    <div class="mb-16">
                        <label class="form-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name"
                            class="form-control @error('full_name') is-invalid @enderror"
                            value="{{ old('full_name', $user->full_name) }}" required>
                        @error('full_name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="mb-16">
                        <label class="form-label">Phone Number <span class="req">*</span></label>
                        <input type="text" name="phone"
                            class="form-control @error('phone') is-invalid @enderror"
                            value="{{ old('phone', $user->phone) }}" required>
                        @error('phone')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <span class="hint">Email address can't be changed here — contact a developer if it needs to
                        change.</span>
                </div>
            </div>

            <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Save Info</button>
        </form>

        {{-- Change Password --}}
        <form method="POST" action="{{ route('admin.profile.password') }}">
            @csrf @method('PUT')

            <div class="settings-card">
                <div class="settings-card-header">
                    <div class="icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-shield-lock"></i>
                    </div>
                    <div>
                        <h3>Change Password</h3>
                        <p>Update your login password</p>
                    </div>
                </div>
                <div class="settings-card-body">
                    <div class="mb-16">
                        <label class="form-label">Current Password <span class="req">*</span></label>
                        <input type="password" name="current_password"
                            class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="mb-16">
                        <label class="form-label">New Password <span class="req">*</span></label>
                        <input type="password" name="password"
                            class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <span class="hint">At least 8 characters.</span>
                    </div>
                    <div>
                        <label class="form-label">Confirm New Password <span class="req">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-save"><i class="bi bi-check-lg"></i> Change Password</button>
        </form>

    </div>

@endsection
