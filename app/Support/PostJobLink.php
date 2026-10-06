<?php

namespace App\Support;

use App\Models\User;

/**
 * Where "Post a job" takes this visitor: an employer account for a guest,
 * the posting form for someone who can already post for a company, and
 * company setup for anyone else.
 */
final class PostJobLink
{
    public static function for(?User $user): string
    {
        $company = $user?->activeCompanies->first(fn ($company) => $user->canManage($company));

        return match (true) {
            $user === null => route('register', ['as' => 'employer']),
            $company !== null => route('employer.jobs.create', $company),
            default => route('companies.create'),
        };
    }
}
