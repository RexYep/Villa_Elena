{{-- Listahan ng ulat ng guest para sa isang booking. Iisang partial para sa
     unang render (booking detail at dashboard) at sa live na refetch
     (customer.bookings.issues.index) — kaya hindi magkaiba ang dalawa.
     Walang output kapag walang ulat. --}}
@if ($reports->isNotEmpty())
    <div class="issue-list {{ ($withCta ?? false) ? 'with-cta' : '' }}">
        <div class="issue-list-label">Your reports</div>
        @foreach ($reports as $report)
            <div class="issue-item">
                <i class="bi bi-{{ $report->category_icon }}" aria-hidden="true"></i>
                <div class="issue-item-main">
                    <div class="issue-item-title">{{ $report->category_label }}</div>
                    @if ($report->description)
                        <div class="issue-item-desc">{{ $report->description }}</div>
                    @endif
                    <div class="issue-item-time">Sent {{ $report->created_at->format('M j, g:i A') }}</div>
                </div>
                <span class="issue-status {{ $report->status_class }}">{{ $report->guest_status_label }}</span>
            </div>
        @endforeach
    </div>
@endif
