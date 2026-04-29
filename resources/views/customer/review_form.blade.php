{{-- SAVE AS: resources/views/customer/review_form.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write a Review — Villa Elena</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--stone:#2c2416;--sand:#f5f0e8;--gold:#b8943f;--gold-light:#d4aa5a;--muted:#8a7f6e;--border:#e4ddd0;--nav-h:72px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Jost',sans-serif;background:var(--sand);min-height:100vh;}
        .nav{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--stone);z-index:100;display:flex;align-items:center;justify-content:space-between;padding:0 40px;}
        .nav-brand{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:20px;text-decoration:none;}
        .logout-link{color:rgba(255,255,255,.4);font-size:12px;text-decoration:none;}
        .logout-link:hover{color:#fff;}
        .main{margin-top:var(--nav-h);max-width:600px;margin-left:auto;margin-right:auto;padding:40px 20px;}
        .page-title{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;margin-bottom:6px;}
        .page-sub{color:var(--muted);font-size:14px;margin-bottom:28px;}

        /* Booking summary */
        .booking-summary{background:#fff;border-radius:14px;border:1px solid var(--border);padding:18px 22px;margin-bottom:24px;display:flex;align-items:center;gap:16px;}
        .summary-icon{width:48px;height:48px;border-radius:12px;background:var(--sand);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
        .summary-name{font-weight:600;font-size:15px;}
        .summary-dates{font-size:12px;color:var(--muted);margin-top:3px;}

        /* Form */
        .form-card{background:#fff;border-radius:16px;border:1px solid var(--border);overflow:hidden;}
        .form-card-head{padding:18px 22px;border-bottom:1px solid var(--border);}
        .form-card-head h3{font-family:'Playfair Display',serif;font-size:17px;font-weight:600;}
        .form-card-body{padding:22px;}
        .form-label{font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:8px;letter-spacing:.2px;}
        .form-control{border:1.5px solid var(--border);border-radius:8px;padding:11px 14px;font-size:14px;font-family:'Jost',sans-serif;width:100%;background:#fff;transition:border-color .2s;}
        .form-control:focus{outline:none;border-color:var(--stone);}
        .mb-16{margin-bottom:16px;}
        .is-invalid{border-color:#dc2626!important;}

        /* Star rating */
        .star-rating{display:flex;gap:6px;margin-bottom:4px;}
        .star-btn{background:none;border:none;cursor:pointer;font-size:32px;color:#d1d5db;transition:all .15s;padding:0;}
        .star-btn:hover,.star-btn.active{color:#f59e0b;transform:scale(1.1);}
        .rating-label{font-size:12px;color:var(--muted);margin-top:4px;height:16px;}

        /* Submit */
        .btn-submit{background:var(--stone);color:#fff;border:none;border-radius:10px;padding:14px;width:100%;font-size:15px;font-weight:600;cursor:pointer;font-family:'Jost',sans-serif;margin-top:8px;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;}
        .btn-submit:hover{background:var(--gold);color:var(--stone);}
        .btn-back{display:inline-flex;align-items:center;gap:7px;color:var(--muted);text-decoration:none;font-size:13px;margin-bottom:20px;}
        .btn-back:hover{color:var(--stone);}
        .char-count{font-size:11px;color:var(--muted);text-align:right;margin-top:4px;}
        .alert{border-radius:10px;font-size:13px;padding:12px 16px;border:none;margin-bottom:16px;}
        .alert-danger{background:#fee2e2;color:#dc2626;}
    </style>
</head>
<body>
<nav class="nav">
    <a href="{{ route('customer.home') }}" class="nav-brand">Villa Elena</a>
    <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;">
            <span class="logout-link">Sign out</span>
        </button>
    </form>
</nav>

<main class="main">
    <a href="{{ route('customer.bookings.show', $booking) }}" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back to booking
    </a>

    <div class="page-title">Write a Review</div>
    <div class="page-sub">Share your experience at Villa Elena Resort</div>

    @if($errors->any())
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}</div>
    @endif

    {{-- Booking Summary --}}
    <div class="booking-summary">
        <div class="summary-icon">🏝️</div>
        <div>
            <div class="summary-name">{{ $booking->property->property_name }}</div>
            <div class="summary-dates">
                {{ $booking->check_in_date->format('M d') }} — {{ $booking->check_out_date->format('M d, Y') }}
                · {{ $booking->num_nights }} night{{ $booking->num_nights!=1?'s':'' }}
                · {{ $booking->booking_ref }}
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('customer.reviews.store', $booking) }}">
        @csrf
        <div class="form-card">
            <div class="form-card-head">
                <h3>Your Review</h3>
            </div>
            <div class="form-card-body">

                {{-- Star Rating --}}
                <div class="mb-16">
                    <label class="form-label">Overall Rating</label>
                    <div class="star-rating" id="starRating">
                        @for($i = 1; $i <= 5; $i++)
                        <button type="button" class="star-btn" data-value="{{ $i }}" onclick="setRating({{ $i }})">★</button>
                        @endfor
                    </div>
                    <div class="rating-label" id="ratingLabel">Click to rate</div>
                    <input type="hidden" name="rating" id="ratingInput" value="{{ old('rating') }}">
                    @error('rating')<div style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</div>@enderror
                </div>

                {{-- Title --}}
                <div class="mb-16">
                    <label class="form-label">Review Title</label>
                    <input type="text" name="title" class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
                        value="{{ old('title') }}"
                        placeholder="Summarize your experience (e.g. Amazing stay, beautiful property!)"
                        maxlength="100">
                    @error('title')<div style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</div>@enderror
                </div>

                {{-- Content --}}
                <div class="mb-16">
                    <label class="form-label">Your Review</label>
                    <textarea name="content" id="reviewContent" class="form-control {{ $errors->has('content') ? 'is-invalid' : '' }}"
                        rows="5" maxlength="1000" minlength="20"
                        placeholder="Tell other guests about your experience — the property, amenities, staff, and overall stay..."
                        oninput="updateCharCount()">{{ old('content') }}</textarea>
                    <div class="char-count"><span id="charCount">0</span>/1000 characters (min. 20)</div>
                    @error('content')<div style="font-size:11px;color:#dc2626;margin-top:4px;">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="bi bi-star-fill"></i> Submit Review
                </button>
                <div style="font-size:11px;color:var(--muted);text-align:center;margin-top:8px;">
                    Your review will be published after admin approval.
                </div>
            </div>
        </div>
    </form>
</main>

<script>
const ratingLabels = ['','Terrible','Poor','Average','Good','Excellent'];
let selectedRating = {{ old('rating', 0) }};

function setRating(value) {
    selectedRating = value;
    document.getElementById('ratingInput').value = value;
    document.getElementById('ratingLabel').textContent = ratingLabels[value];
    document.querySelectorAll('.star-btn').forEach((btn, i) => {
        btn.classList.toggle('active', i < value);
    });
}

// Hover effect
document.querySelectorAll('.star-btn').forEach((btn, i) => {
    btn.addEventListener('mouseenter', () => {
        document.querySelectorAll('.star-btn').forEach((b, j) => {
            b.style.color = j <= i ? '#f59e0b' : '#d1d5db';
        });
    });
    btn.addEventListener('mouseleave', () => {
        document.querySelectorAll('.star-btn').forEach((b, j) => {
            b.style.color = j < selectedRating ? '#f59e0b' : '#d1d5db';
        });
    });
});

// Restore rating on validation error
if (selectedRating > 0) setRating(selectedRating);

function updateCharCount() {
    const len = document.getElementById('reviewContent').value.length;
    document.getElementById('charCount').textContent = len;
    document.getElementById('charCount').style.color = len < 20 ? '#dc2626' : '#16a34a';
}
updateCharCount();
</script>
</body>
</html>