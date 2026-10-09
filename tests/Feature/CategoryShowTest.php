<?php

use App\Models\Category;
use App\Models\JobPosting;
use Livewire\Livewire;

test('category page only shows job postings in that category', function () {
    $engineering = Category::create(['name' => 'Engineering', 'slug' => 'engineering']);
    $marketing = Category::create(['name' => 'Marketing', 'slug' => 'marketing']);

    $engineeringJob = JobPosting::factory()->create(['title' => 'Platform Engineer']);
    $engineeringJob->categories()->attach($engineering);

    $marketingJob = JobPosting::factory()->create(['title' => 'Growth Marketer']);
    $marketingJob->categories()->attach($marketing);

    $response = $this->get(route('categories.show', $engineering));

    $response->assertOk();
    $response->assertSee('Platform Engineer');
    $response->assertDontSee('Growth Marketer');
});

test('a category page is titled by its category and keeps it when the filters are cleared', function () {
    $engineering = Category::create(['name' => 'Engineering', 'slug' => 'engineering']);
    Category::create(['name' => 'Marketing', 'slug' => 'marketing']);

    $engineering->jobPostings()->attach(JobPosting::factory()->create(['title' => 'Platform Engineer']));
    JobPosting::factory()->create(['title' => 'Growth Marketer']);

    $this->get(route('categories.show', $engineering))
        ->assertOk()
        ->assertSee('Engineering jobs - '.config('app.name'));

    Livewire::test('pages::category-show', ['categoryModel' => $engineering])
        ->assertDontSee('Any category')
        ->set('posted', 7)
        ->call('resetFilters')
        ->call('clearFilter', 'category')
        ->assertSet('category', $engineering->id)
        ->assertSet('posted', null)
        ->assertSee('Platform Engineer')
        ->assertDontSee('Growth Marketer');
});
