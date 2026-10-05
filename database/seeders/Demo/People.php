<?php

namespace Database\Seeders\Demo;

use App\Actions\RenderCvPdf;
use App\Enums\DocumentType;
use App\Enums\EmploymentType;
use App\Enums\ProficiencyLevel;
use App\Enums\WorkplaceType;
use App\Models\CandidatePreference;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Models\User;
use App\Support\CvData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Candidates whose profile tells one story: the headline is the job they
 * hold now, the skills are the ones that job uses, the work history climbs
 * the same ladder, and what they studied, where they live and the pay they
 * ask for all fit. CVs someone can open are real PDFs, made by the same
 * template the CV builder uses.
 */
final class People
{
    /**
     * Where seeded CVs are written. Only the seeder writes here, so it can
     * clear the folder before writing it again.
     */
    public const CV_FOLDER = 'documents/seeded';

    private const YEARS = [1 => 'a year', 'two years', 'three years', 'four years', 'five years', 'six years', 'seven years', 'eight years', 'nine years', 'ten years'];

    /** @var array<string, true> */
    private static array $emails = [];

    /**
     * @param  array<string, mixed>  $user  attributes for the account, such as a fixed email
     */
    public static function candidate(string $family, ?string $place = null, array $user = [], bool $withSkills = true): CandidateProfile
    {
        $place ??= self::randomPlace();
        $where = Catalogue::place($place);
        $kind = self::family($family);
        $years = random_int(1, 10);
        $name = $user['name'] ?? Catalogue::personName($place);
        $joined = CarbonImmutable::now()->subDays(random_int(45, 600));

        $account = User::factory()->create([
            'name' => $name,
            'email' => self::email($name),
            'timezone' => $where['timezone'],
            'created_at' => $joined,
            'updated_at' => $joined,
            ...$user,
        ]);

        $profile = CandidateProfile::factory()->for($account)->create([
            'headline' => null,
            'bio' => strtr($kind['bio'], [':years' => self::YEARS[$years], ':focus' => self::pick($kind['focus'])]),
            'location' => self::pick($where['cities']).', '.$where['country'],
            'portfolio_url' => random_int(1, 4) === 1 ? 'https://'.Str::slug($name, '').'.example' : null,
            'github_url' => null,
            'linkedin_url' => null,
        ]);

        // The headline is the title of the latest job, so the level it
        // implies is the one skills and pay follow.
        [$careerStart, $level] = self::giveHistory($profile, $kind, $years);
        $profile->update(['headline' => $kind['titles'][$level]]);

        if ($withSkills) {
            self::giveSkills($profile, $kind, $level);
        }

        self::giveEducation($profile, $kind, $where, $careerStart);
        self::givePreference($profile, $kind, $where, $level);

        return $profile;
    }

    /**
     * The demo candidate, exactly as written in the catalogue.
     *
     * @param  array<string, mixed>  $details
     */
    public static function writtenCandidate(User $account, array $details): CandidateProfile
    {
        $profile = CandidateProfile::factory()->for($account)->create([
            'headline' => $details['headline'],
            'bio' => $details['bio'],
            'location' => $details['location'],
            'portfolio_url' => $details['portfolio_url'],
            'github_url' => null,
            'linkedin_url' => null,
            ...($details['contact'] ?? []),
        ]);

        $skills = Skill::query()->whereIn('name', array_keys($details['skills']))->pluck('id', 'name');
        $profile->skills()->sync($skills->mapWithKeys(fn (int $id, string $name) => [
            $id => ['proficiency' => ProficiencyLevel::from($details['skills'][$name])->value],
        ])->all());

        foreach ($details['experience'] as $job) {
            ExperienceRecord::factory()->for($profile)->create([
                'company_name' => $job['company'],
                'job_title' => $job['title'],
                'description' => $job['description'],
                'start_date' => self::monthsAgo($job['start']),
                'end_date' => $job['end'] === null ? null : self::monthsAgo($job['end']),
            ]);
        }

        $study = $details['education'];
        EducationRecord::factory()->for($profile)->create([
            'institution_name' => $study['institution'],
            'degree' => $study['degree'],
            'field_of_study' => $study['field'],
            'start_date' => self::monthsAgo($study['start']),
            'end_date' => self::monthsAgo($study['end']),
        ]);

        $wants = $details['preference'];
        CandidatePreference::factory()->for($profile)->create([
            'desired_salary_min' => $wants['min'],
            'desired_salary_max' => $wants['max'],
            'desired_salary_currency' => $wants['currency'],
            'preferred_workplace_type' => WorkplaceType::from($wants['workplace']),
            'preferred_employment_type' => EmploymentType::from($wants['employment']),
            'is_actively_searching' => true,
            'available_from' => null,
        ]);

        return $profile;
    }

