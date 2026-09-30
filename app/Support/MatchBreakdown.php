<?php

namespace App\Support;

use App\Enums\MatchCheck;
use App\Enums\MatchCheckResult;
use App\Models\Skill;
use Illuminate\Support\Collection;

/**
 * Why a candidate's match score is what it is, for the candidate alone.
 *
 * The score is the same skill-only number an employer sees; the checks
 * sit beside it and never change it. They compare the candidate's own
 * expectations (salary, workplace, employment type) and experience, which
 * are theirs to see, so mixing them into the shared number would let an
 * employer read a candidate's salary hopes off a percentage.
 */
final readonly class MatchBreakdown
{
    /**
     * @param  Collection<int, Skill>  $matchedRequired
     * @param  Collection<int, Skill>  $matchedNiceToHave
     * @param  Collection<int, Skill>  $missingRequired
     * @param  Collection<int, Skill>  $missingNiceToHave
     * @param  array<string, MatchCheckResult>  $checks  keyed by MatchCheck value, in MatchCheck order
     */
    public function __construct(
        public ?int $score,
        public Collection $matchedRequired,
        public Collection $matchedNiceToHave,
        public Collection $missingRequired,
        public Collection $missingNiceToHave,
        public array $checks,
        public bool $profileIsEmpty,
    ) {}

    public function check(MatchCheck $check): MatchCheckResult
    {
        return $this->checks[$check->value];
    }

    /**
     * Nothing on the candidate's side to compare: no skills, no work
     * history and no preferences. The page asks them to fill in their
     * profile rather than showing a breakdown of blanks.
     */
    public function isEmpty(): bool
    {
        return $this->profileIsEmpty;
    }
}
