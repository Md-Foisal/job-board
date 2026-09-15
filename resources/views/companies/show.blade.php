<x-layouts::guest>
    <div class="bg-zinc-50 pb-16 pt-6 dark:bg-zinc-950">
        <div class="mx-auto max-w-6xl px-6">
            <nav class="text-sm text-zinc-500 dark:text-zinc-500">
                <a href="{{ route('home') }}" class="inline-flex items-center hover:text-brand-700 dark:hover:text-brand-400" wire:navigate title="Home">
            <flux:icon.home variant="mini" class="size-4" />
        </a>
                <span class="mx-1">/</span>
                <span class="text-zinc-700 dark:text-zinc-300">{{ $company->name }}</span>
            </nav>

            {{-- One card: cover photo, profile photo, name and bio all belong
                 to the same unit, so they live inside one bordered card
                 instead of the avatar floating between the page tray and a
                 separate card below it. --}}
            <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <div class="relative">
                    <div class="h-36 w-full overflow-hidden bg-zinc-200 sm:h-52 md:h-64 dark:bg-zinc-800">
                        @if ($company->cover_photo_path)
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::url($company->cover_photo_path) }}"
                                alt=""
                                class="size-full object-cover"
                            >
                        @else
                            <div class="absolute inset-0 bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 dark:from-brand-800 dark:via-brand-900 dark:to-zinc-950">
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

                    <div class="absolute -bottom-12 left-6 flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-4 border-white bg-brand-50 text-3xl font-semibold text-brand-700 shadow-md dark:border-zinc-900 dark:bg-brand-950 dark:text-brand-300 sm:-bottom-14 sm:left-8 sm:size-28">
                        @if ($company->logo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="size-full object-cover">
                        @else
                            {{ \Illuminate\Support\Str::of($company->name)->substr(0, 1) }}
                        @endif
                    </div>
                </div>

                <div class="px-6 pb-6 pt-16 sm:px-8 sm:pb-8 sm:pt-20">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-balance font-display text-3xl font-bold text-zinc-900 dark:text-zinc-50">{{ $company->name }}</h1>

                        @if ($company->verified_at)
                            <span class="inline-flex items-center text-brand-600 dark:text-brand-400" title="Verified company">
                                <flux:icon.check-badge variant="mini" class="inline size-5" />
                            </span>
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ $company->identity_type->label() }}
                        </span>
                        @if ($company->industry)
                            <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $company->industry }}
                            </span>
                        @endif
                        @if ($company->size)
                            <span class="text-zinc-500 dark:text-zinc-500">{{ $company->size }} employees</span>
                        @endif
                    </div>

                    <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-500">
                        <span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-300">{{ $jobPostings->count() }}</span>
                        open {{ \Illuminate\Support\Str::plural('position', $jobPostings->count()) }}
                        <span class="mx-1.5">·</span>
                        On JobBoard since {{ $company->created_at->format('Y') }}
                    </p>

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
                            {!! nl2br(e($company->description)) !!}
                        </div>
                    @endif
                </div>
            </div>

            <section class="mt-10">
                <h2 class="mb-6 font-display text-xl font-semibold text-zinc-900 dark:text-zinc-50">
                    Open positions
                </h2>

                @if ($jobPostings->isEmpty())
                    <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-500">
                        No open positions right now — check back soon.
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($jobPostings as $jobPosting)
                            <x-job-card :job-posting="$jobPosting" />
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts::guest>
