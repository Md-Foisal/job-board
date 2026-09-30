<?php

use App\Enums\AiAvailability;
use App\Enums\AiFeature;
use App\Enums\MembershipRole;
use App\Models\AiUsage;
use App\Models\Company;
use App\Models\User;
use App\Support\AiQuota;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\TextResponse;

beforeEach(function () {
    config([
        'ai.enabled' => true,
        'ai.default' => 'anthropic',
        'ai.providers.anthropic.key' => 'test-key',
    ]);
});

function answered(int $input = 1000, int $output = 200, ?int $cacheRead = null, ?int $cacheWrite = null, string $model = 'claude-haiku-4-5-20251001'): TextResponse
{
    return new TextResponse('ok', new TextUsage($input, $output, $cacheRead, $cacheWrite), new Meta('anthropic', $model));
}

function paidPlan(array $limits): void
{
    config(['plans.default' => 'pro', 'plans.limits.pro' => $limits]);
}

test('nothing runs while AI is switched off, or switched on without a key', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    config(['ai.enabled' => false]);

    foreach (AiFeature::cases() as $feature) {
        expect(AiQuota::availability($feature, $user, $company))->toBe(AiAvailability::Disabled);
    }

    config(['ai.enabled' => true, 'ai.providers.anthropic.key' => null]);

    expect(AiQuota::availability(AiFeature::ResumeParser, $user))->toBe(AiAvailability::Disabled)
        ->and(AiQuota::availability(AiFeature::ReviewScreening, $user))->toBe(AiAvailability::Disabled);
});

test('the free plan runs no AI for people or companies', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    expect($user->plan())->toBe('free')
        ->and($company->plan())->toBe('free');

    foreach ([AiFeature::ResumeParser, AiFeature::MatchExplanation, AiFeature::CvBuilder, AiFeature::JobPostReview] as $feature) {
        expect(AiQuota::availability($feature, $user, $company))->toBe(AiAvailability::NotInPlan)
            ->and(AiQuota::allows($feature, $user, $company))->toBeFalse()
            ->and(AiQuota::remaining($feature, $user, $company))->toBe(0);
    }
});

test('a monthly limit counts only this month, this feature and this person', function () {
    paidPlan(['resume_parser' => 2]);
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();

    $this->travelTo(now()->subMonthNoOverflow());
    AiQuota::record(AiFeature::ResumeParser, $user, null, answered());
    $this->travelBack();

    AiQuota::record(AiFeature::CvBuilder, $user, null, answered());
    AiQuota::record(AiFeature::ResumeParser, $someoneElse, null, answered());

    expect(AiQuota::remaining(AiFeature::ResumeParser, $user))->toBe(2);

    AiQuota::record(AiFeature::ResumeParser, $user, null, answered());

    expect(AiQuota::availability(AiFeature::ResumeParser, $user))->toBe(AiAvailability::Available)
        ->and(AiQuota::remaining(AiFeature::ResumeParser, $user))->toBe(1);

    AiQuota::record(AiFeature::ResumeParser, $user, null, answered());

    expect(AiQuota::availability(AiFeature::ResumeParser, $user))->toBe(AiAvailability::LimitReached)
        ->and(AiQuota::remaining(AiFeature::ResumeParser, $user))->toBe(0);

    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth());

    expect(AiQuota::availability(AiFeature::ResumeParser, $user))->toBe(AiAvailability::Available);
});

test('a company feature is counted per company, shared by the whole team', function () {
    paidPlan(['job_post_review' => 2]);
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    $manager = employerUser($company, MembershipRole::Manager);
    $otherCompany = Company::factory()->create();

    AiQuota::record(AiFeature::JobPostReview, $owner, $company, answered());
    AiQuota::record(AiFeature::JobPostReview, $manager, $company, answered());

    expect(AiQuota::availability(AiFeature::JobPostReview, $owner, $company))->toBe(AiAvailability::LimitReached)
        ->and(AiQuota::availability(AiFeature::JobPostReview, $manager, $company))->toBe(AiAvailability::LimitReached)
        ->and(AiQuota::availability(AiFeature::JobPostReview, $owner, $otherCompany))->toBe(AiAvailability::Available)
        ->and(AiUsage::where('company_id', $company->id)->count())->toBe(2);
});

