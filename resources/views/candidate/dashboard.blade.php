@php
    // The first name, as job sites greet people -- unless the name starts
    // with a short form such as "Md." or "Dr.", which is not what anyone
    // is called; then the whole name.
    $firstWord = \Illuminate\Support\Str::of($user->name)->trim()->explode(' ')->first();
    $greetingName = str_ends_with($firstWord, '.') ? trim($user->name) : $firstWord;
    $needsYou = $closingSaved->isNotEmpty() || $profileGaps !== [] || $importableCv || $reviewable->isNotEmpty();
    $statusIcons = [
        \App\Enums\CandidateApplicationStatus::Applied->value => 'paper-airplane',
        \App\Enums\CandidateApplicationStatus::InReview->value => 'eye',
        \App\Enums\CandidateApplicationStatus::Offer->value => 'gift',
        \App\Enums\CandidateApplicationStatus::Closed->value => 'archive-box',
    ];
@endphp

<x-layouts::app :title="__('Dashboard')">
    <x-page>
        <x-page-header :title="__('Hi, :name', ['name' => $greetingName])" :description="$stateLine">
            <x-slot:actions>
                <flux:button :href="route('jobs.index')" wire:navigate variant="primary" class="btn-sunset" icon="magnifying-glass">{{ __('Find jobs') }}</flux:button>
            </x-slot:actions>
        </x-page-header>

        {{-- The tracker: each count opens the applications behind it. --}}
        <section aria-labelledby="applications-heading">
            <h2 id="applications-heading" class="sr-only">{{ __('Your applications') }}</h2>

            <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach (\App\Enums\CandidateApplicationStatus::cases() as $status)
                    <li>
                        <x-card as="a" interactive padding="sm" :href="route('candidate.applications.index', ['status' => $status->value])" wire:navigate class="flex items-center gap-3">
                            <x-icon-tile :icon="$statusIcons[$status->value]" size="sm" />
                            <span class="min-w-0">
                                <span class="block font-display text-2xl font-semibold tabular-nums text-ink">{{ $statusCounts[$status->value] }}</span>
                                <span class="block text-sm text-ink-muted">{{ __($status->label()) }}</span>
                            </span>
                        </x-card>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            {{-- Needs you: only what the candidate can act on, each a link. --}}
            <x-card padding="none" class="overflow-hidden lg:col-span-2">
                <div class="px-5 pt-5 pb-4 sm:px-6">
                    <flux:heading size="lg" level="2">{{ __('Needs you') }}</flux:heading>
                </div>

                @if ($needsYou)
                    <ul class="divide-y divide-line border-t border-line">
                        @foreach ($closingSaved as $jobPosting)
                            <li>
                                <a href="{{ route('jobs.show', $jobPosting) }}" wire:navigate class="group flex items-center gap-4 px-5 py-4 transition hover:bg-surface sm:px-6">
                                    <x-icon-tile icon="clock" size="sm" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-ink group-hover:text-sunset-small">{{ __('Saved job closes in :time', ['time' => $jobPosting->expires_at->diffForHumans(null, true)]) }}</span>
                                        <span class="block truncate text-sm text-ink-muted">{{ $jobPosting->title }} &middot; {{ $jobPosting->company->name }}</span>
                                    </span>
                                    <flux:icon.chevron-right variant="mini" class="shrink-0 text-ink-muted" aria-hidden="true" />
                                </a>
                            </li>
                        @endforeach

                        @if ($importableCv)
                            <li>
                                <a href="{{ route('candidate.resume-import', $importableCv) }}" wire:navigate class="group flex items-center gap-4 px-5 py-4 transition hover:bg-surface sm:px-6">
                                    <x-icon-tile icon="document-arrow-down" size="sm" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-ink group-hover:text-sunset-small">{{ __('Fill your profile from your CV') }}</span>
                                        <span class="block text-sm text-ink-muted">{{ __('Without skills no job can show how well it fits you. We read :file and you pick what to add.', ['file' => $importableCv->original_filename]) }}</span>
                                    </span>
                                    <flux:icon.chevron-right variant="mini" class="shrink-0 text-ink-muted" aria-hidden="true" />
                                </a>
                            </li>
                        @endif

                        @if ($profileGaps !== [])
                            <li>
                                <a href="{{ route('candidate.profile.edit') }}" wire:navigate class="group flex items-center gap-4 px-5 py-4 transition hover:bg-surface sm:px-6">
                                    <x-icon-tile icon="user-circle" size="sm" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-ink group-hover:text-sunset-small">{{ __('Add :parts to your profile', ['parts' => collect($profileGaps)->map(fn ($gap) => \App\Http\Controllers\CandidateDashboardController::gapLabel($gap))->join(', ', ' and ')]) }}</span>
                                        <span class="block text-sm text-ink-muted">{{ __('These are what a company reads first when you apply.') }}</span>
                                    </span>
                                    <flux:icon.chevron-right variant="mini" class="shrink-0 text-ink-muted" aria-hidden="true" />
                                </a>
                            </li>
                        @endif

                        @foreach ($reviewable as $application)
                            <li>
                                <a href="{{ route('candidate.applications.show', $application) }}" wire:navigate class="group flex items-center gap-4 px-5 py-4 transition hover:bg-surface sm:px-6">
                                    <x-icon-tile icon="chat-bubble-left-right" size="sm" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium text-ink group-hover:text-sunset-small">{{ __('Review how :company hired', ['company' => $application->jobPosting->company->name]) }}</span>
                                        <span class="block text-sm text-ink-muted">{{ __('Your account helps the next person who applies there.') }}</span>
                                    </span>
                                    <flux:icon.chevron-right variant="mini" class="shrink-0 text-ink-muted" aria-hidden="true" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="px-5 pb-5 sm:px-6">
                        <x-empty-state icon="check-circle" :level="3" :heading="__('Nothing needs you right now')" :action-href="route('jobs.index')" :action-label="__('Find jobs')">
                            {{ __('A saved job about to close, a gap in your profile or a company you can review would show here.') }}
                        </x-empty-state>
                    </div>
                @endif
            </x-card>

            <div class="flex flex-col gap-6">
                <x-card padding="none" class="overflow-hidden">
                    <div class="flex items-center justify-between gap-3 px-5 pt-5 pb-4 sm:px-6">
                        <flux:heading size="lg" level="2">{{ __('Recent changes') }}</flux:heading>
                        @if ($recentChanges->isNotEmpty())
                            <flux:link :href="route('candidate.applications.index')" wire:navigate class="text-sm">{{ __('All applications') }}</flux:link>
                        @endif
                    </div>

                    @if ($recentChanges->isEmpty())
                        <p class="border-t border-line px-5 py-4 text-sm text-ink-muted sm:px-6">{{ __('When you apply, every step of each application shows here.') }}</p>
                    @else
                        <ul class="divide-y divide-line border-t border-line">
                            @foreach ($recentChanges as $change)
                                @php
                                    $local = \App\Support\LocalTime::of($change['at']);
                                @endphp
                                <li>
                                    <a href="{{ route('candidate.applications.show', $change['application']) }}" wire:navigate class="group block px-5 py-3 transition hover:bg-surface sm:px-6">
                                        <span class="block text-sm text-ink group-hover:text-sunset-small">{{ $change['line'] }}</span>
                                        <time class="block text-meta text-ink-muted" datetime="{{ $local->toIso8601String() }}" title="{{ $local->format(\App\Support\DateFormat::MOMENT) }}">{{ $change['at']->diffForHumans() }}</time>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                <div class="grid grid-cols-2 gap-4">
                    <x-card as="a" interactive padding="sm" :href="route('candidate.saved-jobs.index')" wire:navigate>
                        <span class="block font-display text-2xl font-semibold tabular-nums text-ink">{{ $savedCount }}</span>
                        <span class="block text-sm text-ink-muted">{{ trans_choice('Saved job|Saved jobs', $savedCount) }}</span>
                    </x-card>
                    <x-card as="a" interactive padding="sm" :href="route('candidate.job-alerts.index')" wire:navigate>
                        <span class="block font-display text-2xl font-semibold tabular-nums text-ink">{{ $alertCount }}</span>
                        <span class="block text-sm text-ink-muted">{{ trans_choice('Job alert on|Job alerts on', $alertCount) }}</span>
                    </x-card>
                </div>
            </div>
        </div>

        <section aria-labelledby="recommended-heading" class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg" level="2" id="recommended-heading">{{ __('Recommended for you') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Open jobs you have not applied to that match at least :percent% of what they ask for, best fit first.', ['percent' => \App\Http\Controllers\CandidateDashboardController::RECOMMEND_FROM_PERCENT]) }}</flux:text>
            </div>

            @if (! $hasSkills)
                {{-- Nothing to match on yet, so nothing is guessed. --}}
                <x-empty-state icon="sparkles" :level="3" :heading="__('Add your skills to see the jobs that fit you')" :action-href="$importableCv ? route('candidate.resume-import', $importableCv) : route('candidate.skills.edit')" :action-label="$importableCv ? __('Fill from your CV') : __('Add skills')">
                    {{ __('Each job is matched against your skills, with how well it fits.') }}
                </x-empty-state>
            @elseif ($recommended->isEmpty())
                <x-empty-state icon="magnifying-glass" :level="3" :heading="__('No strong match open right now')" :action-href="route('jobs.index')" :action-label="__('Browse all jobs')">
                    {{ __('New jobs come in every day. A job alert tells you when one fits.') }}
                </x-empty-state>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($recommended as $match)
                        <x-job-card :job-posting="$match['jobPosting']" :match-score="$match['score']" :show-save-button="true" />
                    @endforeach
                </div>
            @endif
        </section>

        @if ($recentlyViewed->isNotEmpty())
            <section aria-labelledby="viewed-heading" class="flex flex-col gap-4">
                <flux:heading size="lg" level="2" id="viewed-heading">{{ __('Recently viewed') }}</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($recentlyViewed as $jobPosting)
                        <x-job-card :job-posting="$jobPosting" :match-score="$recentlyViewedScores[$jobPosting->id] ?? null" :show-save-button="true" />
                    @endforeach
                </div>
            </section>
        @endif
    </x-page>
</x-layouts::app>
