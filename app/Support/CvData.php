<?php

namespace App\Support;

use App\Enums\LanguageLevel;
use App\Enums\ProficiencyLevel;
use App\Models\Award;
use App\Models\CandidateLanguage;
use App\Models\CandidateProfile;
use App\Models\Certification;
use App\Models\CvVersion;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\VolunteerExperience;
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
 * - Projects: ongoing and newest first, each with its live and code
 *   links printed as text as well as linked.
 * - Certifications: newest first; one past its expiry says so.
 * - Contact: name, email, phone, location and links are all in the body
 *   of the page, since applicant tracking systems often skip headers and
 *   footers.
 *
 * - Languages: strongest first. Awards, volunteering and publications:
 *   newest first.
 *
 * A saved CV (CvVersion) can give its own headline and summary, leave
 * out single entries, and add what belongs on a CV only (CvExtras).
 * When it is aimed at a job, its projects are ordered by how many of
 * the job's skills each mentions, the closest first; roles keep their
 * dates' order, which recruiters read for.
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
     * The same, for the browser engine, which also has the shipped
     * Bengali fonts.
     */
    private const BROWSER_SCRIPTS = '/[^\p{Latin}\p{Greek}\p{Cyrillic}\p{Bengali}\p{Common}\p{Inherited}]/u';

    /**
     * @param  array<int, array{label: string, url: ?string, text: string}>  $links
     * @param  array<int, array{id: int, title: string, company: string, dates: string, description: ?string}>  $experience
     * @param  array<int, array{title: string, institution: string, dates: string}>  $education
     * @param  array<int, string>  $skills
     * @param  array<int, array{name: string, dates: ?string, description: ?string, links: array<int, array{label: string, url: ?string, text: string}>}>  $projects
     * @param  array<int, array{name: string, issuer: string, dates: ?string, credentialId: ?string, link: ?array{label: string, url: ?string, text: string}}>  $certifications
     * @param  array<int, array{name: string, level: LanguageLevel}>  $languages
     * @param  array<int, array{id: int, title: string, issuer: ?string, date: ?string, description: ?string}>  $awards
     * @param  array<int, array{id: int, role: string, organization: string, dates: ?string, description: ?string}>  $volunteering
     * @param  array<int, array{id: int, title: string, publisher: ?string, date: ?string, link: ?array{label: string, url: ?string, text: string}, description: ?string}>  $publications
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
        public readonly array $projects = [],
        public readonly array $certifications = [],
        public readonly array $languages = [],
        public readonly array $awards = [],
        public readonly array $volunteering = [],
        public readonly array $publications = [],
        public readonly CvExtras $extras = new CvExtras,
        public readonly ?string $qr = null,
        public readonly ?string $qrUrl = null,
    ) {}

    /**
     * @param  array<int, string>  $keywords  the skills of the job a CV is aimed at
     * @param  bool  $public  for the web page anyone with the link can open:
     *                        without contact details unless the CV allows them
     */
    public static function fromProfile(CandidateProfile $profile, ?CvVersion $version = null, array $keywords = [], bool $public = false): self
    {
        $user = $profile->user;
        $hidden = fn (string $kind) => $version?->hiddenIds($kind) ?? [];
        $visible = fn (string $kind) => fn ($record) => ! in_array($record->id, $hidden($kind), true);
        $withContact = ! $public || (bool) $version?->public_shows_contact;
        $extras = $version?->cvExtras() ?? new CvExtras;

        return new self(
            name: $user->name,
            email: $withContact ? $user->email : '',
            headline: self::filledOrNull($version?->headline) ?? self::filledOrNull($profile->headline),
            summary: self::filledOrNull($version?->summary) ?? self::filledOrNull($profile->bio),
            phone: $withContact ? self::filledOrNull($profile->phone) : null,
            location: $withContact ? self::filledOrNull($profile->location) : null,
            links: self::links($profile),
            avatarPath: $user->avatar,
            experience: $profile->experienceRecords()->get()
                ->filter($visible('experience'))
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
                ->filter($visible('education'))
                ->sortBy([
                    fn (EducationRecord $a, EducationRecord $b) => ($a->end_date !== null) <=> ($b->end_date !== null),
                    fn (EducationRecord $a, EducationRecord $b) => $b->end_date <=> $a->end_date,
                    fn (EducationRecord $a, EducationRecord $b) => $b->start_date <=> $a->start_date,
                ])
                ->map(fn (EducationRecord $record) => [
                    'id' => $record->id,
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
            projects: $profile->projects()->get()
                ->filter($visible('projects'))
                ->sortBy([
                    fn (Project $a, Project $b) => self::relevance($b, $keywords) <=> self::relevance($a, $keywords),
                    fn (Project $a, Project $b) => ($a->end_date !== null) <=> ($b->end_date !== null),
                    fn (Project $a, Project $b) => $b->end_date <=> $a->end_date,
                    fn (Project $a, Project $b) => $b->start_date <=> $a->start_date,
                    fn (Project $a, Project $b) => $b->id <=> $a->id,
                ])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'dates' => $project->start_date ? self::dates($project->start_date, $project->end_date) : null,
                    'description' => filled(strip_tags((string) $project->description)) ? $project->description : null,
                    'links' => collect(['Live' => $project->url, 'Code' => $project->source_url])
                        ->filter()
                        ->map(fn (string $url, string $label) => self::link($label, $url))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            certifications: $profile->certifications()->get()
                ->filter($visible('certifications'))
                ->sortBy([
                    fn (Certification $a, Certification $b) => $b->issued_on <=> $a->issued_on,
                    fn (Certification $a, Certification $b) => $b->id <=> $a->id,
                ])
                ->map(fn (Certification $certification) => [
                    'id' => $certification->id,
                    'name' => $certification->name,
                    'issuer' => $certification->issuer,
                    'dates' => self::certificationDates($certification),
                    'credentialId' => self::filledOrNull($certification->credential_id),
                    'link' => $certification->credential_url ? self::link(__('Credential'), $certification->credential_url) : null,
                ])
                ->values()
                ->all(),
            languages: $profile->languages()->get()
                ->sortBy([
                    fn (CandidateLanguage $a, CandidateLanguage $b) => $b->level->rank() <=> $a->level->rank(),
                    fn (CandidateLanguage $a, CandidateLanguage $b) => strnatcasecmp($a->name, $b->name),
                ])
                ->map(fn (CandidateLanguage $language) => ['name' => $language->name, 'level' => $language->level])
                ->values()
                ->all(),
            awards: $profile->awards()->newestFirst()->get()
                ->filter($visible('awards'))
                ->map(fn (Award $award) => [
                    'id' => $award->id,
                    'title' => $award->title,
                    'issuer' => self::filledOrNull($award->issuer),
                    'date' => $award->awarded_on?->format(DateFormat::MONTH),
                    'description' => self::filledOrNull($award->description),
                ])
                ->values()
                ->all(),
            volunteering: $profile->volunteerExperiences()->newestFirst()->get()
                ->filter($visible('volunteering'))
                ->map(fn (VolunteerExperience $role) => [
                    'id' => $role->id,
                    'role' => $role->role,
                    'organization' => $role->organization,
                    'dates' => $role->start_date ? self::dates($role->start_date, $role->end_date) : null,
                    'description' => self::filledOrNull($role->description),
                ])
                ->values()
                ->all(),
            publications: $profile->publications()->newestFirst()->get()
                ->filter($visible('publications'))
                ->map(fn (Publication $publication) => [
                    'id' => $publication->id,
                    'title' => $publication->title,
                    'publisher' => self::filledOrNull($publication->publisher),
                    'date' => $publication->published_on?->format(DateFormat::MONTH),
                    'link' => $publication->url ? self::link(__('Read'), $publication->url) : null,
                    'description' => self::filledOrNull($publication->description),
                ])
                ->values()
                ->all(),
            extras: $withContact ? $extras : $extras->withoutContactDetails(),
        );
    }

    /**
     * The same CV with a QR code pointing to its web page, printed at its
     * end.
     */
    public function withQr(string $image, string $url): self
    {
        return new self(
            ...[...get_object_vars($this), 'qr' => $image, 'qrUrl' => $url],
        );
    }

    /**
     * Whether any text on the CV is in Bengali script, so the Bengali
     * font has to go along with a PDF.
     */
    public function hasBengali(): bool
    {
        return preg_match('/\p{Bengali}/u', $this->plainText()) === 1;
    }

    /**
     * Every word on the CV as plain text, for checks that read it the
     * way a parser does: keyword coverage, the score.
     */
    public function plainText(): string
    {
        return collect([
            $this->name, $this->headline, $this->summary, $this->location,
            ...collect($this->experience)->flatMap(fn (array $role) => [$role['title'], $role['company'], strip_tags((string) $role['description'])]),
            ...collect($this->education)->flatMap(fn (array $course) => [$course['title'], $course['institution']]),
            ...$this->skills,
            ...collect($this->projects)->flatMap(fn (array $project) => [$project['name'], strip_tags((string) $project['description'])]),
            ...collect($this->certifications)->flatMap(fn (array $certification) => [$certification['name'], $certification['issuer']]),
            ...collect($this->languages)->pluck('name'),
            ...collect($this->awards)->flatMap(fn (array $award) => [$award['title'], $award['issuer'], $award['description']]),
            ...collect($this->volunteering)->flatMap(fn (array $role) => [$role['role'], $role['organization'], $role['description']]),
            ...collect($this->publications)->flatMap(fn (array $publication) => [$publication['title'], $publication['publisher'], $publication['description']]),
            $this->extras->interests,
            ...collect($this->extras->custom)->flatMap(fn (array $section) => [$section['title'], ...collect($section['items'])->flatMap(fn (array $item) => [$item['title'], $item['subtitle'], $item['description']])]),
            ...array_values($this->extras->personal),
        ])->filter(fn ($text) => filled($text))->implode("\n");
    }

    /**
     * How many of a job's skills a project mentions, in its name or
     * description, as whole words.
     *
     * @param  array<int, string>  $keywords
     */
    private static function relevance(Project $project, array $keywords): int
    {
        if ($keywords === []) {
            return 0;
        }

        $text = $project->name.' '.strip_tags((string) $project->description);

        return collect($keywords)->filter(fn (string $keyword) => SkillMatcher::mentions($keyword, $text))->count();
    }

    /**
     * A CV needs something beyond the contact lines to be worth building:
     * at least one role, course, skill, project or certification.
     */
    public function hasContent(): bool
    {
        return $this->experience !== [] || $this->education !== [] || $this->skills !== []
            || $this->projects !== [] || $this->certifications !== [] || $this->languages !== []
            || $this->awards !== [] || $this->volunteering !== [] || $this->publications !== [];
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
    public function unsupportedScriptParts(bool $browserEngine = false): array
    {
        $pattern = $browserEngine ? self::BROWSER_SCRIPTS : self::SUPPORTED_SCRIPTS;

        $parts = [
            'Name' => [$this->name],
            'Headline' => [$this->headline],
            'Location' => [$this->location],
            'Summary' => [$this->summary],
            'Experience' => collect($this->experience)->flatMap(fn (array $role) => [$role['title'], $role['company'], strip_tags((string) $role['description'])])->all(),
            'Education' => collect($this->education)->flatMap(fn (array $course) => [$course['title'], $course['institution']])->all(),
            'Skills' => $this->skills,
            'Projects' => collect($this->projects)->flatMap(fn (array $project) => [$project['name'], strip_tags((string) $project['description'])])->all(),
            'Certifications' => collect($this->certifications)->flatMap(fn (array $certification) => [$certification['name'], $certification['issuer']])->all(),
            'Languages' => collect($this->languages)->pluck('name')->all(),
            'Awards' => collect($this->awards)->flatMap(fn (array $award) => [$award['title'], $award['issuer'], $award['description']])->all(),
            'Volunteering' => collect($this->volunteering)->flatMap(fn (array $role) => [$role['role'], $role['organization'], $role['description']])->all(),
            'Publications' => collect($this->publications)->flatMap(fn (array $publication) => [$publication['title'], $publication['publisher'], $publication['description']])->all(),
            'Your own sections' => collect($this->extras->custom)->flatMap(fn (array $section) => [$section['title'], ...collect($section['items'])->flatMap(fn (array $item) => [$item['title'], $item['subtitle'], $item['description']])])->all(),
            'Interests' => [$this->extras->interests],
            'Personal information' => array_values($this->extras->personal),
        ];

        return collect($parts)
            ->filter(fn (array $texts) => collect($texts)->contains(fn (?string $text) => $text !== null && preg_match($pattern, $text) === 1))
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
            ->map(fn (string $url, string $label) => self::link($label, $url))
            ->values()
            ->all();
    }

    /**
     * @return array{label: string, url: ?string, text: string}
     */
    private static function link(string $label, string $url): array
    {
        return [
            'label' => $label,
            'url' => preg_match('#^https?://#i', $url) === 1 ? $url : null,
            // Printed as well as linked: a parser reading the text, and a
            // reader holding paper, both need the address itself.
            'text' => rtrim(preg_replace('#^https?://(www\.)?#i', '', $url) ?? $url, '/'),
        ];
    }

    /**
     * "Issued Mar 2024 · Expires Mar 2027", "Expired Jan 2025", or null
     * when neither date was given.
     */
    private static function certificationDates(Certification $certification): ?string
    {
        $parts = array_filter([
            $certification->issued_on ? __('Issued :date', ['date' => $certification->issued_on->format(DateFormat::MONTH)]) : null,
            match (true) {
                $certification->expires_on === null => null,
                $certification->hasExpired() => __('Expired :date', ['date' => $certification->expires_on->format(DateFormat::MONTH)]),
                default => __('Expires :date', ['date' => $certification->expires_on->format(DateFormat::MONTH)]),
            },
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private static function dates(?CarbonInterface $start, ?CarbonInterface $end): string
    {
        return ($start?->format(DateFormat::MONTH) ?? '').' – '.($end?->format(DateFormat::MONTH) ?? __('Present'));
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
