@extends('layouts.admin')

@section('title', 'Reviews — Villa Elena Admin')
@section('page-title', 'Reviews')
@section('page-subtitle', 'Moderate guest reviews and ratings')

@push('styles')
<style>
/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px;}
.stat-card{background:var(--cream);border-radius:12px;border:1px solid var(--border);padding:16px 18px;}
.stat-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;margin-bottom:10px;background:var(--gold-dim);color:var(--gold);}
.stat-val{font-size:24px;font-weight:700;font-family:'Cormorant Garamond',serif;color:var(--stone);line-height:1;}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:3px;}

/* Filter */
.filter-card{background:var(--cream);border-radius:12px;border:1px solid var(--border);padding:14px 18px;margin-bottom:20px;}
.form-label-sm{font-size:11px;font-weight:600;color:var(--text-main);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px;}
.form-select-sm,.form-control-sm{border:1.5px solid var(--border);border-radius:7px;padding:7px 12px;font-size:12px;font-family:'DM Sans',sans-serif;background:#fff;color:var(--text-main);}
.form-select-sm:focus,.form-control-sm:focus{outline:none;border-color:var(--terracotta);}
.btn-filter{background:var(--terracotta);color:#fff;border:none;border-radius:7px;padding:7px 18px;font-size:12px;font-weight:600;cursor:pointer;}
.btn-clear{color:var(--muted);border:1.5px solid var(--border);border-radius:7px;padding:7px 14px;font-size:12px;background:#fff;text-decoration:none;display:inline-block;}

/* Review Cards */
.reviews-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px;}
.review-card{background:var(--cream);border-radius:14px;border:1px solid var(--border);overflow:hidden;transition:box-shadow .2s;}
.review-card:hover{box-shadow:0 4px 20px rgba(44,36,22,.1);}
.review-card.pending{border-top:3px solid #f59e0b;}
.review-card.approved{border-top:3px solid #16a34a;}
.review-card.rejected{border-top:3px solid #dc2626;opacity:.8;}
.review-card-head{padding:14px 16px;display:flex;align-items:flex-start;justify-content:space-between;gap:10px;}
.review-guest{display:flex;align-items:center;gap:10px;}
.guest-avatar{width:38px;height:38px;border-radius:50%;background:var(--sand);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:var(--stone);flex-shrink:0;}
.guest-name{font-weight:600;font-size:13px;color:var(--text-main);}
.review-meta{font-size:11px;color:var(--muted);margin-top:1px;}
.stars{color:#f59e0b;font-size:14px;letter-spacing:1px;}

/* Status badges — semantic, unchanged */
.b-pending{background:var(--tag-amber-bg);color:var(--tag-amber-fg);}
.b-approved{background:var(--tag-green-bg);color:var(--tag-green-fg);}
.b-rejected{background:var(--tag-red-bg);color:var(--tag-red-fg);}

.review-card-body{padding:0 16px 14px;}
.review-title{font-weight:600;font-size:14px;margin-bottom:5px;color:var(--text-main);}
.review-content{font-size:13px;color:var(--text-main);line-height:1.6;margin-bottom:10px;}
.review-property{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;margin-bottom:12px;}
.review-actions{display:flex;gap:7px;flex-wrap:wrap;}

/* Action buttons — semantic (approve/reject) kept, reply/delete aligned to theme */
.btn-approve{background:#dcfce7;color:#15803d;border:none;border-radius:7px;padding:6px 14px;font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;}
.btn-approve:hover{background:#16a34a;color:#fff;}
.btn-reject{background:#fee2e2;color:#dc2626;border:none;border-radius:7px;padding:6px 14px;font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;}
.btn-reject:hover{background:#dc2626;color:#fff;}
.btn-reply{background:var(--sand);color:var(--text-main);border:none;border-radius:7px;padding:6px 14px;font-size:11px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;}
.btn-reply:hover{background:var(--terracotta);color:#fff;}
.btn-delete{background:none;color:var(--muted);border:none;padding:6px 8px;font-size:13px;cursor:pointer;border-radius:7px;transition:all .2s;}
.btn-delete:hover{color:#dc2626;background:#fee2e2;}

.admin-reply-box{background:var(--sand);border-radius:8px;padding:10px 12px;margin-top:10px;border-left:3px solid var(--gold);font-size:12px;color:var(--text-main);}
.admin-reply-label{font-size:10px;color:var(--gold);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;}
.reply-form{margin-top:10px;display:none;}
.reply-input{border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;font-family:'DM Sans',sans-serif;width:100%;resize:none;transition:border-color .2s;background:#fff;color:var(--text-main);}
.reply-input:focus{outline:none;border-color:var(--terracotta);}
.btn-submit-reply{background:var(--terracotta);color:#fff;border:none;border-radius:7px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:6px;transition:background .2s;}
.btn-submit-reply:hover{background:var(--gold);}

/* Modals */
.modal-overlay.open{display:flex;}
.modal-box{width:420px;}
.modal-head h3{font-size:16px;}
.btn-submit{background:#dc2626;}

@media (max-width: 900px) {
    .stats-row{grid-template-columns:repeat(3,1fr);}
}
@media (max-width: 560px) {
    .stats-row{grid-template-columns:1fr 1fr;}
}
</style>
@endpush

@section('content')

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
            <div class="stat-icon tag-amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-val">{{ $stats['pending'] }}</div>
            <div class="stat-lbl">Pending Approval</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#16a34a;"><i class="bi bi-check-circle"></i></div>
            <div class="stat-val">{{ $stats['approved'] }}</div>
            <div class="stat-lbl">Published</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon tag-red"><i class="bi bi-x-circle"></i></div>
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

                    {{-- Auto-Moderation Flag --}}
                    @if($review->status === 'pending' && $review->flag_reason)
                    <div class="admin-reply-box" style="border-left-color:#dc2626;background:#fef2f2;">
                        <div class="admin-reply-label" style="color:#dc2626;"><i class="bi bi-shield-exclamation me-1"></i>Auto-Moderation</div>
                        <div style="font-size:13px;color:var(--text-main);">{{ $review->flag_reason }}</div>
                    </div>
                    @endif

                    {{-- Admin Reply --}}
                    @if($review->admin_reply)
                    <div class="admin-reply-box">
                        <div class="admin-reply-label">Management Reply</div>
                        <div style="font-size:13px;color:var(--text-main);">{{ $review->admin_reply }}</div>
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
@endsection

@section('modals')
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
                <label class="form-label">Reason for Rejection <span class="text-muted-theme" style="font-weight:400;">(optional)</span></label>
                <textarea name="reject_reason" class="form-control" rows="3"
                    placeholder="e.g. Contains inappropriate language, false information..."></textarea>
            </div>
            <button type="submit" class="btn-submit">
                <i class="bi bi-x-circle me-2"></i> Reject Review
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const rejectReviewUrlTemplate = '{{ route('admin.reviews.reject', ['review' => '__ID__']) }}';
function openRejectModal(reviewId) {
    document.getElementById('rejectForm').action = rejectReviewUrlTemplate.replace('__ID__', reviewId);
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
@endpush
