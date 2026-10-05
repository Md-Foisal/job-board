<?php

namespace Database\Seeders;

use App\Actions\AnonymizeUser;
use App\Actions\ChangeApplicationStage;
use App\Enums\AlertFrequency;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\AvailabilityStatus;
use App\Enums\JobAsDescribed;
use App\Enums\ModerationStatus;
use App\Enums\ReviewPart;
use App\Enums\StaffRole;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use App\Models\JobView;
use App\Models\Membership;
use App\Models\User;
use App\Support\ClosingDate;
use App\Support\ReviewScreening;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\Catalogue;
use Database\Seeders\Demo\People;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * One account per role with a password everyone on the team knows, so a
 * fresh database can be looked at from every side without digging through
 * random factory emails. Known passwords are exactly what must never reach
 * a real server, so outside local and testing this does nothing.
 */
class DemoAccountsSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /**
     * Staff cannot reach the panel without two-factor, so they get a fixed
     * secret: add it to any authenticator app once and it keeps working
     * after every fresh seed.
     */
    public const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXP';

    public const RECOVERY_CODES = [
        'demo-recovery-code-1',
        'demo-recovery-code-2',
        'demo-recovery-code-3',
        'demo-recovery-code-4',
        'demo-recovery-code-5',
        'demo-recovery-code-6',
        'demo-recovery-code-7',
        'demo-recovery-code-8',
    ];

    public const DEMO_COMPANY_SLUG = 'fernhill-software';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipped demo accounts: they have known passwords and belong on a developer machine only.');

            return;
        }

        // Seeded CVs are real files; the ones from the last seed belong to
        // rows that no longer exist.
        Storage::disk('local')->deleteDirectory(People::CV_FOLDER);

        $demo = Catalogue::demo();

        $this->staff($demo['staff']['superadmin'], 'superadmin@jobboard.test', StaffRole::SuperAdmin);
        $this->staff($demo['staff']['moderator'], 'moderator@jobboard.test', StaffRole::Moderator);

        // The website shares the owner's email domain, so the verification
        // queue shows a matching domain for this company.
        $employer = User::factory()->create(['name' => $demo['employer']['name'], 'email' => 'employer@jobboard.test', 'timezone' => $demo['company']['timezone']]);
        $company = CompanySeeder::create(self::DEMO_COMPANY_SLUG, $demo['company'], ['website_url' => 'https://jobboard.test']);
        Membership::factory()->owner()->for($company)->for($employer, 'user')->create(['job_title' => $demo['employer']['job_title']]);

        $candidate = User::factory()->create(['name' => $demo['candidate']['name'], 'email' => 'candidate@jobboard.test', 'timezone' => 'Europe/London']);
        $profile = People::writtenCandidate($candidate, [
            ...$demo['candidate'],
            // Ofcom keeps 07700 900000-900999 for fiction, so the demo
            // number can never ring a real phone.
            'contact' => ['phone' => '+44 7700 900123'],
        ]);
        $this->seedCandidateActivity($candidate, $profile);
        $this->seedJobAlerts($candidate, $profile);
        $this->seedCompanyLifecycle($company, $employer);
        $deleted = $this->seedDeletedAccount();

        $this->command?->table(['Role', 'Email', 'Password'], [
            ['Super admin', 'superadmin@jobboard.test', self::PASSWORD],
            ['Moderator', 'moderator@jobboard.test', self::PASSWORD],
            ['Employer (owner of '.$company->name.')', 'employer@jobboard.test', self::PASSWORD],
            ['Candidate', 'candidate@jobboard.test', self::PASSWORD],
            ['Deleted candidate (sign in to restore)', $deleted->email, self::PASSWORD],
        ]);
        $this->command?->line('Staff two-factor secret: '.self::TWO_FACTOR_SECRET.' (or a recovery code, demo-recovery-code-1 to -8)');
    }

    /**
     * A candidate with no history shows empty lists on every page they
     * own, which makes the candidate side look unfinished in every fresh
     * database. The demo one gets applications at different stages, with
     * the timeline entries a real review leaves, plus saved and viewed
     * jobs, all in their own line of work. The applications went to the
     * longest-open of those jobs, so a review had time to happen.
     */
    private function seedCandidateActivity(User $candidate, CandidateProfile $profile): void
    {
        [$fitting, $others] = JobPosting::query()->active()->with('company')->get()->shuffle()
            ->partition(fn (JobPosting $posting) => in_array(People::familyOf($posting), ['fullstack', 'backend', 'frontend'], true));

        if ($fitting->count() < 3 || $fitting->count() + $others->count() < 8) {
            return;
        }

        $applied = $fitting->sortBy('published_at')->take(3)->values();
        $postings = $applied->concat($fitting->diff($applied))->concat($others)->values();
        $appliedAt = $applied->map(fn (JobPosting $posting) => min(
            CarbonImmutable::instance($posting->published_at)->addHours(random_int(4, 48)),
            now()->subHours(2),
        ));

        $resume = People::renderCv($profile, $appliedAt->min()->subDays(3));

        foreach ([[], [ApplicationStage::Shortlisted], [ApplicationStage::Shortlisted, ApplicationStage::Interview]] as $index => $steps) {
            $posting = $applied[$index];
            $application = Application::factory()->create([
                'job_posting_id' => $posting->id,
                'candidate_profile_id' => $profile->id,
                'resume_document_id' => $resume->id,
                'cover_letter' => People::coverLetter($profile, $posting),
                'created_at' => $appliedAt[$index],
                'updated_at' => $appliedAt[$index],
            ]);

            $reviewer = $posting->company->decisionMakers()->first();

            // Each step a few days after the last, in the reviewer's
            // working day and never later than now, with the clock set so
            // the timeline and the email agree.
            foreach ($steps as $step => $stage) {
                if ($reviewer) {
                    $movedAt = $appliedAt[$index]->addDays(2 + $step * 4)
                        ->setTimezone($posting->company->timezone)
                        ->setTime(random_int(9, 17), random_int(0, 59))
                        ->utc();

                    $this->at(min($movedAt, now()->subHour()),
                        fn () => app(ChangeApplicationStage::class)($application->refresh(), $reviewer, $stage));
                }
            }
        }

        $candidate->savedJobs()->attach($postings->slice(3, 2)->pluck('id'));

        $postings->slice(5, 3)->values()->each(fn (JobPosting $posting, int $hoursAgo) => JobView::create([
            'user_id' => $candidate->id,
            'job_posting_id' => $posting->id,
            'viewed_at' => now()->subHours($hoursAgo + 1),
        ]));
    }

    /**
     * One alert that runs and one the candidate has paused, both searches
     * that match seeded postings, so the alerts page and the unsubscribe
     * page have something real to show.
     */
    private function seedJobAlerts(User $candidate, CandidateProfile $profile): void
    {
        $skillId = $profile->skills()->wherePivot('proficiency', 'advanced')->value('skills.id');

        JobAlert::factory()->for($candidate)->create([
            'name' => 'Jobs that use my top skill',
            'criteria' => $skillId ? ['skill' => $skillId] : [],
            'frequency' => AlertFrequency::Daily,
        ]);

        JobAlert::factory()->for($candidate)->paused()->create([
            'name' => 'Remote roles, weekly',
            'criteria' => ['workplaceType' => 'remote'],
            'frequency' => AlertFrequency::Weekly,
        ]);
    }

    /**
     * The demo company's listing shows every state a posting passes
     * through, and one applicant who has since erased their account, so
     * the employer sees what that leaves behind.
     */
    private function seedCompanyLifecycle(Company $company, User $employer): void
    {
        $details = Catalogue::demo()['company'];
        $post = fn (string $role, int $daysAgo, array $attributes = []) => JobPostingSeeder::createPosting(
            $company, $role, $details, [], CarbonImmutable::now()->subDays($daysAgo), ['posted_by_id' => $employer->id, ...$attributes], negotiable: false
        );

        $open = $post('customer-support-specialist', 40, [
            'expires_at' => ClosingDate::endOf(now($company->timezone)->addDays(20)->toDateString(), $company),
        ]);
        $post('qa-tester-junior', 33, [
            'availability_status' => AvailabilityStatus::Expired,
            'expires_at' => ClosingDate::endOf(now($company->timezone)->subDays(3)->toDateString(), $company),
        ]);
        $post('content-writer', 20, ['availability_status' => AvailabilityStatus::Closed]);
        $post('office-manager', 2, ['availability_status' => AvailabilityStatus::Draft, 'published_at' => null]);

        $leaver = User::factory()->create(['name' => 'Former Applicant', 'email' => 'former-applicant@jobboard.test']);
        $profile = CandidateProfile::factory()->for($leaver)->create();
        Application::factory()->create(['job_posting_id' => $open->id, 'candidate_profile_id' => $profile->id, 'created_at' => now()->subDays(9)]);

        app(AnonymizeUser::class)($leaver);

        $this->seedAnalytics($open, $employer);
    }

    /**
     * Six weeks of a live posting's life, so the analytics page has
     * something to draw: daily views, applicants over the month who
     * match its skills to different degrees, and a review that moved
     * some forward, turned two down unseen and hired one. Decisions are
     * dated in the past, outside the window in which they could still be
     * undone, as they would be in a real history.
     */
    private function seedAnalytics(JobPosting $posting, User $reviewer): void
    {
        $skills = $posting->skills()->pluck('skills.id');

        // Days are the company's own, as RecordJobView counts them.
        $today = today($posting->company->timezone);

        foreach (range(39, 0) as $daysAgo) {
            $day = $today->subDays($daysAgo);
            $weekend = $day->isWeekend();

            JobPostingDailyStat::create([
                'job_posting_id' => $posting->id,
                'date' => $day->toDateString(),
                'views' => $weekend ? random_int(3, 9) : random_int(10, 28),
            ]);
        }

        $event = fn (Application $application, array $change, $at) => ApplicationEvent::forceCreate([
            'application_id' => $application->id,
            'changed_by_id' => $reviewer->id,
            ...$change,
            'created_at' => $at,
        ]);

        // What happened to each applicant, by the order they applied in;
        // the rest are still waiting at New.
        $plan = [
            1 => 'hired',
            2 => 'rejected unseen', 5 => 'rejected unseen',
            3 => 'interview', 6 => 'interview', 9 => 'interview',
            4 => 'shortlisted', 7 => 'shortlisted', 10 => 'shortlisted',
        ];

        $applications = [];

        // Days ago each applied, oldest first: uneven, as applications
        // arrive, and still coming in this week.
        $daysAgo = [1 => 37, 35, 35, 31, 28, 27, 24, 20, 17, 17, 12, 9, 6, 3];

        foreach (range(1, 14) as $index) {
            $appliedAt = now()->subDays($daysAgo[$index])->setTime(random_int(8, 20), random_int(0, 59));

            // Mostly people near the office, and with the posting's skills
            // to different degrees, so every match bucket has someone in it.
            $profile = People::candidate('support', random_int(1, 10) <= 8 ? 'uk' : null, withSkills: false);
            $profile->skills()->syncWithoutDetaching(
                $skills->random(random_int(0, $skills->count()))
                    ->mapWithKeys(fn (int $id) => [$id => ['proficiency' => 'intermediate']])
            );

            $application = Application::factory()->create([
                'job_posting_id' => $posting->id,
                'candidate_profile_id' => $profile->id,
                'resume_document_id' => People::renderCv($profile, $appliedAt->subDay())->id,
                'cover_letter' => People::coverLetter($profile, $posting),
                'created_at' => $appliedAt,
                'updated_at' => $appliedAt,
            ]);
            $applications[$index] = $application;

            switch ($plan[$index] ?? 'new') {
                case 'hired':
                    $event($application, ['from_stage' => 'new', 'to_stage' => 'shortlisted'], $appliedAt->addHours(20));
                    $event($application, ['from_stage' => 'shortlisted', 'to_stage' => 'interview'], $appliedAt->addDays(4));
                    $event($application, ['from_stage' => 'interview', 'to_stage' => 'offer'], $appliedAt->addDays(12));
                    $event($application, ['from_outcome_status' => 'active', 'to_outcome_status' => 'hired'], $appliedAt->addDays(16));
                    $application->forceFill([
                        'stage' => ApplicationStage::Offer,
                        'outcome_status' => ApplicationOutcomeStatus::Hired,
                        'decided_at' => $appliedAt->addDays(16),
                    ])->save();
                    break;
                case 'rejected unseen':
                    $event($application, ['from_outcome_status' => 'active', 'to_outcome_status' => 'rejected'], $appliedAt->addDays(2));
                    $application->forceFill([
                        'outcome_status' => ApplicationOutcomeStatus::Rejected,
                        'decided_at' => $appliedAt->addDays(2),
                    ])->save();
                    break;
                case 'interview':
                    $event($application, ['from_stage' => 'new', 'to_stage' => 'shortlisted'], $appliedAt->addHours(30));
                    $event($application, ['from_stage' => 'shortlisted', 'to_stage' => 'interview'], $appliedAt->addDays(5));
                    $application->forceFill(['stage' => ApplicationStage::Interview])->save();
                    break;
                case 'shortlisted':
                    $event($application, ['from_stage' => 'new', 'to_stage' => 'shortlisted'], $appliedAt->addHours(52));
                    $application->forceFill(['stage' => ApplicationStage::Shortlisted])->save();
                    break;
            }
        }

        // Others have saved it without applying yet.
        User::query()
            ->whereHas('candidateProfile', fn ($query) => $query->whereNotIn('id', collect($applications)->pluck('candidate_profile_id')))
            ->where('email', '!=', 'candidate@jobboard.test')
            ->whereNull('anonymized_at')
            ->inRandomOrder()
            ->take(6)
            ->get()
            ->each(fn (User $user) => $user->savedJobs()->attach($posting->id));

        $this->seedReviews($posting, $applications, $reviewer);
    }

    /**
     * Enough published reviews for the company page to show its averages,
     * written by applicants the history above makes eligible (the hire,
     * one turned down, one interviewed), and one more from another
     * interviewee waiting in the staff queue, already read by the AI. The
     * company has answered one of them, so its reviews page shows both
     * states.
     *
     * @param  array<int, Application>  $applications
     */
    private function seedReviews(JobPosting $posting, array $applications, User $responder): void
    {
        $reviews = [
            1 => [5, 5, JobAsDescribed::Yes, 'Clear steps from the first call to the offer', "Every stage was explained before it happened, and I always knew who I was talking to next.\n\nThe offer matched the pay in the posting.", 15],
            2 => [3, 4, JobAsDescribed::Yes, 'A quick no, but at least an answer', 'I was turned down two days after applying. No feedback on why, but a short and polite email beats the silence I get from most places.', 28],
            3 => [4, 3, JobAsDescribed::NotSure, 'Good interview, slow updates afterwards', 'The interview was friendly and focused on real support tickets rather than trick questions. After that I waited over a week without hearing anything, and had to ask for an update.', 20],
            6 => [2, 2, JobAsDescribed::No, 'The role was more sales than support', 'The posting talked about helping customers, but the interview was mostly about hitting upsell targets. I would have liked that to be in the ad.', null],
        ];

        foreach ($reviews as $index => [$overall, $communication, $asDescribed, $title, $body, $publishedDaysAgo]) {
            $application = $applications[$index];

            $review = CompanyReview::create([
                'company_id' => $posting->company_id,
                'application_id' => $application->id,
                'candidate_profile_id' => $application->candidate_profile_id,
                'overall_rating' => $overall,
                'communication_rating' => $communication,
                'job_as_described' => $asDescribed,
                'title' => $title,
                'body' => $body,
                'moderation_status' => $publishedDaysAgo === null ? ModerationStatus::Pending : ModerationStatus::Approved,
                'published_at' => $publishedDaysAgo === null ? null : now()->subDays($publishedDaysAgo),
                // What a run that finds nothing leaves, so the staff view
                // shows the hint without an API key.
                'screening' => $publishedDaysAgo === null
                    ? (new ReviewScreening(ReviewPart::Review, [], CarbonImmutable::now()))->toArray()
                    : null,
            ]);

            if ($index === 2) {
                $review->forceFill([
                    'response_body' => 'Thank you for writing this. We try to answer every applicant within a few days, and we are now adding a short reason to our rejection emails.',
                    'response_status' => ModerationStatus::Approved,
                    'responded_by_id' => $responder->id,
                    'responded_at' => now()->subDays($publishedDaysAgo - 3),
                ])->save();
            }
        }
    }

    /**
     * Deleted five days ago and still inside the grace period: signing in
     * with it leads to the restore page.
     */
    private function seedDeletedAccount(): User
    {
        $user = People::candidate('data', 'de', ['name' => Catalogue::demo()['deleted'], 'email' => 'deleted@jobboard.test'])->user;
        $user->forceFill(['deleted_at' => now()->subDays(5)])->save();

        return $user;
    }

    /**
     * Run an action as if it happened at the given moment.
     */
    private function at(CarbonImmutable $moment, callable $action): mixed
    {
        Carbon::setTestNow($moment);

        try {
            return $action();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function staff(string $name, string $email, StaffRole $role): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'staff_role' => $role,
            'two_factor_secret' => encrypt(self::TWO_FACTOR_SECRET),
            'two_factor_recovery_codes' => encrypt(json_encode(self::RECOVERY_CODES)),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
