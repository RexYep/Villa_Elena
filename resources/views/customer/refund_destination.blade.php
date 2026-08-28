@extends('layouts.customer')

@section('title', 'Where should we send your refund? — Villa Elena')

@push('styles')
    <style>
        .main {
            max-width: 600px;
        }

        .page-title {
            font-weight: 700;
        }

        .booking-summary {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--sand);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .summary-name {
            font-weight: 600;
            font-size: 15px;
        }

        .summary-dates {
            font-size: 12px;
            color: var(--muted);
            margin-top: 3px;
        }

        .refund-amount {
            margin-left: auto;
            text-align: right;
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--stone);
            white-space: nowrap;
        }

        .refund-amount small {
            display: block;
            font-family: 'Jost', sans-serif;
            font-size: 11px;
            font-weight: 500;
            color: var(--muted);
            letter-spacing: .3px;
        }

        .form-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .form-card-head {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
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
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            display: block;
            margin-bottom: 8px;
            letter-spacing: .2px;
        }

        .form-control {
            padding: 11px 14px;
        }

        .form-hint {
            font-size: 11px;
            color: var(--muted);
            margin-top: 6px;
            line-height: 1.5;
        }

        .mb-16 {
            margin-bottom: 16px;
        }

        .is-invalid {
            border-color: #dc2626 !important;
        }

        .field-error {
            font-size: 11px;
            color: #dc2626;
            margin-top: 4px;
        }

        .notice-box {
            background: #f9f5ee;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .warn-box {
            background: #fef6e7;
            border: 1px solid #f5d99b;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 12px;
            color: #7a5b16;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .btn-submit {
            background: var(--stone);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            width: 100%;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Jost', sans-serif;
            margin-top: 8px;
            transition: all .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--gold);
            color: var(--stone);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .btn-back:hover {
            color: var(--stone);
        }

        .saved-note {
            font-size: 12px;
            color: var(--muted);
            margin-top: 14px;
            text-align: center;
        }

        @media (max-width:480px) {
            .booking-summary {
                flex-wrap: wrap;
            }

            .refund-amount {
                margin-left: 0;
                text-align: left;
            }
        }
    </style>
@endpush

@section('content')
    <a href="{{ route('customer.bookings.show', $booking) }}" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back to booking
    </a>

    <div class="page-title">Where should we send your refund?</div>
    <div class="page-sub">Tell us which bank or e-wallet account should receive the money</div>

    @if ($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="booking-summary">
        <div class="summary-icon">💸</div>
        <div>
            <div class="summary-name">{{ $booking->booking_ref }}</div>
            <div class="summary-dates">
                {{ $booking->check_in_date->format('M d, Y') }} · Refund approved
                {{ $payment->payment_date?->format('M d, Y') }}
            </div>
        </div>
        <div class="refund-amount">
            ₱{{ number_format($payment->amount, 2) }}
            <small>REFUND DUE</small>
        </div>
    </div>

    {{-- Ang detalyeng ito ang eksaktong gagamitin sa paglilipat, at
         WALANG paraan para patunayan ang pangalan bago ipadala — walang
         account-name-inquiry endpoint ang PayMongo. Kaya ang babalang
         ito ang tanging depensa laban sa isang bumagsak na transfer. --}}
    <div class="warn-box">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <strong>Please double-check these details.</strong>
        We send the money exactly as entered. If the account name or number is wrong,
        the transfer will fail or be delayed, and we will have to ask you again.
    </div>

    <div class="notice-box">
        <i class="bi bi-info-circle me-1"></i>
        You can use any account you like — it does not have to be the one you paid with.
    </div>

    @if (empty($institutions))
        {{-- Hindi maabot ang listahan ng PayMongo at wala ring stale na
             kopya. Mas mabuting sabihin ito kaysa magpakita ng blangkong
             dropdown na mukhang gumagana pero hindi makakapag-save. --}}
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            We can't load the list of banks and e-wallets right now. Please try again in a few minutes —
            your refund is safe and still recorded.
        </div>
    @else
        <form method="POST" action="{{ route('customer.refunds.destination.update', $payment) }}">
            @csrf
            @method('PUT')
            <div class="form-card">
                <div class="form-card-head">
                    <h3>Refund Account</h3>
                </div>
                <div class="form-card-body">

                    <div class="mb-16">
                        <label class="form-label" for="institution_bic">Bank or E-Wallet</label>
                        <select name="institution_bic" id="institution_bic"
                            class="form-control @error('institution_bic') is-invalid @enderror" required>
                            <option value="">Select where to send it…</option>
                            @foreach ($institutions as $institution)
                                <option value="{{ $institution['bic'] }}"
                                    @selected(old('institution_bic', $destination->institution_bic ?? '') === $institution['bic'])>
                                    {{ $institution['name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('institution_bic')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        <div class="form-hint">GCash is listed as <strong>G-Xchange, Inc.</strong></div>
                    </div>

                    <div class="mb-16">
                        <label class="form-label" for="account_number">Account / Mobile Number</label>
                        <input type="text" name="account_number" id="account_number" inputmode="numeric"
                            class="form-control @error('account_number') is-invalid @enderror"
                            value="{{ old('account_number', $destination->account_number ?? $suggestedNumber) }}"
                            placeholder="09171234567" required>
                        @error('account_number')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        <div class="form-hint">
                            Digits only — no spaces or dashes.
                            For GCash and Maya this is your 11-digit mobile number.
                            @if ($suggestedNumber && ! ($destination->account_number ?? null))
                                We've filled in the number on your profile — please confirm it's the right one.
                            @endif
                        </div>
                    </div>

                    <div class="mb-16">
                        <label class="form-label" for="account_name">Account Name</label>
                        <input type="text" name="account_name" id="account_name"
                            class="form-control @error('account_name') is-invalid @enderror"
                            value="{{ old('account_name', $destination->account_name ?? $booking->user->full_name ?? '') }}"
                            placeholder="Juan Dela Cruz" required>
                        @error('account_name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        <div class="form-hint">
                            Exactly as it appears on the account — not a nickname.
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-send-check"></i>
                        {{ $destination ? 'Update Refund Details' : 'Save Refund Details' }}
                    </button>
                </div>
            </div>
        </form>

        @if ($destination)
            <div class="saved-note">
                Last updated {{ $destination->provided_at?->format('M d, Y g:i A') }}.
                You can change this until the refund is sent.
            </div>
        @endif
    @endif
@endsection
