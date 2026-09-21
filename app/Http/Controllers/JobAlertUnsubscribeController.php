<?php

namespace App\Http\Controllers;

use App\Models\JobAlert;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Switching a job alert off from the email, without signing in
 * (claude/14 route 45). The signed URL is the proof of who asked.
 *
 * Opening the link only asks; the POST does it. Mail scanners and link
 * previews open every link in a message, and an alert that switched off
 * when merely looked at would switch off for no one's reason. The POST
 * is also what a mail client's own one-click unsubscribe button sends.
 */
class JobAlertUnsubscribeController extends Controller
{
    public function show(Request $request, int $jobAlert): View
    {
        return view('job-alerts.unsubscribe', [
            'jobAlert' => JobAlert::find($jobAlert),
            'done' => false,
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(int $jobAlert): View
    {
        // Pausing, not deleting: the candidate can turn it back on from
        // their alerts page. Already deleted is the same answer -- no more
        // emails -- so it is not an error.
        JobAlert::whereKey($jobAlert)->update(['is_active' => false]);

        return view('job-alerts.unsubscribe', [
            'jobAlert' => JobAlert::find($jobAlert),
            'done' => true,
            'action' => null,
        ]);
    }
}
