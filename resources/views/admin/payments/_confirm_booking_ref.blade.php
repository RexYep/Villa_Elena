{{-- Kinukumpirma na ang refund na isinasara ay ang tamang booking.
     Sinusuri ito ng PaymentController::payoutConfirmationProblem() —
     ang tunay pero IBANG booking reference ay tinatanggihan.
     $bookingRef: ang inaasahang reference (null sa modal ng listahan,
     kung saan pinupunan ito ng JS). --}}
<div style="margin-bottom:12px;">
    <label class="field-lbl form-label">Confirm Booking Reference</label>
    <input type="text" name="confirm_booking_ref" class="form-control" required maxlength="32"
        autocomplete="off" style="text-transform:uppercase;"
        @isset($inputId) id="{{ $inputId }}" @endisset
        placeholder="{{ $bookingRef ?? 'VE-XXXXXXXX' }}"
        value="{{ old('confirm_booking_ref') }}">
    <div style="font-size: 13px;color:var(--muted);margin-top:4px;">
        Type the reference of the booking this refund is for
        @if (!empty($bookingRef))
            (<strong>{{ $bookingRef }}</strong>)
        @else
            (<strong data-expected-ref></strong>)
        @endif
        — not the transfer reference.
    </div>
</div>
