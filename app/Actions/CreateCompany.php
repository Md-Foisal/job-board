<?php

namespace App\Actions;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creating a company and making its creator the owner are one act, not
 * two. Splitting them risks a company that exists with nobody able to
 * reach it -- unreachable by its creator and invisible to everyone else,
 * since every company-scoped page is gated on membership.
 */
class CreateCompany
{
    public function __invoke(User $owner, array $data): Company
    {
        return DB::transaction(function () use ($owner, $data) {
            $company = Company::create([
                'name' => $data['name'],
                'slug' => $this->availableSlug($data['name']),
                'identity_type' => $data['identity_type'],
            ]);

            $company->memberships()->create([
                'user_id' => $owner->id,
                'role' => MembershipRole::Owner,
                'status' => MembershipStatus::Active,
            ]);

            return $company;
        });
    }

    /**
     * Company names are not unique -- two unrelated "Bright Consulting"s
     * may both sign up -- but the slug is, because it is the public URL.
     * Numbering the later arrival is the ordinary web convention and
     * keeps the first one's links stable.
     */
    private function availableSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $suffix = 2;

        while (Company::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
