<?php

namespace App\Actions;

use App\Enums\AvailabilityStatus;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writing a job posting touches four tables at once -- the posting, its
 * categories, its skills with how badly each is wanted, and the screening
 * questions -- and a posting saved without its skills is not half saved,
 * it is wrong: the match score every candidate sees would be computed
 * against nothing.
 *
 * Creating and editing share this one path because they differ in almost
 * nothing. What they do not share is the slug: it is the public address
 * of the posting, so it is chosen once and then left alone even if the
 * title is rewritten.
 */
class SaveJobPosting
{
    public function __invoke(
        Company $company,
        User $postedBy,
        array $data,
        ?JobPosting $jobPosting = null,
    ): JobPosting {
        return DB::transaction(function () use ($company, $postedBy, $data, $jobPosting) {
            $attributes = collect($data)->only([
                'title', 'description', 'employment_type', 'workplace_type',
                'location_city', 'location_country', 'min_experience_years',
                'salary_min', 'salary_max', 'salary_currency', 'salary_period',
                'salary_negotiable', 'expires_at',
            ])->all();

            if ($jobPosting === null) {
                $jobPosting = new JobPosting($attributes);
                $jobPosting->company_id = $company->id;
                $jobPosting->posted_by_id = $postedBy->id;
                $jobPosting->slug = $this->availableSlug($data['title']);
            } else {
                $jobPosting->fill($attributes);
            }

            // Publishing is a state change the form asks for, not a column
            // the form fills in -- which is why neither status is
            // mass-assignable. A posting that goes live still has to clear
            // moderation; "published" here means the company is done with
            // it, not that the public can see it.
            $publish = (bool) ($data['publish'] ?? false);

            $jobPosting->availability_status = $publish
                ? AvailabilityStatus::Active
                : AvailabilityStatus::Draft;

            if ($publish && $jobPosting->published_at === null) {
                $jobPosting->published_at = now();
            }

            $jobPosting->save();

            $jobPosting->categories()->sync($data['categories'] ?? []);
            $jobPosting->skills()->sync($this->skillPivot($data['skills'] ?? []));

            $this->syncScreeningQuestions($jobPosting, $data['screening_questions'] ?? []);

            return $jobPosting;
        });
    }

    /**
     * @param  array<int|string, string>  $skills  skill id => importance
     * @return array<int, array{importance: string}>
     */
    private function skillPivot(array $skills): array
    {
        return collect($skills)
            ->mapWithKeys(fn ($importance, $skillId) => [(int) $skillId => ['importance' => $importance]])
            ->all();
    }

    /**
     * Questions are replaced wholesale rather than matched up row by row.
     * Their only identity is their position in the list, and an answer
     * already given is a snapshot on the application, so rewriting the
     * questions never rewrites what anyone answered.
     */
    private function syncScreeningQuestions(JobPosting $jobPosting, array $questions): void
    {
        $jobPosting->screeningQuestions()->delete();

        collect($questions)
            ->map(fn ($text) => trim((string) $text))
            ->filter()
            ->values()
            ->each(fn ($text, $index) => $jobPosting->screeningQuestions()->create([
                'question_text' => $text,
                'display_order' => $index,
            ]));
    }

    private function availableSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'job';
        $slug = $base;
        $suffix = 2;

        while (JobPosting::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
