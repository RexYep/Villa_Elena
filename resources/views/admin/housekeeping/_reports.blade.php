        <div class="table-card">
            <div class="table-header">
                <h3>Issue Reports</h3>
                <span class="count">{{ $reports->total() }} {{ $view === 'open' ? 'open' : 'closed' }}</span>
            </div>

            @forelse ($reports as $report)
                <div class="hk-row">
                    <div class="hk-icon {{ $report->isFromGuest() ? 'guest' : '' }}">
                        <i class="bi bi-{{ $report->category_icon }}"></i>
                    </div>
                    <div class="hk-main">
                        <div class="hk-title">
                            {{ $report->category_label }}
                            <span class="ws-badge tag-source">{{ $report->isFromGuest() ? 'Guest' : 'Staff' }}</span>
                            <span class="ws-badge {{ $report->status_class }}">{{ $report->status_label }}</span>
                        </div>
                        @if ($report->description)
                            <div class="hk-desc">{{ $report->description }}</div>
                        @endif
                        <div class="hk-meta">
                            {{ $report->source_label }} · reported {{ $report->created_at->diffForHumans() }}
                            @if ($report->status === 'in_progress' && $report->started_at)
                                · being fixed since {{ $report->started_at->format('g:i A') }}
                            @elseif ($report->status === 'completed' && $report->completed_at)
                                · fixed {{ $report->completed_at->format('M j, g:i A') }}
                                ({{ $report->created_at->diffForHumans($report->completed_at, true) }} after the report)
                            @elseif ($report->status === 'cancelled')
                                · closed {{ $report->updated_at->format('M j, g:i A') }}
                            @endif
                        </div>
                        @if ($report->isOpen())
                            <div class="hk-actions">
                                <form method="POST" action="{{ route('admin.housekeeping.reports.update', $report) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="hk-btn done"><i class="bi bi-check-lg"></i> Mark fixed</button>
                                </form>
                                <form method="POST" action="{{ route('admin.housekeeping.reports.update', $report) }}"
                                    onsubmit="return confirm('Close this report without fixing it? Use this when it wasn\'t a real problem.')">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="hk-btn cancel">Close</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="hk-empty">
                    <i class="bi bi-emoji-smile"></i>
                    {{ $view === 'open' ? 'No open reports. Guests can report problems from their booking page while checked in.' : 'No closed reports yet.' }}
                </div>
            @endforelse

            @if ($reports->hasPages())
                <div class="pagination-wrap">{{ $reports->links() }}</div>
            @endif
        </div>
