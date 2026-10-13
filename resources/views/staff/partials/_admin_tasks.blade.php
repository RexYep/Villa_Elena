        {{-- The tasks the admin sent. A partial because two paths render it: the
             page, and GET /staff/frontdesk/housekeeping, which re-renders it when
             a task is sent, started, finished or cancelled. --}}
        <div class="card">
            <div class="card-head">
                <h3><i class="bi bi-list-task me-2 text-amber"></i>Tasks from Admin</h3>
                <span class="text-muted-theme fs-14">{{ $inProgressTasks->count() + $pendingTasks->count() }} open</span>
            </div>
            <div class="card-body">
                @forelse ($inProgressTasks->concat($pendingTasks) as $task)
                    <div class="task-row">
                        <div class="task-type-icon {{ $task->status === 'in_progress' ? 'tag-blue' : 'tag-amber' }}">
                            <i class="bi bi-{{ $task->type_icon }}"></i>
                        </div>
                        <div class="task-info">
                            <div class="task-prop">
                                {{ $task->headline }}
                                @if ($task->priority === 'urgent')
                                    <span class="ws-badge ws-urgent">Urgent</span>
                                @endif
                                <span class="ws-badge {{ $task->status_class }}">{{ $task->status_label }}</span>
                            </div>
                            <div class="task-details">
                                {{ $task->type_label }}@if ($task->location) · {{ $task->location }}@endif
                                @if ($task->notes)
                                    · {{ $task->notes }}
                                @endif
                            </div>
                            @include('staff.partials.task_deadline', ['task' => $task])
                        </div>
                        <div class="task-actions">
                            @if ($task->status === 'pending')
                                <form method="POST" action="{{ route('staff.tasks.start', $task) }}" class="task-form-{{ $task->id }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn-sm btn-start"><i class="bi bi-play-fill"></i> Start</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('staff.tasks.complete', $task) }}" class="task-form-{{ $task->id }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn-sm btn-complete"><i class="bi bi-check-lg"></i> Done</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty">
                        <i class="bi bi-check-circle"></i>
                        <p>No tasks from the admin right now.</p>
                    </div>
                @endforelse
            </div>
        </div>
