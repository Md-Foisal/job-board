<?php

namespace App\Models;

use App\Support\CvDesign;
use App\Support\CvExtras;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One saved CV of a candidate: a recipe drawn from the profile, never a
 * file (see the migration). The PDFs built from it go to the document
 * library like any CV.
 */
#[Fillable([
    'candidate_profile_id', 'name', 'design', 'extras', 'hidden_entries', 'headline', 'summary',
    'job_posting_id', 'job_description', 'public_slug', 'published_at', 'public_expires_at', 'public_shows_contact',
])]
class CvVersion extends Model
{
    use HasFactory;

    /**
     * Enough for a CV per kind of job applied for, few enough that the
     * list stays a list.
     */
    public const MAX_PER_PROFILE = 10;

    /**
     * The parts of the profile whose entries a CV can leave out, by the
     * key used in hidden_entries.
     */
    public const ENTRY_KINDS = ['experience', 'projects', 'education', 'certifications', 'awards', 'volunteering', 'publications'];

    protected function casts(): array
    {
        return [
            'design' => 'array',
            'extras' => 'array',
            'hidden_entries' => 'array',
            'published_at' => 'datetime',
            'public_expires_at' => 'datetime',
            'public_shows_contact' => 'boolean',
        ];
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function cvDesign(): CvDesign
    {
        return CvDesign::fromArray($this->design ?? []);
    }

    public function cvExtras(): CvExtras
    {
        return CvExtras::fromArray($this->extras);
    }

    /**
     * The ids left out of this CV for one kind of entry.
     *
     * @return list<int>
     */
    public function hiddenIds(string $kind): array
    {
        return array_values(array_map('intval', (array) ($this->hidden_entries[$kind] ?? [])));
    }

    /**
     * Shared as a web page right now: switched on, and not past the date
     * the candidate set for it to stop.
     */
    public function isPublic(): bool
    {
        return $this->public_slug !== null
            && $this->published_at !== null
            && ($this->public_expires_at === null || $this->public_expires_at->isFuture());
    }

    public function publicUrl(): ?string
    {
        return $this->public_slug === null ? null : route('cv.public', $this->public_slug);
    }

    /**
     * A new address that cannot be guessed from the old one or the name.
     */
    public static function newSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(12));
        } while (self::query()->where('public_slug', $slug)->exists());

        return $slug;
    }
}
