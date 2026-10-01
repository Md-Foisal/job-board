<?php

namespace App\Support;

use App\Enums\AiAvailability;
use App\Enums\AiFeature;
use App\Enums\AiPayer;
use App\Models\AiUsage;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Ai\Responses\TextResponse;

/**
 * Whether an AI feature may run for someone this month, and the record of
 * each run that did.
 *
 * The question is always asked before calling the model, and a run is
 * recorded only after the model has answered: a timeout, an outage or an
 * exception in between writes nothing, so a failure never costs anyone
 * part of their allowance.
 *
 * Allowances are per calendar month in the app's timezone, UTC -- the
 * same window the provider's own spend cap resets on -- and come from
 * config/plans.php for the plan of whoever pays: the person for their own
 * tools, the company for its team's tools. Features the platform runs
 * for itself are never limited here; the provider's spend limit is their
 * ceiling.
 *
 * Two runs started at the same moment can both pass the check, so an
 * allowance can be overshot by a run or two. That is accepted: the cost
 * is a fraction of a cent, and the provider's spend limit bounds the bill.
 */
final class AiQuota
{
    public static function enabled(): bool
    {
        return config('ai.enabled')
            && filled(config('ai.providers.'.config('ai.default').'.key'));
    }

    public static function availability(AiFeature $feature, User $user, ?Company $company = null): AiAvailability
    {
        if (! self::enabled()) {
            return AiAvailability::Disabled;
        }

        if ($feature->payer() === AiPayer::Platform) {
            return AiAvailability::Available;
        }

        $limit = self::limit($feature, $user, $company);

        if ($limit === 0) {
            return AiAvailability::NotInPlan;
        }

        return self::usedThisMonth($feature, $user, $company) < $limit
            ? AiAvailability::Available
            : AiAvailability::LimitReached;
    }

    public static function allows(AiFeature $feature, User $user, ?Company $company = null): bool
    {
        return self::availability($feature, $user, $company) === AiAvailability::Available;
    }

    /**
     * Runs left this month, or null for a feature no plan limits.
     */
    public static function remaining(AiFeature $feature, User $user, ?Company $company = null): ?int
    {
        if ($feature->payer() === AiPayer::Platform) {
            return null;
        }

        return max(0, self::limit($feature, $user, $company) - self::usedThisMonth($feature, $user, $company));
    }

    /**
     * The monthly allowance of the payer's plan. Anything the plan does not
     * list, or lists as something other than a whole number, is 0: a typo
     * in the config must close a feature, not open it without limit.
     */
    public static function limit(AiFeature $feature, User $user, ?Company $company = null): int
    {
        $payer = self::payerFor($feature, $company) ?? $user;
        $limit = config("plans.limits.{$payer->plan()}.{$feature->value}");

        return is_int($limit) && $limit > 0 ? $limit : 0;
    }

    public static function usedThisMonth(AiFeature $feature, User $user, ?Company $company = null): int
    {
        return self::usageOf($feature, $user, $company)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    /**
     * Write down a run the model has answered. Call it only with the
     * response in hand, never before the call.
     */
    public static function record(AiFeature $feature, User $user, ?Company $company, TextResponse $response): AiUsage
    {
        $model = $response->meta->model ?? '';

        return AiUsage::create([
            'user_id' => $user->id,
            'company_id' => self::payerFor($feature, $company)?->id,
            'feature' => $feature,
            'provider' => $response->meta->provider ?? '',
            'model' => $model,
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'cost_micro_usd' => self::costInMicroUsd($model, $response),
        ]);
    }

    /**
     * Prices in config/ai.php are dollars per million tokens, which is the
     * same number as millionths of a dollar per token -- so tokens times
     * price is already micro-dollars. Cache reads and writes are billed at
     * their own rates, and the rest of the input at the base rate. Part of
     * a micro-dollar rounds up: an estimate of what we spend should never
     * come out under the bill. (The round() first drops floating-point
     * noise, so 400.00000000001 does not become 401.)
     */
    public static function costInMicroUsd(string $model, TextResponse $response): ?int
    {
        $price = config("ai.pricing.{$model}");

        if (! is_array($price)) {
            Log::warning('No price configured for AI model; usage recorded without a cost.', ['model' => $model]);

            return null;
        }

        $usage = $response->usage;

        $cost = $usage->uncachedInputTokens() * $price['input']
            + ($usage->cacheReadInputTokens ?? 0) * $price['cache_read']
            + ($usage->cacheWriteInputTokens ?? 0) * $price['cache_write']
            + $usage->outputTokens * $price['output'];

        return (int) ceil(round($cost, 6));
    }

    /**
     * The company whose plan pays, for the features a company pays for.
     * Leaving it out there is a programming error, not a quiet fallback to
     * the person: that would let each teammate spend a separate allowance.
     */
    private static function payerFor(AiFeature $feature, ?Company $company): ?Company
    {
        if ($feature->payer() !== AiPayer::Company) {
            return null;
        }

        if ($company === null) {
            throw new InvalidArgumentException("{$feature->value} is paid for by a company; pass the company.");
        }

        return $company;
    }

    /**
     * @return Builder<AiUsage>
     */
    private static function usageOf(AiFeature $feature, User $user, ?Company $company): Builder
    {
        $query = AiUsage::query()->where('feature', $feature);

        return $feature->payer() === AiPayer::Company
            ? $query->where('company_id', self::payerFor($feature, $company)->id)
            : $query->where('user_id', $user->id);
    }
}