    /**
     * A real PDF of the profile, built with the CV builder's template, as
     * a CV the candidate keeps in their documents.
     */
    public static function renderCv(CandidateProfile $profile, ?CarbonImmutable $at = null): Document
    {
        $cv = CvData::fromProfile($profile->loadMissing('user'));
        $path = self::CV_FOLDER.'/'.Str::random(40).'.pdf';

        Storage::disk('local')->put($path, app(RenderCvPdf::class)($cv));

        return self::cvRecord($profile, $path, $cv->fileName(), $at);
    }

    /**
     * A CV entry with no file behind it, for candidates no demo account
     * can see: rendering a PDF for every one would slow each fresh seed
     * for files nobody opens.
     */
    public static function unrenderedCv(CandidateProfile $profile, ?CarbonImmutable $at = null): Document
    {
        $fileName = str_replace(' ', '-', Str::headline(Str::slug($profile->user->name))).'-CV.pdf';

        return self::cvRecord($profile, self::CV_FOLDER.'/'.Str::random(40).'.pdf', $fileName, $at);
    }

    /**
     * A short letter in the candidate's own words, or none, since the
     * apply form leaves it optional.
     */
    public static function coverLetter(CandidateProfile $profile, JobPosting $posting): ?string
    {
        if (random_int(1, 10) > 6) {
            return null;
        }

        $current = $profile->experienceRecords()->orderByRaw('end_date is null desc')->latest('start_date')->first();
        // Whole years, counted the way the bio counts them: seven years and
        // eight months is still "seven years", or the two would disagree.
        $years = $profile->experienceRecords()->get()->sum(fn (ExperienceRecord $job) => $job->start_date->diffInMonths($job->end_date ?? now())) / 12;
        $line = 'I have '.self::YEARS[max(1, min(10, (int) floor($years)))].' of experience'
            .($current ? ', most recently as '.$current->job_title.' at '.$current->company_name : '').'.';

        return strtr(self::pick(Catalogue::people()['cover']), [
            ':title' => e($posting->title),
            ':company' => e($posting->company->name),
            ':line' => e($line),
            ':name' => e($profile->user->name),
        ]);
    }

    /**
     * The family a catalogue company's posting belongs to, found by its
     * title, or null for one the catalogue did not write.
     */
    public static function familyOf(JobPosting $posting): ?string
    {
        static $families = null;

        $families ??= collect([...Catalogue::companies(), 'demo' => Catalogue::demo()['company'] + ['roles' => []]])
            ->flatMap(fn (array $company) => collect($company['roles'])->mapWithKeys(fn (array $overrides, string $role) => [
                $overrides['title'] ?? Catalogue::role($role)['title'] => Catalogue::role($role)['family'],
            ]))
            ->union(collect(Catalogue::roles())->mapWithKeys(fn (array $role) => [$role['title'] => $role['family']]))
            ->all();

        return $families[$posting->title] ?? null;
    }

    public static function randomPlace(): string
    {
        $places = collect(Catalogue::people()['places'])->map(fn (array $place) => $place['weight']);
        $ticket = random_int(1, $places->sum());

        foreach ($places as $key => $weight) {
            if (($ticket -= $weight) <= 0) {
                return $key;
            }
        }

        return $places->keys()->first();
    }

    /**
     * @return array<string, mixed>
     */
    private static function family(string $family): array
    {
        return Catalogue::people()['families'][$family] ?? throw new InvalidArgumentException("The demo catalogue has no family called {$family}.");
    }

    /**
     * Junior for the first three years, mid-level until six, senior after.
     */
    private static function level(int|float $years): int
    {
        return $years < 3 ? 0 : ($years < 6 ? 1 : 2);
    }

    /**
     * @param  array<string, mixed>  $kind
     */
    private static function giveSkills(CandidateProfile $profile, array $kind, int $level): void
    {
        $extra = collect($kind['extra'])->shuffle()->take(random_int(1, 3))->all();
        $names = [...$kind['skills'], ...$extra];
        $ids = Skill::query()->whereIn('name', $names)->pluck('id', 'name');
        $levels = ProficiencyLevel::cases();

        // Only skills already on the list, so the seeder also runs before
        // the list is seeded.
        $profile->skills()->sync($ids->mapWithKeys(fn (int $id, string $name) => [
            $id => ['proficiency' => $levels[in_array($name, $kind['skills'], true) ? $level : max(0, $level - 1)]->value],
        ])->all());
    }

