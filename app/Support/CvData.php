<?php

namespace App\Support;

use App\Enums\ProficiencyLevel;
use App\Models\CandidateProfile;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\Skill;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Everything a CV built from a profile shows, already in the order and
 * wording the page prints, so the preview and the PDF read the same data
 * and the template only lays it out.
 *
 * - Roles: ongoing ones first, then by end date and start date, newest
 *   first -- the reverse-chronological order recruiters expect.
 * - Education: "BSc in CSE" and the institution, leaving out whatever
 *   part is blank.
 * - Skills: names only, strongest first, then by name. A self-assessed
 *   level cannot be checked by a reader, so the order carries it instead
 *   of a label.
 * - Contact: name, email, phone, location and links are all in the body
 *   of the page, since applicant tracking systems often skip headers and
 *   footers.
 *
 * Salary and work-type preferences are never part of it: they are not
 * shown to employers anywhere else either.
 */
final class CvData
{
    /**
     * Scripts the bundled DejaVu fonts draw correctly. Text in any other
     * script -- Bengali, Arabic, Hebrew, Devanagari, Thai, Chinese -- needs
     * shaping or right-to-left layout that DOMPDF cannot do, or glyphs the
     * font does not have.
     */
    private const SUPPORTED_SCRIPTS = '/[^\p{Latin}\p{Greek}\p{Cyrillic}\p{Common}\p{Inherited}]/u';

    /**
     * @param  array<int, array{label: string, url: ?string, text: string}>  $links
     * @param  array<int, array{id: int, title: string, company: string, dates: string, description: ?string}>  $experience
     * @param  array<int, array{title: string, institution: string, dates: string}>  $education
     * @param  array<int, string>  $skills
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $headline,
        public readonly ?string $summary,
        public readonly ?string $phone,
        public readonly ?string $location,
        public readonly array $links,
        public readonly ?string $avatarPath,
        public readonly array $experience,
        public readonly array $education,
        public readonly array $skills,
    ) {}

    public static function fromProfile(CandidateProfile $profile): self
    {
        $user = $profile->user;

        return new self(
            name: $user->name,
            email: $user->email,
            headline: self::filledOrNull($profile->headline),
            summary: self::filledOrNull($profile->bio),
            phone: self::filledOrNull($profile->phone),
            location: self::filledOrNull($profile->location),
            links: self::links($profile),
            avatarPath: $user->avatar,
            experience: $profile->experienceRecords()->get()
                ->sortBy([
                    fn (ExperienceRecord $a, ExperienceRecord $b) => ($a->end_date !== null) <=> ($b->end_date !== null),
                    fn (ExperienceRecord $a, ExperienceRecord $b) => $b->end_date <=> $a->end_date,
                    fn (ExperienceRecord $a, ExperienceRecord $b) => $b->start_date <=> $a->start_date,
                ])
                ->map(fn (ExperienceRecord $record) => [
                    'id' => $record->id,
                    'title' => $record->job_title,
                    'company' => $record->company_name,
                    'dates' => self::dates($record->start_date, $record->end_date),
                    'description' => filled(strip_tags((string) $record->description)) ? $record->description : null,
                ])
                ->values()
                ->all(),
            education: $profile->educationRecords()->get()
                ->sortBy([
                    fn (EducationRecord $a, EducationRecord $b) => ($a->end_date !== null) <=> ($b->end_date !== null),
                    fn (EducationRecord $a, EducationRecord $b) => $b->end_date <=> $a->end_date,
                    fn (EducationRecord $a, EducationRecord $b) => $b->start_date <=> $a->start_date,
                ])
                ->map(fn (EducationRecord $record) => [
                    'title' => self::qualification($record->degree, $record->field_of_study),
                    'institution' => $record->institution_name,
                    'dates' => self::dates($record->start_date, $record->end_date),
                ])
                ->values()
                ->all(),
            skills: $profile->skills()->get()
                ->sortBy([
                    fn (Skill $a, Skill $b) => self::strength($b->pivot->proficiency) <=> self::strength($a->pivot->proficiency),
                    fn (Skill $a, Skill $b) => strnatcasecmp($a->name, $b->name),
                ])
                ->pluck('name')
                ->values()
                ->all(),
        );
    }

    /**
     * A CV needs something beyond the contact lines to be worth building:
     * at least one role, course or skill.
     */
    public function hasContent(): bool
    {
        return $this->experience !== [] || $this->education !== [] || $this->skills !== [];
    }

