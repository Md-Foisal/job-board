<?php

use App\Enums\EmploymentType;
use App\Enums\SalaryPeriod;
use App\Enums\WorkplaceType;
use App\Models\Company;
use App\Models\JobPosting;
use App\Services\JobPostingStructuredData;

function structuredDataFor(array $attributes = []): array
{
    $job = JobPosting::factory()->create($attributes);

    return app(JobPostingStructuredData::class)->toArray($job->fresh('company'));
}

test('it emits the fields Google for Jobs requires', function () {
    $company = Company::factory()->create(['name' => 'Acme Ltd']);
    $data = structuredDataFor([
        'company_id' => $company->id,
        'title' => 'Senior Laravel Developer',
    ]);

    expect($data['@context'])->toBe('https://schema.org');
    expect($data['@type'])->toBe('JobPosting');
    expect($data['title'])->toBe('Senior Laravel Developer');
    expect($data)->toHaveKeys(['description', 'datePosted', 'validThrough', 'hiringOrganization']);
    expect($data['hiringOrganization']['name'])->toBe('Acme Ltd');
});

test('employment type is translated into Google vocabulary, not our own', function () {
    $data = structuredDataFor(['employment_type' => EmploymentType::Contract]);

    // Our enum value is "contract"; Google's term is CONTRACTOR.
    expect($data['employmentType'])->toBe('CONTRACTOR');
});

test('a remote role says where applicants may live instead of giving an address', function () {
    $data = structuredDataFor([
        'workplace_type' => WorkplaceType::Remote,
        'location_country' => 'Bangladesh',
    ]);

    expect($data['jobLocationType'])->toBe('TELECOMMUTE');
    expect($data['applicantLocationRequirements']['name'])->toBe('Bangladesh');
    expect($data)->not->toHaveKey('jobLocation');
});

test('an on-site role carries a real address and no telecommute flag', function () {
    $data = structuredDataFor([
        'workplace_type' => WorkplaceType::Onsite,
        'location_city' => 'Dhaka',
        'location_country' => 'Bangladesh',
    ]);

    expect($data['jobLocation']['address']['addressLocality'])->toBe('Dhaka');
    expect($data['jobLocation']['address']['addressCountry'])->toBe('Bangladesh');
    expect($data)->not->toHaveKey('jobLocationType');
});

test('a stated salary is published as a range with its period', function () {
    $data = structuredDataFor([
        'salary_min' => 60000,
        'salary_max' => 90000,
        'salary_currency' => 'BDT',
        'salary_period' => SalaryPeriod::Monthly,
        'salary_negotiable' => false,
    ]);

    expect($data['baseSalary']['currency'])->toBe('BDT');
    expect($data['baseSalary']['value']['minValue'])->toBe(60000);
    expect($data['baseSalary']['value']['maxValue'])->toBe(90000);
    expect($data['baseSalary']['value']['unitText'])->toBe('MONTH');
});

test('a posting with no stated salary omits baseSalary entirely', function () {
    $data = structuredDataFor([
        'salary_min' => null,
        'salary_max' => null,
        'salary_currency' => null,
        'salary_period' => null,
        'salary_negotiable' => true,
    ]);

    expect($data)->not->toHaveKey('baseSalary');
});

test('the job detail page renders the JSON-LD script tag', function () {
    $job = JobPosting::factory()->create(['title' => 'Product Designer']);

    $response = $this->get(route('jobs.show', $job));

    $response->assertOk();
    $response->assertSee('application/ld+json', false);
    $response->assertSee('"@type":"JobPosting"', false);
});

test('a draft preview does not tell Google the job is live', function () {
    $job = JobPosting::factory()->draft()->create(['title' => 'Unpublished Role']);
    $member = employerUser($job->company);

    $response = $this->actingAs($member)->get(route('jobs.show', $job));

    $response->assertOk();
    // Asserted so this test cannot pass just because the page rendered
    // nothing useful -- which is exactly how it passed while the guard
    // was silently returning null for every posting.
    $response->assertSee('Unpublished Role');
    $response->assertDontSee('application/ld+json', false);
});

test('a description containing a closing script tag cannot break out of the tag', function () {
    $description = 'Great role. </script><script>alert(1)</script>';
    $job = JobPosting::factory()->create(['description' => $description]);

    $json = app(JobPostingStructuredData::class)->toJson($job);

    // Not a single raw angle bracket survives anywhere in the payload, so
    // nothing in it can close the <script> tag it gets embedded in. This
    // asserts the absence rather than the presence of a particular escape
    // sequence, so it cannot be weakened by a mistyped literal.
    expect($json)->not->toContain('<');
    expect($json)->not->toContain('>');

    // And the text is escaped, not discarded: decoding returns it intact.
    expect(json_decode($json, true)['description'])->toBe($description);
});