    /**
     * Jobs back from today, one to three of them, the title of each earlier
     * one set by how far into the career it started. Most people are still
     * in the last one; the rest left it a few months ago and are looking.
     *
     * @param  array<string, mixed>  $kind
     * @return array{0: CarbonImmutable, 1: int} when the career started, and the level of the latest job
     */
    private static function giveHistory(CandidateProfile $profile, array $kind, int $years): array
    {
        $months = $years * 12 + random_int(0, 11);
        $jobs = $years < 3 ? random_int(1, 2) : ($years < 7 ? 2 : 3);
        $end = random_int(1, 4) === 1 ? random_int(1, 4) : 0;
        $employers = collect(Catalogue::people()['employers'])->shuffle();
        $careerStart = self::monthsAgo($months + $end);
        $elapsed = 0;
        $level = 0;

        foreach (range(1, $jobs) as $index) {
            // People are promoted in place, so the latest job carries the
            // title the whole career has earned.
            $level = self::level(($index === $jobs ? $months : $elapsed) / 12);
            $length = $index === $jobs ? $months - $elapsed : (int) round($months / $jobs * random_int(80, 120) / 100);
            $startsAgo = $months + $end - $elapsed;
            $endsAgo = $index === $jobs ? $end : $startsAgo - $length;

            ExperienceRecord::factory()->for($profile)->create([
                'company_name' => $employers[$index],
                'job_title' => $kind['titles'][$level],
                'description' => '<ul>'.collect($kind['work'])->shuffle()->take(random_int(2, 3))->map(fn (string $line) => '<li>'.e($line).'</li>')->implode('').'</ul>',
                'start_date' => self::monthsAgo($startsAgo),
                'end_date' => $endsAgo === 0 ? null : self::monthsAgo($endsAgo),
            ]);

            $elapsed += $length;
        }

        return [$careerStart, $level];
    }

    /**
     * @param  array<string, mixed>  $kind
     * @param  array<string, mixed>  $where
     */
    private static function giveEducation(CandidateProfile $profile, array $kind, array $where, CarbonImmutable $careerStart): void
    {
        [$degree, $field] = self::pick($kind['study']);
        $finished = $careerStart->subMonths(random_int(1, 8))->startOfMonth();
        $length = match ($degree) {
            'Diploma', 'MSc', 'MBA' => random_int(1, 2),
            default => random_int(3, 4),
        };

        EducationRecord::factory()->for($profile)->create([
            'institution_name' => self::pick($where['universities']),
            'degree' => $degree,
            'field_of_study' => $field,
            'start_date' => $finished->subYears($length)->month(9),
            'end_date' => $finished,
        ]);
    }

    /**
     * A monthly range in the local currency around what their level of
     * work pays where they live.
     *
     * @param  array<string, mixed>  $kind
     * @param  array<string, mixed>  $where
     */
    private static function givePreference(CandidateProfile $profile, array $kind, array $where, int $level): void
    {
        $step = $where['pay'] >= 10 ? 1000 : 100;
        $monthly = $kind['pay'] * [0.75, 1.0, 1.3][$level] * $where['pay'] / 12;
        $round = fn (float $amount) => max($step, (int) (round($amount / $step) * $step));

        CandidatePreference::factory()->for($profile)->create([
            'desired_salary_min' => $round($monthly * 0.95),
            'desired_salary_max' => $round($monthly * 1.15),
            'desired_salary_currency' => $where['currency'],
            'preferred_workplace_type' => self::pick(WorkplaceType::cases()),
            'preferred_employment_type' => random_int(1, 6) === 1 ? EmploymentType::Contract : EmploymentType::FullTime,
            'is_actively_searching' => true,
            'available_from' => random_int(1, 3) === 1 ? CarbonImmutable::today()->addWeeks(random_int(2, 8)) : null,
        ]);
    }

    private static function cvRecord(CandidateProfile $profile, string $path, string $fileName, ?CarbonImmutable $at): Document
    {
        $document = Document::factory()->for($profile)->create([
            'document_type' => DocumentType::Cv,
            'file_path' => $path,
            'original_filename' => $fileName,
        ]);

        if ($at !== null) {
            $document->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        }

        return $document;
    }

    /**
     * An address made from the name, as most people's are, never one
     * already taken.
     */
    public static function email(string $name): string
    {
        $base = Str::slug($name, '.');
        $domains = ['example.com', 'example.org', 'example.net'];

        do {
            $email = $base.(isset(self::$emails[$base.'@']) ? random_int(2, 99) : '').'@'.$domains[array_rand($domains)];
        } while (isset(self::$emails[$email]) || User::query()->where('email', $email)->exists());

        self::$emails[$email] = true;
        self::$emails[$base.'@'] = true;

        return $email;
    }

    private static function monthsAgo(int $months): CarbonImmutable
    {
        return CarbonImmutable::today()->startOfMonth()->subMonths($months);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private static function pick(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
