<?php

namespace App\Actions;

use App\Enums\AvailabilityStatus;
use App\Enums\ModerationStatus;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Post another one like this" -- the common case when a company hires
 * two of the same role, or reruns last quarter's opening.
 *
 * The copy starts as an unpublished draft awaiting moderation, never
 * inheriting the original's approval: otherwise a posting could be
 * approved once and then quietly rewritten into something else through
 * repeated duplication.
 */
class DuplicateJobPosting
{
    public function __invoke(JobPosting $original, User $duplicatedBy): JobPosting
    {
        return DB::transaction(function () use ($original, $duplicatedBy) {
            $copy = $original->replicate([
                'slug', 'availability_status', 'moderation_status', 'published_at', 'submitted_at',
            ]);

            $copy->title = $original->title.' (copy)';
            $copy->slug = $this->availableSlug($copy->title);
            $copy->posted_by_id = $duplicatedBy->id;
            $copy->availability_status = AvailabilityStatus::Draft;
            $copy->moderation_status = ModerationStatus::Pending;
            $copy->published_at = null;
            $copy->submitted_at = null;
            $copy->expires_at = now()->addMonth();
            $copy->save();

            $copy->categories()->sync($original->categories->pluck('id')->all());

            $copy->skills()->sync(
                $original->skills
                    ->mapWithKeys(fn ($skill) => [$skill->id => ['importance' => $skill->pivot->importance]])
                    ->all()
            );

            $original->screeningQuestions->each(fn ($question) => $copy->screeningQuestions()->create([
                'question_text' => $question->question_text,
                'display_order' => $question->display_order,
            ]));

            return $copy;
        });
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
