<?php

namespace Database\Seeders\Demo;

use App\Enums\EmploymentType;
use App\Enums\SalaryPeriod;
use App\Enums\SkillImportance;
use App\Enums\WorkplaceType;
use App\Models\Category;
use App\Models\JobPosting;
use App\Models\Skill;

/**
 * Turns a catalogue role at a catalogue company into a job posting: the
 * description in the same HTML the editor produces, pay in the company's
 * currency at its market's level, and the categories and skills that go
 * with the role.
 */
final class Postings
{
    /**
     * @param  array<string, mixed>  $company
     * @param  array<string, mixed>  $overrides  the company's changes to the role
     * @return array<string, mixed>
     */
    public static function attributes(string $roleKey, array $company, array $overrides = [], bool $negotiable = false): array
    {
        $role = array_replace(Catalogue::role($roleKey), $overrides);
        $workplace = WorkplaceType::from($role['workplace'] ?? $company['workplace']);
        $period = SalaryPeriod::from($role['period'] ?? $company['period']);

        return [
            'title' => $role['title'],
            'description' => self::description($role, $company),
            'employment_type' => EmploymentType::from($role['employment'] ?? EmploymentType::FullTime->value),
            'workplace_type' => $workplace,
            // A remote role still says where someone must be able to work
            // from, which is the country; a city would only mislead.
            'location_city' => $workplace === WorkplaceType::Remote ? null : $company['city'],
            'location_country' => $company['country'],
            'min_experience_years' => $role['experience'],
            ...($negotiable ? self::negotiablePay() : self::pay($role, $company, $period)),
        ];
    }

    /**
     * The role's category and skills, as far as they are seeded: a seeder
     * run on its own, before the lists exist, still works. That every
     * name is on the lists is checked by DemoCatalogueTest instead.
     */
    public static function attachTaxonomy(JobPosting $posting, string $roleKey): void
    {
        $role = Catalogue::role($roleKey);

        $posting->categories()->sync(Category::query()->where('name', $role['category'])->pluck('id'));

        $skills = Skill::query()->whereIn('name', [...$role['required'], ...$role['nice']])->pluck('id', 'name');
        $importance = fn (array $names, SkillImportance $level) => $skills->only($names)
            ->mapWithKeys(fn (int $id) => [$id => ['importance' => $level->value]]);

        $posting->skills()->sync(
            $importance($role['required'], SkillImportance::Required)
                ->union($importance($role['nice'], SkillImportance::NiceToHave))
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>  $role
     * @param  array<string, mixed>  $company
     */
    private static function description(array $role, array $company): string
    {
        $list = fn (array $items) => '<ul>'.collect($items)->map(fn (string $item) => '<li>'.e($item).'</li>')->implode('').'</ul>';

        return '<p>'.e($company['pitch']).'</p>'
            .'<p>'.e($role['summary']).'</p>'
            ."<h3>What you'll do</h3>".$list($role['duties'])
            ."<h3>What you'll bring</h3>".$list($role['requirements'])
            .'<h3>Nice to have</h3>'.$list($role['bonus'])
            .'<h3>What we offer</h3>'.$list($company['benefits']);
    }

    /**
     * The role's US-level figures scaled to the company's market. Hourly
     * roles keep an hourly rate; a market that quotes monthly pay gets a
     * twelfth of the yearly figure. Rounded the way an employer would
     * write it.
     *
     * @param  array<string, mixed>  $role
     * @param  array<string, mixed>  $company
     * @return array<string, mixed>
     */
    private static function pay(array $role, array $company, SalaryPeriod $period): array
    {
        [$min, $max] = array_map(fn (int|float $amount) => match ($period) {
            SalaryPeriod::Hourly => max(1, (int) round($amount * $company['pay'])),
            SalaryPeriod::Monthly => self::roundTo($amount * $company['pay'] / 12, $company['pay'] >= 10 ? 1000 : 100),
            SalaryPeriod::Weekly => self::roundTo($amount * $company['pay'] / 52, 10),
            SalaryPeriod::Yearly => self::roundTo($amount * $company['pay'], 1000),
        }, $role['pay']);

        return [
            'salary_min' => $min,
            'salary_max' => $max,
            'salary_currency' => $company['currency'],
            'salary_period' => $period,
            'salary_negotiable' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function negotiablePay(): array
    {
        return [
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => null,
            'salary_period' => null,
            'salary_negotiable' => true,
        ];
    }

    private static function roundTo(float $amount, int $step): int
    {
        return max($step, (int) (round($amount / $step) * $step));
    }
}
