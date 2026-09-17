{{--
    Deadline line para sa isang housekeeping task (v7.11).

    Ang due date/time ay itinakda ng admin nang ipadala ang task. Walang
    oras → "any time" sa araw na iyon, at overdue lang pagkatapos ng araw.
--}}
@if ($task->due_at)
    <div class="task-ready {{ $task->due_tone }}">
        @if ($task->isOverdue())
            <i class="bi bi-exclamation-triangle-fill"></i>
            Overdue — was due {{ $task->due_label }}
        @else
            <i class="bi bi-clock-fill"></i>
            Due {{ $task->due_label }}
            ({{ $task->due_at->diffForHumans(null, true) }} left)
        @endif
    </div>
@endif
