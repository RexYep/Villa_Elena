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

        /* Ang `min-width: 0` dito ang pumipigil sa isang tahimik na
           pagkawala ng teksto. Ang default na `min-width: auto` ng isang
           flex item ay hindi siya pinapayagang lumiit nang mas maliit pa sa
           pinakamahabang salita nito — kaya ang isang mahabang reference o
           email ay nagpapalobo sa kahon LAMPAS sa gilid ng card (77px sa
           320px, sinukat), at dahil `overflow: hidden` ang `.notif-card`,
           basta na lang PINUPUTOL ang natitira: walang ellipsis, walang
           scrollbar, at malinis pa rin ang sukat ng pahina. Kasama ang
           `overflow-wrap` para mabali mismo ang salita. */
        .notif-body {
            flex: 1;
            min-width: 0;
        }

        .notif-title,
        .notif-msg {
            overflow-wrap: anywhere;
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

        /* Sa 320px ay 171px lang ang natitira sa teksto matapos ang icon,
           ang tuldok at ang padding. Ang padding ang pinakamurang ibigay. */
        @media (max-width:480px) {
            .notif-inner {
                gap: 12px;
                padding: 14px 16px;
            }

            .notif-icon-wrap {
                width: 34px;
                height: 34px;
                font-size: 15px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="page-title">Notifications</div>
    <div class="page-sub">Your booking updates and resort announcements</div>

    @forelse($notifications as $notif)
        @php
            // Ang lumang bersyon ay nag-uuri ayon sa `$notif->type`, na may
            // mga susi tulad ng 'booking_update' at 'payment'. Walang tugma
            // kailanman: lahat ng 279 na row sa notifications ay `in_app`
            // (tingnan ang CLAUDE.md — ito ang tanging halagang ginagamit sa
            // praktika), kaya IISANG kulay-abong "info" na icon ang ipinapakita
            // ng bawat abiso at patay na code ang buong mapa. Ang pamagat ang
            // tanging bahagi na talagang nagsasabi kung tungkol saan ito.
            $subject = \Illuminate\Support\Str::lower($notif->title);
            $style = match (true) {
                str_contains($subject, 'cancel'), str_contains($subject, 'failed'), str_contains($subject, 'rejected')
                    => ['icon' => 'bi-x-circle', 'bg' => '#fee2e2', 'color' => '#dc2626'],
                str_contains($subject, 'refund')
                    => ['icon' => 'bi-arrow-counterclockwise', 'bg' => '#e0e7ff', 'color' => '#4338ca'],
                str_contains($subject, 'payment'), str_contains($subject, 'paid')
                    => ['icon' => 'bi-credit-card', 'bg' => '#dbeafe', 'color' => '#1d4ed8'],
                str_contains($subject, 'reschedul')
                    => ['icon' => 'bi-calendar-event', 'bg' => '#fef9c3', 'color' => '#a16207'],
                str_contains($subject, 'promo'), str_contains($subject, 'offer'), str_contains($subject, 'announcement')
                    => ['icon' => 'bi-megaphone', 'bg' => '#fef9c3', 'color' => '#a16207'],
                str_contains($subject, 'check-out'), str_contains($subject, 'checked out'), str_contains($subject, 'complete')
                    => ['icon' => 'bi-box-arrow-right', 'bg' => '#dcfce7', 'color' => '#15803d'],
                str_contains($subject, 'check-in'), str_contains($subject, 'checked in'), str_contains($subject, 'welcome'), str_contains($subject, 'arrived')
                    => ['icon' => 'bi-box-arrow-in-right', 'bg' => '#dcfce7', 'color' => '#15803d'],
                str_contains($subject, 'booking'), str_contains($subject, 'reservation')
                    => ['icon' => 'bi-calendar-check', 'bg' => '#dcfce7', 'color' => '#15803d'],
                str_contains($subject, 'reminder')
                    => ['icon' => 'bi-bell', 'bg' => '#fef9c3', 'color' => '#a16207'],
                default
                    => ['icon' => 'bi-info-circle', 'bg' => '#f1f5f9', 'color' => '#475569'],
            };
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

