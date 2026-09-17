<?php

namespace App\Http\Controllers\Staff;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\IssueReport;
use App\Models\Property;
use App\Models\StaffLog;
use App\Services\FrontdeskBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Housekeeping sa frontdesk (v7.11): pagtanggap ng task mula sa admin, at
 * pag-uulat/pag-aayos ng problema.
 *
 * Ang staff ay isang pinagsasaluhang account sa iisang monitor, kaya hindi
 * itinatala kung sinong staff — sapat ang status. Ang admin ay inaabisuhan
 * kapag tapos, dahil hindi laging nakabukas ang admin portal.
 */
class HousekeepingController extends Controller
{
    // ── Tasks mula sa admin ────────────────────────────────────────
    public function startTask(HousekeepingTask $task)
    {
        if (! $task->markStarted()) {
            return back()->with('tab', 'housekeeping')->with('error', 'This task was already started, finished or cancelled.');
        }

        StaffLog::record('task_started', 'housekeeping_tasks', $task->id, "Started task \"{$task->headline}\"");
        FrontdeskBroadcast::send('task_started', "Task \"{$task->headline}\" started.",
            taskId: $task->id, actor: Auth::user()->full_name);

        return back()->with('tab', 'housekeeping')->with('success', "Task \"{$task->headline}\" is now in progress.");
    }

    public function completeTask(HousekeepingTask $task)
    {
        if (! $task->markCompleted()) {
            return back()->with('tab', 'housekeeping')->with('error', 'This task was already finished or cancelled.');
        }

        StaffLog::record('task_completed', 'housekeeping_tasks', $task->id, "Completed task \"{$task->headline}\"");
        FrontdeskBroadcast::send('task_completed', "Task \"{$task->headline}\" marked done.",
            taskId: $task->id, actor: Auth::user()->full_name);
        NotificationHelper::housekeepingDone("Task \"{$task->headline}\"", 'done');

        return back()->with('tab', 'housekeeping')->with('success', "✅ Task \"{$task->headline}\" marked done.");
    }

    // ── Issue reports ──────────────────────────────────────────────
    public function storeReport(Request $request)
    {
        $data = $request->validateWithBag('issueReport', IssueReport::rules(), IssueReport::messages());

        $report = IssueReport::create($data + [
            'property_id'   => Property::where('type', 'villa')->value('id'),
            'reported_by'   => Auth::id(),
            'reporter_role' => 'staff',
            'status'        => 'pending',
        ]);

        StaffLog::record('issue_reported', 'issue_reports', $report->id,
            "Reported a {$report->category_label} issue" . ($report->description ? ": {$report->description}" : ''));
        FrontdeskBroadcast::send('issue_reported', "New {$report->category_label} issue reported by staff.",
            reportId: $report->id, actor: Auth::user()->full_name);
        NotificationHelper::issueReported($report);

        return back()->with('tab', 'housekeeping')->with('success', "{$report->category_label} issue logged. The admin has been notified.");
    }

    public function startReport(IssueReport $report)
    {
        if (! $report->markStarted()) {
            return back()->with('tab', 'housekeeping')->with('error', 'This issue was already being handled, fixed or closed.');
        }

        StaffLog::record('issue_started', 'issue_reports', $report->id, "Started fixing {$report->category_label} issue");
        FrontdeskBroadcast::send('issue_started', "{$report->category_label} issue is being fixed.",
            reportId: $report->id, actor: Auth::user()->full_name);

        return back()->with('tab', 'housekeeping')->with('success', "{$report->category_label} issue marked in progress.");
    }

    public function completeReport(IssueReport $report)
    {
        if (! $report->markCompleted()) {
            return back()->with('tab', 'housekeeping')->with('error', 'This issue was already fixed or closed.');
        }

        StaffLog::record('issue_fixed', 'issue_reports', $report->id, "Fixed {$report->category_label} issue");
        FrontdeskBroadcast::send('issue_fixed', "{$report->category_label} issue marked fixed.",
            reportId: $report->id, actor: Auth::user()->full_name);
        NotificationHelper::housekeepingDone("{$report->category_label} issue from {$report->source_label}", 'fixed');

        return back()->with('tab', 'housekeeping')->with('success', "✅ {$report->category_label} issue marked fixed.");
    }
}
