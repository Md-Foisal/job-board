<?php

namespace App\Services;

use App\Enums\EmploymentType;
use App\Enums\SalaryPeriod;
use App\Enums\WorkplaceType;
use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Support\Facades\Storage;

/**
 * schema.org JobPosting structured data (JSON-LD) for the job detail
 * page. This is what makes a posting eligible for Google for Jobs --
 * claude/12's research is why SEO counts as core here rather than a
 * later polish step, and claude/14 step 5 deliberately gives it no
 * route of its own (unlike sitemap.xml): it is emitted inside route
 * 3's own HTML.
 *
 * It lives in a class rather than the Blade view because the shape is
 * conditional -- remote roles describe where applicants may live,
 * on-site roles carry a real address; salary is omitted entirely when
 * the posting does not state one -- and that is logic worth testing,
 * not markup.
 */
class JobPostingStructuredData
{
    /**
     * Google's own employmentType vocabulary. Our enum values are our
     * naming; these strings are theirs, so the mapping is explicit.
     */
    private const EMPLOYMENT_TYPES = [
        EmploymentType::FullTime->value => 'FULL_TIME',
        EmploymentType::PartTime->value => 'PART_TIME',
        EmploymentType::Contract->value => 'CONTRACTOR',
        EmploymentType::Internship->value => 'INTERN',
    ];

    /** schema.org QuantitativeValue unitText, keyed by our salary period. */
    private const SALARY_UNITS = [
        SalaryPeriod::Hourly->value => 'HOUR',
        SalaryPeriod::Weekly->value => 'WEEK',
        SalaryPeriod::Monthly->value => 'MONTH',
        SalaryPeriod::Yearly->value => 'YEAR',
    ];

    /**
     * Encoded for embedding directly inside a <script> tag. The HEX_*
     * flags are the reason this is a method and not a json_encode() in
     * the view: a description containing "</script>" would otherwise
     * break out of the tag, which is an XSS hole, not a typo.
     */
    public function toJson(JobPosting $jobPosting): string
    {
        return json_encode(
            $this->toArray($jobPosting),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    public function toArray(JobPosting $jobPosting): array
    {
        $company = $jobPosting->company;

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $jobPosting->title,
            'description' => $jobPosting->description,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => $company->name,
                'value' => (string) $jobPosting->id,
            ],
            // published_at is null until a posting goes live, and a draft
            // never reaches this class (the view only emits it for a
            // publicly visible posting) -- the fallback is so a
            // half-migrated row degrades instead of throwing.
            'datePosted' => ($jobPosting->published_at ?? $jobPosting->created_at)->toDateString(),
            'validThrough' => $jobPosting->expires_at->toAtomString(),
            'directApply' => true,
            'hiringOrganization' => $this->organization($company),
        ];

        if (isset(self::EMPLOYMENT_TYPES[$jobPosting->employment_type->value])) {
            $data['employmentType'] = self::EMPLOYMENT_TYPES[$jobPosting->employment_type->value];
        }

        $data += $this->location($jobPosting);

        if ($salary = $this->baseSalary($jobPosting)) {
            $data['baseSalary'] = $salary;
        }

        if ($jobPosting->min_experience_years) {
            $data['experienceRequirements'] = [
                '@type' => 'OccupationalExperienceRequirements',
                'monthsOfExperience' => $jobPosting->min_experience_years * 12,
            ];
        }

        return $data;
    }

    private function organization(Company $company): array
    {
        $organization = [
            '@type' => 'Organization',
            'name' => $company->name,
        ];

        if ($company->website_url) {
            $organization['sameAs'] = $company->website_url;
        }

        if ($company->logo_path) {
            // Absolute: Storage::url() can return a root-relative path,
            // and crawlers need a resolvable URL.
            $organization['logo'] = url(Storage::url($company->logo_path));
        }

        return $organization;
    }

    /**
     * Google treats a fully remote role differently from an on-site
     * one: TELECOMMUTE plus where applicants may live, instead of a
     * physical address. Hybrid keeps the address -- there is a real
     * office to show up at. When we do not know the country we say
     * nothing rather than guess one: invented data in structured
     * markup is worse than a missing recommended field.
     */
    private function location(JobPosting $jobPosting): array
    {
        if ($jobPosting->workplace_type === WorkplaceType::Remote) {
            $remote = ['jobLocationType' => 'TELECOMMUTE'];

            if ($jobPosting->location_country) {
                $remote['applicantLocationRequirements'] = [
                    '@type' => 'Country',
                    'name' => $jobPosting->location_country,
                ];
            }

            return $remote;
        }

        $address = ['@type' => 'PostalAddress'];

        if ($jobPosting->location_city) {
            $address['addressLocality'] = $jobPosting->location_city;
        }

        if ($jobPosting->location_country) {
            $address['addressCountry'] = $jobPosting->location_country;
        }

        return [
            'jobLocation' => [
                '@type' => 'Place',
                'address' => $address,
            ],
        ];
    }

    /**
     * Omitted entirely unless the posting states a real figure -- the
     * salary-transparency promise is to publish what is known, not to
     * publish an empty shell that reads as "salary: nothing".
     */
    private function baseSalary(JobPosting $jobPosting): ?array
    {
        if (! $jobPosting->salary_currency || ! $jobPosting->salary_period) {
            return null;
        }

        if ($jobPosting->salary_min === null && $jobPosting->salary_max === null) {
            return null;
        }

        $value = [
            '@type' => 'QuantitativeValue',
            'unitText' => self::SALARY_UNITS[$jobPosting->salary_period->value] ?? 'MONTH',
        ];

        if ($jobPosting->salary_min !== null) {
            $value['minValue'] = $jobPosting->salary_min;
        }

        if ($jobPosting->salary_max !== null) {
            $value['maxValue'] = $jobPosting->salary_max;
        }

        return [
            '@type' => 'MonetaryAmount',
            'currency' => $jobPosting->salary_currency,
            'value' => $value,
        ];
    }
}
