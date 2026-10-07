<?php

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Livewire\CommandPalette;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\JobView;
use App\Models\User;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

/*
 * The frame every workspace page shares: one header with one <h1>, a
 * link back to the parent page instead of a breadcrumb trail, two page
 * widths, and the command palette in both workspaces.
 */

test('breadcrumbs on public pages start at Jobs, without a Home crumb', function () {
    $category = Category::create(['name' => 'Engineering', 'slug' => 'engineering']);
    $job = JobPosting::factory()->create();
    $category->jobPostings()->attach($job);

    foreach ([route('jobs.show', $job), route('categories.show', $category)] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->toContain('aria-label="Breadcrumb"')
            ->not->toContain('aria-label="Home"');

        $trail = str($html)->after('aria-label="Breadcrumb"')->before('</nav>');
        expect((string) $trail)->toContain('href="'.route('jobs.index').'"');
    }
});

test('company and static pages carry no breadcrumb', function () {
    $company = Company::factory()->create();

    $this->get(route('companies.show', $company))->assertOk()->assertDontSee('aria-label="Breadcrumb"', false);
    $this->get(route('about'))->assertOk()->assertDontSee('aria-label="Breadcrumb"', false);
});

test('pages below a list link back to it by its title', function () {
    $company = Company::factory()->create();
    $manager = employerUser($company, MembershipRole::Manager);
    $job = JobPosting::factory()->for($company)->create(['title' => 'Support Specialist']);
    $application = Application::factory()->for($job)->create();
    $candidate = $application->candidateProfile->user;

    $this->actingAs($candidate)
        ->get(route('candidate.applications.show', $application))
        ->assertOk()
        ->assertSee('Back to', false)
        ->assertSee('href="'.route('candidate.applications.index').'"', false)
        ->assertDontSee('aria-label="Breadcrumb"', false);

    $this->actingAs($manager)
        ->get(route('employer.jobs.applications', ['company' => $company, 'jobPosting' => $job]))
        ->assertOk()
        ->assertSeeInOrder(['Back to', 'Job postings']);

    $this->actingAs($manager)
        ->get(route('employer.applications.show', ['company' => $company, 'application' => $application]))
        ->assertOk()
        ->assertSeeInOrder(['Back to', 'Support Specialist'])
        ->assertSee('href="'.route('employer.jobs.applications', ['company' => $company, 'jobPosting' => $job]).'"', false);
});

test('every workspace page has exactly one top-level heading', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    $job = JobPosting::factory()->for($company)->create();
    $application = Application::factory()->for($job)->create();
    $candidate = $application->candidateProfile->user;

    $candidatePages = [
        route('candidate.dashboard'), route('candidate.profile.edit'), route('candidate.preferences.edit'),
        route('candidate.education.index'), route('candidate.experience.index'), route('candidate.documents.index'),
        route('candidate.cv-builder'), route('candidate.skills.edit'), route('candidate.applications.index'),
        route('candidate.applications.show', $application), route('candidate.saved-jobs.index'),
        route('candidate.job-alerts.index'), route('profile.edit'),
    ];

    $companyPages = [
        route('employer.dashboard', $company), route('employer.jobs.index', $company), route('employer.jobs.create', $company),
        route('employer.jobs.edit', ['company' => $company, 'jobPosting' => $job]),
        route('employer.jobs.applications', ['company' => $company, 'jobPosting' => $job]),
        route('employer.applications.show', ['company' => $company, 'application' => $application]),
        route('employer.analytics', $company), route('employer.reviews', $company), route('employer.team.index', $company),
        route('employer.company.edit', $company), route('employer.recruiter-profile.edit', ['company' => $company->slug]),
    ];

    foreach ([[$candidate, $candidatePages], [$owner, $companyPages]] as [$user, $pages]) {
        foreach ($pages as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            expect(preg_match_all('/<h1[\s>]/', $html))->toBe(1, "{$url} should have one <h1>");
        }
    }
});

test('workspace pages take their width from the page component', function () {
    $offenders = [];

    foreach (['candidate', 'employer', 'pages/candidate', 'pages/employer', 'pages/settings'] as $directory) {
        foreach (Finder::create()->files()->in(resource_path("views/{$directory}"))->name('*.blade.php') as $file) {
            if (preg_match('/mx-auto[^"]*max-w-(?:sm|md|lg|xl|[2-7]xl)\b/', $file->getContents())) {
                $offenders[] = "{$directory}/{$file->getRelativePathname()}";
            }
        }
    }

    expect($offenders)->toBe([], 'Wrap the page in <x-page> (wide or narrow) instead of a hand-set width.');
});

