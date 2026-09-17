@extends('layouts.admin')

@section('title', 'Housekeeping — Villa Elena Admin')
@section('page-title', 'Housekeeping')
@section('page-subtitle', 'Send tasks to the staff frontdesk and follow issue reports')

@push('styles')
    <style>
        .hk-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .hk-views {
            display: inline-flex;
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 3px;
        }

        .hk-views a {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            text-decoration: none;
        }

        .hk-views a.active {
            background: var(--terracotta);
            color: #fff;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--terracotta);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }

        .btn-add:hover {
            background: var(--gold);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--cream);
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 18px 20px;
        }

        .stat-val {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--stone);
            line-height: 1;
        }

        .stat-val.alert-val {
            color: #dc2626;
        }

        .stat-lbl {
            font-size: 13px;
            color: var(--muted);
            margin-top: 6px;
        }

        .hk-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        .table-header .count {
            font-size: 13px;
            color: var(--muted);
            font-family: 'DM Sans', sans-serif;
            font-weight: 400;
        }

        .hk-row {
            display: flex;
            gap: 14px;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
        }

        .hk-row:last-child {
            border-bottom: none;
        }

        .hk-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
            background: var(--gold-dim);
            color: var(--gold);
        }

        .hk-icon.guest {
            background: #fee2e2;
            color: #dc2626;
        }

        .hk-main {
            flex: 1;
            min-width: 0;
        }

        .hk-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .hk-desc {
            font-size: 13px;
            color: var(--text-main);
            margin-top: 4px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .hk-meta {
            font-size: 13px;
            color: var(--muted);
            margin-top: 5px;
            line-height: 1.5;
        }

        .hk-due {
            font-weight: 600;
        }

        .hk-due.late {
            color: #dc2626;
        }

        .hk-due.soon {
            color: #a16207;
        }

        .hk-actions {
            display: flex;
            gap: 6px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .hk-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 7px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text-main);
        }

        .hk-btn.done {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }

        .hk-btn.done:hover {
            background: #15803d;
        }

        .hk-btn.cancel:hover {
            border-color: #ef4444;
            color: #dc2626;
        }

        .ws-badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .ws-pending { background: #fef3c7; color: #92400e; }
        .ws-in-progress { background: #dbeafe; color: #1d4ed8; }
        .ws-completed { background: #dcfce7; color: #15803d; }
        .ws-cancelled { background: #e2e8f0; color: #475569; }
        .tag-urgent { background: #fee2e2; color: #b91c1c; }
        .tag-source { background: var(--sand); color: var(--stone); }

        .hk-empty {
            text-align: center;
            padding: 44px 20px;
            color: var(--muted);
            font-size: 13px;
        }

        .hk-empty i {
            font-size: 34px;
            display: block;
            margin-bottom: 8px;
            opacity: .4;
        }

        .priority-pick {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .priority-pick label {
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 9px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            background: #fff;
        }

        .priority-pick input {
            position: absolute;
            opacity: 0;
        }

        .priority-pick input:checked+span {
            font-weight: 700;
        }

        .priority-pick label:has(input:checked) {
            border-color: var(--terracotta);
            background: var(--gold-dim);
        }

        .priority-pick label:has(input[value="urgent"]:checked) {
            border-color: #ef4444;
            background: #fef2f2;
            color: #b91c1c;
        }

        .priority-pick label:has(input:focus-visible) {
            outline: 2px solid var(--terracotta);
            outline-offset: 2px;
        }

        .modal-field {
            margin-bottom: 14px;
        }

        @media (max-width: 1100px) {
            .hk-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }

            .hk-top .btn-add {
                width: 100%;
                justify-content: center;
            }

            .hk-row {
                padding: 14px 16px;
            }

            .two-col {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-val {{ $stats['open_reports'] ? 'alert-val' : '' }}">{{ $stats['open_reports'] }}</div>
            <div class="stat-lbl">Open issue reports</div>
        </div>
        <div class="stat-card">
            <div class="stat-val">{{ $stats['open_tasks'] }}</div>
            <div class="stat-lbl">Open tasks</div>
        </div>
        <div class="stat-card">
            <div class="stat-val {{ $stats['overdue'] ? 'alert-val' : '' }}">{{ $stats['overdue'] }}</div>
            <div class="stat-lbl">Overdue tasks</div>
        </div>
        <div class="stat-card">
            <div class="stat-val">{{ $stats['done_week'] }}</div>
            <div class="stat-lbl">Done this week</div>
        </div>
    </div>

    <div class="hk-top">
        <nav class="hk-views" aria-label="Filter">
            <a href="{{ route('admin.housekeeping.index') }}" class="{{ $view === 'open' ? 'active' : '' }}"
                @if ($view === 'open') aria-current="page" @endif>Open</a>
            <a href="{{ route('admin.housekeeping.index', ['view' => 'closed']) }}"
                class="{{ $view === 'closed' ? 'active' : '' }}"
                @if ($view === 'closed') aria-current="page" @endif>Done &amp; cancelled</a>
        </nav>
        <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#newTaskModal">
            <i class="bi bi-plus-lg"></i> New Task
        </button>
    </div>

    <div class="hk-grid">

        {{-- Issue reports — mula sa guest (naka-check-in) o staff --}}
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

        {{-- Tasks na ipinadala sa staff --}}
        <div class="table-card">
            <div class="table-header">
                <h3>Tasks</h3>
                <span class="count">{{ $tasks->total() }} {{ $view === 'open' ? 'open' : 'closed' }}</span>
            </div>

            @forelse ($tasks as $task)
                <div class="hk-row">
                    <div class="hk-icon"><i class="bi bi-{{ $task->type_icon }}"></i></div>
                    <div class="hk-main">
                        <div class="hk-title">
                            {{ $task->headline }}
                            @if ($task->priority === 'urgent')
                                <span class="ws-badge tag-urgent">Urgent</span>
                            @endif
                            <span class="ws-badge {{ $task->status_class }}">{{ $task->status_label }}</span>
                        </div>
                        <div class="hk-meta">
                            {{ $task->type_label }}@if ($task->location) · {{ $task->location }}@endif
                            · <span class="hk-due {{ $task->due_tone }}">
                                @if ($task->isOverdue())
                                    <i class="bi bi-exclamation-triangle-fill"></i> Overdue — was due {{ $task->due_label }}
                                @else
                                    Due {{ $task->due_label }}
                                @endif
                            </span>
                        </div>
                        @if ($task->notes)
                            <div class="hk-desc">{{ $task->notes }}</div>
                        @endif
                        <div class="hk-meta">
                            @if ($task->creator)
                                Sent by {{ $task->creator->full_name }} {{ $task->created_at->diffForHumans() }}
                            @else
                                Created automatically {{ $task->created_at->format('M j, Y') }} (old system)
                            @endif
                            @if ($task->status === 'in_progress' && $task->started_at)
                                · started {{ $task->started_at->format('M j, g:i A') }}
                            @elseif ($task->status === 'completed' && $task->completed_at)
                                · done {{ $task->completed_at->format('M j, g:i A') }}
                                @if ($task->started_at)
                                    (took {{ $task->started_at->diffForHumans($task->completed_at, true) }})
                                @endif
                            @endif
                        </div>
                        @if ($task->isOpen())
                            <div class="hk-actions">
                                <form method="POST" action="{{ route('admin.housekeeping.tasks.update', $task) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="hk-btn done"><i class="bi bi-check-lg"></i> Mark done</button>
                                </form>
                                <form method="POST" action="{{ route('admin.housekeeping.tasks.update', $task) }}"
                                    onsubmit="return confirm('Cancel this task? The staff frontdesk will stop showing it.')">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="hk-btn cancel">Cancel task</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="hk-empty">
                    <i class="bi bi-clipboard-check"></i>
                    {{ $view === 'open' ? 'No open tasks. Use "New Task" to send one to the staff frontdesk.' : 'No finished or cancelled tasks yet.' }}
                </div>
            @endforelse

            @if ($tasks->hasPages())
                <div class="pagination-wrap">{{ $tasks->links() }}</div>
            @endif
        </div>
    </div>

@endsection

@section('modals')
    @php $taskErrors = $errors->newTask; @endphp
    <div class="modal fade" id="newTaskModal" tabindex="-1" aria-labelledby="newTaskTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:14px;border:none;">
                <form method="POST" action="{{ route('admin.housekeeping.tasks.store') }}">
                    @csrf
                    <div class="modal-body" style="padding:24px;">
                        <h5 id="newTaskTitle" style="font-family:'Cormorant Garamond',serif;font-size:22px;margin-bottom:4px;">
                            New task for staff</h5>
                        <p style="font-size:13px;color:var(--muted);margin-bottom:18px;">
                            It appears on the staff frontdesk right away.
                        </p>

                        <div class="modal-field">
                            <label class="form-label" for="taskTitle">What needs to be done <span class="req">*</span></label>
                            <input type="text" id="taskTitle" name="title" maxlength="150" required
                                class="form-control {{ $taskErrors->has('title') ? 'is-invalid' : '' }}"
                                value="{{ old('title') }}" placeholder="e.g. Clean the villa before the 7 PM guests">
                            @if ($taskErrors->has('title'))
                                <span class="invalid-feedback">{{ $taskErrors->first('title') }}</span>
                            @endif
                        </div>

                        <div class="two-col modal-field">
                            <div>
                                <label class="form-label" for="taskType">Type <span class="req">*</span></label>
                                <select id="taskType" name="task_type" class="form-select">
                                    @foreach (\App\Models\HousekeepingTask::TYPES as $key => $type)
                                        <option value="{{ $key }}" @selected(old('task_type', 'checkout_clean') === $key)>{{ $type['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="taskLocation">Location</label>
                                <input type="text" id="taskLocation" name="location" maxlength="100" class="form-control"
                                    value="{{ old('location') }}" placeholder="e.g. Room C, Pool area">
                            </div>
                        </div>

                        <div class="two-col modal-field">
                            <div>
                                <label class="form-label" for="taskDueDate">Due date <span class="req">*</span></label>
                                <input type="date" id="taskDueDate" name="due_date" required min="{{ today()->format('Y-m-d') }}"
                                    class="form-control {{ $taskErrors->has('due_date') ? 'is-invalid' : '' }}"
                                    value="{{ old('due_date', today()->format('Y-m-d')) }}">
                                @if ($taskErrors->has('due_date'))
                                    <span class="invalid-feedback">{{ $taskErrors->first('due_date') }}</span>
                                @endif
                            </div>
                            <div>
                                <label class="form-label" for="taskDueTime">Due time</label>
                                <input type="time" id="taskDueTime" name="due_time"
                                    class="form-control {{ $taskErrors->has('due_time') ? 'is-invalid' : '' }}"
                                    value="{{ old('due_time') }}">
                                @if ($taskErrors->has('due_time'))
                                    <span class="invalid-feedback">{{ $taskErrors->first('due_time') }}</span>
                                @else
                                    <span class="hint">Leave empty for "any time that day".</span>
                                @endif
                            </div>
                        </div>

                        <div class="modal-field">
                            <span class="form-label" id="priorityLabel">Priority</span>
                            <div class="priority-pick" role="radiogroup" aria-labelledby="priorityLabel">
                                @foreach (\App\Models\HousekeepingTask::PRIORITIES as $key => $label)
                                    <label>
                                        <input type="radio" name="priority" value="{{ $key }}" @checked(old('priority', 'normal') === $key)>
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="modal-field">
                            <label class="form-label" for="taskNotes">Notes for staff</label>
                            <textarea id="taskNotes" name="notes" rows="3" maxlength="1000" class="form-control"
                                placeholder="Anything staff should know">{{ old('notes') }}</textarea>
                        </div>

                        <div class="d-flex gap-2" style="margin-top:20px;">
                            <button type="button" class="btn btn-light w-50" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-add w-50" style="justify-content:center;">
                                <i class="bi bi-send"></i> Send to staff
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($taskErrors->any())
        <script>
            // Validation error — buksan muli ang form para hindi mawala ang inilagay.
            document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('newTaskModal')).show());
        </script>
    @endif
@endsection
