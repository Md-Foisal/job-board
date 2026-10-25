<?php

use App\Enums\SkillImportance;
use App\Models\CvVersion;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;
use App\Models\Project;
use App\Models\Skill;
use App\Support\CvData;
use App\Support\CvDesign;
use App\Support\CvScore;
use App\Support\CvTailoring;
use App\Support\CvVersions;
use Livewire\Livewire;

function versionsCandidate()
{
    $candidate = candidateUser();
    ExperienceRecord::factory()->for($candidate->candidateProfile)->create(['job_title' => 'Backend Developer', 'company_name' => 'Acme']);

    return $candidate->fresh();
}

/**
 * A job asking for Laravel (required) and Vue, whose description also
 * names Docker.
 */
function laravelJob(): JobPosting
{
    $job = JobPosting::factory()->create(['title' => 'Laravel Developer', 'description' => '<p>You will ship features with Docker every week.</p>']);
    $job->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['importance' => SkillImportance::Required]);
    $job->skills()->attach(Skill::firstOrCreate(['name' => 'Vue.js']), ['importance' => SkillImportance::NiceToHave]);
    Skill::firstOrCreate(['name' => 'Docker']);

    return $job->fresh();
}

test('the first visit makes "My CV"; later visits open the one worked on last', function () {
    $candidate = versionsCandidate();

    Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')->assertSet('versionName', 'My CV');
    Livewire::actingAs($candidate)->test('pages::candidate.cv-builder');

    expect($candidate->candidateProfile->cvVersions()->count())->toBe(1);
});

test('a copy keeps the design and extras but not the web page', function () {
    $candidate = versionsCandidate();
    $original = CvVersion::factory()->for($candidate->candidateProfile)->published()->create([
        'name' => 'Backend', 'design' => [...(new CvDesign)->toArray(), 'template' => 'modern'], 'extras' => ['interests' => 'Chess'],
    ]);

    $copy = CvVersions::copy($original);

    expect($copy->name)->toBe('Copy of Backend')
        ->and($copy->design['template'])->toBe('modern')
        ->and($copy->cvExtras()->interests)->toBe('Chess')
        ->and($copy->public_slug)->toBeNull();
});

test('the builder copies, renames and deletes CVs, but never the last one', function () {
    $candidate = versionsCandidate();

    Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->set('versionName', 'Backend roles')
        ->call('copyVersion')
        ->assertRedirect();

    $profile = $candidate->candidateProfile;
    expect($profile->cvVersions()->pluck('name')->sort()->values()->all())->toBe(['Backend roles', 'Copy of Backend roles']);

    $copy = $profile->cvVersions()->where('name', 'Copy of Backend roles')->sole();
    Livewire::actingAs($candidate)->withQueryParams(['version' => $copy->id])->test('pages::candidate.cv-builder')
        ->assertSet('versionId', $copy->id)
        ->call('deleteVersion');

    expect($profile->cvVersions()->count())->toBe(1);

    Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')->call('deleteVersion');
    expect($profile->cvVersions()->count())->toBe(1);
});

test('a candidate keeps at most ten CVs', function () {
    $candidate = versionsCandidate();
    CvVersion::factory()->count(CvVersion::MAX_PER_PROFILE)->for($candidate->candidateProfile)->create();

    Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')->call('copyVersion');

    expect($candidate->candidateProfile->cvVersions()->count())->toBe(CvVersion::MAX_PER_PROFILE);
});

test('another candidate\'s CV cannot be opened by its number', function () {
    $other = CvVersion::factory()->create(['name' => 'Not yours']);
    $candidate = versionsCandidate();

    Livewire::actingAs($candidate)->withQueryParams(['version' => $other->id])->test('pages::candidate.cv-builder')
        ->assertSet('versionName', 'My CV')
        ->call('openVersion', $other->id)
        ->assertNoRedirect();
});

test('a headline and summary for one CV replace the profile\'s on that CV only', function () {
    $candidate = versionsCandidate();
    $candidate->candidateProfile->update(['headline' => 'Developer', 'bio' => 'General summary.']);

    $page = Livewire::actingAs($candidate)->test('pages::candidate.cv-builder')
        ->set('cvHeadline', 'Laravel backend developer')
        ->set('cvSummary', 'Payments and queues.');

    expect($page->instance()->preview)->toContain('Laravel backend developer')->toContain('Payments and queues.')
        ->and($candidate->candidateProfile->fresh()->headline)->toBe('Developer');
});

test('tailoring a CV to a job makes one copy for it, and opens it again next time', function () {
    $candidate = versionsCandidate();
    $job = laravelJob();

    $this->actingAs($candidate)->get(route('candidate.cv-builder.tailor', $job))->assertRedirect();
    $this->actingAs($candidate)->get(route('candidate.cv-builder.tailor', $job))->assertRedirect();

    $version = $candidate->candidateProfile->cvVersions()->where('job_posting_id', $job->id)->sole();
    expect($version->name)->toBe('Laravel Developer at '.$job->company->name);

    Livewire::actingAs($candidate)->withQueryParams(['version' => $version->id])->test('pages::candidate.cv-builder')
        ->assertSee('This CV is aimed at Laravel Developer');
});

