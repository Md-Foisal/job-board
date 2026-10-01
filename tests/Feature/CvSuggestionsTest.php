<?php

use App\Actions\ApplyCvSuggestions;
use App\Ai\Agents\CvWriter;
use App\Enums\AiFeature;
use App\Jobs\PolishCvWithAi;
use App\Models\AiUsage;
use App\Models\CandidatePreference;
use App\Models\ExperienceRecord;
use App\Models\Skill;
use App\Models\User;
use App\Support\CvSuggestions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    config([
        'ai.enabled' => true,
        'ai.providers.anthropic.key' => 'test-key',
        'plans.default' => 'pro',
        'plans.limits.pro' => ['cv_builder' => 3],
    ]);
});

/**
 * Nadia: a summary, a role whose description has numbers, a role with no
 * description, and contact details the AI must never see.
 */
function nadia(): User
{
    $nadia = candidateUser()->fresh();
    $nadia->forceFill(['name' => 'Nadia Islam', 'email' => 'nadia@example.com'])->save();

    $profile = $nadia->candidateProfile;
    $profile->update([
        'headline' => 'Developer',
        'bio' => 'I build web apps.',
        'phone' => '+880 1999-406281',
        'location' => 'Khulna, Bangladesh',
        'linkedin_url' => 'https://www.linkedin.com/in/nadia-islam',
        'github_url' => null,
        'portfolio_url' => null,
    ]);
    ExperienceRecord::factory()->for($profile)->create([
        'job_title' => 'Laravel Developer', 'company_name' => 'Acme Ltd', 'start_date' => '2021-03-01', 'end_date' => null,
        'description' => '<p>Built the payments API used by 3 teams.</p>',
    ]);
    ExperienceRecord::factory()->for($profile)->create([
        'job_title' => 'Intern', 'company_name' => 'Beta Inc', 'start_date' => '2020-01-01', 'end_date' => '2020-06-01', 'description' => null,
    ]);
    $profile->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['proficiency' => 'advanced']);
    CandidatePreference::factory()->for($profile)->create(['desired_salary_min' => 76543, 'desired_salary_currency' => 'BDT']);

    return $nadia->fresh();
}

function acmeRole(User $candidate): ExperienceRecord
{
    return $candidate->candidateProfile->experienceRecords()->where('company_name', 'Acme Ltd')->sole();
}

function cvAnswer(User $candidate, array $overrides = []): array
{
    return array_merge([
        'headline' => 'Laravel Developer',
        'summary' => 'Laravel developer who builds payment APIs that several teams rely on.',
        'experience' => [
            ['id' => acmeRole($candidate)->id, 'bullets' => ['Built the payments API used by 3 teams', 'Designed its <b>error</b> handling']],
        ],
        'tips' => ['Add how many payments the API handles each day.'],
    ], $overrides);
}

function cvMaterialSent(AgentPrompt $prompt): string
{
    return collect($prompt->messages)
        ->first(fn ($message) => $message instanceof ToolResultMessage)
        ->toolResults->first()->result;
}

function runPolish(User $candidate): void
{
    Cache::put(PolishCvWithAi::runningKey($candidate->candidateProfile->id), true, PolishCvWithAi::RUNNING_SECONDS);

    PolishCvWithAi::dispatchSync($candidate->id);
}

function cvResult(User $candidate): ?array
{
    return Cache::get(PolishCvWithAi::resultKey($candidate->candidateProfile->id));
}

function polishPage(User $candidate)
{
    return Livewire::actingAs($candidate)->test('pages::candidate.cv-builder');
}

