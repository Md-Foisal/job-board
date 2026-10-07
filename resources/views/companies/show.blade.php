@php
    $jobCount = $jobPostings->count();
    $sections = array_filter([
        'about' => $company->description ? __('About') : null,
        'jobs' => __('Jobs'),
        'reviews' => __('Reviews'),
    ]);
    $counts = ['jobs' => $jobCount, 'reviews' => $reviewSummary->count];
@endphp

<x-layouts::guest :title="$company->name">
    <div class="mx-auto max-w-6xl px-4 pt-6 sm:px-6 lg:pt-10">
        <x-breadcrumb :items="[['label' => $company->name]]" />

        {{-- One card: cover photo, profile photo, name and the links to the
             page's sections all belong to the same unit, so they live
             inside one bordered card instead of the avatar floating
             between the page and a separate card below it. --}}
        <x-card padding="none" class="mt-4 overflow-hidden">
            <div class="relative">
                <div class="h-32 w-full overflow-hidden bg-surface sm:h-44 md:h-56">
                    @if ($company->cover_photo_path)
                        <img
                            src="{{ \Illuminate\Support\Facades\Storage::url($company->cover_photo_path) }}"
                            alt=""
                            class="size-full object-cover"
                        >
                    @else
                        <div class="bg-sunset absolute inset-0">
                            <div
                                class="absolute inset-0 opacity-[0.15]"
                                style="background-image: radial-gradient(circle, white 1.5px, transparent 1.5px); background-size: 22px 22px;"
                            ></div>
                            <span class="pointer-events-none absolute -bottom-8 -right-2 select-none font-display text-[9rem] font-bold leading-none text-white/10 sm:-bottom-10 sm:text-[13rem]">
                                {{ \Illuminate\Support\Str::of($company->name)->substr(0, 1) }}
                            </span>
                        </div>
                    @endif
                </div>

                <x-company-logo :company="$company" size="xl" class="absolute -bottom-12 left-6 border-4 border-canvas shadow-md sm:-bottom-14 sm:left-8 sm:size-28" />
            </div>

            <div class="flex flex-col gap-5 px-6 pb-6 pt-16 sm:flex-row sm:items-end sm:justify-between sm:px-8 sm:pt-20">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-balance text-heading text-ink sm:text-title">{{ $company->name }}</h1>

                        @if ($company->verified_at)
                            <x-verified-badge size="lg" />
                        @endif
                    </div>

                    <p class="mt-1 text-ink-muted">
                        {{ collect([$company->identity_type->label(), $company->industry])->filter()->implode(' · ') }}
                    </p>

                    @if ($reviewSummary->count > 0)
                        <p class="mt-3 text-sm">
                            <a href="#reviews" class="inline-flex items-center gap-1.5 text-ink-muted hover:text-sunset-small">
                                @if ($reviewSummary->hasAverages())
                                    <x-rating-stars :value="$reviewSummary->overall" />
                                    <span class="font-medium tabular-nums text-ink" aria-hidden="true">{{ number_format($reviewSummary->overall, 1) }}</span>
                                @endif
                                <span class="underline underline-offset-2">{{ trans_choice(':count hiring process review|:count hiring process reviews', $reviewSummary->count, ['count' => $reviewSummary->count]) }}</span>
                            </a>
                        </p>
                    @endif
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($company->website_url)
                        <flux:button :href="$company->website_url" icon:trailing="arrow-top-right-on-square" target="_blank" rel="noopener noreferrer">
                            {{ __('Visit website') }}
                        </flux:button>
                    @endif

                    <livewire:report-button :reportable="$company" :key="'report-'.$company->id" />
                </div>
            </div>

            {{-- Jump links to the page's own sections, with how much is in
                 each. One page rather than tabs: the sections are short
                 enough to read in one scroll, and search engines see the
                 jobs and reviews on the company's own address. --}}
            <nav aria-label="{{ __('On this page') }}" class="flex gap-1 overflow-x-auto border-t border-line px-4 sm:px-6">
                @foreach ($sections as $id => $label)
                    <a href="#{{ $id }}" class="inline-flex shrink-0 items-center gap-2 border-b-2 border-transparent px-2 py-3 text-sm font-medium text-ink-muted transition-colors hover:border-line-strong hover:text-ink">
                        {{ $label }}
                        @isset($counts[$id])
                            <flux:badge size="sm" class="tabular-nums">{{ $counts[$id] }}</flux:badge>
                        @endisset
                    </a>
                @endforeach
            </nav>
        </x-card>

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-x-10">
            {{-- The facts first in the page's order, so on a phone they come
                 straight after the header; on a wide screen they sit in the
                 right-hand column, as on the job page. --}}
            <aside class="lg:col-start-2 lg:row-start-1">
                <x-card subtle padding="sm" class="lg:sticky lg:top-24">
                    <h2 class="text-meta font-semibold uppercase tracking-wide text-ink-muted">{{ __('Company details') }}</h2>
                    <dl class="mt-3 grid grid-cols-[8rem_minmax(0,1fr)] gap-x-3 gap-y-2.5 text-sm">
                        <dt class="text-ink-muted">{{ __('Type') }}</dt>
                        <dd class="text-ink">{{ $company->identity_type->label() }}</dd>

                        @if ($company->industry)
                            <dt class="text-ink-muted">{{ __('Industry') }}</dt>
                            <dd class="text-ink">{{ $company->industry }}</dd>
                        @endif

                        @if ($company->size)
                            <dt class="text-ink-muted">{{ __('Size') }}</dt>
                            <dd class="text-ink">{{ __(':size employees', ['size' => $company->size]) }}</dd>
                        @endif

                        <dt class="text-ink-muted">{{ __('Open jobs') }}</dt>
                        <dd class="tabular-nums text-ink">{{ $jobCount }}</dd>

                        <dt class="text-ink-muted">{{ __('On :app since', ['app' => config('app.name')]) }}</dt>
                        <dd class="tabular-nums text-ink">{{ \App\Support\LocalTime::of($company->created_at)->format('Y') }}</dd>
                    </dl>

                    @if ($responsivePercent !== null)
                        <x-responsive-badge :percent="$responsivePercent" show-detail class="mt-4 border-t border-line pt-4" />
                    @endif
                </x-card>
            </aside>

            <div class="min-w-0 space-y-12 lg:col-start-1 lg:row-start-1">
                @if ($company->description)
                    <section id="about" aria-labelledby="about-heading">
                        <h2 id="about-heading" class="text-heading text-ink">{{ __('About') }}</h2>
                        <div class="prose prose-zinc mt-4 max-w-none dark:prose-invert">
                            <div class="prose-content">{!! $company->description !!}</div>
                        </div>
                    </section>
                @endif

                <section id="jobs" aria-labelledby="jobs-heading">
                    <h2 id="jobs-heading" class="text-heading text-ink">{{ __('Open jobs') }}</h2>

                    @if ($jobPostings->isEmpty())
                        <x-empty-state icon="briefcase" :level="3" class="mt-4" :heading="__('No open jobs right now')">
                            {{ __('When :company posts a job, it will appear here.', ['company' => $company->name]) }}
                        </x-empty-state>
                    @else
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @foreach ($jobPostings as $jobPosting)
                                <x-job-card
                                    :job-posting="$jobPosting"
                                    :show-company="false"
                                    :show-save-button="$canSave"
                                    :saved="in_array($jobPosting->id, $savedJobIds, true)"
                                />
                            @endforeach
                        </div>
                    @endif
                </section>

                <section id="reviews" aria-labelledby="reviews-heading">
                    <h2 id="reviews-heading" class="text-heading text-ink">
                        {{ __('Hiring process reviews') }}
                    </h2>
                    <p class="mt-1 max-w-3xl text-sm text-ink-muted">
                        {{ __('Written by people who applied here through :app and read by our team before they appear. Names, jobs and outcomes are never shown.', ['app' => config('app.name')]) }}
                    </p>

                    @if ($reviewSummary->count === 0)
                        <x-empty-state icon="chat-bubble-left-right" :level="3" class="mt-6" :heading="__('No reviews yet.')">
                            {{ __('Anyone who applies here through :app can review the hiring process once they get a decision, reach an interview, or go :days days without an answer.', [
                                'app' => config('app.name'),
                                'days' => \App\Support\ReviewEligibility::UNANSWERED_DAYS,
                            ]) }}
                        </x-empty-state>
                    @else
                        @if ($reviewSummary->hasAverages())
                            <x-card as="dl" class="mt-6 grid gap-6 sm:grid-cols-3">
                                <div>
                                    <dt class="text-sm text-ink-muted">{{ __('Overall') }}</dt>
                                    <dd class="mt-1 flex items-center gap-2">
                                        <span class="font-display text-2xl font-semibold tabular-nums text-ink" aria-hidden="true">{{ number_format($reviewSummary->overall, 1) }}</span>
                                        <x-rating-stars :value="$reviewSummary->overall" size="lg" />
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-ink-muted">{{ __('Communication') }}</dt>
                                    <dd class="mt-1 flex items-center gap-2">
                                        <span class="font-display text-2xl font-semibold tabular-nums text-ink" aria-hidden="true">{{ number_format($reviewSummary->communication, 1) }}</span>
                                        <x-rating-stars :value="$reviewSummary->communication" size="lg" />
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-ink-muted">{{ __('Job as described') }}</dt>
                                    <dd class="mt-1 text-ink-soft">
                                        <span class="font-display text-2xl font-semibold tabular-nums text-ink">{{ $reviewSummary->asDescribed }}</span>
                                        {{ __('of :count said yes', ['count' => $reviewSummary->count]) }}
                                    </dd>
                                </div>
                            </x-card>
                            <p class="mt-2 text-xs text-ink-muted">
                                {{ trans_choice('Based on :count published review.|Based on :count published reviews.', $reviewSummary->count, ['count' => $reviewSummary->count]) }}
                            </p>
                        @else
                            <p class="mt-6 text-sm text-ink-muted">
                                {{ trans_choice(':count review so far. Averages appear once there are :min.|:count reviews so far. Averages appear once there are :min.', $reviewSummary->count, [
                                    'count' => $reviewSummary->count,
                                    'min' => \App\Support\ReviewSummary::MIN_FOR_AVERAGES,
                                ]) }}
                            </p>
                        @endif

                        <div class="mt-6 space-y-4">
                            @foreach ($reviews as $review)
                                <x-card as="article" aria-labelledby="review-{{ $review->id }}-title">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <x-rating-stars :value="$review->overall_rating" />
                                            <h3 id="review-{{ $review->id }}-title" class="mt-2 font-medium text-ink">{{ $review->title }}</h3>
                                            <p class="mt-1 text-xs text-ink-muted">
                                                {{ __('Verified applicant') }}
                                                <span class="mx-1">·</span>
                                                @php
                                                    $publishedAt = \App\Support\LocalTime::of($review->published_at);
                                                @endphp
                                                <time datetime="{{ $publishedAt->format('Y-m') }}">{{ $publishedAt->format(\App\Support\DateFormat::MONTH) }}</time>
                                            </p>
                                        </div>

                                        <livewire:report-button :reportable="$review" :key="'report-review-'.$review->id" />
                                    </div>

                                    <p class="mt-3 whitespace-pre-line text-sm text-ink-soft">{{ $review->body }}</p>

                                    <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                        <div class="flex gap-1">
                                            <dt class="text-ink-muted">{{ __('Communication') }}:</dt>
                                            <dd class="font-medium text-ink">{{ __(':n out of 5', ['n' => $review->communication_rating]) }}</dd>
                                        </div>
                                        <div class="flex gap-1">
                                            <dt class="text-ink-muted">{{ __('Job as described') }}:</dt>
                                            <dd class="font-medium text-ink">{{ $review->job_as_described->label() }}</dd>
                                        </div>
                                    </dl>

                                    <x-review-response :review="$review" :company="$company" />
                                </x-card>
                            @endforeach
                        </div>

                        @if ($reviews->hasPages())
                            <div class="mt-6">
                                {{ $reviews->links() }}
                            </div>
                        @endif
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-layouts::guest>
