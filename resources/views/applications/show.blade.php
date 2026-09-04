<x-layouts::app :title="'Job Application'">
    <div class="max-w-4xl mx-auto py-8 px-4">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-zinc-800 dark:text-white">Job Application</h1>
            <a href="{{ route('applications.index') }}"
                class="px-4 py-2 bg-zinc-800 text-white rounded-lg text-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-800 dark:hover:bg-zinc-100">
                Explore more applications
            </a>
        </div>

        {{-- application Cards --}}
        <div
            class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition">

            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-800 dark:text-white">
                        {{ $application->jobListing->title }}
                    </h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
                        {{ $application->jobListing->employerProfile->displayName() }} @if($application->jobListing->employerProfile?->verified)<span title="Verified by JobBoard"><flux:icon.check-badge variant="micro" class="inline text-blue-500 align-text-bottom" /></span>@endif ·
                        {{ $application->jobListing->location }}
                    </p>
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

            {{-- Cover Letter --}}
            @if($application->cover_letter)
                <div class="mt-4 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                    <h3 class="text-sm font-medium text-zinc-800 dark:text-white mb-2">Cover Letter</h3>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-line">{{ $application->cover_letter }}
                    </p>
                </div>
            @endif

            {{-- Resume --}}
            <div class="mt-4 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                <h3 class="text-sm font-medium text-zinc-800 dark:text-white mb-2">Resume</h3>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    <a href="{{ Storage::url($application->resume) }}" target="_blank"
                        class="hover:text-zinc-700 dark:hover:text-zinc-400">
                        📁View Resume
                    </a>
                </p>
            </div>

            <div class="flex flex-wrap gap-2 mt-3">
                {{-- Type Badge --}}
                <span
                    class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ ucfirst($application->jobListing->employment_type) }}
                </span>
                <span
                    class="text-xs px-2.5 py-1 rounded-full bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ ucfirst($application->jobListing->workplace_type) }}
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
                Posted by {{ $application->jobListing->user?->name ?? 'Deleted user' }} ·
                {{ $application->jobListing->created_at->diffForHumans() }}. You applied
                {{ $application->created_at->diffForHumans() }}
            </p>
        </div>

    </div>
</x-layouts::app>