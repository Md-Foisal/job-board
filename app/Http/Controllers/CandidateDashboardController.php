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
     * added there is a one-line addition here too. Labeled (not a bare
     * list) because the dashboard names the specific missing items, not
     * just a percentage -- a number alone gives no next action.
     */
    private const PROFILE_FIELD_LABELS = [
        'headline' => 'Headline',
        'bio' => 'Bio',
        'cover_photo_path' => 'Cover photo',
        'portfolio_url' => 'Portfolio link',
        'github_url' => 'GitHub link',
        'linkedin_url' => 'LinkedIn link',
    ];

    public function index(Request $request): View
    {
        $candidateProfile = $request->user()->candidateProfile;

        $fieldChecks = collect(self::PROFILE_FIELD_LABELS)
            ->mapWithKeys(fn (string $label, string $field) => [
                $label => filled($candidateProfile->{$field}),
            ]);

        // The scalar fields above are only the identity card -- the things
        // an employer actually cares about (education, work history,
        // skills, a CV) live in their own tables. Each of those counts as
        // one more "field" filled, same weight as headline/bio/links, so a
        // profile with zero work history and no CV can no longer read as
        // 100% complete just because the photo and bio are filled in.
        $sectionChecks = collect([
            'Education' => $candidateProfile->educationRecords()->exists(),
            'Experience' => $candidateProfile->experienceRecords()->exists(),
            'Skills' => $candidateProfile->skills()->exists(),
            'A document (CV)' => $candidateProfile->documents()->exists(),
        ]);

        $allChecks = $fieldChecks->merge($sectionChecks);

        $profileCompletionPercent = (int) round(
            $allChecks->filter()->count() / $allChecks->count() * 100
        );

        // Named, not just counted: a bare percentage gives no next action --
        // real profile-completion UX (LinkedIn and friends) always names the
        // specific missing piece, not just a number.
        $missingProfileItems = $allChecks->reject(fn (bool $filled) => $filled)->keys()->values();

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
            'missingProfileItems' => $missingProfileItems,
            'activeApplicationCount' => $activeApplicationCount,
            'recentlyViewedJobs' => $recentlyViewedJobs,
        ]);
    }
}
