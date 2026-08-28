@extends('layouts.customer')

@section('title', 'My Reviews — Villa Elena')

@push('styles')
    <style>
        .main {
            max-width: 720px;
        }

        .page-title {
            font-weight: 700;
        }

        .review-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 20px 22px;
            margin-bottom: 16px;
        }

        .review-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 8px;
        }

        .review-property {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 600;
        }

        .review-meta {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .review-stars {
            color: #f59e0b;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .review-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .review-content {
            font-size: 13px;
            color: #374151;
            line-height: 1.6;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }

        .btn-edit,
        .btn-delete {
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Jost', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit {
            background: #f1f5f9;
            color: #374151;
        }

        .btn-edit:hover {
            background: #e2e8f0;
        }

        .btn-delete {
            background: #fef2f2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fee2e2;
        }

        .admin-reply {
            background: #f9f5ee;
            border-radius: 10px;
            padding: 12px 14px;
            margin-top: 12px;
            font-size: 12px;
        }

        .admin-reply strong {
            display: block;
            color: var(--stone);
            margin-bottom: 3px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
    </style>
@endpush

@section('content')
    <div class="page-title">My Reviews</div>
    <div class="page-sub" style="margin-bottom:20px;">Reviews you've submitted for past stays</div>

    @if (session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    @forelse($reviews as $review)
        <div class="review-card">
            <div class="review-head">
                <div>
                    <div class="review-property">{{ $review->booking->property->property_name ?? 'N/A' }}</div>
                    <div class="review-meta">
                        {{ $review->booking->booking_ref ?? '' }} · {{ $review->created_at->format('M d, Y') }}
                    </div>
                </div>
                @php
                    $badge = match ($review->status) {
                        'approved' => ['#dcfce7', '#15803d', 'Approved'],
                        'rejected' => ['#fee2e2', '#dc2626', 'Rejected'],
                        default => ['#fef3c7', '#b45309', 'Pending'],
                    };
                @endphp
                <span
                    style="background:{{ $badge[0] }};color:{{ $badge[1] }};padding:4px 12px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;">{{ $badge[2] }}</span>
            </div>

            <div class="review-stars">
                @for ($i = 1; $i <= 5; $i++)
                    <i class="bi bi-star{{ $i <= $review->rating ? '-fill' : '' }}"></i>
                @endfor
            </div>

            <div class="review-title">{{ $review->title }}</div>
            <div class="review-content">{{ $review->content }}</div>

            @if ($review->status === 'rejected' && $review->admin_note)
                <div class="admin-reply" style="background:#fef2f2;">
                    <strong style="color:#dc2626;">Why this was rejected:</strong>
                    {{ $review->admin_note }}
                </div>
            @endif

            @if ($review->admin_reply)
                <div class="admin-reply">
                    <strong>Resort's Reply:</strong>
                    {{ $review->admin_reply }}
                </div>
            @endif

            <div class="review-actions">
                <a href="{{ route('customer.reviews.edit', $review) }}" class="btn-edit">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <form method="POST" action="{{ route('customer.reviews.destroy', $review) }}"
                    onsubmit="return confirm('Delete this review? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-delete"><i class="bi bi-trash"></i> Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="empty-state">
            <i class="bi bi-star" style="font-size:40px;opacity:.3;"></i>
            <p style="margin-top:12px;">You haven't written any reviews yet.</p>
        </div>
    @endforelse
@endsection

