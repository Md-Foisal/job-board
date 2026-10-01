<?php

namespace App\Support;

use App\Enums\CvGap;

/**
 * What a CV built from the profile is missing, in the order the parts
 * appear on the page. Runs on our own server only, for every plan.
 *
 * Profile links are not checked: whether a LinkedIn, GitHub or portfolio
 * link belongs on a CV depends on the line of work, so a missing one is
 * not a gap for everybody.
 */
final class CvChecks
{
    /**
     * @return array<int, array{gap: CvGap, subject: ?string}>
     */
    public static function for(CvData $cv): array
    {
        $gaps = [];

        $add = function (CvGap $gap, ?string $subject = null) use (&$gaps) {
            $gaps[] = ['gap' => $gap, 'subject' => $subject];
        };

        if ($cv->headline === null) {
            $add(CvGap::Headline);
        }

        if ($cv->phone === null) {
            $add(CvGap::Phone);
        }

        if ($cv->location === null) {
            $add(CvGap::Location);
        }

        if ($cv->summary === null) {
            $add(CvGap::Summary);
        }

        if ($cv->experience === []) {
            $add(CvGap::Experience);
        }

        foreach ($cv->experience as $role) {
            if ($role['description'] === null) {
                $add(CvGap::RoleDescription, __(':title at :company', ['title' => $role['title'], 'company' => $role['company']]));
            }
        }

        if ($cv->education === []) {
            $add(CvGap::Education);
        }

        if ($cv->skills === []) {
            $add(CvGap::Skills);
        }

        return $gaps;
    }
}
