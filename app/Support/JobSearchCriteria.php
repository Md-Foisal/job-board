<?php

namespace App\Support;

use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use App\Models\Category;
use App\Models\Skill;

/**
 * The job search filters as plain data: what the search page applies,
 * what a job alert stores, and what its emails match against. One shape
 * for all three, so an alert never means something different from the
 * search it was saved from.
 */
final class JobSearchCriteria
{
    public const KEYS = [
        'q', 'skill', 'category', 'location', 'workplaceType',
        'employmentType', 'currency', 'salaryMin', 'salaryMax', 'experience',
    ];

    private const INTEGERS = ['skill', 'category', 'salaryMin', 'salaryMax', 'experience'];

    /**
     * Keeps only the known filters that are actually set. Anything that is
     * not a valid value -- a workplace type that does not exist, a salary
     * that is not a number -- is dropped rather than trusted.
     *
     * Pay figures are kept only with the currency they are in: amounts in
     * different currencies are never compared, so a figure on its own
     * matches nothing in particular and is dropped too.
     *
     * @return array<string, int|string>
     */
    public static function from(array $input): array
    {
        $criteria = [];

        foreach (self::KEYS as $key) {
            $value = $input[$key] ?? null;

            if (in_array($key, self::INTEGERS, true)) {
                $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $value = $value === false ? null : $value;
            } elseif (is_string($value)) {
                $value = trim($value);
            } else {
                $value = null;
            }

            $value = match ($key) {
                'workplaceType' => WorkplaceType::tryFrom((string) $value)?->value,
                'employmentType' => EmploymentType::tryFrom((string) $value)?->value,
                'currency' => SalaryCurrencies::isInUse(strtoupper((string) $value)) ? strtoupper($value) : null,
                default => $value,
            };

            if ($value !== null && $value !== '') {
                $criteria[$key] = $value;
            }
        }

        if (! isset($criteria['currency'])) {
            unset($criteria['salaryMin'], $criteria['salaryMax']);
        }

        return $criteria;
    }

    /**
     * The criteria in words, for the alerts list and the alert email.
     * Takes names already looked up so a list of alerts costs one query
     * per kind, not one per alert.
     *
     * @param  array<int, string>  $skillNames
     * @param  array<int, string>  $categoryNames
     * @return list<string>
     */
    public static function describe(array $criteria, array $skillNames = [], array $categoryNames = []): array
    {
        $parts = [];

        if (isset($criteria['q'])) {
            $parts[] = '"'.$criteria['q'].'"';
        }

        if (isset($criteria['skill'])) {
            $parts[] = $skillNames[$criteria['skill']] ?? __('a skill that was removed');
        }

        if (isset($criteria['category'])) {
            $parts[] = $categoryNames[$criteria['category']] ?? __('a category that was removed');
        }

        if (isset($criteria['workplaceType'])) {
            $parts[] = WorkplaceType::from($criteria['workplaceType'])->label();
        }

        if (isset($criteria['employmentType'])) {
            $parts[] = EmploymentType::from($criteria['employmentType'])->label();
        }

        if (isset($criteria['location'])) {
            $parts[] = __('in :city', ['city' => $criteria['location']]);
        }

        if (isset($criteria['currency'])) {
            $currency = $criteria['currency'];

            $parts[] = match (true) {
                isset($criteria['salaryMin'], $criteria['salaryMax']) => __('pay :currency :min–:max a month', ['currency' => $currency, 'min' => number_format($criteria['salaryMin']), 'max' => number_format($criteria['salaryMax'])]),
                isset($criteria['salaryMin']) => __('pay from :currency :min a month', ['currency' => $currency, 'min' => number_format($criteria['salaryMin'])]),
                isset($criteria['salaryMax']) => __('pay up to :currency :max a month', ['currency' => $currency, 'max' => number_format($criteria['salaryMax'])]),
                default => __('paid in :currency', ['currency' => $currency]),
            };
        }

        if (isset($criteria['experience'])) {
            $parts[] = trans_choice('{1} for :count year of experience|[2,*] for :count years of experience', $criteria['experience']);
        }

        return $parts === [] ? [__('Every new job')] : $parts;
    }

    /**
     * Names for every skill and category a set of criteria mention, in two
     * queries. Removed ones are left out so describe() can say so.
     *
     * @param  iterable<array>  $criteriaList
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    public static function names(iterable $criteriaList): array
    {
        $skillIds = [];
        $categoryIds = [];

        foreach ($criteriaList as $criteria) {
            if (isset($criteria['skill'])) {
                $skillIds[] = $criteria['skill'];
            }

            if (isset($criteria['category'])) {
                $categoryIds[] = $criteria['category'];
            }
        }

        return [
            $skillIds === [] ? [] : Skill::whereIn('id', $skillIds)->pluck('name', 'id')->all(),
            $categoryIds === [] ? [] : Category::whereIn('id', $categoryIds)->pluck('name', 'id')->all(),
        ];
    }
}