test('only the professional profile is sent, as the tool\'s result, with each role\'s id', function () {
    $nadia = nadia();
    CvWriter::fake([cvAnswer($nadia)])->preventStrayPrompts();

    runPolish($nadia);

    CvWriter::assertPrompted(function (AgentPrompt $prompt) use ($nadia) {
        $text = cvMaterialSent($prompt);
        $material = json_decode($text, true);

        return $prompt->prompt === 'Write the suggestions from the profile the tool returned.'
            && array_keys($material) === ['headline', 'summary', 'experience', 'education', 'skills']
            && $material['experience'][0]['id'] === acmeRole($nadia)->id
            && $material['experience'][0]['description'] === 'Built the payments API used by 3 teams.'
            && ! str_contains($text, 'Nadia Islam')
            && ! str_contains($text, 'nadia@example.com')
            && ! str_contains($text, '406281')
            && ! str_contains($text, 'Khulna')
            && ! str_contains($text, 'linkedin')
            && ! str_contains($text, '76543');
    });
});

test('the checked suggestions wait an hour for the page, and the run is counted', function () {
    $nadia = nadia();
    CvWriter::fake([cvAnswer($nadia)]);

    runPolish($nadia);

    $result = cvResult($nadia);

    expect($result['status'])->toBe('done')
        ->and($result['suggestions']['headline']['suggested'])->toBe('Laravel Developer')
        ->and(Cache::has(PolishCvWithAi::runningKey($nadia->candidateProfile->id)))->toBeFalse()
        ->and(AiUsage::sole()->feature)->toBe(AiFeature::CvBuilder);
});

test('a run that fails is recorded as failed, counts for nothing, and stops the page waiting', function () {
    $nadia = nadia();
    CvWriter::fake([fn () => throw new RuntimeException('Connection reset')]);

    expect(fn () => runPolish($nadia))->toThrow(RuntimeException::class);

    expect(cvResult($nadia))->toBe(['status' => 'failed'])
        ->and(Cache::has(PolishCvWithAi::runningKey($nadia->candidateProfile->id)))->toBeFalse()
        ->and(AiUsage::count())->toBe(0);
});

test('nothing is sent when the allowance ran out while the run waited', function () {
    $nadia = nadia();
    CvWriter::fake()->preventStrayPrompts();

    foreach (range(1, 3) as $ignored) {
        AiUsage::create(['user_id' => $nadia->id, 'feature' => AiFeature::CvBuilder, 'provider' => 'anthropic', 'model' => 'm', 'input_tokens' => 1, 'output_tokens' => 1]);
    }

    runPolish($nadia);

    expect(cvResult($nadia))->toBe(['status' => 'unavailable']);
    CvWriter::assertNeverPrompted();
});

test('bullets become an escaped list, and a role that is not on this profile is dropped', function () {
    $nadia = nadia();
    $someoneElses = ExperienceRecord::factory()->create();

    $suggestions = CvSuggestions::fromAiAnswer(cvAnswer($nadia, ['experience' => [
        ['id' => acmeRole($nadia)->id, 'bullets' => ['• Built the payments API used by 3 teams', 'Designed its <b>error</b> handling', '', str_repeat('x', 201)]],
        ['id' => $someoneElses->id, 'bullets' => ['Ran a bank']],
        ['id' => 'not a number', 'bullets' => ['Anything']],
    ]]), $nadia->candidateProfile);

    expect($suggestions->experience)->toHaveCount(1)
        ->and($suggestions->experience[0]['bullets'])->toBe(['Built the payments API used by 3 teams', 'Designed its error handling'])
        ->and($suggestions->experience[0]['html'])->toBe('<ul><li>Built the payments API used by 3 teams</li><li>Designed its error handling</li></ul>')
        ->and($suggestions->experience[0]['too_long'])->toBeFalse();
});

test('markup the model writes is stored as text, never as markup', function () {
    $nadia = nadia();

    $suggestions = CvSuggestions::fromAiAnswer(cvAnswer($nadia, ['experience' => [
        ['id' => acmeRole($nadia)->id, 'bullets' => ['Wrote 5 < 6 tests & "docs"']],
    ]]), $nadia->candidateProfile);

    expect($suggestions->experience[0]['html'])->toBe('<ul><li>Wrote 5 &lt; 6 tests &amp; "docs"</li></ul>');
});

