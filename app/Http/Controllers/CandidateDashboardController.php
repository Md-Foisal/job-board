<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationOutcomeStatus;
use App\Models\Application;
use App\Models\JobView;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CandidateDashboardController extends Controller
{
    /**
     * claude/14 (route ১১) is explicit: the completion % counts
     * $user->candidateProfile's own filled fields, computed on every
     * request rather than stored (claude/13's decision -- there is no
     * "completion" column to go stale the moment a field changes). This
     * mirrors the model's #[Fillable(...)] set exactly, so a future field
     * added there is a one-line addition here too.
     */
    private const PROFILE_FIELDS = [
        'headline', 'bio', 'cover_photo_path', 'portfolio_url', 'github_url', 'linkedin_url',
    ];

    public function index(Request $request): View
    {
        $candidateProfile = $request->user()->candidateProfile;

        $filledFieldCount = collect(self::PROFILE_FIELDS)
            ->filter(fn (string $field) => filled($candidateProfile->{$field}))
            ->count();

        // The scalar fields above are only the identity card -- the things
        // an employer actually cares about (education, work history,
        // skills, a CV) live in their own tables. Each of those counts as
        // one more "field" filled, same weight as headline/bio/links, so a
        // profile with zero work history and no CV can no longer read as
        // 100% complete just because the photo and bio are filled in.
        $sectionPresence = [
            $candidateProfile->educationRecords()->exists(),
            $candidateProfile->experienceRecords()->exists(),
            $candidateProfile->skills()->exists(),
            $candidateProfile->documents()->exists(),
        ];

        $filledFieldCount += collect($sectionPresence)->filter()->count();
        $totalFieldCount = count(self::PROFILE_FIELDS) + count($sectionPresence);

        $profileCompletionPercent = (int) round($filledFieldCount / $totalFieldCount * 100);

        $activeApplicationCount = Application::query()
            ->where('candidate_profile_id', $candidateProfile->id)
            ->where('outcome_status', ApplicationOutcomeStatus::Active)
            ->count();

        // One JobView row per (user, job posting) -- already deduped at the
        // write side (recordView() upserts on that pair), so this is just
        // the 6 most recently touched rows, not a dedupe-on-read job here.
        $recentlyViewedJobs = JobView::query()
            ->with('jobPosting.company')
            ->where('user_id', $request->user()->id)
            ->latest('viewed_at')
            ->take(6)
            ->get()
            ->pluck('jobPosting');

        return view('candidate.dashboard', [
            'profileCompletionPercent' => $profileCompletionPercent,
            'activeApplicationCount' => $activeApplicationCount,
            'recentlyViewedJobs' => $recentlyViewedJobs,
        ]);
    }
}
