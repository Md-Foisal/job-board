<?php

namespace Database\Seeders;

use App\Actions\AnonymizeUser;
use App\Actions\ChangeApplicationStage;
use App\Enums\AlertFrequency;
use App\Enums\ApplicationStage;
use App\Enums\DocumentType;
use App\Enums\StaffRole;
use App\Models\Application;
use App\Models\CandidatePreference;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Document;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\JobView;
use App\Models\Membership;
use App\Models\User;
use Database\Seeders\Concerns\SeedsCandidateSkills;
use Illuminate\Database\Seeder;

/**
 * One account per role with a password everyone on the team knows, so a
 * fresh database can be looked at from every side without digging through
 * random factory emails. Known passwords are exactly what must never reach
 * a real server, so outside local and testing this does nothing.
 */
class DemoAccountsSeeder extends Seeder
{
    use SeedsCandidateSkills;

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

    public const DEMO_COMPANY_SLUG = 'demo-hiring-co';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipped demo accounts: they have known passwords and belong on a developer machine only.');

            return;
        }

        $this->staff('Super Admin', 'superadmin@jobboard.test', StaffRole::SuperAdmin);
        $this->staff('Moderator', 'moderator@jobboard.test', StaffRole::Moderator);

        // The website shares the owner's email domain, so the verification
        // queue shows a matching domain for this company.
        $employer = User::factory()->create(['name' => 'Demo Employer', 'email' => 'employer@jobboard.test']);
        $company = Company::factory()->create([
            'name' => 'Demo Hiring Co',
            'slug' => self::DEMO_COMPANY_SLUG,
            'website_url' => 'https://jobboard.test',
        ]);
        Membership::factory()->owner()->for($company)->for($employer, 'user')->create();

        $candidate = User::factory()->create(['name' => 'Demo Candidate', 'email' => 'candidate@jobboard.test']);
        $profile = CandidateProfile::factory()
            ->for($candidate)
            ->has(CandidatePreference::factory(), 'preference')
            ->create();
        $this->attachSkills($profile, 5, 8);
        $this->seedCandidateActivity($candidate, $profile);
        $this->seedJobAlerts($candidate, $profile);
        $this->seedCompanyLifecycle($company, $employer);
        $deleted = $this->seedDeletedAccount();

        $this->command?->table(['Role', 'Email', 'Password'], [
            ['Super admin', 'superadmin@jobboard.test', self::PASSWORD],
            ['Moderator', 'moderator@jobboard.test', self::PASSWORD],
            ['Employer (owner of Demo Hiring Co)', 'employer@jobboard.test', self::PASSWORD],
            ['Candidate', 'candidate@jobboard.test', self::PASSWORD],
            ['Deleted candidate (sign in to restore)', $deleted->email, self::PASSWORD],
        ]);
        $this->command?->line('Staff two-factor secret: '.self::TWO_FACTOR_SECRET.' (or a recovery code, demo-recovery-code-1 to -8)');
    }

    /**
     * A candidate with no history shows empty lists on every page they
     * own, which makes the candidate side look unfinished in every fresh
     * database. The demo one gets applications at different stages, with
     * the timeline entries a real review leaves, plus saved and viewed jobs.
     */
    private function seedCandidateActivity(User $candidate, CandidateProfile $profile): void
    {
        $postings = JobPosting::query()->active()->with('company')->inRandomOrder()->limit(8)->get();

        if ($postings->count() < 8) {
            return;
        }

        $resume = Document::factory()->create([
            'candidate_profile_id' => $profile->id,
            'document_type' => DocumentType::Cv,
        ]);

        foreach ([ApplicationStage::New, ApplicationStage::Shortlisted, ApplicationStage::Interview] as $index => $stage) {
            $posting = $postings[$index];
            $application = Application::factory()->create([
                'job_posting_id' => $posting->id,
                'candidate_profile_id' => $profile->id,
                'resume_document_id' => $resume->id,
            ]);

            $reviewer = $posting->company->decisionMakers()->first();

            if ($stage !== ApplicationStage::New && $reviewer) {
                app(ChangeApplicationStage::class)($application, $reviewer, $stage);
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
        $skillId = $profile->skills()->value('skills.id');

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
        $open = JobPosting::factory()->for($company)->create(['title' => 'Customer Support Specialist', 'posted_by_id' => $employer->id]);
        JobPosting::factory()->for($company)->expired()->create(['title' => 'Junior QA Tester', 'posted_by_id' => $employer->id]);
        JobPosting::factory()->for($company)->closed()->create(['title' => 'Content Writer', 'posted_by_id' => $employer->id]);
        JobPosting::factory()->for($company)->draft()->create(['title' => 'Office Manager', 'posted_by_id' => $employer->id]);

        $leaver = User::factory()->create(['name' => 'Former Applicant', 'email' => 'former-applicant@jobboard.test']);
        $profile = CandidateProfile::factory()->for($leaver)->create();
        Application::factory()->create(['job_posting_id' => $open->id, 'candidate_profile_id' => $profile->id]);

        app(AnonymizeUser::class)($leaver);
    }

    /**
     * Deleted five days ago and still inside the grace period: signing in
     * with it leads to the restore page.
     */
    private function seedDeletedAccount(): User
    {
        $user = User::factory()->create(['name' => 'Deleted Candidate', 'email' => 'deleted@jobboard.test']);
        CandidateProfile::factory()->for($user)->create();
        $user->forceFill(['deleted_at' => now()->subDays(5)])->save();

        return $user;
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