test('a number the candidate did not write is flagged, per role and for the summary', function () {
    $nadia = nadia();

    $suggestions = CvSuggestions::fromAiAnswer(cvAnswer($nadia, [
        'summary' => 'Laravel developer since 2021 who cut costs by 40%.',
        'experience' => [['id' => acmeRole($nadia)->id, 'bullets' => ['Built the payments API used by 3 teams and 12,000 shops']]],
    ]), $nadia->candidateProfile);

    expect($suggestions->summary['new_numbers'])->toBe(['40'])
        ->and($suggestions->experience[0]['new_numbers'])->toBe(['12,000'])
        ->and($suggestions->headline['new_numbers'])->toBe([]);
});

test('a number from another role counts as new for this one', function () {
    $nadia = nadia();
    $intern = $nadia->candidateProfile->experienceRecords()->where('company_name', 'Beta Inc')->sole();
    $intern->update(['description' => '<p>Answered support tickets.</p>']);

    $suggestions = CvSuggestions::fromAiAnswer(cvAnswer($nadia, [
        'experience' => [['id' => $intern->id, 'bullets' => ['Answered support tickets for 3 teams']]],
    ]), $nadia->candidateProfile->fresh());

    expect($suggestions->experience[0]['new_numbers'])->toBe(['3']);
});

test('bullets that would not fit a role description once escaped are kept but marked', function () {
    $nadia = nadia();
    // Six bullets of 200 characters fit; ampersands, stored as &amp;, can push them past 2,000.
    $long = array_fill(0, 6, trim(str_repeat('R&D ', 50)));

    $suggestions = CvSuggestions::fromAiAnswer(cvAnswer($nadia, [
        'experience' => [['id' => acmeRole($nadia)->id, 'bullets' => $long]],
    ]), $nadia->candidateProfile);

    expect($suggestions->experience[0]['too_long'])->toBeTrue();
});

test('a headline or summary the same as now, and empty answers, are not offered', function () {
    $nadia = nadia();

    $suggestions = CvSuggestions::fromAiAnswer(['headline' => 'Developer', 'summary' => '  ', 'experience' => 'nope', 'tips' => []], $nadia->candidateProfile);

    expect($suggestions->isEmpty())->toBeTrue();
});

test('an answer with nothing usable is shown as a failure', function () {
    $nadia = nadia();
    CvWriter::fake([['headline' => null, 'summary' => null, 'experience' => [], 'tips' => []]]);

    runPolish($nadia);

    expect(cvResult($nadia))->toBe(['status' => 'failed']);
});

test('only the ticked suggestions are applied, in one go', function () {
    $nadia = nadia();
    $suggestions = CvSuggestions::fromAiAnswer(cvAnswer($nadia), $nadia->candidateProfile);

    $result = app(ApplyCvSuggestions::class)($nadia->candidateProfile, $suggestions, [
        'headline' => false, 'summary' => true, 'experience' => [acmeRole($nadia)->id],
    ]);

    $profile = $nadia->candidateProfile->fresh();

    expect($result)->toBe(['applied' => ['summary', acmeRole($nadia)->id], 'skipped' => 0])
        ->and($profile->headline)->toBe('Developer')
        ->and($profile->bio)->toBe('Laravel developer who builds payment APIs that several teams rely on.')
        ->and(acmeRole($nadia)->description)->toBe('<ul><li>Built the payments API used by 3 teams</li><li>Designed its error handling</li></ul>');
});

