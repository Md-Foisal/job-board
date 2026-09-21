<?php

namespace Database\Seeders;

use App\Actions\ApproveJobPosting;
use App\Actions\BanCompany;
use App\Actions\DismissReports;
use App\Actions\RejectJobPosting;
use App\Actions\RequestCompanyDocuments;
use App\Actions\SuspendUser;
use App\Actions\VerifyCompany;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Enums\SkillImportance;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Report;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Something in every staff queue and every moderation state an employer
 * can see, made through the same actions staff use, so the log, the trust
 * tier and the report counts agree with each other the way they would in
 * real use. Needs the demo staff accounts, so it runs after them and does
 * nothing without them.
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

        $live = $this->submittedPosting($company, 'Senior Laravel Developer');
        app(ApproveJobPosting::class)($live, $moderator);

        $this->submittedPosting($company, 'Frontend Engineer');

        $rejected = $this->submittedPosting($company, 'Earn $5000 a week from home');
        app(RejectJobPosting::class)($rejected, $moderator, $rejectionReasons[1]);

        $reported = $this->submittedPosting($company, 'Backend Engineer (Go)');
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
            app(ApproveJobPosting::class)($posting, $moderator);
        }
        app(VerifyCompany::class)($trusted, $moderator);

        $verified = $companies->shift();
        app(VerifyCompany::class)($verified, $moderator);

        // The posting queue, one of them past the review target so the
        // dashboard shows what overdue looks like.
        $companies->take(4)->each(function (Company $company, int $index) {
            $posting = $company->jobPostings->first();
            $this->resubmit($posting);
            $posting->published_at = now()->subHours($index === 0 ? 30 : $index * 3);
            $posting->saveQuietly();
        });

        // Open reports below the hiding threshold, on a posting and on a company.
        $this->report($verified->jobPostings->first(), 1, 'Spam or fake listing');
        $this->report($companies->get(1), 2, 'Inappropriate content');

        // Reports staff already looked at and found nothing in.
        $dismissed = $companies->get(2)->jobPostings->last();
        $this->report($dismissed, 1, 'Other');
        app(DismissReports::class)($dismissed->reports()->first(), $moderator, 'Checked the company site; the role is real.');

        $banned = $companies->last();
        $this->report($banned, 2, 'Scam or fraud');
        app(BanCompany::class)($banned, $superAdmin, 'Asked applicants to pay a registration fee.');

        $suspended = $this->reporters->last();
        app(SuspendUser::class)($suspended, $superAdmin, 'Sent the same abusive message to several employers.');
    }

    private function submittedPosting(Company $company, string $title): JobPosting
    {
        $posting = JobPosting::factory()->for($company)->pendingModeration()->create([
            'title' => $title,
            'posted_by_id' => $company->memberships()->value('user_id'),
            'published_at' => now()->subHours(random_int(1, 6)),
        ]);

        $posting->categories()->attach(Category::query()->inRandomOrder()->value('id'));
        $posting->skills()->attach(
            Skill::query()->inRandomOrder()->limit(4)->pluck('id')
                ->mapWithKeys(fn (int $id) => [$id => ['importance' => SkillImportance::Required->value]])
        );

        return $posting;
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
