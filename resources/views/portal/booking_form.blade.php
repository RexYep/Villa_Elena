@extends('layouts.portal')

@section('title', 'Complete Booking — Villa Elena Resort')

@push('styles')
<style>
.main{max-width:960px;}
.field-readonly{background:#f9f5ee;}

/* Steps */
.steps{display:flex;align-items:center;gap:0;margin-bottom:36px;}
.step{display:flex;align-items:center;gap:10px;font-size:13px;}
.step-num{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
.step.done .step-num{background:var(--gold);color:var(--stone);}
.step.active .step-num{background:var(--stone);color:#fff;}
.step.inactive .step-num{background:var(--border);color:var(--muted);}
.step.active .step-label{font-weight:600;color:var(--stone);}
.step.inactive .step-label{color:var(--muted);}
.step-line{flex:1;height:1px;background:var(--border);margin:0 16px;}

/* Layout */
.form-grid{display:grid;grid-template-columns:1fr 320px;gap:28px;align-items:start;}
.form-card{background:#fff;border-radius:16px;border:1px solid var(--border);overflow:hidden;margin-bottom:20px;}
.form-card-head{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;}
.form-card-head .icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;}
.form-card-head h3{font-family:'Playfair Display',serif;font-size:17px;font-weight:600;}
.form-card-body{padding:24px;}
.form-label{font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;display:block;letter-spacing:.3px;}
.form-control:focus{box-shadow:0 0 0 3px rgba(44,36,22,.06);}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.mb-14{margin-bottom:14px;}

/* Summary card */
.summary-card{background:#fff;border-radius:16px;border:1px solid var(--border);overflow:hidden;position:sticky;top:calc(var(--nav-h)+20px);}
.summary-img{width:100%;height:160px;object-fit:cover;}
.summary-img-placeholder{width:100%;height:160px;background:var(--sand);display:flex;align-items:center;justify-content:center;font-size:40px;color:var(--muted);opacity:.4;}
.summary-body{padding:20px;}
.summary-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:600;margin-bottom:4px;}
.summary-dates{font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;margin-bottom:16px;}
.price-row{display:flex;justify-content:space-between;font-size:13px;padding:7px 0;border-bottom:1px solid #f4efe6;}
.price-row:last-child{border-bottom:none;}
.price-row.total{font-weight:700;font-size:16px;border-top:2px solid var(--border);padding-top:12px;margin-top:4px;}
.deposit-box{background:rgba(196,103,58,.07);border:1px solid rgba(196,103,58,.2);border-radius:10px;padding:12px;margin:12px 0;font-size:12px;color:var(--terracotta);text-align:center;}
.deposit-amount{font-size:18px;font-weight:700;font-family:'Playfair Display',serif;display:block;margin:4px 0;}
.night-breakdown{max-height:140px;overflow-y:auto;}
.night-row{display:flex;justify-content:space-between;font-size:12px;padding:4px 0;color:var(--muted);}
.night-row.weekend{color:var(--gold);}

/* Submit */
.btn-submit{background:var(--stone);color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:700;font-family:'Jost',sans-serif;width:100%;cursor:pointer;transition:all .2s;margin-top:8px;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-submit:hover{background:var(--gold);color:var(--stone);}
.terms-note{font-size:11px;color:var(--muted);text-align:center;margin-top:8px;line-height:1.5;}

@media (max-width: 800px) {
    .form-grid{grid-template-columns:1fr;}
    .summary-card{position:static;}
}
@media (max-width: 480px) {
    .two-col{grid-template-columns:1fr;}
    .steps{gap:0;}
    .step-label{display:none;}
    .step-line{margin:0 8px;}
}
</style>
@endpush

@section('content')
    <div class="breadcrumb-row">
        <a href="{{ route('home') }}">Home</a> <span>›</span>
        <a href="{{ route('portal.property', $property) }}">{{ $property->property_name }}</a>
        <span>›</span>
        <span style="color:var(--stone);font-weight:500;">Complete Booking</span>
    </div>

    {{-- Steps --}}
    <div class="steps">
        <div class="step done"><div class="step-num"><i class="bi bi-check"></i></div><span class="step-label">Select Dates</span></div>
        <div class="step-line"></div>
        <div class="step active"><div class="step-num">2</div><span class="step-label">Your Details</span></div>
        <div class="step-line"></div>
        <div class="step inactive"><div class="step-num">3</div><span class="step-label">Payment</span></div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('portal.book.submit', $property) }}">
        @csrf
        <input type="hidden" name="checkin" value="{{ $checkin->toDateString() }}">
        <input type="hidden" name="slot"    value="{{ $slot }}">
        <input type="hidden" name="guests"  value="{{ $request->guests }}">

        <div class="form-grid">
            <div>
                {{-- Guest Info --}}
                <div class="form-card">
                    <div class="form-card-head">
                        <div class="icon tag-blue"><i class="bi bi-person"></i></div>
                        <h3>Guest Information</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="two-col mb-14">
                            <div>
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control field-readonly" value="{{ auth()->user()->full_name }}" readonly>
                            </div>
                            <div>
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control field-readonly" value="{{ auth()->user()->email }}" readonly>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control field-readonly" value="{{ auth()->user()->phone ?? '' }}" readonly>
                        </div>
                    </div>
                </div>

                {{-- Stay Details --}}
                <div class="form-card">
                    <div class="form-card-head">
                        <div class="icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-calendar3"></i></div>
                        <h3>Stay Details</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="two-col mb-14">
                            <div>
                                <label class="form-label">Check-in</label>
                                <input type="text" class="form-control field-readonly" value="{{ $checkin->format('l, M d, Y g:i A') }}" readonly>
                            </div>
                            <div>
                                <label class="form-label">Check-out</label>
                                <input type="text" class="form-control field-readonly" value="{{ $checkout->format('l, M d, Y g:i A') }}" readonly>
                            </div>
                        </div>
                        <div class="two-col mb-14">
                            <div>
                                <label class="form-label">Duration</label>
                                <input type="text" class="form-control field-readonly" value="{{ $nights }} night{{ $nights!=1?'s':'' }}" readonly>
                            </div>
                            <div>
                                <label class="form-label">Guests</label>
                                <input type="text" class="form-control field-readonly" value="{{ $request->guests }} guest{{ $request->guests > 1 ? 's' : '' }}" readonly>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Special Requests <span class="text-muted-theme" style="font-weight:400;">(optional)</span></label>
                            <textarea name="special_requests" class="form-control" rows="3"
                                placeholder="Early check-in, dietary requirements, celebrations, etc.">{{ old('special_requests') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Policies --}}
                <div class="form-card">
                    <div class="form-card-head">
                        <div class="icon tag-amber"><i class="bi bi-info-circle"></i></div>
                        <h3>Booking Policies</h3>
                    </div>
                    <div class="form-card-body">
                        <div class="text-muted-theme" style="font-size:13px;line-height:1.8;">
                            <p style="margin-bottom:8px;"><strong style="color:var(--stone);">Deposit Required:</strong> A minimum {{ $depositPct }}% deposit (₱{{ number_format($depositAmount,2) }}) must be paid via PayMongo to confirm your booking. Your reservation is confirmed automatically once payment is successful.</p>
                            <p style="margin-bottom:8px;"><strong style="color:var(--stone);">Check-in Time:</strong> {{ $checkin->format('g:i A') }}</p>
                            <p style="margin-bottom:8px;"><strong style="color:var(--stone);">Check-out Time:</strong> {{ $checkout->format('g:i A') }}</p>
                            <p><strong style="color:var(--stone);">Cancellation:</strong> Free cancellation 48 hours before check-in. Later cancellations may forfeit the deposit.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            <div>
                <div class="summary-card">
                    @if($property->primaryImage)
                        <img src="{{ $property->primaryImage->url }}" class="summary-img" alt="">
                    @else
                        <div class="summary-img-placeholder"><i class="bi bi-house"></i></div>
                    @endif
                    <div class="summary-body">
                        <div class="summary-name">{{ $property->property_name }}</div>
                        <div class="summary-dates">
                            <i class="bi bi-calendar3" style="font-size:11px;"></i>
                            {{ $checkin->format('M d') }} → {{ $checkout->format('M d, Y') }}
                        </div>

                        {{-- Night Breakdown --}}
                        <div class="night-breakdown">
                            @foreach($nightBreakdown as $night)
                            <div class="night-row {{ $night['weekend'] ? 'weekend' : '' }}">
                                <span>{{ $night['date'] }} {{ $night['weekend'] ? '★' : '' }}</span>
                                <span>₱{{ number_format($night['price'],0) }}</span>
                            </div>
                            @endforeach
                        </div>

                        <div style="height:1px;background:var(--border);margin:12px 0;"></div>

                        <div class="price-row">
                            <span class="text-muted-theme">{{ $nights }} night{{ $nights!=1?'s':'' }}</span>
                            <span>₱{{ number_format($baseAmount,2) }}</span>
                        </div>
                        <div class="price-row total">
                            <span>Total</span>
                            <span>₱{{ number_format($baseAmount,2) }}</span>
                        </div>

                        <div class="deposit-box">
                            Minimum deposit required ({{ $depositPct }}%)
                            <span class="deposit-amount">₱{{ number_format($depositAmount,2) }}</span>
                            Payable on the next step
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="bi bi-credit-card"></i> Proceed to Payment
                        </button>
                        <div class="terms-note">
                            By proceeding, you agree to our booking policies.<br>
                            You'll be taken to secure payment via PayMongo next.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
