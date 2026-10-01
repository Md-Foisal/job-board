<?php

use App\Ai\Agents\ResumeParser;
use App\Enums\AiFeature;
use App\Jobs\ParseResumeWithAi;
use App\Models\AiUsage;
use App\Models\Document;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Support\CvFiles;

beforeEach(function () {
    Storage::fake('local');
    config([
        'ai.enabled' => true,
        'ai.providers.anthropic.key' => 'test-key',
        'plans.default' => 'pro',
        'plans.limits.pro' => ['resume_parser' => 3],
    ]);

    foreach (['Laravel', 'PHP'] as $name) {
        Skill::create(['name' => $name]);
    }
});

/**
 * What a model reading Karim's CV sends back, including things the job
 * must clean up: a month in the wrong format, a role with no company, a
 * link that is not a profile, and a skill that is not on our list.
 */
function karimsAnswer(): array
{
    return [
        'headline' => '  Backend developer  ',
        'summary' => 'I build Laravel applications.',
        'links' => [
            'linkedin_url' => 'https://www.linkedin.com/in/karim-rahman',
            'github_url' => 'https://github.com/laravel/framework',
            'portfolio_url' => null,
        ],
        'skills' => ['laravel', 'PHP', 'Kubernetes'],
        'experience' => [
            ['company_name' => 'Acme Ltd', 'job_title' => 'Backend Developer', 'description' => 'APIs in Laravel', 'start_month' => '2021-03', 'end_month' => null],
            ['company_name' => 'Beta Inc', 'job_title' => 'Intern', 'description' => null, 'start_month' => 'Summer 2019', 'end_month' => '2019-08'],
            ['company_name' => '', 'job_title' => 'Freelancer', 'description' => null, 'start_month' => '2018-01', 'end_month' => null],
        ],
        'education' => [
            ['institution_name' => 'University of Dhaka', 'degree' => 'BSc', 'field_of_study' => 'CSE', 'start_month' => '2015-01', 'end_month' => '2019-01'],
        ],
    ];
}

function aiCv(string $extension = 'pdf'): Document
{
    $bytes = $extension === 'pdf'
        ? CvFiles::pdf([['Karim Rahman', 'Backend developer']])
        : CvFiles::docx(['Karim Rahman', 'Backend developer at Acme Ltd since March 2021']);

    return CvFiles::stored($extension, $bytes, ['candidate_profile_id' => candidateUser()->candidateProfile->id]);
}

function runParse(Document $document): void
{
    Cache::put(ParseResumeWithAi::runningKey($document->id), true, ParseResumeWithAi::RUNNING_SECONDS);

    ParseResumeWithAi::dispatchSync($document->id, $document->candidateProfile->user_id);
}

test('a PDF is sent as the file itself, and the checked answer waits for the page', function () {
    ResumeParser::fake([karimsAnswer()])->preventStrayPrompts();
    $document = aiCv();

    runParse($document);

    ResumeParser::assertPrompted(fn (AgentPrompt $prompt) => $prompt->attachments->count() === 1
        && $prompt->attachments->first() instanceof StoredDocument);

    $result = Cache::get(ParseResumeWithAi::resultKey($document->id));

    expect($result['status'])->toBe('done')
        ->and(Cache::has(ParseResumeWithAi::runningKey($document->id)))->toBeFalse()
        ->and($result['draft']['headline'])->toBe('Backend developer')
        ->and($result['draft']['links'])->toBe(['linkedin_url' => 'https://www.linkedin.com/in/karim-rahman', 'github_url' => null, 'portfolio_url' => null])
        ->and(array_column($result['draft']['skills'], 'name'))->toBe(['Laravel', 'PHP'])
        ->and($result['draft']['unmatched_skills'])->toBe(['Kubernetes'])
        ->and(array_column($result['draft']['experience'], 'company_name'))->toBe(['Acme Ltd', 'Beta Inc'])
        ->and($result['draft']['experience'][1]['start_month'])->toBeNull()
        ->and($result['draft']['education'][0]['institution_name'])->toBe('University of Dhaka');
});

