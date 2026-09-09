<?php

use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use App\Models\Category;
use App\Models\JobPosting;
use App\Enums\SalaryPeriod;
use App\Models\Skill;

test('skill filters postings that require the given skill', function () {
    $laravel = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $vue = Skill::create(['name' => 'Vue', 'slug' => 'vue']);

    $withLaravel = JobPosting::factory()->create();
    $withLaravel->skills()->attach($laravel);

    $withVue = JobPosting::factory()->create();
    $withVue->skills()->attach($vue);

    $result = JobPosting::query()->skill($laravel->id)->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($withLaravel->id);
});

test('category filters postings within the given category', function () {
    $backend = Category::create(['name' => 'Backend', 'slug' => 'backend']);
    $design = Category::create(['name' => 'Design', 'slug' => 'design']);

    $backendJob = JobPosting::factory()->create();
    $backendJob->categories()->attach($backend);

    $designJob = JobPosting::factory()->create();
    $designJob->categories()->attach($design);

    $result = JobPosting::query()->category($backend->id)->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($backendJob->id);
});

test('salaryBetween returns only postings whose range overlaps the given range', function () {
    $cheap = JobPosting::factory()->create(['salary_min' => 20000, 'salary_max' => 30000, 'salary_negotiable' => false, 'salary_period' => SalaryPeriod::Monthly]);
    $mid = JobPosting::factory()->create(['salary_min' => 50000, 'salary_max' => 70000, 'salary_negotiable' => false, 'salary_period' => SalaryPeriod::Monthly]);
    $expensive = JobPosting::factory()->create(['salary_min' => 100000, 'salary_max' => 150000, 'salary_negotiable' => false, 'salary_period' => SalaryPeriod::Monthly]);

    $result = JobPosting::query()->salaryBetween(40000, 80000)->get();

    expect($result->pluck('id'))->toContain($mid->id)
        ->and($result->pluck('id'))->not->toContain($cheap->id)
        ->and($result->pluck('id'))->not->toContain($expensive->id);
});

test('location filters by partial city match', function () {
    $dhaka = JobPosting::factory()->create(['location_city' => 'Dhaka']);
    $ctg = JobPosting::factory()->create(['location_city' => 'Chattogram']);

    $result = JobPosting::query()->location('Dha')->get();

    expect($result->pluck('id'))->toContain($dhaka->id)
        ->and($result->pluck('id'))->not->toContain($ctg->id);
});

test('workplaceType filters by remote/onsite/hybrid', function () {
    $remote = JobPosting::factory()->create(['workplace_type' => WorkplaceType::Remote]);
    $onsite = JobPosting::factory()->create(['workplace_type' => WorkplaceType::Onsite]);

    $result = JobPosting::query()->workplaceType(WorkplaceType::Remote)->get();

    expect($result->pluck('id'))->toContain($remote->id)
        ->and($result->pluck('id'))->not->toContain($onsite->id);
});

test('employmentType filters by full-time/part-time/etc', function () {
    $fullTime = JobPosting::factory()->create(['employment_type' => EmploymentType::FullTime]);
    $internship = JobPosting::factory()->create(['employment_type' => EmploymentType::Internship]);

    $result = JobPosting::query()->employmentType(EmploymentType::FullTime)->get();

    expect($result->pluck('id'))->toContain($fullTime->id)
        ->and($result->pluck('id'))->not->toContain($internship->id);
});

test('experience filters out postings that require more years than the candidate has', function () {
    $junior = JobPosting::factory()->create(['min_experience_years' => 1]);
    $senior = JobPosting::factory()->create(['min_experience_years' => 8]);

    $result = JobPosting::query()->experience(2)->get();

    expect($result->pluck('id'))->toContain($junior->id)
        ->and($result->pluck('id'))->not->toContain($senior->id);
});

test('sortBy orders postings correctly', function () {
    $cheap = JobPosting::factory()->create(['salary_min' => 20000, 'salary_max' => 25000, 'salary_negotiable' => false, 'salary_period' => SalaryPeriod::Monthly]);
    $expensive = JobPosting::factory()->create(['salary_min' => 90000, 'salary_max' => 120000, 'salary_negotiable' => false, 'salary_period' => SalaryPeriod::Monthly]);

    $result = JobPosting::query()->sortBy('salary_high')->get();

    expect($result->first()->id)->toBe($expensive->id);
});

test('filters can be chained together', function () {
    $laravel = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);

    $match = JobPosting::factory()->create([
        'workplace_type' => WorkplaceType::Remote,
        'employment_type' => EmploymentType::FullTime,
    ]);
    $match->skills()->attach($laravel);

    $wrongType = JobPosting::factory()->create([
        'workplace_type' => WorkplaceType::Onsite,
        'employment_type' => EmploymentType::FullTime,
    ]);
    $wrongType->skills()->attach($laravel);

    $result = JobPosting::query()
        ->skill($laravel->id)
        ->workplaceType(WorkplaceType::Remote)
        ->employmentType(EmploymentType::FullTime)
        ->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($match->id);
});
