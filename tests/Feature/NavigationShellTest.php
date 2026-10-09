<?php

use App\Enums\MembershipRole;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Support\Navigation\Navigation;
use Illuminate\Support\Facades\Storage;

test('the guest navbar sends employers to their own page, not straight to sign-up', function () {
    $this->get(route('jobs.index'))
        ->assertOk()
        ->assertSee('For employers')
        ->assertSee('href="'.route('employers').'"', false);
});

test('the guest footer has a column for each audience', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertSeeInOrder(['Find a job', 'Browse jobs', 'Hire', 'For employers', 'Post a job', 'About', 'Privacy', 'Terms']);
});

test('the employers page sends a guest to sign up as an employer', function () {
    $this->get(route('employers'))
        ->assertOk()
        ->assertSee('How it works')
        ->assertSee('href="'.e(route('register', ['as' => 'employer'])).'"', false);
});

test('the employers page sends someone who can post straight to the posting form', function () {
    $company = Company::factory()->create();
    $manager = employerUser($company, MembershipRole::Manager);

    $this->actingAs($manager)
        ->get(route('employers'))
        ->assertOk()
        ->assertSee('href="'.route('employer.jobs.create', $company).'"', false);
});

test('the employers page sends an account without a company to company setup', function () {
    $html = $this->actingAs(candidateUser())
        ->get(route('employers'))
        ->assertOk()
        ->getContent();

    // The account menu links there too, so look for it on the button itself.
    $setup = preg_quote('href="'.route('companies.create').'"', '/');

    expect($html)->toMatch('/'.$setup.'[^>]*>(?:(?!<\/a>).)*Post a job/s');
});

test('registration asks for the side first and arrives with employer chosen from the employers page', function () {
    $candidateFirst = $this->get(route('register'))->assertOk()->getContent();
    $employerFirst = $this->get(route('register', ['as' => 'employer']))->assertOk()->getContent();

    expect($candidateFirst)
        ->toMatch('/<ui-radio[^>]*value="candidate"[^>]*\\schecked(?:="checked")?[\\s>]/')
        ->not->toMatch('/<ui-radio[^>]*value="employer"[^>]*\\schecked(?:="checked")?[\\s>]/')
        ->and(strpos($candidateFirst, 'name="role"'))->toBeLessThan(strpos($candidateFirst, 'name="name"'));

    expect($employerFirst)
        ->toMatch('/<ui-radio[^>]*value="employer"[^>]*\\schecked(?:="checked")?[\\s>]/')
        ->not->toMatch('/<ui-radio[^>]*value="candidate"[^>]*\\schecked(?:="checked")?[\\s>]/');
});

test('the candidate sidebar groups job search apart from the profile', function () {
    $candidate = candidateUser();

    // Experience, education and skills are parts of My profile, not pages
    // beside it. Checked on the list the sidebar draws from: the page
    // itself may still link to them, from an empty state for instance.
    $labels = collect(Navigation::personal($candidate))->flatMap(fn ($section) => $section->items)->pluck('label');
    expect($labels)->not->toContain('Experience')->not->toContain('Education')->not->toContain('Skills');

    $this->actingAs($candidate)
        ->get(route('candidate.dashboard'))
        ->assertOk()
        ->assertSeeInOrder([
            'Dashboard',
            'Job search', 'Find jobs', 'Applications', 'Saved jobs', 'Job alerts',
            'Profile', 'My profile', 'Documents', 'CV builder', 'Job preferences',
            'Account', 'Settings',
        ])
        ->assertDontSee('>Platform<', false);
});

test('the account menu shows the uploaded photo', function () {
    $user = candidateUser();
    $user->update(['avatar' => 'avatars/rafi.jpg']);

    $this->actingAs($user)
        ->get(route('jobs.index'))
        ->assertOk()
        ->assertSee(Storage::url('avatars/rafi.jpg'), false);
});

test('a recruiter photo stands in when there is no profile photo', function () {
    $user = employerUser();
    $user->recruiterProfile()->create(['avatar_path' => 'recruiters/hannah.jpg']);

    expect($user->fresh()->avatarUrl())->toBe(Storage::url('recruiters/hannah.jpg'))
        ->and(User::factory()->create()->avatarUrl())->toBeNull();
});

test('staff reach the admin panel from the account menu', function () {
    $this->actingAs(staffUser())
        ->get(route('jobs.index'))
        ->assertOk()
        ->assertSee('Admin panel');
});

test('the workspace keeps Post a job in the sidebar for those who can post', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser($company, MembershipRole::Manager))
        ->get(route('employer.dashboard', $company))
        ->assertOk()
        ->assertSee('href="'.route('employer.jobs.create', $company).'"', false);

    $this->actingAs(employerUser($company))
        ->get(route('employer.dashboard', $company))
        ->assertOk()
        ->assertDontSee('href="'.route('employer.jobs.create', $company).'"', false);
});

test('settings sections are tabs, and the account one is not called Profile', function () {
    $this->actingAs(candidateUser())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSeeInOrder(['Account', 'Security', 'Appearance']);
});

test('the sitemap lists the employers page', function () {
    $this->get(route('sitemap'))
        ->assertOk()
        ->assertSee(route('employers'), false);
});

test('unverified companies are flagged as waiting work in the staff menu', function () {
    expect(CompanyResource::getNavigationBadgeColor())->toBe('warning');
});
