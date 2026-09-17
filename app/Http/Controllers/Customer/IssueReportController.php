<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\IssueReport;
use App\Services\FrontdeskBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pag-uulat ng problema mula sa guest, habang naka-check-in (v7.11).
 *
 * Direktang ipinapadala sa staff frontdesk AT sa admin nang sabay — hindi
 * laging nakabukas ang admin portal, at ang guest ay nasa villa ngayon.
 */
class IssueReportController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);

        if (! $booking->canReportIssues()) {
            return back()->with('error', 'You can report an issue only while you are checked in.');
        }

        $data = $request->validateWithBag('issueReport', IssueReport::rules(), IssueReport::messages());

        $report = IssueReport::create($data + [
            'property_id'   => $booking->property_id,
            'booking_id'    => $booking->id,
            'reported_by'   => Auth::id(),
            'reporter_role' => 'customer',
            'status'        => 'pending',
        ]);

        FrontdeskBroadcast::send(
            'issue_reported',
            "Guest {$booking->user->full_name} reported a {$report->category_label} problem"
                . ($report->description ? ": \"{$report->description}\"" : '.'),
            reportId: $report->id,
            actor: $booking->user->full_name,
        );
        NotificationHelper::issueReported($report);

        return back()->with('success', 'Thanks — your report was sent. Our staff have been alerted.');
    }
}
