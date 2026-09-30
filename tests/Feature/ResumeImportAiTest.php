<?php

use App\Ai\Agents\ResumeParser;
use App\Enums\AiFeature;
use App\Jobs\ParseResumeWithAi;
use App\Models\AiUsage;
use App\Models\Document;
use App\Models\ExperienceRecord;
use App\Models\Skill;
use App\Models\User;
use App\Support\ResumeDraft;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CvFiles;

beforeEach(function () {
    Storage::fake('local');

    foreach (['Laravel', 'PHP'] as $name) {
        Skill::create(['name' => $name]);
    }
});

function aiOn(int $allowance = 3): void
{
    config([
        'ai.enabled' => true,
        'ai.providers.anthropic.key' => 'test-key',
        'plans.default' => 'pro',
        'plans.limits.pro' => ['resume_parser' => $allowance],
    ]);
}

function blankCandidate(): User
{
    $candidate = candidateUser();
    $candidate->candidateProfile->update([
        'headline' => null, 'bio' => null, 'linkedin_url' => null, 'github_url' => null, 'portfolio_url' => null,
    ]);

    return $candidate;
}

function cvFor(User $candidate, string $extension = 'pdf', array $lines = ['Karim Rahman', 'Laravel developer']): Document
{
    $bytes = $extension === 'pdf' ? CvFiles::pdf([$lines]) : CvFiles::docx($lines);

    return CvFiles::stored($extension, $bytes, ['candidate_profile_id' => $candidate->candidateProfile->id]);
}

function aiAnswer(array $overrides = []): array
{
    return array_merge([
        'headline' => 'Backend developer',
        'summary' => 'I build Laravel applications.',
        'links' => ['linkedin_url' => null, 'github_url' => 'https://github.com/karim', 'portfolio_url' => null],
        'skills' => ['Laravel', 'PHP', 'Kubernetes'],
        'experience' => [
            ['company_name' => 'Acme Ltd', 'job_title' => 'Backend Developer', 'description' => 'APIs in Laravel', 'start_month' => '2021-03', 'end_month' => null],
            ['company_name' => 'Beta Inc', 'job_title' => 'Intern', 'description' => null, 'start_month' => null, 'end_month' => '2019-08'],
        ],
        'education' => [
            ['institution_name' => 'University of Dhaka', 'degree' => 'BSc', 'field_of_study' => 'CSE', 'start_month' => '2015-01', 'end_month' => '2019-01'],
        ],
    ], $overrides);
}

function aiPage(User $candidate, Document $document)
{
    return Livewire::actingAs($candidate)->test('pages::candidate.resume-import', ['document' => $document]);
}

test('with AI switched off, as at launch, nothing about AI is shown', function () {
    $candidate = blankCandidate();

    aiPage($candidate, cvFor($candidate))
        ->assertDontSee('Read with AI')
        ->assertDontSee('Anthropic');
});

test('on the free plan the AI reading is not offered', function () {
    aiOn();
    config(['plans.default' => 'free']);
    $candidate = blankCandidate();

    aiPage($candidate, cvFor($candidate))->assertDontSee('Read with AI');
});

test('where the plan allows it, the AI reading is offered with who will read the CV', function () {
    aiOn();
    $candidate = blankCandidate();

    aiPage($candidate, cvFor($candidate))
        ->assertSee('Read with AI')
        ->assertSee('Your CV is sent to Anthropic to be read. Anthropic does not keep it or use it to train models.');
});

test('a scanned PDF can still be read by the AI, but a Word file with no text cannot', function () {
    aiOn();
    $candidate = blankCandidate();

    aiPage($candidate, cvFor($candidate, 'pdf', []))
        ->assertSee("We couldn't read any text in this CV")
        ->assertSee('Read with AI');

    aiPage($candidate, CvFiles::stored('docx', CvFiles::docx([]), ['candidate_profile_id' => $candidate->candidateProfile->id]))
        ->assertDontSee('Read with AI');
});

test('asking for the AI reading queues it and waits for it', function () {
    aiOn();
    Queue::fake();
    $candidate = blankCandidate();
    $document = cvFor($candidate);

    aiPage($candidate, $document)
        ->call('readWithAi')
        ->assertSet('aiStatus', 'running')
        ->assertSee('Reading your CV…')
        ->assertSeeHtml('wire:poll.2s="checkAi"');

    Queue::assertPushed(ParseResumeWithAi::class, fn ($job) => $job->documentId === $document->id && $job->userId === $candidate->id);
});

test('a reading that never finishes stops the wait once its running mark expires', function () {
    aiOn();
    Queue::fake();
    $candidate = blankCandidate();
    $document = cvFor($candidate);

    $page = aiPage($candidate, $document)->call('readWithAi');

    Cache::forget(ParseResumeWithAi::runningKey($document->id));

    $page->call('checkAi')
        ->assertSet('aiStatus', 'failed')
        ->assertSee("The AI couldn't read your CV this time")
        ->assertSee('Try again');
});

test('the AI reading can be asked for five times a day', function () {
    aiOn(allowance: 50);
    Queue::fake();
    $candidate = blankCandidate();
    $document = cvFor($candidate);

    foreach (range(1, ParseResumeWithAi::DAILY_ATTEMPTS) as $ignored) {
        Cache::forget(ParseResumeWithAi::runningKey($document->id));
        aiPage($candidate, $document)->call('readWithAi')->assertSet('aiStatus', 'running');
    }

    Cache::forget(ParseResumeWithAi::runningKey($document->id));
    aiPage($candidate, $document)->call('readWithAi')->assertSet('aiStatus', null);

    Queue::assertPushed(ParseResumeWithAi::class, ParseResumeWithAi::DAILY_ATTEMPTS);
});

