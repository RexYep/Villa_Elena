@extends('layouts.customer')

@section('title', 'Notifications — Villa Elena Resort')

@push('styles')
    <style>
        .notif-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 10px;
            transition: box-shadow .2s;
        }

        .notif-card.unread {
            border-left: 3px solid var(--gold);
        }

        .notif-inner {
            display: flex;
            gap: 16px;
            padding: 18px 22px;
        }

        .notif-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .notif-body {
            flex: 1;
        }

        .notif-title {
            font-weight: 600;
            font-size: 14px;
        }

        .notif-msg {
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
            line-height: 1.5;
        }

        .notif-time {
            font-size: 13px;
            color: #c4bdb2;
            margin-top: 5px;
        }

        .unread-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gold);
            flex-shrink: 0;
            margin-top: 6px;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 44px;
            display: block;
            margin-bottom: 12px;
            opacity: .35;
        }

        a.notif-card {
            display: block;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        a.notif-card:hover {
            box-shadow: 0 4px 16px rgba(44, 36, 22, .08);
        }
    </style>
@endpush

@section('content')
    <div class="page-title">Notifications</div>
    <div class="page-sub">Your booking updates and resort announcements</div>

    @forelse($notifications as $notif)
        @php
            $icons = [
                'booking_update' => ['icon' => 'bi-calendar-check', 'bg' => '#dcfce7', 'color' => '#15803d'],
                'payment' => ['icon' => 'bi-credit-card', 'bg' => '#dbeafe', 'color' => '#1d4ed8'],
                'cancellation' => ['icon' => 'bi-x-circle', 'bg' => '#fee2e2', 'color' => '#dc2626'],
                'reminder' => ['icon' => 'bi-bell', 'bg' => '#fef9c3', 'color' => '#a16207'],
            ];
            $style = $icons[$notif->type] ?? ['icon' => 'bi-info-circle', 'bg' => '#f1f5f9', 'color' => '#475569'];
        @endphp
        <a href="{{ route('customer.notifications.open', $notif) }}"
            class="notif-card {{ !$notif->is_read ? 'unread' : '' }}">
            <div class="notif-inner">
                <div class="notif-icon-wrap" style="background:{{ $style['bg'] }};color:{{ $style['color'] }};">
                    <i class="bi {{ $style['icon'] }}"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-title">{{ $notif->title }}</div>
                    <div class="notif-msg">{{ $notif->message }}</div>
                    <div class="notif-time">{{ $notif->created_at->diffForHumans() }} ·
                        {{ $notif->created_at->format('M d, Y h:i A') }}</div>
                </div>
                @if (!$notif->is_read)
                    <div class="unread-dot"></div>
                @endif
            </div>
        </a>
    @empty
        <div class="empty-state">
            <i class="bi bi-bell-slash"></i>
            <p style="font-size:15px;margin-bottom:4px;">No notifications yet</p>
            <p style="font-size:13px;">We'll notify you about booking updates and important information.</p>
        </div>
    @endforelse

    @if ($notifications->hasPages())
        <div style="margin-top:20px;">{{ $notifications->links() }}</div>
    @endif
@endsection

