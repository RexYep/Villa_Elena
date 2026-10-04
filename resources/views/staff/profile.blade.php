@extends('layouts.staff')

@section('title', 'My Account — Villa Elena Staff')
@section('page-title', 'My Account')
@section('page-subtitle', 'Your name, contact number and password')

@push('styles')
    <style>
        .account-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start;
        }

        .account-card {
            background: var(--white);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .account-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
        }

        .account-card-header h3 {
            font-family: var(--font-display);
            font-size: 17px;
            font-weight: 600;
            color: var(--text-main);
            margin: 0;
        }

        .account-card-header p {
            font-size: 14px;
            color: var(--muted);
            margin: 2px 0 0;
        }

        .account-card-body {
            padding: 24px;
        }

        .account-field {
            margin-bottom: 16px;
        }

        .account-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .account-hint {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-top: 6px;
        }

        .account-error {
            display: block;
            font-size: 13px;
            color: #dc2626;
            margin-top: 6px;
        }

        .btn-account-save {
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 11px 28px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }

        .btn-account-save:hover {
            background: var(--gold);
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

    <div class="account-layout">

        <form method="POST" action="{{ route('staff.profile.update') }}">
            @csrf @method('PUT')

            <div class="account-card">
                <div class="account-card-header">
                    <h3>Account Info</h3>
                    <p>{{ $user->email }} · {{ ucfirst($user->role) }}</p>
                </div>
                <div class="account-card-body">
                    <div class="account-field">
                        <label for="full_name">Full Name</label>
                        <input type="text" name="full_name" id="full_name" autocomplete="name"
                            class="form-control @error('full_name') is-invalid @enderror"
                            value="{{ old('full_name', $user->full_name) }}" required>
                        @error('full_name')
                            <span class="account-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="account-field">
                        <label for="phone">Phone Number</label>
                        <input type="tel" name="phone" id="phone" autocomplete="tel"
                            class="form-control @error('phone') is-invalid @enderror"
                            value="{{ old('phone', $user->phone) }}" required>
                        @error('phone')
                            <span class="account-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <span class="account-hint">Your email address can only be changed by an admin.</span>
                </div>
            </div>

            <button type="submit" class="btn-account-save"><i class="bi bi-check-lg"></i> Save Info</button>
        </form>

        <form method="POST" action="{{ route('staff.profile.password') }}">
            @csrf @method('PUT')

            <div class="account-card">
                <div class="account-card-header">
                    <h3>Change Password</h3>
                    <p>Update your login password</p>
                </div>
                <div class="account-card-body">
                    <div class="account-field">
                        <label for="current_password">Current Password</label>
                        <input type="password" name="current_password" id="current_password"
                            autocomplete="current-password"
                            class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')
                            <span class="account-error">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="account-field">
                        <label for="password">New Password</label>
                        <input type="password" name="password" id="password" autocomplete="new-password"
                            class="form-control @error('password') is-invalid @enderror" required minlength="8">
                        @error('password')
                            <span class="account-error">{{ $message }}</span>
                        @enderror
                        <span class="account-hint">At least 8 characters.</span>
                    </div>
                    <div class="account-field">
                        <label for="password_confirmation">Confirm New Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                            autocomplete="new-password" class="form-control" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-account-save"><i class="bi bi-check-lg"></i> Change Password</button>
        </form>

    </div>

@endsection
