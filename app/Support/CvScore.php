<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * A graded checklist for one CV, by our own reading on our own server:
 * what recruiters and resume checkers (Enhancv, Jobscan, Rezi) look for
 * and a parser can be tested for -- reachable contact details, a summary
 * of a readable length, roles described in bullet points that start
 * with what was done and carry numbers, enough skills, no unexplained
 * gaps, a layout tracking systems read in order, and, for a CV aimed at
 * a job, the job's keywords.
 *
 * The score is the share of the checks' weight that passes. A check that
 * does not apply (gaps with one role, keywords with no job) is left out
 * rather than passed.
 */
final class CvScore
{
    /**
     * Words a bullet point usually starts with when it says what the
     * person did. Not exhaustive; a miss only lowers one check.
     */
    private const ACTION_VERBS = [
        'achieved', 'added', 'analysed', 'analyzed', 'architected', 'automated', 'built', 'championed', 'cleaned', 'coached',
        'collaborated', 'configured', 'consolidated', 'converted', 'coordinated', 'created', 'cut', 'debugged', 'decreased', 'defined',
        'delivered', 'deployed', 'designed', 'developed', 'documented', 'drove', 'enabled', 'engineered', 'established', 'expanded',
        'fixed', 'founded', 'grew', 'guided', 'handled', 'helped', 'identified', 'implemented', 'improved', 'increased',
        'integrated', 'introduced', 'launched', 'led', 'maintained', 'managed', 'mentored', 'migrated', 'modernised', 'modernized',
        'monitored', 'negotiated', 'optimised', 'optimized', 'organised', 'organized', 'owned', 'planned', 'presented', 'produced',
        'programmed', 'published', 'raised', 'rebuilt', 'redesigned', 'reduced', 'refactored', 'released', 'replaced', 'researched',
        'resolved', 'restructured', 'reviewed', 'rewrote', 'saved', 'scaled', 'secured', 'set', 'shipped', 'simplified',
        'solved', 'spearheaded', 'standardised', 'standardized', 'started', 'streamlined', 'strengthened', 'supervised', 'supported', 'taught',
        'tested', 'trained', 'translated', 'upgraded', 'wrote',
    ];

    public const MIN_SKILLS = 5;

    public const SUMMARY_MIN = 150;

    public const SUMMARY_MAX = 900;

    public const GAP_MONTHS = 6;

    /**
     * @param  list<array{key: string, label: string, passed: bool, advice: string, weight: int}>  $checks
     */
    public function __construct(
        public int $score,
        public array $checks,
    ) {}

