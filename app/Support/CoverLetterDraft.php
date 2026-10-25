<?php

namespace App\Support;

use App\Models\CandidateProfile;
use App\Models\JobPosting;
use App\Models\Project;

/**
 * A first draft of a cover letter from the candidate's own profile and
 * the job, by our own rules, with no AI: who is writing and for what,
 * the job's skills they list, one piece of their own work that touches
 * the most of those skills, and a close. Every sentence is built from
 * what the candidate wrote, so it claims nothing they did not; it is a
 * starting point to put in their own words.
 */
final class CoverLetterDraft
{
    public static function write(CandidateProfile $profile, ?JobPosting $job, ?string $company, ?string $role, ?string $recipient): string
    {
        $company = filled($company) ? trim($company) : ($job?->company?->name);
        $role = filled($role) ? trim($role) : $job?->title;
        $name = $profile->user->name;

        $skills = $profile->skills()->pluck('name')->all();
        $keywords = $job ? collect($job->skills()->pluck('name'))->merge(SkillMatcher::inText(strip_tags((string) $job->description))->pluck('name'))->unique()->values()->all() : [];
        $shared = array_values(array_filter($skills, fn (string $skill) => in_array(mb_strtolower($skill), array_map('mb_strtolower', $keywords), true)));

        $paragraphs = [];
        $paragraphs[] = filled($recipient) ? __('Dear :name,', ['name' => trim($recipient)]) : __('Dear Hiring Team,');

        $opening = match (true) {
            filled($role) && filled($company) => __('I am writing to apply for the :role position at :company.', ['role' => $role, 'company' => $company]),
            filled($role) => __('I am writing to apply for the :role position.', ['role' => $role]),
            filled($company) => __('I am writing to apply for a position at :company.', ['company' => $company]),
            default => __('I am writing to apply for this position.'),
        };

        if (filled($profile->headline)) {
            $opening .= ' '.__('I am a :headline.', ['headline' => self::lowerFirst(trim($profile->headline))]);
        }

        $paragraphs[] = $opening;

        if ($shared !== []) {
            $paragraphs[] = __('In my work I use :skills, which this role asks for.', ['skills' => self::listOf(array_slice($shared, 0, 5))]);
        } elseif ($skills !== []) {
            $paragraphs[] = __('In my work I use :skills.', ['skills' => self::listOf(array_slice($skills, 0, 5))]);
        }

        if ($proof = self::proof($profile, $keywords)) {
            $paragraphs[] = $proof;
        }

        $paragraphs[] = filled($company)
            ? __('I would welcome the chance to talk about how I can help :company. Thank you for your time.', ['company' => $company])
            : __('I would welcome the chance to talk about how I can help. Thank you for your time.');

        $paragraphs[] = __('Sincerely,')."\n".$name;

        return implode("\n\n", $paragraphs);
    }

    /**
     * One sentence about the candidate's own work: the role or project
     * that mentions the most of the job's skills, or the latest role.
     *
     * @param  array<int, string>  $keywords
     */
    private static function proof(CandidateProfile $profile, array $keywords): ?string
    {
        $hits = fn (string $text) => collect($keywords)->filter(fn (string $keyword) => SkillMatcher::mentions($keyword, $text))->count();

        $role = $profile->experienceRecords()->orderByRaw('end_date is not null')->orderByDesc('start_date')->get()
            ->filter(fn ($record) => filled(strip_tags((string) $record->description)))
            ->sortByDesc(fn ($record) => $hits($record->job_title.' '.strip_tags((string) $record->description)))
            ->first();

        $project = $profile->projects()->newestFirst()->get()
            ->filter(fn (Project $project) => filled(strip_tags((string) $project->description)))
            ->sortByDesc(fn (Project $project) => $hits($project->name.' '.strip_tags((string) $project->description)))
            ->first();

        $roleHits = $role ? $hits($role->job_title.' '.strip_tags((string) $role->description)) : -1;
        $projectHits = $project ? $hits($project->name.' '.strip_tags((string) $project->description)) : -1;

        if ($role && $roleHits >= $projectHits) {
            return __('At :company, as :title, I :did.', [
                'company' => $role->company_name,
                'title' => $role->job_title,
                'did' => self::firstPoint((string) $role->description),
            ]);
        }

        if ($project) {
            return __('In :project, :did.', [
                'project' => $project->name,
                'did' => self::lowerFirst(self::firstPoint((string) $project->description)),
            ]);
        }

        return null;
    }

    /**
     * The first bullet point of a description, or its first sentence,
     * without its full stop and with a lower-case first word unless it is
     * an acronym ("API", "AWS").
     */
    private static function firstPoint(string $html): string
    {
        preg_match('#<li[^>]*>(.*?)</li>#is', $html, $item);
        $text = trim(html_entity_decode(strip_tags($item[1] ?? $html)));
        $text = preg_split('/(?<=[.!?])\s+/u', $text)[0] ?? $text;

        return self::lowerFirst(rtrim($text, " .!?\t\n"));
    }

    private static function lowerFirst(string $text): string
    {
        $words = explode(' ', $text, 2);

        if (mb_strtoupper($words[0]) === $words[0]) {
            return $text;
        }

        return mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /**
     * @param  array<int, string>  $items
     */
    private static function listOf(array $items): string
    {
        return count($items) <= 1
            ? implode('', $items)
            : implode(', ', array_slice($items, 0, -1)).' '.__('and').' '.end($items);
    }
}
