<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews — Villa Elena Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root{--navy:#0D1B2A;--gold:#C9A84C;--gold-light:#E8C97A;--bg:#F4F6F9;--white:#fff;--border:#E2E8F0;--muted:#6B7A8D;--sidebar:260px;--topbar:68px;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:#1e293b;}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:var(--navy);z-index:100;display:flex;flex-direction:column;}
        .sidebar-brand{padding:22px 24px 18px;border-bottom:1px solid rgba(255,255,255,.07);text-decoration:none;display:block;}
        .brand-name{font-family:'Playfair Display',serif;color:var(--gold-light);font-size:18px;}
        .brand-sub{color:rgba(255,255,255,.3);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-top:2px;}
        .sidebar-nav{flex:1;padding:16px 12px;overflow-y:auto;}
        .nav-label{font-size:10px;color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:1.5px;padding:10px 12px 6px;font-weight:600;}
        .nav-item{display:flex;align-items:center;gap:11px;padding:10px 14px;border-radius:9px;color:rgba(255,255,255,.55);text-decoration:none;font-size:13.5px;font-weight:500;margin-bottom:2px;transition:all .2s;}
        .nav-item:hover{background:rgba(255,255,255,.06);color:#fff;}
        .nav-item.active{background:rgba(201,168,76,.15);color:var(--gold-light);}
        .nav-item i{font-size:16px;width:20px;text-align:center;}
        .nav-badge{margin-left:auto;background:rgba(239,68,68,.2);color:#fca5a5;padding:1px 8px;border-radius:100px;font-size:10px;font-weight:700;}
        .sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.07);}
        .user-info{display:flex;align-items:center;gap:10px;}
        .user-avatar{width:34px;height:34px;border-radius:50%;background:var(--gold);color:var(--navy);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;}
        .user-name{color:rgba(255,255,255,.7);font-size:13px;}
        .user-role{color:rgba(255,255,255,.3);font-size:11px;}
        .logout-btn{margin-left:auto;color:rgba(255,255,255,.3);font-size:18px;background:none;border:none;cursor:pointer;}
        .logout-btn:hover{color:#fff;}
        .topbar{position:fixed;top:0;left:var(--sidebar);right:0;height:var(--topbar);background:#fff;border-bottom:1px solid var(--border);z-index:99;display:flex;align-items:center;padding:0 28px;}
        .topbar-title{font-family:'Playfair Display',serif;font-size:20px;color:var(--navy);font-weight:700;}
        .topbar-sub{font-size:13px;color:var(--muted);margin-top:2px;}
        .main{margin-left:var(--sidebar);margin-top:var(--topbar);padding:28px;}

        /* Stats */
        .stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px;}
        .stat-card{background:#fff;border-radius:12px;border:1px solid var(--border);padding:16px 18px;}
        .stat-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:10px;}
        .stat-val{font-size:24px;font-weight:700;font-family:'Playfair Display',serif;color:var(--navy);line-height:1;}
        .stat-lbl{font-size:11px;color:var(--muted);margin-top:3px;}

        /* Filter */
        .filter-card{background:#fff;border-radius:12px;border:1px solid var(--border);padding:14px 18px;margin-bottom:20px;}
        .form-label-sm{font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px;}
        .form-select-sm,.form-control-sm{border:1.5px solid var(--border);border-radius:7px;padding:7px 12px;font-size:12px;font-family:'DM Sans',sans-serif;background:#fff;}
        .form-select-sm:focus,.form-control-sm:focus{outline:none;border-color:var(--navy);}
        .btn-filter{background:var(--navy);color:#fff;border:none;border-radius:7px;padding:7px 18px;font-size:12px;font-weight:600;cursor:pointer;}
        .btn-clear{color:var(--muted);border:1.5px solid var(--border);border-radius:7px;padding:7px 14px;font-size:12px;background:#fff;text-decoration:none;display:inline-block;}

        /* Review Cards */
        .reviews-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px;}
        .review-card{background:#fff;border-radius:14px;border:1px solid var(--border);overflow:hidden;transition:box-shadow .2s;}
        .review-card:hover{box-shadow:0 4px 20px rgba(13,27,42,.08);}
        .review-card.pending{border-top:3px solid #f59e0b;}
        .review-card.approved{border-top:3px solid #16a34a;}
        .review-card.rejected{border-top:3px solid #dc2626;opacity:.8;}
        .review-card-head{padding:14px 16px;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}
        .review-guest{display:flex;align-items:center;gap:10px;}
        .guest-avatar{width:38px;height:38px;border-radius:50%;background:var(--bg);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:var(--navy);flex-shrink:0;}
        .guest-name{font-weight:600;font-size:13px;}
        .review-meta{font-size:11px;color:var(--muted);margin-top:1px;}
        .stars{color:#f59e0b;font-size:14px;letter-spacing:1px;}
        .badge{padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;}
        .b-pending{background:#fef9c3;color:#a16207;}
        .b-approved{background:#dcfce7;color:#15803d;}
        .b-rejected{background:#fee2e2;color:#dc2626;}
        .review-card-body{padding:0 16px 14px;}
        .review-title{font-weight:600;font-size:14px;margin-bottom:5px;}
        .review-content{font-size:13px;color:#374151;line-height:1.6;margin-bottom:10px;}
        .review-property{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;margin-bottom:12px;}
        .review-actions{display:flex;gap:7px;flex-wrap:wrap;}
        .btn-approve{background:#dcfce7;color:#15803d;border:none;border-radius:7px;padding:6px 14px;font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;}
        .btn-approve:hover{background:#16a34a;color:#fff;}
        .btn-reject{background:#fee2e2;color:#dc2626;border:none;border-radius:7px;padding:6px 14px;font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;}
        .btn-reject:hover{background:#dc2626;color:#fff;}
        .btn-reply{background:#f1f5f9;color:#475569;border:none;border-radius:7px;padding:6px 14px;font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;}
        .btn-reply:hover{background:var(--navy);color:#fff;}
        .btn-delete{background:none;color:#94a3b8;border:none;padding:6px 8px;font-size:13px;cursor:pointer;border-radius:7px;transition:all .2s;}
        .btn-delete:hover{color:#dc2626;background:#fee2e2;}
        .admin-reply-box{background:#f8fafc;border-radius:8px;padding:10px 12px;margin-top:10px;border-left:3px solid var(--gold);font-size:12px;}
        .admin-reply-label{font-size:10px;color:var(--gold);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
        .reply-form{margin-top:10px;display:none;}
        .reply-input{border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;resize:none;transition:border-color .2s;}
        .reply-input:focus{outline:none;border-color:var(--navy);}
        .btn-submit-reply{background:var(--navy);color:#fff;border:none;border-radius:7px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:6px;}

        /* Modals */
        .modal-overlay{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:#fff;border-radius:16px;width:420px;max-width:calc(100vw - 32px);overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2);}
        .modal-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .modal-head h3{font-family:'Playfair Display',serif;font-size:16px;font-weight:600;}
        .modal-close{background:#f1f5f9;border:none;border-radius:7px;padding:5px 10px;font-size:12px;color:var(--muted);cursor:pointer;}
        .modal-body{padding:20px;}
        .form-label{font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;}
        .form-control{border:1.5px solid var(--border);border-radius:8px;padding:10px 14px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;}
        .form-control:focus{outline:none;border-color:var(--navy);}
        .btn-submit{background:#dc2626;color:#fff;border:none;border-radius:8px;padding:11px;width:100%;font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:8px;}

        .alert{border-radius:10px;font-size:13px;padding:12px 16px;border:none;margin-bottom:18px;}
        .alert-success{background:#dcfce7;color:#15803d;}
        .empty-state{text-align:center;padding:60px;color:var(--muted);}
        .empty-state i{font-size:44px;display:block;margin-bottom:12px;opacity:.35;}
        .pagination .page-link{border-radius:7px;font-size:13px;color:var(--navy);border-color:var(--border);}
        .pagination .page-item.active .page-link{background:var(--navy);border-color:var(--navy);}
    </style>
</head>
<body>

<aside class="sidebar">
    <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
        <div class="brand-name">Villa Elena</div>
        <div class="brand-sub">Resort Management</div>
    </a>
    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="{{ route('admin.dashboard') }}"        class="nav-item"><i class="bi bi-grid"></i> Dashboard</a>
        <a href="{{ route('admin.bookings.index') }}"   class="nav-item"><i class="bi bi-calendar3"></i> Bookings</a>
        <a href="{{ route('admin.properties.index') }}" class="nav-item"><i class="bi bi-buildings"></i> Properties</a>
        <a href="{{ route('admin.payments.index') }}"   class="nav-item"><i class="bi bi-cash-stack"></i> Payments</a>
        <div class="nav-label" style="margin-top:8px;">People</div>
        <a href="{{ route('admin.users.index') }}"      class="nav-item"><i class="bi bi-people"></i> Guests</a>
        <div class="nav-label" style="margin-top:8px;">Analytics</div>
        <a href="{{ route('admin.reviews.index') }}"    class="nav-item active"><i class="bi bi-star"></i> Reviews
            @if(isset($stats['pending']) && $stats['pending'] > 0)
                <span class="nav-badge">{{ $stats['pending'] }}</span>
            @endif
        </a>
        <a href="{{ route('admin.reports.index') }}"    class="nav-item"><i class="bi bi-bar-chart-line"></i> Reports</a>
        <a href="{{ route('admin.settings.index') }}"   class="nav-item"><i class="bi bi-gear"></i> Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">{{ strtoupper(substr(auth()->user()->full_name, 0, 1)) }}</div>
            <div><div class="user-name">{{ auth()->user()->full_name }}</div><div class="user-role">Administrator</div></div>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;margin-left:auto;">
                @csrf <button type="submit" class="logout-btn"><i class="bi bi-box-arrow-right"></i></button>
            </form>
        </div>
    </div>
</aside>

<div class="topbar">
    <div>
        <div class="topbar-title">Reviews</div>
        <div class="topbar-sub">Moderate guest reviews and ratings</div>
    </div>
</div>

<main class="main">

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    {{-- Stats --}}
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f1f5f9;color:#475569;"><i class="bi bi-chat-square-text"></i></div>
            <div class="stat-val">{{ $stats['total'] }}</div>
            <div class="stat-lbl">Total Reviews</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3;color:#a16207;"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-val">{{ $stats['pending'] }}</div>
            <div class="stat-lbl">Pending Approval</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-check-circle"></i></div>
            <div class="stat-val">{{ $stats['approved'] }}</div>
            <div class="stat-lbl">Published</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fee2e2;color:#dc2626;"><i class="bi bi-x-circle"></i></div>
            <div class="stat-val">{{ $stats['rejected'] }}</div>
            <div class="stat-lbl">Rejected</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef9c3;color:#f59e0b;"><i class="bi bi-star-fill"></i></div>
            <div class="stat-val">{{ $stats['avg_rating'] ?: '—' }}</div>
            <div class="stat-lbl">Average Rating</div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-card">
        <form method="GET" action="{{ route('admin.reviews.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-auto" style="min-width:140px;">
                    <label class="form-label-sm">Status</label>
                    <select name="status" class="form-select-sm form-select">
                        <option value="">All Status</option>
                        <option value="pending"  {{ request('status')=='pending'  ?'selected':'' }}>Pending</option>
                        <option value="approved" {{ request('status')=='approved' ?'selected':'' }}>Approved</option>
                        <option value="rejected" {{ request('status')=='rejected' ?'selected':'' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-auto" style="min-width:120px;">
                    <label class="form-label-sm">Rating</label>
                    <select name="rating" class="form-select-sm form-select">
                        <option value="">All Ratings</option>
                        @for($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" {{ request('rating')==$i ?'selected':'' }}>{{ $i }} ⭐</option>
                        @endfor
                    </select>
                </div>
                <div class="col-auto" style="min-width:180px;">
                    <label class="form-label-sm">Property</label>
                    <select name="property_id" class="form-select-sm form-select">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                        <option value="{{ $property->id }}" {{ request('property_id')==$property->id ?'selected':'' }}>
                            {{ $property->property_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn-filter">Filter</button>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.reviews.index') }}" class="btn-clear">Clear</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Reviews Grid --}}
    @if($reviews->isEmpty())
        <div class="empty-state">
            <i class="bi bi-star"></i>
            <p style="font-size:15px;font-weight:500;margin-bottom:4px;">No reviews found</p>
            <p style="font-size:13px;">Reviews will appear here once guests submit them after checkout.</p>
        </div>
    @else
        <div class="reviews-grid">
            @foreach($reviews as $review)
            <div class="review-card {{ $review->status }}">
                <div class="review-card-head">
                    <div class="review-guest">
                        <div class="guest-avatar">{{ strtoupper(substr($review->user->full_name ?? 'G', 0, 1)) }}</div>
                        <div>
                            <div class="guest-name">{{ $review->user->full_name ?? 'Guest' }}</div>
                            <div class="review-meta">{{ $review->created_at->format('M d, Y') }}</div>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;">
                        <span class="badge b-{{ $review->status }}">{{ ucfirst($review->status) }}</span>
                        <div class="stars">
                            @for($i = 1; $i <= 5; $i++)
                                {{ $i <= $review->rating ? '★' : '☆' }}
                            @endfor
                        </div>
                    </div>
                </div>
                <div class="review-card-body">
                    <div class="review-property">
                        <i class="bi bi-house"></i> {{ $review->property->property_name ?? 'N/A' }}
                    </div>
                    <div class="review-title">{{ $review->title }}</div>
                    <div class="review-content">{{ Str::limit($review->content, 180) }}</div>

                    {{-- Admin Reply --}}
                    @if($review->admin_reply)
                    <div class="admin-reply-box">
                        <div class="admin-reply-label">Management Reply</div>
                        <div style="font-size:13px;color:#374151;">{{ $review->admin_reply }}</div>
                    </div>
                    @endif

                    {{-- Actions --}}
                    <div class="review-actions">
                        @if($review->status === 'pending')
                        <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-approve">
                                <i class="bi bi-check-lg me-1"></i> Approve
                            </button>
                        </form>
                        <button type="button" class="btn-reject"
                            onclick="openRejectModal({{ $review->id }})">
                            <i class="bi bi-x-lg me-1"></i> Reject
                        </button>
                        @endif

                        @if($review->status === 'approved' && !$review->admin_reply)
                        <button type="button" class="btn-reply"
                            onclick="toggleReply({{ $review->id }})">
                            <i class="bi bi-reply me-1"></i> Reply
                        </button>
                        @endif

                        <form method="POST" action="{{ route('admin.reviews.destroy', $review) }}" style="display:inline;"
                            onsubmit="return confirm('Delete this review?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-delete" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>

                    {{-- Reply Form --}}
                    <div class="reply-form" id="replyForm{{ $review->id }}">
                        <form method="POST" action="{{ route('admin.reviews.reply', $review) }}">
                            @csrf @method('POST')
                            <textarea name="reply" class="reply-input" rows="2"
                                placeholder="Write a reply as Villa Elena Resort..." required minlength="5"></textarea>
                            <button type="submit" class="btn-submit-reply">
                                <i class="bi bi-send me-1"></i> Post Reply
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if($reviews->hasPages())
            <div style="margin-top:20px;">{{ $reviews->links() }}</div>
        @endif
    @endif
</main>

{{-- Reject Modal --}}
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Reject Review</h3>
            <button class="modal-close" onclick="closeRejectModal()">Close</button>
        </div>
        <form id="rejectForm" method="POST" class="modal-body">
            @csrf @method('PATCH')
            <div style="margin-bottom:14px;">
                <label class="form-label">Reason for Rejection <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
                <textarea name="reject_reason" class="form-control" rows="3"
                    placeholder="e.g. Contains inappropriate language, false information..."></textarea>
            </div>
            <button type="submit" class="btn-submit">
                <i class="bi bi-x-circle me-2"></i> Reject Review
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openRejectModal(reviewId) {
    document.getElementById('rejectForm').action = `/admin/reviews/${reviewId}/reject`;
    document.getElementById('rejectModal').classList.add('open');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('open');
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
function toggleReply(reviewId) {
    const form = document.getElementById('replyForm' + reviewId);
    form.style.display = form.style.display === 'none' || !form.style.display ? 'block' : 'none';
}
setTimeout(() => { document.querySelectorAll('.alert').forEach(a => a.style.display='none'); }, 5000);
</script>
@include('admin.partials.realtime') 
</body>
</html>