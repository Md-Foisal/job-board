<x-layouts::guest :title="$jobPosting->title.' at '.$jobPosting->company->name">
    {{-- Google-for-Jobs structured data (claude/14 step 5: no route of its
         own, emitted inside this page's HTML). Built and safely encoded in
         App\Services\JobPostingStructuredData. --}}
    @if ($structuredData)
        <script type="application/ld+json">{!! $structuredData !!}</script>
    @endif

    <article class="mx-auto max-w-3xl px-6 py-12">
        <x-breadcrumb :items="[
            ['label' => __('Jobs'), 'url' => route('jobs.index')],
            ['label' => $jobPosting->title],
        ]" />

        <header class="mt-4 flex items-start gap-4 border-b border-zinc-200 pb-6 dark:border-zinc-800">
            <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-brand-50 text-lg font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                @if ($jobPosting->company->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($jobPosting->company->logo_path) }}" alt="{{ $jobPosting->company->name }}" class="size-full object-cover">
                @else
                    {{ \Illuminate\Support\Str::of($jobPosting->company->name)->substr(0, 1) }}
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-display text-2xl font-bold text-zinc-900 dark:text-zinc-50">{{ $jobPosting->title }}</h1>

                    @if (!$jobPosting->isOpen())
                        <span class="shrink-0 rounded-full bg-danger-50 px-2.5 py-1 text-xs font-medium text-danger-700 dark:bg-danger-950 dark:text-danger-300">
                            Expired
                        </span>
                    @elseif ($jobPosting->expires_at->diffInDays(now()) <= 3)
                        <span class="shrink-0 rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-700 dark:bg-warning-950 dark:text-warning-300">
                            Expires {{ $jobPosting->expires_at->diffForHumans() }}
                        </span>
                    @endif
                </div>

                <p class="mt-1 text-zinc-600 dark:text-zinc-400">
                    <a href="{{ route('companies.show', $jobPosting->company) }}" class="hover:text-brand-700 dark:hover:text-brand-400" wire:navigate>
                        {{ $jobPosting->company->name }}
                    </a>
                    @if ($jobPosting->company->verified_at)
                        <x-verified-badge class="ml-1 align-middle" />
                    @endif
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $jobPosting->workplace_type->label() }}
                    </span>
                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $jobPosting->employment_type->label() }}
                    </span>
                    @if ($jobPosting->location_city)
                        <span class="text-zinc-500 dark:text-zinc-500">{{ $jobPosting->location_city }}</span>
                    @endif
                </div>
            </div>
        </header>

        <div class="mt-6 flex flex-wrap items-center gap-3" x-data="{ copied: false }">
            @if (auth()->check() && auth()->user()->isCandidate())
                @if ($jobPosting->isPubliclyVisible())
                    <flux:button href="{{ route('jobs.apply', $jobPosting) }}" variant="primary" wire:navigate>Apply now</flux:button>
                @endif
                <livewire:save-job-button :job-posting="$jobPosting" :key="'save-'.$jobPosting->id" />
            @elseif (!auth()->check())
                <flux:button href="{{ route('register') }}" variant="primary" wire:navigate>Sign up to apply</flux:button>
            @endif

            <flux:button
                variant="ghost"
                icon="link"
                x-on:click="navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 2000)"
            >
                <span x-show="!copied">Share</span>
                <span x-show="copied" x-cloak>Link copied</span>
            </flux:button>

            <livewire:report-button :reportable="$jobPosting" :key="'report-'.$jobPosting->id" />
        </div>

        @if ($jobPosting->salary_negotiable || $jobPosting->salary_min || $jobPosting->salary_max)
            <p class="mt-6 font-display text-lg font-semibold tabular-nums text-brand-700 dark:text-brand-400">
                @if ($jobPosting->salary_negotiable)
                    Salary: Negotiable
                @else
                    {{ $jobPosting->salary_currency }} {{ number_format($jobPosting->salary_min) }}–{{ number_format($jobPosting->salary_max) }}
                    <span class="font-normal text-zinc-500 dark:text-zinc-500">/ {{ \Illuminate\Support\Str::lower($jobPosting->salary_period->label()) }}</span>
                @endif
            </p>
        @endif

        <div class="prose prose-zinc mt-6 max-w-none dark:prose-invert">
            <div class="prose-content">{!! $jobPosting->description !!}</div>
        </div>

        @if ($jobPosting->skills->isNotEmpty())
            <div class="mt-8 flex flex-wrap gap-2">
                @foreach ($jobPosting->skills as $skill)
                    <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                        {{ $skill->name }}
                    </span>
                @endforeach
            </div>
        @endif

        @if ($jobPosting->postedBy)
            @php
                $recruiter = $jobPosting->postedBy->recruiterProfile;
                $recruiterName = $recruiter?->display_name ?: $jobPosting->postedBy->name;
                $recruiterPhoto = $recruiter?->avatar_path ?: $jobPosting->postedBy->avatar;
            @endphp

            <div class="mt-10 flex items-start gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-zinc-100 text-sm font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    @if ($recruiterPhoto)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($recruiterPhoto) }}" alt="{{ $recruiterName }}" class="size-full object-cover">
                    @else
                        {{ $jobPosting->postedBy->initials() }}
                    @endif
                </div>
                <div class="text-sm">
                    <p class="text-zinc-500 dark:text-zinc-500">Posted by</p>
                    <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $recruiterName }}</p>
                    @if ($recruiter?->bio)
                        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $recruiter->bio }}</p>
                    @endif
                </div>
            </div>
        @endif
    </article>
</x-layouts::guest>
