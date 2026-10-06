<x-layouts::guest>
    <div class="bg-surface pb-16 pt-6">
        <div class="mx-auto max-w-6xl px-6">
            <nav class="text-sm text-ink-muted">
                <a href="{{ route('home') }}" class="inline-flex items-center hover:text-sunset-small" wire:navigate title="Home">
            <flux:icon.home variant="mini" class="size-4" />
        </a>
                <span class="mx-1">/</span>
                <span class="text-ink-soft">{{ $company->name }}</span>
            </nav>

            {{-- One card: cover photo, profile photo, name and bio all belong
                 to the same unit, so they live inside one bordered card
                 instead of the avatar floating between the page tray and a
                 separate card below it. --}}
            <x-card padding="none" class="mt-4 overflow-hidden">
                <div class="relative">
                    <div class="h-36 w-full overflow-hidden bg-surface sm:h-52 md:h-64">
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

                <div class="px-6 pb-6 pt-16 sm:px-8 sm:pb-8 sm:pt-20">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-balance font-display text-3xl font-bold text-ink">{{ $company->name }}</h1>

                        @if ($company->verified_at)
                            <x-verified-badge size="lg" />
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <x-chip>{{ $company->identity_type->label() }}</x-chip>
                        @if ($company->industry)
                            <x-chip>{{ $company->industry }}</x-chip>
                        @endif
                        @if ($company->size)
                            <span class="text-ink-muted">{{ $company->size }} employees</span>
                        @endif
                    </div>

                    <p class="mt-4 text-sm text-ink-muted">
                        <span class="font-semibold tabular-nums text-ink-soft">{{ $jobPostings->count() }}</span>
                        open {{ \Illuminate\Support\Str::plural('position', $jobPostings->count()) }}
                        <span class="mx-1.5">·</span>
                        On JobBoard since {{ \App\Support\LocalTime::of($company->created_at)->format('Y') }}
                    </p>

                    @if ($responsivePercent !== null)
                        <x-responsive-badge :percent="$responsivePercent" show-detail class="mt-3" />
                    @endif

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        @if ($company->website_url)
                            <flux:button href="{{ $company->website_url }}" variant="primary" icon:trailing="arrow-top-right-on-square" target="_blank" rel="noopener noreferrer">
                                Visit website
                            </flux:button>
                        @endif

                        <livewire:report-button :reportable="$company" :key="'report-'.$company->id" />
                    </div>

                    @if ($company->description)
                        <div class="prose prose-zinc mt-6 max-w-3xl dark:prose-invert">
                            <div class="prose-content">{!! $company->description !!}</div>
                        </div>
                    @endif
                </div>
            </x-card>

            <section class="mt-10">
                <h2 class="mb-6 font-display text-xl font-semibold text-ink">
                    Open positions
                </h2>

                @if ($jobPostings->isEmpty())
                    <x-empty-state icon="briefcase" :level="3" :heading="__('No open positions right now')">
                        {{ __('Check back soon.') }}
                    </x-empty-state>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($jobPostings as $jobPosting)
                            <x-job-card :job-posting="$jobPosting" />
                        @endforeach
                    </div>
                @endif
            </section>

            <section id="reviews" class="mt-12 scroll-mt-6" aria-labelledby="reviews-heading">
                <h2 id="reviews-heading" class="font-display text-xl font-semibold text-ink">
                    {{ __('Hiring process reviews') }}
                </h2>
                <p class="mt-1 max-w-3xl text-sm text-ink-muted">
                    {{ __('Written by people who applied here through JobBoard and read by our team before they appear. Names, jobs and outcomes are never shown.') }}
                </p>

                @if ($reviewSummary->count === 0)
                    <x-empty-state icon="chat-bubble-left-right" :level="3" class="mt-6" :heading="__('No reviews yet.')">
                        {{ __('Anyone who applies here through JobBoard can review the hiring process once they get a decision, reach an interview, or go a month without an answer.') }}
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
                                            @php($publishedAt = \App\Support\LocalTime::of($review->published_at))
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
</x-layouts::guest>