test('when the allowance is used up the page says so instead of offering the AI', function () {
    aiOn(allowance: 1);
    $candidate = blankCandidate();
    AiUsage::create(['user_id' => $candidate->id, 'feature' => AiFeature::ResumeParser, 'provider' => 'anthropic', 'model' => 'm', 'input_tokens' => 1, 'output_tokens' => 1]);

    aiPage($candidate, cvFor($candidate))
        ->assertSee("You've used this month's AI readings.")
        ->assertDontSee('Read with AI');
});

test('the AI\'s suggestions appear next to what the product found, ticked by the usual rules', function () {
    aiOn();
    ResumeParser::fake([aiAnswer()]);
    $candidate = blankCandidate();

    aiPage($candidate, cvFor($candidate))
        ->call('readWithAi')
        ->assertSet('aiStatus', 'done')
        ->assertSee('Backend developer')
        ->assertSee('Backend Developer at Acme Ltd')
        ->assertSee('Mar 2021 – Present')
        ->assertSee('Your CV gives no start date. Add it to include this.')
        ->assertSee('Not on our list, so not added: Kubernetes.')
        ->assertSet('chosenText', ['headline', 'bio'])
        ->assertSet('chosenExperience', [0])
        ->assertSet('chosenEducation', [0])
        ->assertSet('chosenLinks', ['github_url']);
});

test('what the candidate unticked while the AI was reading stays unticked', function () {
    aiOn();
    Queue::fake();
    $candidate = blankCandidate();
    $document = cvFor($candidate);
    $laravel = Skill::where('name', 'Laravel')->first();

    $page = aiPage($candidate, $document)
        ->assertSet('chosenSkills', [$laravel->id])
        ->call('readWithAi')
        ->set('chosenSkills', []);

    Cache::put(ParseResumeWithAi::resultKey($document->id), ['status' => 'done', 'draft' => ResumeDraft::fromAiAnswer(aiAnswer())->toArray()]);

    $page->call('checkAi')
        ->assertSet('chosenSkills', [Skill::where('name', 'PHP')->value('id')]);
});

test('adding writes the chosen roles, courses and text, with a start month typed in where the CV had none', function () {
    aiOn();
    ResumeParser::fake([aiAnswer()]);
    $candidate = blankCandidate();

    aiPage($candidate, cvFor($candidate))
        ->call('readWithAi')
        ->set('chosenText', ['headline'])
        ->set('chosenExperience', [0, 1])
        ->set('experienceStarts', [1 => '2019-06'])
        ->call('import')
        ->assertHasNoErrors();

    $profile = $candidate->candidateProfile->refresh();
    $roles = $profile->experienceRecords()->orderBy('start_date')->get();

    expect($profile->headline)->toBe('Backend developer')
        ->and($profile->bio)->toBeNull()
        ->and($roles->pluck('company_name')->all())->toBe(['Beta Inc', 'Acme Ltd'])
        ->and($roles[0]->start_date->format('Y-m-d'))->toBe('2019-06-01')
        ->and($roles[0]->end_date->format('Y-m-d'))->toBe('2019-08-01')
        ->and($roles[1]->end_date)->toBeNull()
        ->and($profile->educationRecords()->sole()->institution_name)->toBe('University of Dhaka');
});

test('a role ticked without a start month, or ending before it starts, is refused on that role and nothing is added', function () {
    aiOn();
    ResumeParser::fake([aiAnswer()]);
    $candidate = blankCandidate();

    $page = aiPage($candidate, cvFor($candidate))
        ->call('readWithAi')
        ->set('chosenExperience', [0, 1])
        ->call('import')
        ->assertHasErrors(['experienceStarts.1']);

    expect($candidate->candidateProfile->experienceRecords()->count())->toBe(0);

    $page->set('experienceStarts', [1 => '2020-01'])
        ->call('import')
        ->assertHasErrors(['experienceStarts.1']);

    expect($candidate->candidateProfile->experienceRecords()->count())->toBe(0);
});

test('a role already on the profile is shown as such', function () {
    aiOn();
    ResumeParser::fake([aiAnswer()]);
    $candidate = blankCandidate();
    ExperienceRecord::factory()->for($candidate->candidateProfile)->create([
        'company_name' => 'Acme Ltd', 'job_title' => 'Backend Developer', 'start_date' => '2021-03-01',
    ]);

    aiPage($candidate, cvFor($candidate))
        ->call('readWithAi')
        ->assertSet('chosenExperience', [])
        ->assertSee('already on your profile');
});

test('a headline already on the profile is offered as a replacement, unticked', function () {
    aiOn();
    ResumeParser::fake([aiAnswer()]);
    $candidate = blankCandidate();
    $candidate->candidateProfile->update(['headline' => 'Junior developer']);

    aiPage($candidate, cvFor($candidate))
        ->call('readWithAi')
        ->assertSet('chosenText', ['bio'])
        ->assertSee('Replace your headline');
});

test('coming back within the hour shows the AI\'s reading without asking again', function () {
    aiOn();
    ResumeParser::fake([aiAnswer()])->preventStrayPrompts();
    $candidate = blankCandidate();
    $document = cvFor($candidate);

    aiPage($candidate, $document)->call('readWithAi');

    aiPage($candidate, $document)
        ->assertSet('aiStatus', 'done')
        ->assertSee('Backend Developer at Acme Ltd');

    ResumeParser::assertPromptedTimes(1);
});