test('a company feature cannot be asked about or recorded without the company', function () {
    $user = User::factory()->create();

    expect(fn () => AiQuota::availability(AiFeature::JobPostReview, $user))->toThrow(InvalidArgumentException::class)
        ->and(fn () => AiQuota::record(AiFeature::JobPostReview, $user, null, answered()))->toThrow(InvalidArgumentException::class);

    expect(AiUsage::count())->toBe(0);
});

test('a personal feature never lands on a company allowance', function () {
    paidPlan(['cv_builder' => 5]);
    $company = Company::factory()->create();
    $user = employerUser($company);

    AiQuota::record(AiFeature::CvBuilder, $user, $company, answered());

    expect(AiUsage::sole()->company_id)->toBeNull();
});

test('review screening is the platform\'s own cost and no plan limits it', function () {
    $author = User::factory()->create();

    AiQuota::record(AiFeature::ReviewScreening, $author, null, answered());
    AiQuota::record(AiFeature::ReviewScreening, $author, null, answered());

    expect(AiQuota::availability(AiFeature::ReviewScreening, $author))->toBe(AiAvailability::Available)
        ->and(AiQuota::remaining(AiFeature::ReviewScreening, $author))->toBeNull()
        ->and(AiUsage::where('feature', AiFeature::ReviewScreening)->where('user_id', $author->id)->count())->toBe(2);
});

test('asking whether a feature may run never uses the allowance up', function () {
    paidPlan(['match_explanation' => 1]);
    $user = User::factory()->create();

    foreach (range(1, 3) as $ignored) {
        AiQuota::availability(AiFeature::MatchExplanation, $user);
        AiQuota::allows(AiFeature::MatchExplanation, $user);
        AiQuota::remaining(AiFeature::MatchExplanation, $user);
    }

    expect(AiUsage::count())->toBe(0)
        ->and(AiQuota::allows(AiFeature::MatchExplanation, $user))->toBeTrue();
});

test('a limit that is missing or not a positive whole number closes the feature', function (mixed $limit) {
    paidPlan(['resume_parser' => $limit]);

    expect(AiQuota::availability(AiFeature::ResumeParser, User::factory()->create()))->toBe(AiAvailability::NotInPlan);
})->with([
    'missing' => null,
    'a string' => '5',
    'negative' => -1,
    'a float' => 2.5,
]);

test('a recorded run keeps the counts and the cost, and nothing of what was said', function () {
    $user = User::factory()->create();

    $usage = AiQuota::record(AiFeature::ResumeParser, $user, null, answered(input: 10_000, output: 1_000, cacheRead: 4_000, cacheWrite: 2_000));

    // 4,000 uncached x $1 + 4,000 cache reads x $0.10 + 2,000 cache writes x $1.25
    // + 1,000 output x $5, all per million tokens = 11,900 micro-dollars.
    expect($usage->fresh()->only(['user_id', 'company_id', 'provider', 'model', 'input_tokens', 'output_tokens', 'cost_micro_usd']))->toBe([
        'user_id' => $user->id,
        'company_id' => null,
        'provider' => 'anthropic',
        'model' => 'claude-haiku-4-5-20251001',
        'input_tokens' => 10_000,
        'output_tokens' => 1_000,
        'cost_micro_usd' => 11_900,
    ])->and($usage->feature)->toBe(AiFeature::ResumeParser)
        ->and($usage->created_at)->not->toBeNull();
});

test('a fraction of a micro-dollar rounds up, and an unpriced model records no cost', function () {
    $user = User::factory()->create();
    Log::spy();

    $cheap = AiQuota::record(AiFeature::ResumeParser, $user, null, answered(input: 3, output: 0, cacheRead: 3));
    $unpriced = AiQuota::record(AiFeature::ResumeParser, $user, null, answered(model: 'some-future-model'));

    expect($cheap->cost_micro_usd)->toBe(1)
        ->and($unpriced->fresh()->cost_micro_usd)->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});
