{{--
    The employers' front door. Everything it promises is something the
    product does today; there are no invented numbers, logos or quotes on
    it. "Post a job" goes wherever this visitor's next step really is: an
    account for a guest, company setup for an account without a company,
    and the posting form for anyone who can already post.
--}}
@php
    $visitor = auth()->user();
    $postingCompany = $visitor?->activeCompanies->first(fn ($company) => $visitor->canManage($company));

    $postJobUrl = match (true) {
        $visitor === null => route('register', ['as' => 'employer']),
        $postingCompany !== null => route('employer.jobs.create', $postingCompany),
        default => route('companies.create'),
    };

    $steps = [
        ['icon' => 'building-office-2', 'title' => __('Set up your company'), 'text' => __('Add your name, logo and what you do. Our staff check that the company is real before it gets the Verified badge.')],
        ['icon' => 'document-plus', 'title' => __('Post a job'), 'text' => __('Skills, pay range, workplace and screening questions. While a company is new, our staff read its posts before they go public.')],
        ['icon' => 'user-group', 'title' => __('Review applicants'), 'text' => __('See each applicant’s skills next to what the job asks for, their answers and CV, and keep private notes with your team.')],
        ['icon' => 'check-circle', 'title' => __('Answer everyone'), 'text' => __('Move people through shortlisted, interview and offer, then hire or reject. You have ten minutes to undo before the email goes out.')],
    ];

    $features = [
        ['icon' => 'eye', 'title' => __('You decide, not an algorithm'), 'text' => __('Applicants are never scored or ranked by AI for you. You see the same skills match the candidate sees, and the call is yours.')],
        ['icon' => 'users', 'title' => __('Hire as a team'), 'text' => __('Invite colleagues as owner, manager or member. Nobody can act above their role.')],
        ['icon' => 'chart-bar', 'title' => __('See what is working'), 'text' => __('Views, applications, how far applicants get and how fast your team answers, with suggestions for each post.')],
        ['icon' => 'bolt', 'title' => __('Get known for answering'), 'text' => __('Companies that reply to the people who apply carry a Responsive employer mark on their job pages and company page.')],
        ['icon' => 'chat-bubble-left-right', 'title' => __('Answer reviews in public'), 'text' => __('Candidates can review your hiring process. You can reply where everyone reading it can see.')],
        ['icon' => 'identification', 'title' => __('One account, many companies'), 'text' => __('Agencies and independent recruiters can work for several companies and switch between them from the top bar.')],
    ];

    $questions = [
        [__('What does it cost?'), __('Nothing right now. Posting jobs, reviewing applicants and inviting your team are free.')],
        [__('Why is my job post waiting for review?'), __('While a company is new, our staff read its posts before the public sees them, to keep scams off the board. You will see the result on your job postings page.')],
        [__('How does a company get the Verified badge?'), __('Our staff check that the company is who it says it is, and may ask you for documents first.')],
        [__('Can I hire for more than one company?'), __('Yes. One account can belong to several companies, and your recruiter profile goes with you to each of them.')],
        [__('Can I also look for a job with the same account?'), __('Yes. You can start a candidate profile at any time, and the two sides stay separate.')],
    ];
@endphp

<x-layouts::guest :title="__('For employers')">
    <section class="relative overflow-hidden border-b border-line">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,var(--color-line-strong)_1px,transparent_0)] [background-size:22px_22px] [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_75%)]"></div>

        <div class="relative mx-auto flex max-w-4xl flex-col items-center px-6 py-20 text-center sm:py-28">
            <p class="text-meta font-semibold uppercase tracking-[0.12em] text-ink-muted">{{ __('For employers') }}</p>

            <h1 class="mt-4 text-title text-ink sm:text-display">
                {{ __('Hire people for') }} <span class="text-sunset">{{ __('the skills') }}</span> {{ __('the job needs') }}
            </h1>

            <p class="mt-6 max-w-2xl text-body text-ink-muted sm:text-lg">
                {{ __('Post a job, see how each applicant’s skills match it, and work through them with your team. Every candidate can see where they stand, so a company that answers gets noticed for it.') }}
            </p>

            <div class="mt-10 flex flex-col items-center gap-3 sm:flex-row">
                <flux:button :href="$postJobUrl" variant="primary" class="btn-sunset" icon:trailing="arrow-right">
                    {{ __('Post a job') }}
                </flux:button>

                @guest
                    <flux:button :href="route('login')" variant="ghost">
                        {{ __('Log in to your account') }}
                    </flux:button>
                @endguest
            </div>
        </div>
    </section>

    <section aria-labelledby="how-it-works" class="mx-auto max-w-6xl px-6 py-20">
        <h2 id="how-it-works" class="text-title text-ink">{{ __('How it works') }}</h2>

        <ol class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($steps as $number => $step)
                <li>
                    <x-card class="flex h-full flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <span class="flex size-10 items-center justify-center rounded-control bg-surface text-ink ring-1 ring-line">
                                <flux:icon :icon="$step['icon']" class="size-5" />
                            </span>
                            <span class="font-mono text-meta text-ink-muted">0{{ $number + 1 }}</span>
                        </div>
                        <h3 class="text-subheading text-ink">{{ $step['title'] }}</h3>
                        <p class="text-meta text-ink-muted">{{ $step['text'] }}</p>
                    </x-card>
                </li>
            @endforeach
        </ol>
    </section>

    <section aria-labelledby="what-you-get" class="border-y border-line bg-surface">
        <div class="mx-auto max-w-6xl px-6 py-20">
            <h2 id="what-you-get" class="text-title text-ink">{{ __('What you get') }}</h2>

            <div class="mt-10 grid gap-x-10 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as $feature)
                    <div class="flex gap-4">
                        <flux:icon :icon="$feature['icon']" class="mt-0.5 size-6 shrink-0 text-brand-600 dark:text-brand-400" />
                        <div>
                            <h3 class="text-subheading text-ink">{{ $feature['title'] }}</h3>
                            <p class="mt-1.5 text-meta text-ink-muted">{{ $feature['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section aria-labelledby="questions" class="mx-auto max-w-3xl px-6 py-20">
        <h2 id="questions" class="text-title text-ink">{{ __('Questions') }}</h2>

        <div class="mt-8 divide-y divide-line border-y border-line">
            @foreach ($questions as [$question, $answer])
                <details class="group py-5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-subheading text-ink [&::-webkit-details-marker]:hidden">
                        {{ $question }}
                        <flux:icon.plus class="size-5 shrink-0 text-ink-muted transition-transform duration-200 ease-brand group-open:rotate-45" />
                    </summary>
                    <p class="mt-3 text-body text-ink-muted">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-6">
        <div class="flex flex-col items-center gap-6 rounded-card bg-ink px-6 py-14 text-center text-canvas">
            <h2 class="text-title">{{ __('Ready to post your first job?') }}</h2>
            {{-- The gradient stays with the first Post a job at the top: one
                 per page. This one is the inverse of the band it sits on. --}}
            <flux:button :href="$postJobUrl" icon:trailing="arrow-right" class="border-transparent! bg-canvas! text-ink! hover:opacity-90">
                {{ __('Post a job') }}
            </flux:button>
        </div>
    </section>
</x-layouts::guest>
