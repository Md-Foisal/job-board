<x-layouts::guest :title="$jobPosting->title.' at '.$jobPosting->company->name">
    {{-- Google-for-Jobs structured data: no route of its own, emitted
         inside this page's HTML. Built and safely encoded in
         App\Services\JobPostingStructuredData. --}}
    @if ($structuredData)
        <script type="application/ld+json">{!! $structuredData !!}</script>
    @endif

    @php
        $company = $jobPosting->company;
        $pay = $jobPosting->payRange();
        $category = $jobPosting->categories->first();
        $requiredSkills = $jobPosting->skills->filter(fn ($skill) => $skill->pivot->importance === \App\Enums\SkillImportance::Required);
        $niceSkills = $jobPosting->skills->diff($requiredSkills);
        $where = collect([$jobPosting->location_city, $jobPosting->workplace_type->label()])->filter()->implode(' · ');
        $location = collect([$jobPosting->location_city, $jobPosting->location_country])->filter()->implode(', ');
        $closesOn = \App\Support\ClosingDate::day($jobPosting);
        // A closing day means the end of that day where the company is.
        // Said out loud only to someone whose own day ends at another time.
        $closingZone = $company->timezone !== \App\Support\LocalTime::zone()
            ? \App\Support\LocalTime::label($company->timezone, at: $jobPosting->expires_at)
            : null;
        // Guests get the button too: it signs them in and brings them back
        // to the form. The bar on phones follows whichever button shows.
        $offersApply = $canApply || ($isPublic && ! auth()->check());
        $recruiter = $jobPosting->postedBy?->recruiterProfile;
        $recruiterName = $recruiter?->display_name ?: $jobPosting->postedBy?->name;
        $recruiterPhoto = $recruiter?->avatar_path ?: $jobPosting->postedBy?->avatar;
    @endphp

    <div
        class="mx-auto max-w-6xl px-4 pt-6 sm:px-6 lg:pt-10"
        x-data="{ applyInView: true }"
        @if ($offersApply) data-sticky-apply @endif
    >
        <x-breadcrumb :items="array_values(array_filter([
            ['label' => __('Jobs'), 'url' => route('jobs.index')],
            $category ? ['label' => $category->name, 'url' => route('categories.show', $category)] : null,
            ['label' => $jobPosting->title],
        ]))" />

        <div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_21rem] lg:gap-x-10">
            <header class="flex items-start gap-4 lg:col-start-1 lg:row-start-1">
                <x-company-logo :company="$company" size="lg" />

                <div class="min-w-0 flex-1">
                    <h1 class="text-balance font-display text-heading text-ink sm:text-title">{{ $jobPosting->title }}</h1>

                    <p class="mt-1 flex flex-wrap items-center gap-x-1 text-ink-muted">
                        <a href="{{ route('companies.show', $company) }}" class="font-medium text-ink-soft hover:text-sunset-small" wire:navigate>
                            {{ $company->name }}
                        </a>
                        @if ($company->verified_at)
                            <x-verified-badge />
                        @endif
                    </p>

                    @if ($reviewSummary->count > 0 || $responsivePercent !== null)
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                            @if ($reviewSummary->count > 0)
                                {{-- A plain link, not wire:navigate, so the browser
                                     itself scrolls to the reviews on arrival. --}}
                                <a href="{{ route('companies.show', $company) }}#reviews" class="inline-flex items-center gap-1.5 text-ink-muted hover:text-sunset-small">
                                    @if ($reviewSummary->hasAverages())
                                        <x-rating-stars :value="$reviewSummary->overall" />
                                        <span class="font-medium tabular-nums text-ink" aria-hidden="true">{{ number_format($reviewSummary->overall, 1) }}</span>
                                    @endif
                                    <span class="underline underline-offset-2">{{ trans_choice(':count hiring process review|:count hiring process reviews', $reviewSummary->count, ['count' => $reviewSummary->count]) }}</span>
                                </a>
                            @endif

                            @if ($responsivePercent !== null)
                                <x-responsive-badge :percent="$responsivePercent" />
                            @endif
                        </div>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        @if ($where !== '')
                            <x-chip>
                                <flux:icon.map-pin variant="micro" class="size-3.5" aria-hidden="true" />
                                {{ $where }}
                            </x-chip>
                        @endif
                        <x-chip>
                            <flux:icon.briefcase variant="micro" class="size-3.5" aria-hidden="true" />
                            {{ $jobPosting->employment_type->label() }}
                        </x-chip>
                        @if ($jobPosting->published_at)
                            <span class="ms-1 inline-flex items-center gap-1 text-meta text-ink-muted">
                                <flux:icon.clock variant="micro" class="size-3.5" aria-hidden="true" />
                                {{ __('Posted') }} <time datetime="{{ $jobPosting->published_at->toAtomString() }}">{{ $jobPosting->published_at->diffForHumans() }}</time>
                            </span>
                        @endif
                        {{-- Only the company's own people reach a posting that is
                             not open, and a draft or a closed posting is not
                             "expired": the box beside it says what it is. --}}
                        @if ($jobPosting->isExpired())
                            <flux:badge color="red" size="sm">{{ __('Expired') }}</flux:badge>
                        @else
                            <x-closing-soon :job-posting="$jobPosting" />
                        @endif
                    </div>
                </div>
            </header>

            {{-- The apply card. After the header in the page's order, so on a
                 phone it comes before the description, as Indeed's does; on a
                 wide screen it sits in its own column and stays in view. --}}
            <aside class="lg:col-start-2 lg:row-span-2 lg:row-start-1" aria-label="{{ __('Apply and job details') }}">
                <x-card padding="sm" class="lg:sticky lg:top-20">
                    @if ($jobPosting->salary_negotiable)
                        <p class="text-subheading text-ink-muted">{{ __('Pay negotiable') }}</p>
                    @elseif ($pay)
                        <p class="font-display text-heading tabular-nums text-ink">
                            {{ $pay }}
                            @if ($jobPosting->salary_period)
                                <span class="text-body font-normal text-ink-muted">{{ $jobPosting->salary_period->per() }}</span>
                            @endif
                        </p>
                    @endif

                    <div @class(['mt-4' => $jobPosting->salary_negotiable || $pay])>
                        @if ($isMember)
                            <div class="rounded-control bg-surface p-3 text-sm">
                                <p class="font-medium text-ink">
                                    {{ $isPublic ? __('This is how candidates see your job.') : __('Only your team can see this page.') }}
                                </p>
                                @unless ($isPublic)
                                    {{-- The reason (a draft, in review, closed, held back by
                                         reports) is worked out in one place, the postings
                                         list, rather than a second time here. --}}
                                    <p class="mt-1 text-ink-muted">{{ __('Candidates can\'t see it right now. Your job postings list says why.') }}</p>
                                @endunless
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @can('update', $jobPosting)
                                        <flux:button size="sm" icon="pencil-square" :href="route('employer.jobs.edit', ['company' => $company, 'jobPosting' => $jobPosting])" wire:navigate>
                                            {{ __('Edit job') }}
                                        </flux:button>
                                    @endcan
                                    <flux:button size="sm" variant="ghost" :href="route('employer.jobs.index', $company)" wire:navigate>
                                        {{ __('Job postings') }}
                                    </flux:button>
                                </div>
                            </div>
                        @elseif ($application)
                            <div class="flex items-start gap-3 rounded-control bg-success-50 p-3 text-sm dark:bg-success-950">
                                <flux:icon.check-circle variant="mini" class="mt-0.5 size-5 shrink-0 text-success-700 dark:text-success-300" aria-hidden="true" />
                                <div>
                                    <p class="font-medium text-ink">
                                        {{ __('You applied on :date', ['date' => \App\Support\LocalTime::of($application->created_at)->format(\App\Support\DateFormat::DAY)]) }}
                                    </p>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        <x-application-status :application="$application" for-candidate />
                                    </div>
                                    <a href="{{ route('candidate.applications.show', $application) }}" class="mt-1 inline-block font-medium text-sunset-small hover:underline" wire:navigate>
                                        {{ __('Follow your application') }}
                                    </a>
                                </div>
                            </div>
                        @elseif ($offersApply)
                            {{-- A full page load rather than wire:navigate: a guest is
                                 passed on to the sign-in page, which has a layout of its own. --}}
                            <div x-intersect:enter="applyInView = true" x-intersect:leave="applyInView = false">
                                <flux:button :href="route('jobs.apply', $jobPosting)" variant="primary" class="btn-sunset w-full">
                                    {{ __('Apply now') }}
                                </flux:button>
                            </div>
                            @guest
                                <p class="mt-2 text-center text-meta text-ink-muted">{{ __('Sign in or create a free account to apply.') }}</p>
                            @endguest
                        {{-- Staff run the board; an invitation to apply on every
                             posting they look at is noise to them. --}}
                        @elseif ($isPublic && ! $isCandidate && ! auth()->user()->isStaff())
                            <div class="rounded-control bg-surface p-3 text-sm">
                                <p class="text-ink-muted">{{ __('Applying needs a candidate profile. Your account can have one alongside what it already does.') }}</p>
                                <form method="POST" action="{{ route('candidate.start') }}" class="mt-3">
                                    @csrf
                                    <flux:button type="submit" size="sm">{{ __('Start a candidate profile') }}</flux:button>
                                </form>
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2" x-data="copyText">
                        @if ($canSave && $isPublic)
                            <livewire:save-job-button :job-posting="$jobPosting" :key="'save-'.$jobPosting->id" />
                        @endif

                        <flux:button variant="ghost" icon="link" x-on:click="copy(window.location.href)">
                            <span x-show="copyState !== 'copied'">{{ __('Share') }}</span>
                            <span x-show="copyState === 'copied'" x-cloak>{{ __('Link copied') }}</span>
                        </flux:button>

                        {{-- Nobody reports their own company's posting. --}}
                        @unless ($isMember)
                            <livewire:report-button :reportable="$jobPosting" :key="'report-'.$jobPosting->id" />
                        @endunless

                        <span
                            role="status"
                            class="basis-full text-sm text-danger-700 dark:text-danger-300"
                            x-bind:class="copyState === 'failed' ? '' : 'sr-only'"
                            x-text="({ copied: @js(__('Link copied')), failed: @js(__("Couldn't copy the link — copy it from your browser's address bar.")) })[copyState] ?? ''"
                        ></span>
                    </div>

                    <h2 class="mt-5 border-t border-line pt-4 text-meta font-semibold uppercase tracking-wide text-ink-muted">{{ __('Job details') }}</h2>
                    <dl class="mt-3 grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-2.5 text-sm">
                        <dt class="text-ink-muted">{{ __('Pay') }}</dt>
                        <dd class="text-ink">
                            @if ($jobPosting->salary_negotiable)
                                {{ __('Negotiable') }}
                            @elseif ($pay)
                                {{ $pay }} {{ $jobPosting->salary_period?->per() }}
                            @else
                                {{ __('Not stated') }}
                            @endif
                        </dd>

                        <dt class="text-ink-muted">{{ __('Job type') }}</dt>
                        <dd class="text-ink">{{ $jobPosting->employment_type->label() }}</dd>

                        <dt class="text-ink-muted">{{ __('Workplace') }}</dt>
                        <dd class="text-ink">{{ $jobPosting->workplace_type->label() }}</dd>

                        @if ($location !== '')
                            <dt class="text-ink-muted">{{ __('Location') }}</dt>
                            <dd class="text-ink">{{ $location }}</dd>
                        @endif

                        @if ($jobPosting->min_experience_years !== null)
                            <dt class="text-ink-muted">{{ __('Experience') }}</dt>
                            <dd class="text-ink">
                                {{ $jobPosting->min_experience_years === 0
                                    ? __('No minimum')
                                    : trans_choice('{1} :count+ year|[2,*] :count+ years', $jobPosting->min_experience_years) }}
                            </dd>
                        @endif

                        @if ($jobPosting->categories->isNotEmpty())
                            <dt class="text-ink-muted">{{ trans_choice('Category|Categories', $jobPosting->categories->count()) }}</dt>
                            <dd class="text-ink">
                                @foreach ($jobPosting->categories as $jobCategory)
                                    <a href="{{ route('categories.show', $jobCategory) }}" class="hover:text-sunset-small hover:underline" wire:navigate>{{ $jobCategory->name }}</a>@if (! $loop->last), @endif
                                @endforeach
                            </dd>
                        @endif

                        @if ($jobPosting->published_at)
                            <dt class="text-ink-muted">{{ __('Posted') }}</dt>
                            <dd class="text-ink">{{ \App\Support\LocalTime::of($jobPosting->published_at)->format(\App\Support\DateFormat::DAY) }}</dd>
                        @endif

                        <dt class="text-ink-muted">{{ __('Closes') }}</dt>
                        <dd class="text-ink">
                            {{ $closesOn->format(\App\Support\DateFormat::DAY) }}
                            @if ($closingZone)
                                <span class="block text-meta text-ink-muted">{{ __('End of the day in :zone', ['zone' => $closingZone]) }}</span>
                            @endif
                        </dd>
                    </dl>
                </x-card>
            </aside>

            <div class="min-w-0 lg:col-start-1 lg:row-start-2">
                @if ($isCandidate && $isPublic)
                    <livewire:match-breakdown :job-posting="$jobPosting" :key="'match-'.$jobPosting->id" defer />
                @endif

                <section class="mt-8 first:mt-0" aria-labelledby="job-description-heading">
                    <h2 id="job-description-heading" class="font-display text-heading text-ink">{{ __('About the job') }}</h2>
                    <div class="prose prose-zinc mt-4 max-w-none dark:prose-invert">
                        <div class="prose-content">{!! $jobPosting->description !!}</div>
                    </div>
                </section>

                @if ($jobPosting->skills->isNotEmpty())
                    <section class="mt-10" aria-labelledby="job-skills-heading">
                        <h2 id="job-skills-heading" class="font-display text-heading text-ink">{{ __('Skills') }}</h2>
                        @foreach ([__('Required') => $requiredSkills, __('Nice to have') => $niceSkills] as $label => $group)
                            @if ($group->isNotEmpty())
                                <h3 class="mt-4 text-meta font-medium uppercase tracking-wide text-ink-muted">{{ $label }}</h3>
                                <ul class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($group as $skill)
                                        <li><x-chip variant="skill">{{ $skill->name }}</x-chip></li>
                                    @endforeach
                                </ul>
                            @endif
                        @endforeach
                    </section>
                @endif

                <section class="mt-10" aria-labelledby="job-company-heading">
                    <h2 id="job-company-heading" class="font-display text-heading text-ink">{{ __('About the company') }}</h2>
                    <x-card padding="sm" class="mt-4">
                        <div class="flex items-start gap-3">
                            <x-company-logo :company="$company" />
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-x-1">
                                    <a href="{{ route('companies.show', $company) }}" class="text-subheading text-ink hover:text-sunset-small" wire:navigate>{{ $company->name }}</a>
                                    @if ($company->verified_at)
                                        <x-verified-badge />
                                    @endif
                                </p>
                                <p class="mt-0.5 text-meta text-ink-muted">
                                    {{ collect([
                                        $company->identity_type->label(),
                                        $company->industry,
                                        $company->size ? __(':size employees', ['size' => $company->size]) : null,
                                    ])->filter()->implode(' · ') }}
                                </p>
                            </div>
                        </div>

                        @if ($company->description)
                            {{-- A space before every tag, so the end of one paragraph does
                                 not run into the start of the next once the tags are gone;
                                 entities decoded, since {{ }} escapes the text again. --}}
                            <p class="mt-4 text-sm text-ink-soft">{{ \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish(html_entity_decode(strip_tags(str_replace('<', ' <', $company->description)), ENT_QUOTES | ENT_HTML5)), 240) }}</p>
                        @endif

                        <a href="{{ route('companies.show', $company) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-sunset-small hover:underline" wire:navigate>
                            {{ $openJobsCount > 0
                                ? trans_choice('{1} See the company and its :count open job|[2,*] See the company and its :count open jobs', $openJobsCount)
                                : __('See the company') }}
                            <flux:icon.arrow-right variant="micro" class="size-4" aria-hidden="true" />
                        </a>

                        @if ($recruiterName)
                            <div class="mt-5 flex items-start gap-3 border-t border-line pt-4">
                                <flux:avatar circle size="sm" :src="$recruiterPhoto ? \Illuminate\Support\Facades\Storage::url($recruiterPhoto) : null" :name="$recruiterName" :initials="$jobPosting->postedBy->initials()" />
                                <div class="min-w-0 text-sm">
                                    <p class="text-meta text-ink-muted">{{ __('Posted by') }}</p>
                                    <p class="font-medium text-ink">{{ $recruiterName }}</p>
                                    @if ($recruiter?->bio)
                                        <p class="mt-1 text-ink-muted">{{ $recruiter->bio }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </x-card>
                </section>
            </div>
        </div>

        @if ($similarJobs->isNotEmpty())
            <section class="mt-14" aria-labelledby="similar-jobs-heading">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="similar-jobs-heading" class="font-display text-heading text-ink">{{ __('Similar jobs') }}</h2>
                    @if ($category)
                        <a href="{{ route('categories.show', $category) }}" class="text-sm font-medium text-sunset-small hover:underline" wire:navigate>
                            {{ __('All :category jobs', ['category' => $category->name]) }}
                        </a>
                    @endif
                </div>
                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($similarJobs as $similarJob)
                        <x-job-card
                            :job-posting="$similarJob"
                            :show-save-button="$canSave"
                            :saved="in_array($similarJob->id, $savedJobIds, true)"
                        />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- On a phone the Apply button scrolls away with the card it sits
             in, and the description that follows is the long part of the
             page. This bar brings it back once it has gone, and only then,
             so two Apply buttons are never on screen at once. --}}
        @if ($offersApply)
            <div
                class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-canvas/95 px-4 py-3 backdrop-blur-md lg:hidden"
                x-show="! applyInView"
                x-transition.opacity
                x-cloak
            >
                <div class="mx-auto flex max-w-6xl items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{{ $jobPosting->title }}</p>
                        <p class="truncate text-meta text-ink-muted">
                            {{ $jobPosting->salary_negotiable ? __('Pay negotiable') : ($pay ? $pay.' '.$jobPosting->salary_period?->per() : $company->name) }}
                        </p>
                    </div>
                    <flux:button :href="route('jobs.apply', $jobPosting)" variant="primary" class="btn-sunset shrink-0">
                        {{ __('Apply now') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </div>
</x-layouts::guest>
