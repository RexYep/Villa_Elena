    @if ($urgentItem)
        @php $item = $urgentItem['item']; @endphp
        @if ($urgentItem['kind'] === 'report')
            <div class="clean-banner urgent">
                <div class="clean-icon"><i class="bi bi-{{ $item->category_icon }}"></i></div>
                <div class="clean-main">
                    <div class="clean-title">
                        {{ $item->isFromGuest() ? 'Guest reported a problem' : 'Issue reported' }}:
                        {{ $item->category_label }}
                    </div>
                    <div class="clean-sub">
                        @if ($item->description)
                            "{{ \Illuminate\Support\Str::limit($item->description, 120) }}" ·
                        @endif
                        {{ $item->source_label }} · {{ $item->created_at->diffForHumans() }}
                        · {{ $item->status_label }}
                    </div>
                </div>
                <div class="banner-actions">
                    @if ($item->status === 'pending')
                        <form method="POST" action="{{ route('staff.reports.start', $item) }}" class="report-form-{{ $item->id }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-clean btn-clean-start"><i class="bi bi-play-fill"></i> Start fixing</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('staff.reports.complete', $item) }}" class="report-form-{{ $item->id }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn-clean"><i class="bi bi-check-lg"></i> Mark fixed</button>
                    </form>
                </div>
            </div>
        @else
            @php $tone = $item->due_tone === 'late' ? 'urgent' : ($item->due_tone === 'soon' || $item->priority === 'urgent' ? 'soon' : 'calm'); @endphp
            <div class="clean-banner {{ $tone }}">
                <div class="clean-icon"><i class="bi bi-{{ $item->type_icon }}"></i></div>
                <div class="clean-main">
                    <div class="clean-title">
                        @if ($item->priority === 'urgent')
                            Urgent task:
                        @else
                            Task from admin:
                        @endif
                        {{ $item->headline }}
                    </div>
                    <div class="clean-sub">
                        @if ($item->isOverdue())
                            Overdue — was due {{ $item->due_label }}
                        @else
                            Due {{ $item->due_label }} ({{ $item->due_at->diffForHumans(null, true) }} left)
                        @endif
                        @if ($item->location)
                            · {{ $item->location }}
                        @endif
                        · {{ $item->status_label }}
                    </div>
                </div>
                <div class="banner-actions">
                    @if ($item->status === 'pending')
                        <form method="POST" action="{{ route('staff.tasks.start', $item) }}" class="task-form-{{ $item->id }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-clean btn-clean-start"><i class="bi bi-play-fill"></i> Start</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('staff.tasks.complete', $item) }}" class="task-form-{{ $item->id }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn-clean"><i class="bi bi-check-lg"></i> Mark done</button>
                    </form>
                </div>
            </div>
        @endif
    @endif
