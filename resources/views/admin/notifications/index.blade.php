@extends('layouts.admin')

@section('title', 'Notifications — Villa Elena Admin')
@section('page-title', 'Notifications')
@section('page-subtitle', 'Booking, payment, and guest activity alerts')

@push('styles')
    <style>
        .notif-card {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 10px;
            display: block;
            text-decoration: none;
            color: inherit;
            transition: box-shadow .2s;
        }

        .notif-card:hover {
            box-shadow: 0 4px 16px rgba(44, 36, 22, .08);
        }

        .notif-card.unread {
            border-left: 3px solid var(--terracotta);
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
            color: var(--text-main);
        }

        .notif-msg {
            font-size: 13px;
            color: var(--muted);
            margin-top: 3px;
            line-height: 1.5;
        }

        .notif-time {
            font-size: 13px;
            color: var(--muted);
            margin-top: 5px;
        }

        .unread-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--terracotta);
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
    </style>
@endpush

@section('content')
    @forelse($notifications as $notif)
        @php
            $icons = [
                'in_app' => ['icon' => 'bi-info-circle', 'bg' => '#f1f5f9', 'color' => '#475569'],
            ];
            $style = $icons[$notif->type] ?? $icons['in_app'];
        @endphp
        <a href="{{ route('admin.notifications.open', $notif) }}" class="notif-card {{ !$notif->is_read ? 'unread' : '' }}">
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
            <p style="font-size:13px;">Booking, payment, and guest activity will show up here.</p>
        </div>
    @endforelse

    @if ($notifications->hasPages())
        <div style="margin-top:20px;">{{ $notifications->links() }}</div>
    @endif
@endsection