    /**
     * @param  array{covered: list<string>, missing: list<string>, percent: ?int}|null  $coverage
     * @param  list<array{start: ?string, end: ?string}>  $roleDates  each role's start and end, Y-m-d
     */
    public static function for(CvData $cv, CvDesign $design, ?array $coverage = null, array $roleDates = []): self
    {
        $checks = [];
        $add = function (string $key, int $weight, bool $passed, string $label, string $advice) use (&$checks) {
            $checks[] = compact('key', 'label', 'passed', 'advice', 'weight');
        };

        $add('contact', 10, $cv->email !== '' && $cv->phone !== null && $cv->location !== null,
            __('Email, phone and city at the top'),
            __('Add a phone number and the city you live in, so an employer can reach you and see where you are.'));

        $add('headline', 5, $cv->headline !== null,
            __('A headline under your name'),
            __('Add a one-line headline, such as "Laravel developer", so a reader knows what you do at a glance.'));

        $summaryLength = mb_strlen((string) $cv->summary);
        $add('summary', 10, $summaryLength >= self::SUMMARY_MIN && $summaryLength <= self::SUMMARY_MAX,
            __('A summary of two to five lines'),
            $summaryLength === 0
                ? __('Add a short summary: who you are, what you are good at and what you are looking for.')
                : ($summaryLength < self::SUMMARY_MIN
                    ? __('Your summary is short. Two to five lines say more about what you are good at.')
                    : __('Your summary is long. Readers skim; keep it to two to five lines.')));

        $roles = $cv->experience;

        if ($roles !== []) {
            $described = collect($roles)->every(fn (array $role) => $role['description'] !== null);
            $add('described', 10, $described,
                __('Every role says what you did'),
                __('Some roles have no description. Add a few lines on what you did and what came of it.'));

            $bullets = collect($roles)->flatMap(fn (array $role) => self::bullets((string) $role['description']));
            $inBullets = collect($roles)->filter(fn (array $role) => $role['description'] !== null)
                ->every(fn (array $role) => count(self::bullets((string) $role['description'])) >= 2);
            $add('bullets', 10, $described && $inBullets,
                __('Roles written as bullet points'),
                __('Write each role as two or more bullet points. Recruiters scan bullets far faster than paragraphs.'));

            $withNumbers = collect($roles)->filter(fn (array $role) => preg_match('/\d/', strip_tags((string) $role['description'])) === 1)->count();
            $add('numbers', 10, $withNumbers * 2 >= count($roles),
                __('Results with numbers'),
                __('Put a number in at least half of your roles: how many users, how much faster, how much was saved.'));

            $actions = $bullets->filter(fn (string $bullet) => in_array(mb_strtolower(strtok(trim($bullet), ' ,.;:') ?: ''), self::ACTION_VERBS, true))->count();
            $add('verbs', 10, $bullets->isNotEmpty() && $actions * 10 >= $bullets->count() * 6,
                __('Bullet points start with what you did'),
                __('Start most bullet points with a verb like "Built", "Led" or "Reduced" rather than "Responsible for".'));

            if (count($roleDates) >= 2) {
                $add('gaps', 5, self::longestGap($roleDates) <= self::GAP_MONTHS,
                    __('No long gaps between roles'),
                    __('There is a gap of more than six months between two roles. A line about it (study, a project, family) answers the question before it is asked.'));
            }
        } else {
            $add('experience', 10, false,
                __('Work experience'),
                __('Add any work you have done, including internships and freelance projects.'));
        }

        $add('skills', 10, count($cv->skills) >= self::MIN_SKILLS,
            __('At least :count skills', ['count' => self::MIN_SKILLS]),
            __('List at least :count skills. Tracking systems match applications on them.', ['count' => self::MIN_SKILLS]));

        $add('education', 5, $cv->education !== [],
            __('Education'),
            __('Add your education, even if it is not finished.'));

        $add('layout', 5, $design->template->readsWellInTrackingSystems(),
            __('A layout tracking systems read in order'),
            __('Two-column layouts can be read out of order by application systems. Use a one-column template when you apply online.'));

        if ($coverage !== null && $coverage['percent'] !== null) {
            $add('keywords', 15, $coverage['percent'] >= CvTailoring::GOOD_COVERAGE,
                __('The job\'s keywords appear on your CV'),
                __('Only :percent% of the skills this job names appear on your CV. Add the ones you really have, in your skills or in your roles.', ['percent' => $coverage['percent']]));
        }

        $total = array_sum(array_column($checks, 'weight'));
        $passed = array_sum(array_column(array_filter($checks, fn (array $check) => $check['passed']), 'weight'));

        return new self($total === 0 ? 0 : (int) round($passed / $total * 100), $checks);
    }

    /**
     * @return list<array{key: string, label: string, passed: bool, advice: string, weight: int}>
     */
    public function failing(): array
    {
        return array_values(array_filter($this->checks, fn (array $check) => ! $check['passed']));
    }

    /**
     * The bullet points of a role description: its list items, or, with
     * no list, nothing.
     *
     * @return list<string>
     */
    private static function bullets(string $html): array
    {
        preg_match_all('#<li[^>]*>(.*?)</li>#is', $html, $matches);

        return array_values(array_filter(array_map(fn (string $item) => trim(html_entity_decode(strip_tags($item))), $matches[1])));
    }

    /**
     * The longest stretch, in whole months, between the end of one role
     * and the start of the next, counting roles that overlap as covered.
     *
     * @param  list<array{start: ?string, end: ?string}>  $roles
     */
    private static function longestGap(array $roles): int
    {
        try {
            $spans = collect($roles)
                ->filter(fn (array $role) => $role['start'] !== null)
                ->map(fn (array $role) => [CarbonImmutable::parse($role['start']), $role['end'] ? CarbonImmutable::parse($role['end']) : CarbonImmutable::now()])
                ->sortBy(fn (array $span) => $span[0])
                ->values();
        } catch (Throwable) {
            return 0;
        }

        $longest = 0;
        $reach = null;

        foreach ($spans as [$start, $end]) {
            if ($reach !== null && $start->greaterThan($reach)) {
                $longest = max($longest, (int) $reach->diffInMonths($start));
            }

            $reach = $reach === null || $end->greaterThan($reach) ? $end : $reach;
        }

        return $longest;
    }
}