test('a part the candidate changed since the AI read it keeps their own text', function () {
    $nadia = nadia();
    $suggestions = CvSuggestions::fromAiAnswer(cvAnswer($nadia), $nadia->candidateProfile);

    $this->travel(1)->minutes();
    $nadia->candidateProfile->update(['headline' => 'Senior Developer']);
    acmeRole($nadia)->update(['description' => '<p>My own words.</p>']);

    $result = app(ApplyCvSuggestions::class)($nadia->candidateProfile->fresh(), $suggestions, [
        'headline' => true, 'summary' => false, 'experience' => [acmeRole($nadia)->id],
    ]);

    expect($result)->toBe(['applied' => [], 'skipped' => 2])
        ->and($nadia->candidateProfile->fresh()->headline)->toBe('Senior Developer')
        ->and(acmeRole($nadia)->description)->toBe('<p>My own words.</p>');
});

test('with AI off, or a plan without it, the CV builder has no AI part', function (array $config) {
    config($config);

    polishPage(nadia())
        ->assertSee('Save to my CVs')
        ->assertDontSee('Improve with AI')
        ->call('polish')
        ->assertSet('aiStatus', null);
})->with([
    'AI off' => [['ai.enabled' => false]],
    'free plan' => [['plans.default' => 'free']],
]);

test('the button says what is sent and to whom', function () {
    polishPage(nadia())
        ->assertSee('Improve with AI')
        ->assertSee('(not your name, contact details, links or photo) are sent to Anthropic');
});

test('asking starts one run, however many times it is clicked or the page is open', function () {
    Queue::fake();
    $nadia = nadia();

    $first = polishPage($nadia);
    $second = polishPage($nadia);

    $first->call('polish')
        ->assertSet('aiStatus', 'running')
        ->assertSee('Writing suggestions…')
        ->assertSeeHtml('wire:poll.2s="checkAi"')
        ->call('polish');
    $second->call('polish')->assertSet('aiStatus', 'running');

    polishPage($nadia)->assertSet('aiStatus', 'running');

    Queue::assertPushed(PolishCvWithAi::class, 1);
});

test('suggestions are shown when they arrive, with numbers to check left unticked', function () {
    $nadia = nadia();
    CvWriter::fake([cvAnswer($nadia, [
        'summary' => 'Laravel developer who cut costs by 40%.',
    ])]);

    polishPage($nadia)
        ->call('polish')
        ->assertSet('aiStatus', 'done')
        ->assertSee('AI-generated — check every line')
        ->assertSee('Laravel developer who cut costs by 40%.')
        ->assertSee('Check these numbers, which your profile does not give: 40.')
        ->assertSee('Add how many payments the API handles each day.')
        ->assertSet('useHeadline', true)
        ->assertSet('useSummary', false)
        ->assertSet('useRoles', [acmeRole($nadia)->id]);
});

test('applying writes the ticked parts, rebuilds the preview, and takes them off the list', function () {
    $nadia = nadia();
    CvWriter::fake([cvAnswer($nadia)]);

    $page = polishPage($nadia)
        ->call('polish')
        ->set('useSummary', false)
        ->call('applySuggestions');

    expect($nadia->candidateProfile->fresh()->headline)->toBe('Laravel Developer')
        ->and($page->instance()->preview)->toContain('Laravel Developer')->toContain('Designed its error handling');

    $page->assertSet('useHeadline', false)
        ->assertSet('useRoles', [])
        ->assertSet('useSummary', true);

    expect(cvResult($nadia)['suggestions']['headline'])->toBeNull()
        ->and(cvResult($nadia)['suggestions']['summary'])->not->toBeNull();
});

test('what is left after applying is kept only until the hour runs out', function () {
    $nadia = nadia();
    CvWriter::fake([cvAnswer($nadia)]);

    $page = polishPage($nadia)->call('polish');

    $this->travel(50)->minutes();
    $page->set('useSummary', false)->call('applySuggestions');

    expect(cvResult($nadia)['suggestions']['summary'])->not->toBeNull();

    $this->travel(11)->minutes();

    expect(cvResult($nadia))->toBeNull();
});