test('a phone number is kept only in a shape the profile form accepts, and a location is cut to fit', function (mixed $phone, ?string $kept) {
    ResumeParser::fake([[...karimsAnswer(), 'phone' => $phone, 'location' => '  Dhaka,   Bangladesh '.str_repeat('x', 200)]]);
    $document = aiCv();

    runParse($document);

    $draft = Cache::get(ParseResumeWithAi::resultKey($document->id))['draft'];

    expect($draft['phone'])->toBe($kept)
        ->and($draft['location'])->toStartWith('Dhaka, Bangladesh x')
        ->and(mb_strlen($draft['location']))->toBe(100);
})->with([
    'international, with separators' => ['+880 1712-345678', '+880 1712-345678'],
    'national, with brackets' => ['(020) 7946 0000', '(020) 7946 0000'],
    'not a number' => ['call me on WhatsApp', null],
    'too few digits' => ['1234', null],
    'longer than the field' => ['+1 234 567 890 123 456 789 012 345', null],
    'not text' => [8801712345678, null],
]);

test('the AI is asked for a phone number and a city, never a street address', function () {
    $instructions = (string) (new ResumeParser)->instructions();

    expect($instructions)->toContain('phone:')
        ->toContain('location:')
        ->toContain('Never a street, house number or postcode.');
});

test('a Word CV is sent as the text the product already reads, with no file attached', function () {
    ResumeParser::fake([karimsAnswer()])->preventStrayPrompts();

    runParse(aiCv('docx'));

    ResumeParser::assertPrompted(fn (AgentPrompt $prompt) => $prompt->attachments->isEmpty()
        && $prompt->contains('Backend developer at Acme Ltd since March 2021'));
});

test('an answered reading is counted against the candidate\'s allowance', function () {
    ResumeParser::fake([karimsAnswer()]);
    $document = aiCv();

    runParse($document);

    $usage = AiUsage::sole();

    expect($usage->feature)->toBe(AiFeature::ResumeParser)
        ->and($usage->user_id)->toBe($document->candidateProfile->user_id)
        ->and($usage->model)->toBe('claude-haiku-4-5-20251001');
});

test('a reading that fails is recorded as failed, counts for nothing, and stops the page waiting', function (Throwable $failure) {
    ResumeParser::fake([fn () => throw $failure]);
    $document = aiCv();

    expect(fn () => runParse($document))->toThrow($failure::class);

    expect(Cache::get(ParseResumeWithAi::resultKey($document->id)))->toBe(['status' => 'failed'])
        ->and(Cache::has(ParseResumeWithAi::runningKey($document->id)))->toBeFalse()
        ->and(AiUsage::count())->toBe(0);
})->with([
    'the provider\'s spend limit or rate limit' => fn () => RateLimitedException::forProvider('anthropic', 429),
    'the provider overloaded' => fn () => ProviderOverloadedException::forProvider('anthropic', 529),
    'any other error' => fn () => new RuntimeException('Connection reset'),
]);

test('a reading whose allowance ran out before it started sends nothing', function () {
    ResumeParser::fake()->preventStrayPrompts();
    $document = aiCv();
    $candidate = User::find($document->candidateProfile->user_id);

    foreach (range(1, 3) as $ignored) {
        AiUsage::create(['user_id' => $candidate->id, 'feature' => AiFeature::ResumeParser, 'provider' => 'anthropic', 'model' => 'm', 'input_tokens' => 1, 'output_tokens' => 1]);
    }

    runParse($document);

    ResumeParser::assertNeverPrompted();
    expect(Cache::get(ParseResumeWithAi::resultKey($document->id)))->toBe(['status' => 'unavailable']);
});

test('a CV rotated out of the library while the reading waited is still read', function () {
    ResumeParser::fake([karimsAnswer()]);
    $document = aiCv();
    $document->delete();

    runParse($document);

    expect(Cache::get(ParseResumeWithAi::resultKey($document->id))['status'])->toBe('done');
});
