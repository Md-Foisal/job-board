<?php

namespace Database\Seeders;

use App\Actions\ApproveJobPosting;
use App\Actions\BanCompany;
use App\Actions\DismissReports;
use App\Actions\RejectJobPosting;
use App\Actions\RequestCompanyDocuments;
use App\Actions\SuspendUser;
use App\Actions\VerifyCompany;
use App\Enums\EmploymentType;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Enums\WorkplaceType;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Report;
use App\Models\User;
use App\Support\ClosingDate;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\Catalogue;
use Database\Seeders\Demo\Postings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Something in every staff queue and every moderation state an employer
 * can see, made through the same actions staff use, so the log, the trust
 * tier and the report counts agree with each other the way they would in
 * real use. Needs the demo staff accounts, so it runs after them and does
 * nothing without them. Decisions about older things are dated back to
 * when they would have been made, so the log reads as weeks of work.
 */
class ModerationSeeder extends Seeder
{
    private Collection $reporters;

    public function run(): void
    {
        $moderator = User::query()->where('email', 'moderator@jobboard.test')->first();
        $superAdmin = User::query()->where('email', 'superadmin@jobboard.test')->first();

        if (! $moderator || ! $superAdmin) {
            return;
        }

        // The demo candidate stays out of it, so its account is never the
        // one that ends up suspended.
        $this->reporters = User::query()
            ->whereHas('candidateProfile')
            ->where('email', '!=', 'candidate@jobboard.test')
            ->get();

        $this->seedDemoCompany($moderator);
        $this->seedOtherCompanies($moderator, $superAdmin);
    }

    /**
     * The employer demo account sees one posting in each state.
     */
    private function seedDemoCompany(User $moderator): void
    {
        $company = Company::query()->where('slug', DemoAccountsSeeder::DEMO_COMPANY_SLUG)->firstOrFail();
        $rejectionReasons = array_keys(JobPostingResource::rejectionTemplates());

        $live = $this->submittedPosting($company, 'senior-laravel-developer');
        app(ApproveJobPosting::class)($live, $moderator);

        $this->submittedPosting($company, 'frontend-engineer');

        $rejected = $this->submittedScam($company);
        app(RejectJobPosting::class)($rejected, $moderator, $rejectionReasons[1]);

        $reported = $this->submittedPosting($company, 'backend-engineer-go');
        app(ApproveJobPosting::class)($reported, $moderator);
        $this->report($reported, Report::HIDE_AFTER_REPORTERS, 'Scam or fraud');

        app(RequestCompanyDocuments::class)($company, $moderator, 'A copy of your trade licence, and a link to the company on a site we can check it against.');
    }

    private function seedOtherCompanies(User $moderator, User $superAdmin): void
    {
        $companies = Company::query()
            ->where('slug', '!=', DemoAccountsSeeder::DEMO_COMPANY_SLUG)
            ->with('jobPostings')
            ->get()
            ->shuffle();

        // A company with three postings approved by hand: its next one
        // skips the queue.
        $trusted = $companies->shift();
        foreach ($trusted->jobPostings->take(Company::TRUSTED_AFTER_APPROVALS) as $posting) {
            $this->resubmit($posting);
            $this->at(CarbonImmutable::instance($posting->published_at)->addMinutes(random_int(20, 240)),
                fn () => app(ApproveJobPosting::class)($posting, $moderator));
        }
        $this->at(now()->subDays(random_int(10, 40)), fn () => app(VerifyCompany::class)($trusted, $moderator));

        $verified = $companies->shift();
        $this->at(now()->subDays(random_int(3, 9)), fn () => app(VerifyCompany::class)($verified, $moderator));

        // The posting queue, one of them past the review target so the
        // dashboard shows what overdue looks like.
        $companies->take(4)->each(function (Company $company, int $index) {
            $posting = $company->jobPostings->first();
            $this->resubmit($posting);
            $posting->submitted_at = now()->subHours($index === 0 ? 30 : $index * 3);
            $posting->saveQuietly();
        });

        // Open reports below the hiding threshold, on a posting and on a company.
        $this->report($verified->jobPostings->first(), 1, 'Spam or fake');
        $this->report($companies->get(1), 2, 'Inappropriate content');

        // Reports staff already looked at and found nothing in.
        $dismissed = $companies->get(2)->jobPostings->last();
        $reportedAt = min(CarbonImmutable::instance($dismissed->published_at)->addDay(), now()->subHours(8));
        $this->at($reportedAt, fn () => $this->report($dismissed, 1, 'Other'));
        $this->at($reportedAt->addHours(5), fn () => app(DismissReports::class)($dismissed->reports()->first(), $moderator, 'Checked the company site; the role is real.'));

        // Recent, so none of its postings went up after the ban.
        $banned = $companies->last();
        $this->at(now()->subHours(16), fn () => $this->report($banned, 2, 'Scam or fraud'));
        $this->at(now()->subHours(7), fn () => app(BanCompany::class)($banned, $superAdmin, 'Asked applicants to pay a registration fee.'));

        $suspended = $this->reporters->last();
        $this->at(now()->subDay()->subHours(5), fn () => app(SuspendUser::class)($suspended, $superAdmin, 'Sent the same abusive message to several employers.'));
    }

    private function submittedPosting(Company $company, string $roleKey): JobPosting
    {
        $posting = $this->submitted($company, Postings::attributes($roleKey, Catalogue::demo()['company']));

        Postings::attachTaxonomy($posting, $roleKey);

        return $posting;
    }

    /**
     * The kind of posting the queue exists to catch: no real role, big
     * promises, and a fee before anything starts.
     */
    private function submittedScam(Company $company): JobPosting
    {
        return $this->submitted($company, [
            'title' => 'Earn $5000 a week from home',
            'description' => '<p>No experience needed! Work from home in your spare time and earn up to $5000 a week.</p>'
                .'<p>To get started, pay a one-time registration fee of $49 for your training pack. Places are limited, so apply today.</p>',
            'employment_type' => EmploymentType::PartTime,
            'workplace_type' => WorkplaceType::Remote,
            'location_city' => null,
            'location_country' => 'United Kingdom',
            'min_experience_years' => 0,
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => null,
            'salary_period' => null,
            'salary_negotiable' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function submitted(Company $company, array $attributes): JobPosting
    {
        $submitted = now()->subHours(random_int(1, 6));

        return JobPosting::factory()->for($company)->pendingModeration()->create([
            ...$attributes,
            'posted_by_id' => $company->memberships()->value('user_id'),
            'published_at' => $submitted,
            'submitted_at' => $submitted,
            'expires_at' => ClosingDate::monthAfter($company),
        ]);
    }

    /**
     * Put a seeded posting back in the queue, as if it had just been
     * submitted. Submitting is not a staff decision, so it leaves no event.
     */
    private function resubmit(JobPosting $posting): void
    {
        $posting->moderation_status = ModerationStatus::Pending;
        $posting->saveQuietly();
    }

    /**
     * Run $action as if it were $moment, so what it records is dated then.
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

    /**
     * Reports from different people, which is what counts towards hiding.
     */
    private function report(Model $subject, int $people, string $reason): void
    {
        $this->reporters->take($people)->each(fn (User $reporter) => $subject->reports()->create([
            'reporter_id' => $reporter->id,
            'reason' => $reason,
            'review_status' => ReportStatus::Pending,
        ]));
    }
}