test('applying with nothing ticked writes nothing', function () {
    $nadia = nadia();
    CvWriter::fake([cvAnswer($nadia)]);

    polishPage($nadia)
        ->call('polish')
        ->set('useHeadline', false)
        ->set('useSummary', false)
        ->set('useRoles', [])
        ->call('applySuggestions');

    expect($nadia->candidateProfile->fresh()->headline)->toBe('Developer');
});

test('coming back within the hour shows the suggestions without asking again', function () {
    $nadia = nadia();
    CvWriter::fake([cvAnswer($nadia)])->preventStrayPrompts();

    polishPage($nadia)->call('polish');

    polishPage($nadia)
        ->assertSet('aiStatus', 'done')
        ->assertSee('Designed its error handling');

    expect(AiUsage::count())->toBe(1);
});

test('a role changed since then is shown as such and cannot be ticked', function () {
    $nadia = nadia();
    CvWriter::fake([cvAnswer($nadia)]);
    polishPage($nadia)->call('polish');

    $this->travel(1)->minutes();
    acmeRole($nadia)->update(['description' => '<p>My own words.</p>']);

    polishPage($nadia)
        ->assertSee('You changed or removed this role since the AI read it, so your own text stays.')
        ->assertSet('useRoles', []);
});

test('discarding the suggestions forgets them', function () {
    $nadia = nadia();
    CvWriter::fake([cvAnswer($nadia)]);

    polishPage($nadia)
        ->call('polish')
        ->call('discardSuggestions')
        ->assertSet('aiStatus', null)
        ->assertDontSee('AI suggestions');

    expect(cvResult($nadia))->toBeNull();
});

test('a run that never reports back is shown as failed, and can be tried again', function () {
    Queue::fake();
    $nadia = nadia();

    $page = polishPage($nadia)->call('polish');

    Cache::forget(PolishCvWithAi::runningKey($nadia->candidateProfile->id));

    $page->call('checkAi')
        ->assertSet('aiStatus', 'failed')
        ->assertSee("The AI couldn't write suggestions this time")
        ->assertSee('Try again');
});

test('when the month\'s allowance is used, the page says when it comes back', function () {
    $nadia = nadia();

    foreach (range(1, 3) as $ignored) {
        AiUsage::create(['user_id' => $nadia->id, 'feature' => AiFeature::CvBuilder, 'provider' => 'anthropic', 'model' => 'm', 'input_tokens' => 1, 'output_tokens' => 1]);
    }

    polishPage($nadia)
        ->assertSee("You've used this month's AI suggestions. They reset on")
        ->assertDontSee('Improve with AI');
});

test('a candidate can ask only so many times a day', function () {
    Queue::fake();
    $nadia = nadia();

    foreach (range(1, PolishCvWithAi::DAILY_ATTEMPTS) as $ignored) {
        Cache::forget(PolishCvWithAi::runningKey($nadia->candidateProfile->id));
        polishPage($nadia)->call('polish');
    }

    Cache::forget(PolishCvWithAi::runningKey($nadia->candidateProfile->id));

    polishPage($nadia)->call('polish')->assertSet('aiStatus', null);

    Queue::assertPushed(PolishCvWithAi::class, PolishCvWithAi::DAILY_ATTEMPTS);
});

test('bullets too long for the description are never applied, even if ticked', function () {
    $nadia = nadia();
    $suggestions = CvSuggestions::fromAiAnswer(cvAnswer($nadia, [
        'experience' => [['id' => acmeRole($nadia)->id, 'bullets' => array_fill(0, 6, trim(str_repeat('R&D ', 50)))]],
    ]), $nadia->candidateProfile);

    $result = app(ApplyCvSuggestions::class)($nadia->candidateProfile, $suggestions, ['experience' => [acmeRole($nadia)->id]]);

    expect($result)->toBe(['applied' => [], 'skipped' => 1])
        ->and(acmeRole($nadia)->description)->toBe('<p>Built the payments API used by 3 teams.</p>');
});
