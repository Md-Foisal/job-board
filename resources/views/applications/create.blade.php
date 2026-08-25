<x-layouts::app :title="'Create Application'">
    <div class="max-w-4xl mx-auto py-8 px-4">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-zinc-800 dark:text-white">Create Applications</h1>
            <a href="{{ route('job-listings.show', $jobListing) }}"
                class="px-4 py-2 bg-zinc-800 text-white rounded-lg text-sm hover:bg-zinc-700 dark:bg-white dark:text-zinc-800 dark:hover:bg-zinc-100">
                Back to Job Details
            </a>
        </div>

        {{-- Job Details --}}
        <div class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition mb-6">

          <div class="flex items-start justify-between gap-4">
            <div>
              <h2 class="text-lg font-semibold text-zinc-800 dark:text-white">{{ $jobListing->title }}</h2>
              <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $jobListing->employerProfile->displayName() }} @if($jobListing->employerProfile?->verified)<span title="Verified by JobBoard"><flux:icon.check-badge variant="micro" class="inline text-blue-500 align-text-bottom" /></span>@endif · {{ $jobListing->location }}</p>
            </div>

            {{-- Status Badge --}}
            <span @class([
    'text-xs font-medium px-2.5 py-1 rounded-full shrink-0',
    'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $jobListing->status === 'open',
    'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300' => $jobListing->status === 'closed',
])>
              {{ ucfirst($jobListing->status) }}
            </span>
          </div>

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
                {{ $jobListing->salary_min }} - {{ $jobListing->salary_max }} {{ $jobListing->salary_currency }}/{{ $jobListing->salary_period }}
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

          {{-- Posted Info --}}
          <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-3">
            Posted by {{ $jobListing->user->name }} · {{ $jobListing->created_at->diffForHumans() }}
          </p>
        </div>

        {{-- Application create --}}
        <form action="{{ route('applications.store') }}" method="POST" class="flex flex-col gap-4" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="job_listing_id" value="{{ $jobListing->id }}">

            {{-- Cover Letter --}}
            <label for="cover_letter"
                class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">Cover Letter</h2>
                <textarea type="text" name="cover_letter" id="cover_letter" placeholder="Write your cover letter here"
                    class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ old('cover_letter') }}</textarea>
                @error('cover_letter')
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}
                    </p>
                @enderror
            </label>

            {{-- Resume --}}
            <label for="resume"
                class="block p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl hover:border-zinc-400 dark:hover:border-zinc-500 transition cursor-pointer">
                <h2 class="test-lg font-semibold text-zinc-800 dark:text-white mb-2">📁Resume</h2>
                <input type="file" name="resume" id="resume" value="{{ old('resume') }}" class="w-full outline-none px-3 focus:ring focus:ring-zinc-200 dark:focus:ring-zinc-700 py-3 rounded-sm bg-zinc-100 test-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                @error('resume')
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 mt-2">* {{ $message }}
                    </p>
                @enderror
            </label>

            {{-- Submit & cancel --}}
            <div
                class="flex alignitems-center justify-evenly bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-3 gap-2">
                <button type="submit" class="cursor-pointer 
                        w-full 
                        rounded 
                        px-4 py-2 
                        bg-zinc-100
                        text-zinc-800 
                        hover:bg-zinc-200 
                        dark:bg-zinc-800 
                        dark:text-zinc-100
                        dark:hover:bg-zinc-700">Create</button>

                <a href="{{ route('job-listings.show', $jobListing) }}" class="cursor-pointer 
                        w-full 
                        rounded 
                        px-4 py-2 
                        bg-zinc-100
                        text-zinc-800 
                        hover:bg-zinc-200 
                        dark:bg-zinc-800 
                        dark:text-zinc-100
                        dark:hover:bg-zinc-700 text-center">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts::app>
