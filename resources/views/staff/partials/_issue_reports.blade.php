        <div class="card mb-3">
            <div class="card-head">
                <h3><i class="bi bi-exclamation-octagon me-2" style="color:#dc2626;"></i>Issue Reports</h3>
                <span class="text-muted-theme" style="font-size: 14px;">{{ $openReports->count() }} open</span>
            </div>
            <div class="card-body">
                @forelse ($openReports as $report)
                    <div class="task-row">
                        <div class="task-type-icon {{ $report->isFromGuest() ? 'tag-red' : 'tag-amber' }}">
                            <i class="bi bi-{{ $report->category_icon }}"></i>
                        </div>
                        <div class="task-info">
                            <div class="task-prop">
                                {{ $report->category_label }}
                                <span class="ws-badge {{ $report->status_class }}">{{ $report->status_label }}</span>
                            </div>
                            @if ($report->description)
                                <div class="task-details">{{ $report->description }}</div>
                            @endif
                            <div class="task-date">{{ $report->source_label }} · {{ $report->created_at->diffForHumans() }}</div>
                        </div>
                        <div class="task-actions">
                            @if ($report->status === 'pending')
                                <form method="POST" action="{{ route('staff.reports.start', $report) }}" class="report-form-{{ $report->id }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-sm btn-start"><i class="bi bi-play-fill"></i> Start</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('staff.reports.complete', $report) }}" class="report-form-{{ $report->id }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-sm btn-complete"><i class="bi bi-check-lg"></i> Fixed</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-emoji-smile"></i>
                        <p>No reported problems.</p>
                    </div>
                @endforelse
            </div>
        </div>
