<?php

use App\Enums\ApplicationStage;
use App\Enums\AvailabilityStatus;
use App\Enums\MembershipRole;
use App\Models\Application;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\RecruiterProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a candidate cannot reach the recruiter profile page', function () {
    $this->actingAs(candidateUser())
        ->get(route('employer.recruiter-profile.edit'))
        ->assertForbidden();
});

test('any member can open their own recruiter profile, whatever their rank', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser($company, MembershipRole::Member))
        ->get(route('employer.recruiter-profile.edit'))
        ->assertOk()
        ->assertSee('Your recruiter profile');
});

test('the profile is created on first save', function () {
    $company = Company::factory()->create();
    $user = employerUser($company);

    expect($user->recruiterProfile)->toBeNull();

    $this->actingAs($user)->patch(route('employer.recruiter-profile.update'), [
        'display_name' => 'Nadia from Northwind',
        'bio' => 'I hire backend engineers.',
    ])->assertRedirect();

    $profile = $user->fresh()->recruiterProfile;

    expect($profile)->not->toBeNull();
    expect($profile->display_name)->toBe('Nadia from Northwind');
    expect($profile->bio)->toBe('I hire backend engineers.');
});

test('saving again edits the same profile rather than making a second', function () {
    $company = Company::factory()->create();
    $user = employerUser($company);
    RecruiterProfile::factory()->for($user)->create(['display_name' => 'Old Name']);

    $this->actingAs($user)->patch(route('employer.recruiter-profile.update'), [
        'display_name' => 'New Name',
    ]);

    expect(RecruiterProfile::where('user_id', $user->id)->count())->toBe(1);
    expect($user->fresh()->recruiterProfile->display_name)->toBe('New Name');
});

test('a new photo replaces the old file', function () {
    Storage::fake('public');

    $company = Company::factory()->create();
    $user = employerUser($company);
    $profile = RecruiterProfile::factory()->for($user)->create([
        'avatar_path' => UploadedFile::fake()->image('old.png')->store('recruiter-avatars', 'public'),
    ]);
    $originalPath = $profile->avatar_path;

    $this->actingAs($user)->patch(route('employer.recruiter-profile.update'), [
        'display_name' => 'Nadia',
        'avatar' => UploadedFile::fake()->image('new.png'),
    ]);

    expect($profile->fresh()->avatar_path)->not->toBe($originalPath);
    Storage::disk('public')->assertMissing($originalPath);
});

test('the job page shows the recruiter face when there is one', function () {
    $company = Company::factory()->create();
    $recruiter = employerUser($company, MembershipRole::Manager);
    RecruiterProfile::factory()->for($recruiter)->create([
        'display_name' => 'Nadia from Northwind',
        'bio' => 'I hire backend engineers.',
    ]);

    $job = JobPosting::factory()->for($company)->create([
        'posted_by_id' => $recruiter->id,
        'availability_status' => AvailabilityStatus::Active,
    ]);

    $this->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Nadia from Northwind')
        ->assertSee('I hire backend engineers.');
});

test('the job page falls back to the account name when there is none', function () {
    $company = Company::factory()->create();
    $recruiter = employerUser($company, MembershipRole::Manager);

    $job = JobPosting::factory()->for($company)->create([
        'posted_by_id' => $recruiter->id,
        'availability_status' => AvailabilityStatus::Active,
    ]);

    $this->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee($recruiter->name);
});

test('the dashboard counts open jobs, applications, and the ones waiting', function () {
    $company = Company::factory()->create();

    $open = JobPosting::factory()->for($company)->create(['availability_status' => AvailabilityStatus::Active]);
    JobPosting::factory()->for($company)->create(['availability_status' => AvailabilityStatus::Closed]);

    Application::factory()->count(2)->create([
        'job_posting_id' => $open->id,
        'stage' => ApplicationStage::New,
    ]);
    Application::factory()->create([
        'job_posting_id' => $open->id,
        'stage' => ApplicationStage::Interview,
    ]);

    $response = $this->actingAs(employerUser($company))
        ->get(route('employer.dashboard', $company));

    $response->assertOk();
    $response->assertSee($open->title);
    $response->assertSeeInOrder(['Open jobs', '1']);
    $response->assertSeeInOrder(['Applications', '3']);
    $response->assertSeeInOrder(['Waiting on you', '2']);
});

test('the dashboard says so plainly when there are no postings', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser($company))
        ->get(route('employer.dashboard', $company))
        ->assertOk()
        ->assertSee('No job postings yet.');
});

test('another company\'s numbers never appear', function () {
    $mine = Company::factory()->create();
    $theirs = Company::factory()->create();
    $theirJob = JobPosting::factory()->for($theirs)->create();

    $this->actingAs(employerUser($mine))
        ->get(route('employer.dashboard', $mine))
        ->assertOk()
        ->assertDontSee($theirJob->title);
});
