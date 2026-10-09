@php
    $words = [__('skills'), __('pay'), __('city'), __('pace')];
    // The rising column needs enough rows to read as a stream.
    $showFeed = $jobPostings->count() >= 3;
@endphp

<x-layouts::guest>
    {{--
        The hero. A field of faint dots, and the ones near the pointer light
        up in the brand gradient; on a touch screen the light drifts on its
        own, and with reduced motion only the faint dots stay. The last word
        of the heading turns through what people look for in a job, and the
        newest openings rise past on the right.
    --}}
    <section
        class="hero-spotlight relative isolate border-b border-line"
        x-data="{ paused: false }"
        :class="paused && 'is-paused'"
        x-on:pointermove="if ($event.pointerType === 'mouse') { const r = $el.getBoundingClientRect(); $el.style.setProperty('--spot-x', ($event.clientX - r.left) + 'px'); $el.style.setProperty('--spot-y', ($event.clientY - r.top) + 'px') }"
    >
        <div class="absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
            <div class="hero-dots absolute inset-0"></div>
            <div class="hero-dots-lit absolute inset-0"></div>
        </div>

        <div @class([
            'mx-auto grid max-w-6xl items-center gap-12 px-6 py-16 sm:py-24',
            'lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]' => $showFeed,
        ])>
            <div class="min-w-0">
                @if ($openJobsCount > 0)
                    <p class="inline-flex flex-wrap items-center gap-2 rounded-full border border-line bg-canvas/80 py-1 ps-1 pe-3 text-meta font-medium text-ink-muted backdrop-blur">
                        <span class="bg-sunset rounded-full px-2 py-0.5 font-semibold tabular-nums text-white">
                            {{ trans_choice(':count open role|:count open roles', $openJobsCount, ['count' => number_format($openJobsCount)]) }}
                        </span>
                        @if ($hiringCompaniesCount > 0)
                            {{ trans_choice('from :count company hiring now|from :count companies hiring now', $hiringCompaniesCount, ['count' => number_format($hiringCompaniesCount)]) }}
                        @endif
                    </p>
                @endif

                <h1 class="mt-6 text-[clamp(2.5rem,6vw,4.5rem)] leading-[1.05] font-bold tracking-[-0.05em] text-balance text-ink">
                    <span class="sr-only">{{ __('Find work that fits your skills') }}</span>
                    <span aria-hidden="true">
                        {{ __('Find work that fits') }}<br>
                        {{ __('your') }}
                        <span class="hero-words"><span class="hero-words-track">
                            @foreach ([...$words, $words[0]] as $word)
                                <span class="text-sunset">{{ $word }}</span>
                            @endforeach
                        </span></span>
                    </span>
                </h1>

                <p class="mt-6 max-w-lg text-lg leading-relaxed text-ink-muted">
                    {{ __('Look around without an account. Add your skills and every job shows how well you match.') }}
                </p>

                <div class="mt-8">
                    <livewire:job-search-autocomplete variant="hero" />
                </div>
            </div>

            @if ($showFeed)
                {{-- The newest openings, twice over so the column can loop
                     without a seam; the second copy is for the eye only.
                     It stops while the pointer or the keyboard is on it. --}}
                <div class="hero-feed hidden h-[28rem] overflow-hidden lg:block">
                    <h2 class="sr-only">{{ __('Newest openings') }}</h2>
                    <div class="hero-feed-track">
                        @foreach ([false, true] as $copy)
                            <ul @if ($copy) aria-hidden="true" @endif>
                                @foreach ($jobPostings as $jobPosting)
                                    @php $pay = $jobPosting->payRange(); @endphp
                                    <li class="pb-3">
                                        <x-card
                                            as="a"
                                            padding="none"
                                            href="{{ route('jobs.show', $jobPosting) }}"
                                            wire:navigate
                                            :tabindex="$copy ? '-1' : null"
                                            class="flex items-center gap-3 px-4 py-3.5 transition-colors hover:border-line-strong"
                                        >
                                            <x-company-logo :company="$jobPosting->company" size="sm" />
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate font-semibold text-ink">{{ $jobPosting->title }}</span>
                                                <span class="block truncate text-meta text-ink-muted">
                                                    {{ collect([$jobPosting->location_city, $pay])->filter()->whenEmpty(fn ($parts) => $parts->push($jobPosting->employment_type->label()))->implode(' · ') }}
                                                </span>
                                            </span>
                                            <x-chip class="shrink-0">{{ $jobPosting->workplace_type->label() }}</x-chip>
                                        </x-card>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- The heading and the column keep moving for as long as the page
             is open, so there is a way to stop them that does not hold the
             pointer or the focus (WCAG 2.2.2). Nothing moves with reduced
             motion, so the button is not needed there. --}}
        <flux:button
            variant="ghost"
            size="xs"
            square
            x-on:click="paused = ! paused"
            ::aria-pressed="paused.toString()"
            :aria-label="__('Pause the animation')"
            class="absolute! end-4 bottom-4 motion-reduce:hidden"
        >
            <flux:icon.pause variant="micro" x-show="! paused" aria-hidden="true" />
            <flux:icon.play variant="micro" x-show="paused" x-cloak aria-hidden="true" />
        </flux:button>
    </section>

    {{-- What the board does that others do not: the match is shown to the
         candidate, not used against them. The card is an example, and says
         so; the numbers in it agree with each other (5 of 7 skills). --}}
    <section class="mx-auto grid max-w-6xl items-center gap-12 px-6 py-20 lg:grid-cols-2">
        <div>
            <p class="w-fit font-mono text-meta tracking-wider uppercase text-sunset-small">{{ __('Your match, before you apply') }}</p>
            <h2 class="mt-3 text-title text-ink">{{ __('See which skills you have, and which you are missing.') }}</h2>
            <p class="mt-4 max-w-xl text-body text-ink-muted sm:text-lg">
                {{ __('Every job compares its skills, pay, workplace, job type and experience with yours. The full comparison is yours alone; the company sees only the skills match.') }}
            </p>
            @guest
                <flux:button :href="route('register')" variant="primary" icon:trailing="arrow-right" class="mt-8">
                    {{ __('Create a profile') }}
                </flux:button>
            @endguest
        </div>

        @php
            $have = ['PHP', 'Laravel', 'MySQL', 'Vue.js', 'Docker'];
            $missing = ['REST APIs', 'AWS'];
            $percent = (int) round(count($have) / (count($have) + count($missing)) * 100);
            $circumference = 2 * M_PI * 36;
        @endphp

        <div
            x-data="{ shown: false }"
            x-intersect.once="shown = true"
            role="img"
            aria-label="{{ __('Example: a Laravel developer job showing a :percent% skills match, with five of its seven skills on your profile and two missing.', ['percent' => $percent]) }}"
            class="match-demo rounded-[1.5rem] border border-line bg-surface p-6 shadow-glow sm:p-8"
            :class="shown && 'is-shown'"
        >
            <div class="flex items-center gap-3">
                <x-icon-tile icon="code-bracket" />
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-ink">{{ __('Laravel Developer') }}</p>
                    <p class="text-sm text-ink-muted">{{ __('Example job · Hybrid') }}</p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-6">
                <div class="relative size-24 shrink-0">
                    <svg viewBox="0 0 92 92" class="size-full -rotate-90">
                        <defs>
                            <linearGradient id="match-demo-ring" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="var(--color-sunset-ink-1)" />
                                <stop offset="55%" stop-color="var(--color-sunset-ink-2)" />
                                <stop offset="100%" stop-color="var(--color-sunset-ink-3)" />
                            </linearGradient>
                        </defs>
                        <circle cx="46" cy="46" r="36" fill="none" stroke="var(--color-line)" stroke-width="8" />
                        <circle
                            class="match-demo-ring"
                            cx="46" cy="46" r="36" fill="none" stroke="url(#match-demo-ring)" stroke-width="8" stroke-linecap="round"
                            style="--ring-full: {{ round($circumference, 1) }}; --ring-rest: {{ round($circumference * (1 - $percent / 100), 1) }}"
                        />
                    </svg>
                    <span class="match-demo-number absolute inset-0 flex items-center justify-center text-heading font-bold tabular-nums text-ink">{{ $percent }}%</span>
                </div>

                <div class="flex min-w-48 flex-1 flex-wrap gap-2">
                    @foreach ($have as $skill)
                        <x-chip variant="skill" class="match-demo-chip" style="--chip-delay: {{ 0.4 + $loop->index * 0.25 }}s">{{ $skill }}</x-chip>
                    @endforeach
                    @foreach ($missing as $skill)
                        <x-chip variant="missing">{{ $skill }}</x-chip>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="mx-auto max-w-6xl px-6 pb-20">
            <h2 class="text-title text-ink">{{ __('Browse by category') }}</h2>
            <div class="mt-6 grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-3 xl:grid-cols-4">
                @foreach ($categories as $category)
                    <x-category-card :category="$category" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-6xl px-6 pb-20">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 class="text-title text-ink">{{ __('Recent openings') }}</h2>
            @if ($openJobsCount > 0)
                <a href="{{ route('jobs.index') }}" class="group inline-flex items-center gap-1 text-sm font-medium text-ink-muted transition-colors hover:text-ink" wire:navigate>
                    {{ trans_choice('Browse the :count open job|Browse all :count open jobs', $openJobsCount, ['count' => number_format($openJobsCount)]) }}
                    <flux:icon.arrow-right variant="micro" class="transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                </a>
            @endif
        </div>

        <div class="mt-6">
            @if ($jobPostings->isEmpty())
                <x-empty-state :heading="__('No open jobs right now')" icon="briefcase">
                    {{ __('New jobs show up here as soon as companies post them.') }}
                </x-empty-state>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($jobPostings as $jobPosting)
                        <x-job-card :job-posting="$jobPosting" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- The other half of the board. The gradient on this page belongs to
         the search, so this button is the inverse of the band it sits on. --}}
    <section class="mx-auto max-w-6xl px-6">
        <div class="relative isolate flex flex-col items-start justify-between gap-6 overflow-hidden rounded-[1.5rem] bg-ink px-8 py-12 text-canvas sm:px-12 md:flex-row md:items-center">
            <div class="bg-sunset absolute -top-28 -right-20 -z-10 h-72 w-96 rounded-full opacity-40 blur-3xl" aria-hidden="true"></div>
            <div class="max-w-xl">
                <h2 class="text-title">{{ __('Hiring? Meet people who already match.') }}</h2>
                <p class="mt-3 text-body opacity-75 sm:text-lg">{{ __('Post a job and see each applicant’s skills match beside their name.') }}</p>
            </div>
            <flux:button :href="\App\Support\PostJobLink::for(auth()->user())" icon:trailing="arrow-right" class="shrink-0 border-transparent! bg-canvas! text-ink! hover:opacity-90">
                {{ __('Post a job') }}
            </flux:button>
        </div>
    </section>
</x-layouts::guest>