test('the top bar of the candidate workspace opens the palette instead of repeating the sidebar', function () {
    $this->actingAs(candidateUser())
        ->get(route('candidate.dashboard'))
        ->assertOk()
        ->assertSee("\$dispatch('open-command-palette')", false)
        ->assertSee('Search jobs or jump to a page')
        ->assertSeeLivewire(CommandPalette::class);
});

test('the company workspace has the palette, scoped to the company', function () {
    $company = Company::factory()->create(['name' => 'Fernhill Software']);

    $this->actingAs(employerUser($company))
        ->get(route('employer.dashboard', $company))
        ->assertOk()
        ->assertSee('Search applicants, jobs or pages')
        ->assertSeeLivewire(CommandPalette::class);
});

test('the palette lists the same pages as the sidebar', function () {
    Livewire::actingAs(candidateUser())
        ->test(CommandPalette::class)
        ->assertSeeInOrder(['Dashboard', 'Find jobs', 'Applications', 'Saved jobs', 'Job alerts', 'Overview', 'Experience', 'Education', 'Skills', 'Documents', 'CV builder', 'Job preferences', 'Settings'])
        ->assertSee(route('candidate.saved-jobs.index'), false);
});

test('the palette offers Post a job only to those who can post', function () {
    $company = Company::factory()->create();
    $create = route('employer.jobs.create', $company);

    Livewire::actingAs(employerUser($company, MembershipRole::Manager))
        ->test(CommandPalette::class, ['company' => $company])
        ->assertSee($create, false);

    Livewire::actingAs(employerUser($company))
        ->test(CommandPalette::class, ['company' => $company])
        ->assertDontSee($create, false)
        ->assertDontSee(route('employer.team.index', $company), false);
});

test('the palette searches open jobs on the personal side', function () {
    JobPosting::factory()->create(['title' => 'Laravel Developer']);
    JobPosting::factory()->closed()->create(['title' => 'Laravel Lead']);

    Livewire::actingAs(candidateUser())
        ->test(CommandPalette::class)
        ->set('q', 'Laravel')
        ->assertSee('Laravel Developer')
        ->assertDontSee('Laravel Lead')
        ->set('q', 'L')
        ->assertDontSee('Laravel Developer');
});

test('the palette finds applicants by name, only in the company being searched', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $member = employerUser($company);

    $ours = Application::factory()->for(JobPosting::factory()->for($company))->create([
        'candidate_profile_id' => CandidateProfile::factory()->for(User::factory()->state(['name' => 'Nadia Rahman'])),
    ]);
    Application::factory()->for(JobPosting::factory()->for($other))->create([
        'candidate_profile_id' => CandidateProfile::factory()->for(User::factory()->state(['name' => 'Nadia Karim'])),
    ]);

    Livewire::actingAs($member)
        ->test(CommandPalette::class, ['company' => $company])
        ->set('q', 'Nadia')
        ->assertSee('Nadia Rahman')
        ->assertSee(route('employer.applications.show', ['company' => $company, 'application' => $ours]), false)
        ->assertDontSee('Nadia Karim');
});

test('the palette refuses a company the user does not work at', function () {
    $company = Company::factory()->create();

    Livewire::actingAs(employerUser())
        ->test(CommandPalette::class, ['company' => $company])
        ->assertForbidden();
});

test('the palette stops searching a company once the membership ends', function () {
    $company = Company::factory()->create();
    $member = employerUser($company);

    $palette = Livewire::actingAs($member)->test(CommandPalette::class, ['company' => $company]);

    $member->memberships()->update(['status' => MembershipStatus::Inactive]);

    $palette->set('q', 'anyone')->assertForbidden();
});

test('opening the palette shows the jobs the candidate last viewed, and nothing before it opens', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create(['title' => 'Viewed Role']);
    JobView::create(['user_id' => $candidate->id, 'job_posting_id' => $job->id, 'viewed_at' => now()]);

    Livewire::actingAs($candidate)
        ->test(CommandPalette::class)
        ->assertDontSee('Viewed Role')
        ->set('ready', true)
        ->assertSee('Recently viewed')
        ->assertSee('Viewed Role');
});