test('a job nobody can see cannot be tailored to', function () {
    $job = JobPosting::factory()->draft()->create();

    $this->actingAs(versionsCandidate())->get(route('candidate.cv-builder.tailor', $job))->assertNotFound();
});

test('the job page offers to tailor the CV and write a cover letter', function () {
    $candidate = versionsCandidate();
    $candidate->candidateProfile->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['proficiency' => 'advanced']);
    $job = laravelJob();

    Livewire::actingAs($candidate)->test('match-breakdown', ['jobPosting' => $job])
        ->assertSee('Tailor my CV for this job')
        ->assertSee(route('candidate.cv-builder.tailor', $job))
        ->assertSee('Write a cover letter');
});

test('coverage counts the job\'s skills and the skills its description names', function () {
    $candidate = versionsCandidate();
    $profile = $candidate->candidateProfile;
    $profile->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['proficiency' => 'advanced']);
    $job = laravelJob();
    $version = CvVersions::forJob($profile, $job);

    $tailoring = CvTailoring::for($version);
    $coverage = $tailoring->coverage(CvData::fromProfile($profile->fresh(), $version, $tailoring->keywords));

    expect($tailoring->keywords)->toEqualCanonicalizing(['Laravel', 'Vue.js', 'Docker'])
        ->and($tailoring->required)->toBe(['Laravel'])
        ->and($coverage['covered'])->toBe(['Laravel'])
        ->and($coverage['missing'])->toEqualCanonicalizing(['Vue.js', 'Docker'])
        ->and($coverage['percent'])->toBe(33);
});

test('a pasted job description is read the same way, and the tailor panel shows the gap', function () {
    $candidate = versionsCandidate();
    Skill::firstOrCreate(['name' => 'PostgreSQL']);
    $version = CvVersions::open($candidate->candidateProfile);

    Livewire::actingAs($candidate)->test('cv.tailor', ['versionId' => $version->id])
        ->set('jobDescription', 'We run PostgreSQL in production.')
        ->assertDispatched('cv-updated')
        ->assertSee('PostgreSQL')
        ->assertSee('0% covered');

    expect($version->fresh()->job_description)->toBe('We run PostgreSQL in production.');
});

test('on a CV aimed at a job, the projects closest to it come first', function () {
    $candidate = versionsCandidate();
    $profile = $candidate->candidateProfile;
    Project::factory()->for($profile)->create(['name' => 'Newest unrelated', 'description' => 'A photo gallery.', 'start_date' => '2025-06-01']);
    Project::factory()->for($profile)->create(['name' => 'Older match', 'description' => 'Built with Laravel and Docker.', 'start_date' => '2023-01-01']);

    $plain = CvData::fromProfile($profile->fresh());
    $aimed = CvData::fromProfile($profile->fresh(), null, ['Laravel', 'Docker']);

    expect(array_column($plain->projects, 'name'))->toBe(['Newest unrelated', 'Older match'])
        ->and(array_column($aimed->projects, 'name'))->toBe(['Older match', 'Newest unrelated']);
});

test('the score passes a strong CV and explains what a weak one lacks', function () {
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;
    $profile->update([
        'headline' => 'Backend developer',
        'bio' => str_repeat('I build reliable Laravel applications for payments and logistics. ', 4),
        'phone' => '+880 1712-000000',
        'location' => 'Dhaka',
    ]);
    ExperienceRecord::factory()->for($profile)->create(['start_date' => '2022-01-01', 'end_date' => null, 'description' => '<ul><li>Built the payments API used by 40,000 people</li><li>Reduced checkout time by 30%</li></ul>']);
    ExperienceRecord::factory()->for($profile)->create(['start_date' => '2020-01-01', 'end_date' => '2021-12-01', 'description' => '<ul><li>Led a team of 4</li><li>Shipped 12 releases</li></ul>']);
    EducationRecord::factory()->for($profile)->create();
    foreach (['PHP', 'Laravel', 'MySQL', 'Redis', 'Docker'] as $name) {
        $profile->skills()->attach(Skill::firstOrCreate(['name' => $name]), ['proficiency' => 'advanced']);
    }

    $roles = [['start' => '2022-01-01', 'end' => null], ['start' => '2020-01-01', 'end' => '2021-12-01']];
    $strong = CvScore::for(CvData::fromProfile($profile->fresh()), new CvDesign, null, $roles);

    expect($strong->score)->toBe(100)->and($strong->failing())->toBe([]);

    $weak = CvScore::for(CvData::fromProfile(candidateUser()->candidateProfile), CvDesign::from('creative', 'teal'), ['covered' => [], 'missing' => ['Laravel'], 'percent' => 0]);

    expect($weak->score)->toBeLessThan(50)
        ->and(collect($weak->failing())->pluck('key')->all())->toContain('contact', 'experience', 'skills', 'layout', 'keywords');
});

test('a gap of more than six months between roles is pointed out', function () {
    $cv = CvData::fromProfile(versionsCandidate()->candidateProfile);
    $score = CvScore::for($cv, new CvDesign, null, [['start' => '2019-01-01', 'end' => '2019-12-01'], ['start' => '2021-01-01', 'end' => null]]);

    expect(collect($score->failing())->pluck('key'))->toContain('gaps');
});
