<x-layouts::app :title="'Show Job'">
  <div class="max-w-4xl mx-auto py-8 px-4">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-zinc-800 dark:text-white">Job Show</h1>
      @auth
        <nav class="flex items-center gap-1">
          @if (auth()->check() && auth()->user()->hasRole('candidate') && $jobListing->isOpen())
            <a href="{{ route('applications.create', ['job_listing_id' => $jobListing->id]) }}"
              class="px-4 py-2 bg-zinc-800 text-white rounded-lg text-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-800 dark:hover:bg-zinc-100">
              Apply
            </a>
          @endif
          @can('update', $jobListing)
            <a href="{{ route('job-listings.edit', $jobListing->id) }}"
              class="px-4 py-2 bg-zinc-800 text-white rounded-lg text-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-800 dark:hover:bg-zinc-100">
              edit
            </a>
          @endcan
        </nav>
      @else
        <a href="{{ route('login') }}"
          class="px-4 py-2 bg-zinc-800 text-white rounded-lg text-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-800 dark:hover:bg-zinc-100">
          Login or Register to Apply
        </a>
      @endauth
    </div>
    <div
      class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition">

      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-semibold text-zinc-800 dark:text-white">{{ $jobListing->title }}</h2>
          <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $jobListing->employerProfile->displayName() }} @if($jobListing->employerProfile?->verified)<span title="Verified by JobBoard"><flux:icon.check-badge variant="micro" class="inline text-blue-500 align-text-bottom" /></span>@endif ·
            {{ $jobListing->location }}</p>
        </div>

        {{-- Status Badge --}}
        <span @class([
  'text-xs font-medium px-2.5 py-1 rounded-full shrink-0',
  'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $jobListing->isOpen(),
  'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $jobListing->status === \App\Enums\JobListingStatus::Open && $jobListing->isExpired(),
  'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' => $jobListing->status === \App\Enums\JobListingStatus::Closed,
])>
          @if ($jobListing->status === \App\Enums\JobListingStatus::Closed)
            Closed
          @elseif ($jobListing->isExpired())
            Expired
          @else
            Open
          @endif
        </span>

      </div>

      @unless ($jobListing->isOpen())
        <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-2">
          This job listing is no longer accepting applications. Please check other open listings.
        </p>
      @endunless

      <p class="mt-3">{{ $jobListing->description }}</p>

      <div class="flex flex-wrap gap-2 mt-3">
        {{-- Employment Type + Work Location Badges --}}
        <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
          {{ ucfirst($jobListing->employment_type) }}
        </span>
        <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
          {{ ucfirst($jobListing->work_location) }}
        </span>

        {{-- Salary --}}
        @if($jobListing->salary_min && $jobListing->salary_max)
          <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
            {{ $jobListing->salary_min }} - {{ $jobListing->salary_max }}
            {{ $jobListing->salary_currency }}/{{ $jobListing->salary_period }}
          </span>
        @elseif($jobListing->salary_min)
          <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
            {{ $jobListing->salary_min }}+ {{ $jobListing->salary_currency }}/{{ $jobListing->salary_period }}
          </span>
        @elseif($jobListing->salary_max)
          <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
            Up to {{ $jobListing->salary_max }} {{ $jobListing->salary_currency }}/{{ $jobListing->salary_period }}
          </span>
        @else
          <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
            Salary negotiable
          </span>
        @endif
      </div>

      <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-3">
        Posted by {{ $jobListing->user->name }} · {{ $jobListing->created_at->diffForHumans() }}
      </p>
    </div>
  </div>
</x-layouts::app>