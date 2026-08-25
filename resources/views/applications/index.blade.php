<x-layouts::app :title="'Job Applications'">
    <div class="max-w-4xl mx-auto py-8 px-4">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-zinc-800 dark:text-white">Job Applications</h1>
                <a href="{{ route('job-listings.index') }}"
                    class="px-4 py-2 bg-zinc-800 text-white rounded-lg text-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-800 dark:hover:bg-zinc-100">
                    Explore more applications
                </a>
        </div>

        {{-- application Cards --}}
        <div class="flex flex-col gap-4">
            @forelse($applications as $application)
                <a href="{{ route('applications.show', $application) }}"
                    class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition">

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-zinc-800 dark:text-white">{{ $application->jobListing->title }}</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $application->jobListing->employerProfile->displayName() }} @if($application->jobListing->employerProfile?->verified)<span title="Verified by JobBoard"><flux:icon.check-badge variant="micro" class="inline text-blue-500 align-text-bottom" /></span>@endif ·
                                {{ $application->jobListing->location }}</p>
                        </div>

                        <div class="flex items-center gap-1">
                            {{--Job Post Status Badge --}}
                            <span @class([
                                'text-xs font-medium px-2.5 py-1 rounded-full shrink-0',
                                'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $application->jobListing->status === \App\Enums\JobListingStatus::Open,
                                'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300' => $application->jobListing->status === \App\Enums\JobListingStatus::Closed,
                            ])>
                  {{ $application->jobListing->status->label() }}
                            </span>

                            {{-- Application Status Badge --}}
                            <span @class([
                                'text-xs font-medium px-2.5 py-1 rounded-full shrink-0',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' => $application->status === \App\Enums\ApplicationStatus::Pending,
                                'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $application->status === \App\Enums\ApplicationStatus::Accepted,
                                'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' => $application->status === \App\Enums\ApplicationStatus::Rejected,
                            ])>
                  {{ $application->status->label() }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-3">
                        {{-- Employment Type + Work Location Badges --}}
                        <span
                            class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ ucfirst($application->jobListing->employment_type) }}
                        </span>
                        <span
                            class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ ucfirst($application->jobListing->work_location) }}
                        </span>

                        {{-- Salary --}}
                        @if($application->jobListing->salary_min && $application->jobListing->salary_max)
                            <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $application->jobListing->salary_min }} - {{ $application->jobListing->salary_max }} {{ $application->jobListing->salary_currency }}/{{ $application->jobListing->salary_period }}
                            </span>
                        @elseif($application->jobListing->salary_min)
                            <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $application->jobListing->salary_min }}+ {{ $application->jobListing->salary_currency }}/{{ $application->jobListing->salary_period }}
                            </span>
                        @elseif($application->jobListing->salary_max)
                            <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                Up to {{ $application->jobListing->salary_max }} {{ $application->jobListing->salary_currency }}/{{ $application->jobListing->salary_period }}
                            </span>
                        @else
                            <span class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                Salary negotiable
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-3">
                        Posted by {{ $application->jobListing->user->name }} · {{ $application->jobListing->created_at->diffForHumans() }}. You applied {{ $application->created_at->diffForHumans() }}
                    </p>
                </a>
            @empty
                <p class="text-center text-zinc-400 dark:text-zinc-500 py-16">No application found.</p>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $applications->links() }}
        </div>

    </div>
</x-layouts::app>