    /**
     * "Karim-Rahman-CV.pdf", with accents dropped ("José" becomes "Jose").
     * A name in another script becomes plain "CV.pdf": spelling it in
     * Latin letters is a transliteration only the person can choose, and
     * an automatic one turns "করিম" into "Krim".
     */
    public function fileName(): string
    {
        $slug = preg_match(self::SUPPORTED_SCRIPTS, $this->name) === 1 ? '' : Str::slug($this->name);

        return $slug === '' ? 'CV.pdf' : str_replace(' ', '-', Str::headline($slug)).'-CV.pdf';
    }

    /**
     * The profile photo as a data URI, so the PDF renderer never has to
     * fetch anything: DOMPDF keeps remote and local file access off, and
     * the template stays the same for the preview. Null when there is no
     * photo, or its file is missing.
     */
    public function photo(): ?string
    {
        $disk = Storage::disk('public');

        if ($this->avatarPath === null || ! $disk->exists($this->avatarPath)) {
            return null;
        }

        $type = $disk->mimeType($this->avatarPath);

        if (! in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$type.';base64,'.base64_encode($disk->get($this->avatarPath));
    }

    /**
     * The parts of the CV holding text the PDF cannot draw correctly, in
     * the order they appear on the page.
     *
     * @return array<int, string>
     */
    public function unsupportedScriptParts(): array
    {
        $parts = [
            'Name' => [$this->name],
            'Headline' => [$this->headline],
            'Location' => [$this->location],
            'Summary' => [$this->summary],
            'Experience' => collect($this->experience)->flatMap(fn (array $role) => [$role['title'], $role['company'], strip_tags((string) $role['description'])])->all(),
            'Education' => collect($this->education)->flatMap(fn (array $course) => [$course['title'], $course['institution']])->all(),
            'Skills' => $this->skills,
        ];

        return collect($parts)
            ->filter(fn (array $texts) => collect($texts)->contains(fn (?string $text) => $text !== null && preg_match(self::SUPPORTED_SCRIPTS, $text) === 1))
            ->keys()
            ->all();
    }

    /**
     * Only a web address becomes a link. The profile form accepts nothing
     * else, but a link is followed by whoever opens the CV, so one written
     * by any other path is printed as text and never made clickable.
     *
     * @return array<int, array{label: string, url: ?string, text: string}>
     */
    private static function links(CandidateProfile $profile): array
    {
        return collect([
            'LinkedIn' => $profile->linkedin_url,
            'GitHub' => $profile->github_url,
            'Portfolio' => $profile->portfolio_url,
        ])
            ->filter()
            ->map(fn (string $url, string $label) => [
                'label' => $label,
                'url' => preg_match('#^https?://#i', $url) === 1 ? $url : null,
                // Printed as well as linked: a parser reading the text,
                // and a reader holding paper, both need the address itself.
                'text' => rtrim(preg_replace('#^https?://(www\.)?#i', '', $url) ?? $url, '/'),
            ])
            ->values()
            ->all();
    }

    private static function dates(?CarbonInterface $start, ?CarbonInterface $end): string
    {
        return ($start?->format('M Y') ?? '').' – '.($end?->format('M Y') ?? __('Present'));
    }

    private static function qualification(?string $degree, ?string $field): string
    {
        $degree = self::filledOrNull($degree);
        $field = self::filledOrNull($field);

        return match (true) {
            $degree !== null && $field !== null => __(':degree in :field', ['degree' => $degree, 'field' => $field]),
            default => $degree ?? $field ?? '',
        };
    }

    private static function strength(mixed $proficiency): int
    {
        return match ($proficiency instanceof ProficiencyLevel ? $proficiency : ProficiencyLevel::tryFrom((string) $proficiency)) {
            ProficiencyLevel::Advanced => 3,
            ProficiencyLevel::Intermediate => 2,
            ProficiencyLevel::Beginner => 1,
            default => 0,
        };
    }

    private static function filledOrNull(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }
}
