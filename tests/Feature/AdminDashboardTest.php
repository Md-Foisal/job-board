<?php

use App\Enums\ReportStatus;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Widgets\ModerationQueuesOverview;
use App\Filament\Widgets\PlatformOverview;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Livewire\Livewire;

it('links every queue from the dashboard', function () {
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ModerationQueuesOverview::class)
        ->assertSee(JobPostingResource::getUrl('index'))
        ->assertSee(ReportResource::getUrl('index'))
        ->assertSee(CompanyResource::getUrl('index'));
});

it('says how long the oldest item in a queue has waited, not just how many there are', function () {
    JobPosting::factory()->pendingModeration()->create(['submitted_at' => now()->subDays(2)]);
    JobPosting::factory()->pendingModeration()->create(['submitted_at' => now()->subHour()]);
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ModerationQueuesOverview::class)
        ->assertSee('Job postings to review')
        ->assertSee('Oldest waiting 2 days');
});

it('calls an empty queue all clear', function () {
    Company::query()->update(['verified_at' => now()]);
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ModerationQueuesOverview::class)
        ->assertSee('All clear');
});

it('reports how many closed reports this week led to action', function () {
    $posting = JobPosting::factory()->create();
    foreach ([ReportStatus::Actioned, ReportStatus::Reviewed] as $status) {
        $report = $posting->reports()->create(['reporter_id' => User::factory()->create()->id, 'reason' => 'x']);
        $report->review_status = $status;
        $report->save();
    }
    $this->actingAs(staffWithTwoFactor());

    // How the queues are being worked sits with the queues.
    Livewire::test(ModerationQueuesOverview::class)
        ->assertSee('No decisions this week')
        ->assertSee('50% of closed reports led to action');
});

it('shows four queue cards and four platform figures, so neither row has a gap', function () {
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ModerationQueuesOverview::class)
        ->assertSee('Job postings to review')
        ->assertSee('Reviews and answers to check')
        ->assertSee('Reported things')
        ->assertSee('Companies not verified')
        ->assertDontSee('Company responses to check');

    Livewire::test(PlatformOverview::class)
        ->assertSee('Live job postings')
        ->assertSee('Applications this week')
        ->assertDontSee('Moderation decisions this week');
});
