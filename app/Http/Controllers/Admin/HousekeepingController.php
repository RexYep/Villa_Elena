<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\IssueReport;
use App\Models\Property;
use App\Models\StaffLog;
use App\Services\FrontdeskBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Housekeeping (v7.11) — ang admin ay nagpapadala ng task sa staff, at
 * sinusubaybayan ang mga ulat ng problema mula sa guest at staff.
 *
 * Walang awtomatikong task na simula v7.11: ang check-in ay hindi na
 * gumagawa ng `checkout_clean`. Ang ulat ng guest ay HINDI dumadaan dito
 * muna — dumarating sa staff at sa admin nang sabay — kaya ang page na ito
 * ay para sa pagsubaybay, hindi hadlang sa pagkumpuni.
 */
class HousekeepingController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->query('view') === 'closed' ? 'closed' : 'open';

        $tasks = HousekeepingTask::query()
            ->with(['creator'])
            ->when($view === 'open',
                fn ($q) => $q->open()
                    ->orderByRaw("priority = 'urgent' desc")
                    ->orderBy('due_date')
                    ->orderByRaw('due_time is null, due_time'),
                fn ($q) => $q->closed()->orderByDesc('updated_at')->orderByDesc('id'))
            ->paginate(15, ['*'], 'tasks_page')
            ->withQueryString();

        $reports = $this->reports($request, $view);
        $stats   = $this->stats();

        return view('admin.housekeeping.index', compact('view', 'tasks', 'reports', 'stats'));
    }

    /**
     * GET /admin/housekeeping/live — ang stats row at ang Issue Reports card,
     * muling ni-render ng parehong partial na ginamit ng index(). Tinatawag
     * ng page kapag may `issues.changed` (bagong ulat o nagbagong status),
     * at tuwing 60s bilang salo. Walang kopya ng listahan sa JS.
     */
    public function live(Request $request)
    {
        $view    = $request->query('view') === 'closed' ? 'closed' : 'open';
        $reports = $this->reports($request, $view);
        $stats   = $this->stats();

        return response()->json([
            'stats_html'   => view('admin.housekeeping._stats', compact('stats'))->render(),
            'reports_html' => view('admin.housekeeping._reports', compact('reports', 'view'))->render(),
        ]);
    }

    private function reports(Request $request, string $view)
    {
        return IssueReport::query()
            ->with(['reporter', 'booking'])
            ->when($view === 'open',
                fn ($q) => $q->open()->oldest(),
                fn ($q) => $q->closed()->orderByDesc('updated_at')->orderByDesc('id'))
            ->paginate(15, ['*'], 'reports_page')
            // Mula man sa index() o sa live(), ang pagination links ay
            // laging tumuturo sa page mismo, hindi sa JSON endpoint.
            ->withPath(route('admin.housekeeping.index'))
            ->appends($request->except('reports_page'));
    }

    private function stats(): array
    {
        $weekStart = now()->startOfWeek();

        return [
            'open_tasks'   => HousekeepingTask::open()->count(),
            'open_reports' => IssueReport::open()->count(),
            // Overdue ay nangangailangan ng due_time, kaya kinukuwenta sa PHP
            // — ang mga bukas na task ay laging kaunti.
            'overdue'      => HousekeepingTask::open()->get()->filter->isOverdue()->count(),
            'done_week'    => HousekeepingTask::where('status', 'completed')->where('completed_at', '>=', $weekStart)->count()
                            + IssueReport::where('status', 'completed')->where('completed_at', '>=', $weekStart)->count(),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validateWithBag('newTask', [
            'task_type' => 'required|in:' . implode(',', array_keys(HousekeepingTask::TYPES)),
            'title'     => 'required|string|max:150',
            'location'  => 'nullable|string|max:100',
            'priority'  => 'required|in:' . implode(',', array_keys(HousekeepingTask::PRIORITIES)),
            'due_date'  => 'required|date|after_or_equal:today',
            'due_time'  => 'nullable|date_format:H:i',
            'notes'     => 'nullable|string|max:1000',
        ], [
            'due_date.after_or_equal' => 'The due date cannot be in the past.',
        ]);

        // `after_or_equal:today` ay petsa lang — ang oras na lumipas na
        // ngayong araw ay magpapadala ng task na overdue na mula umpisa.
        if (! empty($data['due_time'])
            && now()->gt(\Illuminate\Support\Carbon::parse("{$data['due_date']} {$data['due_time']}"))) {
            return back()->withInput()->withErrors(['due_time' => 'That time has already passed today.'], 'newTask');
        }

        $task = HousekeepingTask::create($data + [
            'property_id' => Property::where('type', 'villa')->value('id'),
            'created_by'  => Auth::id(),
            'status'      => 'pending',
        ]);

        StaffLog::record('task_created', 'housekeeping_tasks', $task->id,
            "Sent task \"{$task->headline}\" to staff (due {$task->due_label})");

        FrontdeskBroadcast::send(
            'task_assigned',
            ($task->priority === 'urgent' ? 'URGENT task' : 'New task') . " from admin: \"{$task->headline}\" — due {$task->due_label}.",
            taskId: $task->id,
            actor: Auth::user()->full_name,
        );

        return redirect()->route('admin.housekeeping.index')
            ->with('success', "Task \"{$task->headline}\" sent to the staff frontdesk.");
    }

    /** Admin ay pwedeng markahan itong Done (hal. sinabi ng staff nang personal) o Cancelled. */
    public function updateTask(Request $request, HousekeepingTask $task)
    {
        $status = $request->validate(['status' => 'required|in:completed,cancelled'])['status'];

        $changed = $status === 'completed' ? $task->markCompleted() : $task->markCancelled();
        if (! $changed) {
            return back()->with('error', "Task \"{$task->headline}\" is already {$task->status_label}.");
        }

        StaffLog::record("task_{$status}", 'housekeeping_tasks', $task->id,
            "Admin marked task \"{$task->headline}\" {$task->status_label}");
        FrontdeskBroadcast::send("task_{$status}", "Admin marked task \"{$task->headline}\" {$task->status_label}.",
            taskId: $task->id, actor: Auth::user()->full_name);

        return back()->with('success', "Task \"{$task->headline}\" marked {$task->status_label}.");
    }

    public function updateReport(Request $request, IssueReport $report)
    {
        $status = $request->validate(['status' => 'required|in:completed,cancelled'])['status'];

        $changed = $status === 'completed' ? $report->markCompleted() : $report->markCancelled();
        if (! $changed) {
            return back()->with('error', "This report is already {$report->status_label}.");
        }

        StaffLog::record("issue_{$status}", 'issue_reports', $report->id,
            "Admin marked {$report->category_label} report {$report->status_label}");
        FrontdeskBroadcast::send("issue_{$status}", "Admin marked the {$report->category_label} issue {$report->status_label}.",
            reportId: $report->id, actor: Auth::user()->full_name);

        return back()->with('success', "{$report->category_label} report marked {$report->status_label}.");
    }
}
