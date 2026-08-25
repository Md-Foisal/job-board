<?php

use Livewire\Component;
use App\Models\JobListing;
use Livewire\Attributes\Computed;

new class extends Component {
  public string $search = '';
  public string $employmentType = '';
  public string $workLocation = '';
  public string $location = '';
  public string $salaryType = ''; // '', 'range', 'contract'
  public string $salaryCurrency = '';
  public ?int $minSalary = null;

  #[Computed]
  public function availableCurrencies()
  {
    return JobListing::query()
      ->whereNotNull('salary_currency')
      ->distinct()
      ->orderBy('salary_currency')
      ->pluck('salary_currency');
  }

  #[Computed]
  public function jobListings()
  {
    return JobListing::active()
      ->with(['user:id,name,email', 'employerProfile:id,name,verified'])
      ->when($this->search, function ($query) {
        $query->where('title', 'like', '%' . $this->search . '%')
          ->orWhereHas('employerProfile', function ($q) {
            $q->where('name', 'like', '%' . $this->search . '%');
          })
          ->orWhere('location', 'like', '%' . $this->search . '%')
          ->orWhere('description', 'like', '%' . $this->search . '%');
      })
      ->when($this->employmentType, function ($query) {
        $query->where('employment_type', $this->employmentType);
      })
      ->when($this->workLocation, function ($query) {
        $query->where('work_location', $this->workLocation);
      })
      ->when($this->location, function ($query) {
        $query->where('location', 'like', '%' . $this->location . '%');
      })
      ->when($this->salaryType === 'range' && $this->salaryCurrency && $this->minSalary, function ($query) {
        $query->where('salary_currency', $this->salaryCurrency)
          ->where('salary_max_monthly', '>=', $this->minSalary);
      })
      ->when($this->salaryType === 'contract', function ($query) {
        $query->where('salary_period', 'contract');
      })
      ->latest()
      ->paginate(10);
  }
}
?>

<div>
    <div class="flex gap-2 mb-6">
      {{-- Search --}}
      <input type="search" wire:model.live="search" placeholder="Search for jobs..." class="w-0 grow-3 px-5 py-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded hover:border-zinc-400 dark:hover:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:focus:ring-zinc-600 transition">
    
      {{-- Filters --}}
        <select wire:model.live="employmentType" class="w-0 grow-1 p-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded hover:border-zinc-400 dark:hover:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:focus:ring-zinc-600 transition">
          <option value="">Employment Type</option>
          <option value="full-time">Full Time</option>
          <option value="part-time">Part Time</option>
          <option value="contract">Contract</option>
          <option value="internship">Internship</option>
          <option value="freelance">Freelance</option>
        </select>

        <select wire:model.live="workLocation" class="w-0 grow-1 p-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded hover:border-zinc-400 dark:hover:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:focus:ring-zinc-600 transition">
          <option value="">Work Location</option>
          <option value="remote">Remote</option>
          <option value="onsite">Onsite</option>
          <option value="hybrid">Hybrid</option>
        </select>
    
        <input type="text" wire:model.live="location" placeholder="location" class="w-0 grow-1 p-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded hover:border-zinc-400 dark:hover:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:focus:ring-zinc-600 transition">

        <select wire:model.live="salaryType" class="w-0 grow-1 p-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded hover:border-zinc-400 dark:hover:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:focus:ring-zinc-600 transition">
          <option value="">Any Salary</option>
          <option value="range">Salary Range</option>
          <option value="contract">Contract</option>
        </select>

        @if($salaryType === 'range')
          <select wire:model.live="salaryCurrency" class="w-0 grow-1 p-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded hover:border-zinc-400 dark:hover:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:focus:ring-zinc-600 transition">
            <option value="">Currency</option>
            @foreach($this->availableCurrencies as $currency)
              <option value="{{ $currency }}">{{ $currency }}</option>
            @endforeach
          </select>

          <input type="number" wire:model.live="minSalary" placeholder="Min salary/month" class="w-0 grow-1 px-5 py-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded hover:border-zinc-400 dark:hover:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-300 dark:focus:ring-zinc-600 transition">
        @endif
    </div>

        {{-- Job Cards --}}
    <div class="flex flex-col gap-4">
      @forelse($this->jobListings as $job)
        <a href="{{ route('job-listings.show', $job) }}"
          class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition">

          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-zinc-800 dark:text-white">{{ $job->title }}</h2>
              <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $job->employerProfile->displayName() }} @if($job->employerProfile?->verified)<span title="Verified by JobBoard"><flux:icon.check-badge variant="micro" class="inline text-blue-500 align-text-bottom" /></span>@endif · {{ $job->location }}</p>
            </div>

            {{-- Status Badge --}}
            <span @class([
    'text-xs font-medium px-2.5 py-1 rounded-full shrink-0',
    'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $job->status === \App\Enums\JobListingStatus::Open,
    'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300' => $job->status === \App\Enums\JobListingStatus::Closed,
  ])>
              {{ $job->status->label() }}
            </span>
          </div>

          <div class="flex flex-wrap gap-2 mt-3">
            {{-- Employment Type + Work Location Badges --}}
            <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
              {{ ucfirst($job->employment_type) }}
            </span>
            <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
              {{ ucfirst($job->work_location) }}
            </span>

            {{-- salary_min salary_max salary_currency salary_period --}}
            @if($job->salary_min && $job->salary_max)
              <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                {{ $job->salary_min }} - {{ $job->salary_max }} {{ $job->salary_currency }}/{{ $job->salary_period }}
              </span>
            @elseif($job->salary_min)
              <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                {{ $job->salary_min }}+ {{ $job->salary_currency }}/{{ $job->salary_period }}
              </span>
            @elseif($job->salary_max)
              <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                Up to {{ $job->salary_max }} {{ $job->salary_currency }}/{{ $job->salary_period }}
              </span>
            @else
              <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                Salary negotiable
              </span>
            @endif
          </div>

          <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-3">
            Posted by {{ $job->user->name }} · {{ $job->created_at->diffForHumans() }}
          </p>
        </a>
      @empty
        <p class="text-center text-zinc-400 dark:text-zinc-500 py-16">No job listings found.</p>
      @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-6">
      {{ $this->jobListings->links() }}
    </div>
</div>
