@extends('layouts.portal')

@section('title', 'Guest Reviews — Villa Elena Resort')

@push('styles')
<style>
    body { font-family: 'Inter', sans-serif; background: var(--cream); color: var(--stone); line-height: 1.6; }

    .section { max-width: 1280px; margin: 0 auto; padding: 60px 40px 120px; }
    .section-eyebrow { font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: var(--gold); font-weight: 500; margin-bottom: 10px; }
    .section-title { font-family: 'Playfair Display', serif; font-size: clamp(30px, 4vw, 44px); font-weight: 600; color: var(--stone); }

    .reviews-header { text-align: center; margin-bottom: 20px; }
    .reviews-stats { font-size: 15px; color: var(--muted); margin-top: 14px; }
    .reviews-stats strong { color: var(--gold); font-size: 18px; }

    .back-link { display: inline-flex; align-items: center; gap: 7px; color: var(--muted); text-decoration: none; font-size: 13px; margin-bottom: 30px; }
    .back-link:hover { color: var(--stone); }

    .testimonials-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 28px; margin-top: 52px; }
    .testimonial-card { background: #fff; border-radius: 22px; padding: 36px 32px; border: 1px solid var(--border); position: relative; transition: all 0.35s ease; }
    .testimonial-card:hover { transform: translateY(-6px); box-shadow: 0 20px 50px rgba(44,36,22,0.1); border-color: transparent; }
    .testimonial-quote { font-size: 52px; color: var(--gold); font-family: 'Playfair Display', serif; line-height: 1; margin-bottom: 12px; opacity: 0.5; }
    .testimonial-text { color: var(--stone); font-size: 15px; line-height: 1.75; font-style: italic; margin-bottom: 28px; }
    .testimonial-stars { color: var(--gold); font-size: 14px; letter-spacing: 2px; margin-bottom: 16px; }
    .testimonial-author { display: flex; align-items: center; gap: 14px; }
    .author-avatar { width: 46px; height: 46px; border-radius: 50%; background: var(--sand); display: flex; align-items: center; justify-content: center; font-size: 18px; color: var(--gold); font-family: 'Playfair Display', serif; font-weight: 700; flex-shrink: 0; }
    .author-name { font-weight: 600; font-size: 15px; color: var(--stone); }
    .author-location { font-size: 12px; color: var(--muted); margin-top: 2px; }

    .reply-note { background: var(--sand); border-radius: 10px; padding: 12px 14px; margin-top: 18px; font-size: 12px; color: var(--stone); }
    .reply-note strong { color: var(--gold); display: block; margin-bottom: 3px; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }

    .empty-state { text-align: center; padding: 80px 20px; color: var(--muted); }

    .pagination-wrap { margin-top: 56px; display: flex; justify-content: center; }

    @media (max-width: 640px) {
        .testimonials-grid { grid-template-columns: 1fr; }
        .section { padding: 40px 20px 80px; }
    }
</style>
@endpush

@section('content')
<div class="section">
    <a href="{{ route('home') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to Villa Elena</a>

    <div class="reviews-header">
        <div class="section-eyebrow">Guest Voices</div>
        <div class="section-title">All Guest Reviews</div>
        @if($totalReviews > 0)
            <div class="reviews-stats"><strong>{{ $avgRating }}★</strong> average from {{ $totalReviews }} {{ Str::plural('review', $totalReviews) }}</div>
        @endif
    </div>

    @if($reviews->isEmpty())
        <div class="empty-state">
            <i class="bi bi-star" style="font-size:40px;opacity:.3;"></i>
            <p style="margin-top:12px;">No reviews yet — be the first to share your experience at Villa Elena!</p>
        </div>
    @else
        <div class="testimonials-grid">
            @foreach($reviews as $review)
                <div class="testimonial-card">
                    <div class="testimonial-quote">"</div>
                    <div class="testimonial-stars">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                    <p class="testimonial-text">"{{ $review->content }}"</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">{{ strtoupper(substr($review->user->full_name ?? 'G', 0, 1)) }}</div>
                        <div>
                            <div class="author-name">{{ $review->user->full_name ?? 'Guest' }}</div>
                            <div class="author-location">{{ $review->created_at->format('M Y') }}</div>
                        </div>
                    </div>

                    @if($review->admin_reply)
                        <div class="reply-note">
                            <strong>Villa Elena's Reply</strong>
                            {{ $review->admin_reply }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="pagination-wrap">
            {{ $reviews->links() }}
        </div>
    @endif
</div>
@endsection
