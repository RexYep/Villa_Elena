{{--
    Deadline line para sa isang housekeeping task.

    Ang "Ready by" ay ang oras ng SUSUNOD na check-in, hindi ang
    scheduled_date — dalawang oras lang ang pagitan ng checkout at ng
    susunod na slot (5PM→7PM, 6AM→8AM), kaya ang petsa lang ay hindi
    sapat para malaman kung kailan talaga kailangang tapos.

    Kung walang naka-book na kasunod, walang deadline — ipapakita na
    lang ang scheduled date, at hindi ito minamadali.
--}}
@if($task->ready_by)
    @php
        $minutesLeft = now()->diffInMinutes($task->ready_by, false);
        $tone = $minutesLeft < 0 ? 'late' : ($minutesLeft <= 240 ? 'soon' : 'ok');
    @endphp
    <div class="task-ready {{ $tone }}">
        @if($minutesLeft < 0)
            <i class="bi bi-exclamation-triangle-fill"></i>
            Next guest was due {{ $task->ready_by->format('M j, g:i A') }} — {{ $task->ready_by->diffForHumans() }}
        @else
            <i class="bi bi-clock-fill"></i>
            Ready by {{ $task->ready_by->format('M j, g:i A') }}
            ({{ $task->ready_by->diffForHumans(null, true) }} left)
        @endif
        @if($task->next_guest)
            · {{ $task->next_guest }}
        @endif
    </div>
@else
    <div class="task-date">
        Scheduled: {{ $task->scheduled_date?->format('M d, Y') ?? '—' }} · no booking follows yet
    </div>
@endif
