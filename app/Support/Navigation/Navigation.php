<?php

namespace App\Support\Navigation;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;

/**
 * The pages of each workspace, in sidebar order. The sidebar draws them
 * and the command palette searches them, so the two cannot drift apart:
 * a page added here appears in both, and a page someone may not open is
 * left out of both by the same check.
 */
final class Navigation
{
    /**
     * The person's own side: the candidate pages when they have a
     * candidate profile, and their account in every case.
     *
     * @return list<NavSection>
     */
    public static function personal(User $user): array
    {
        $sections = [
            new NavSection(null, [
                // Straight to the candidate dashboard when that is where
                // /dashboard would send them anyway: one less redirect.
                new NavItem(
                    __('Dashboard'),
                    'home',
                    $user->isCandidate() ? route('candidate.dashboard') : route('dashboard'),
                    ['dashboard', 'candidate.dashboard'],
                ),
            ]),
        ];

        if ($user->isCandidate()) {
            $sections[] = new NavSection(__('Job search'), [
                new NavItem(__('Find jobs'), 'magnifying-glass', route('jobs.index')),
                new NavItem(__('Applications'), 'paper-airplane', route('candidate.applications.index'), ['candidate.applications.*']),
                new NavItem(__('Saved jobs'), 'bookmark', route('candidate.saved-jobs.index'), ['candidate.saved-jobs.*']),
                new NavItem(__('Job alerts'), 'bell', route('candidate.job-alerts.index'), ['candidate.job-alerts.*']),
            ]);

            // Experience, Education and Skills are parts of the profile and
            // are edited on it, so they light up My profile rather than
            // standing beside it. Documents and the CV builder are the CV
            // library; preferences are private. Neither is the profile.
            $sections[] = new NavSection(__('Profile'), [
                new NavItem(__('My profile'), 'user-circle', route('candidate.profile.edit'), [
                    'candidate.profile.*', 'candidate.resume-import',
                    'candidate.experience.*', 'candidate.education.*', 'candidate.skills.*',
                ]),
                new NavItem(__('Documents'), 'document-text', route('candidate.documents.index'), ['candidate.documents.*']),
                new NavItem(__('CV builder'), 'document-plus', route('candidate.cv-builder'), ['candidate.cv-builder']),
                new NavItem(__('Job preferences'), 'adjustments-horizontal', route('candidate.preferences.edit'), ['candidate.preferences.*']),
            ]);
        }

        $sections[] = new NavSection(__('Account'), [
            new NavItem(__('Settings'), 'cog-6-tooth', route('profile.edit'), ['profile.edit', 'security.edit', 'appearance.edit']),
        ]);

        return $sections;
    }

    /**
     * One company's workspace, daily work first and housekeeping after.
     * Applications are not listed: they belong to a job posting and are
     * reached from it.
     *
     * The Reviews count is a query, and only the sidebar shows it, so the
     * palette asks for the pages without it.
     *
     * @return list<NavSection>
     */
    public static function company(User $user, Company $company, bool $withCounts = true): array
    {
        // The count is for those who can answer: to a plain member it
        // would be a to-do they cannot do.
        $reviewsAwaiting = $withCounts && $user->canManage($company)
            ? $company->reviews()->published()->awaitingResponse()->count()
            : 0;

        $sections = [
            new NavSection(null, [
                new NavItem(__('Dashboard'), 'home', route('employer.dashboard', $company), ['employer.dashboard']),
            ]),
            new NavSection(__('Hiring'), [
                new NavItem(__('Job postings'), 'briefcase', route('employer.jobs.index', $company), ['employer.jobs.*', 'employer.applications.*']),
                new NavItem(__('Analytics'), 'chart-bar', route('employer.analytics', $company), ['employer.analytics']),
                new NavItem(__('Reviews'), 'chat-bubble-left-right', route('employer.reviews', $company), ['employer.reviews'], $reviewsAwaiting > 0 ? $reviewsAwaiting : null),
            ]),
        ];

        if ($user->can('update', $company)) {
            $sections[] = new NavSection(__('Company'), [
                new NavItem(__('Company profile'), 'building-office', route('employer.company.edit', $company), ['employer.company.*']),
                new NavItem(__('Team'), 'users', route('employer.team.index', $company), ['employer.team.*']),
            ]);
        }

        // Apart from the Company section on purpose: these are the
        // person's own, not the company's, and every member has them
        // regardless of rank. The recruiter page takes the workspace it
        // was opened from along, so it keeps this company in view.
        $sections[] = new NavSection(__('You'), [
            new NavItem(__('Recruiter profile'), 'identification', route('employer.recruiter-profile.edit', ['company' => $company->slug]), ['employer.recruiter-profile.*']),
            new NavItem(__('Settings'), 'cog-6-tooth', route('profile.edit')),
        ]);

        return $sections;
    }

    /**
     * The one action an employer comes to do most, offered beside the
     * pages to those whose role lets them post.
     */
    public static function postJob(User $user, Company $company): ?NavItem
    {
        return $user->can('create', [JobPosting::class, $company])
            ? new NavItem(__('Post a job'), 'plus', route('employer.jobs.create', $company))
            : null;
    }
}
