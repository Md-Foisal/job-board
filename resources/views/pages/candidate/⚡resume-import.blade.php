<?php

use App\Actions\ImportResumeToProfile;
use App\Enums\AiAvailability;
use App\Enums\AiFeature;
use App\Enums\DocumentType;
use App\Jobs\ParseResumeWithAi;
use App\Models\Document;
use App\Support\AiQuota;
use App\Support\CvText;
use App\Support\ResumeDraft;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Fill your profile from your CV')] class extends Component {
    private const LINK_LABELS = [
        'linkedin_url' => 'LinkedIn',
        'github_url' => 'GitHub',
        'portfolio_url' => 'Portfolio',
    ];

    #[Locked]
    public Document $document;

    #[Locked]
    public bool $readable = false;

    /**
     * What the product's own reading suggested, and what the AI reading
     * suggested, both fixed on the server so the only thing the browser
     * can change is which of these are ticked.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $draft = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $aiDraft = [];

    /**
     * Where the AI reading is: null (not asked), running, done, failed, or
     * unavailable (the allowance ran out before it started).
     */
    #[Locked]
    public ?string $aiStatus = null;

    /** @var array<int, string> */
    public array $chosenLinks = [];

    /** @var array<int, int> */
    public array $chosenSkills = [];

    /** @var array<int, string> */
    public array $chosenText = [];

    /** @var array<int, string> */
    public array $chosenContact = [];

    /** @var array<int, int> */
    public array $chosenExperience = [];

    /** @var array<int, int> */
    public array $chosenEducation = [];

    /**
     * Start months the candidate types in for entries the CV gave no start
     * date for, keyed by the entry's position.
     *
     * @var array<int, string>
     */
    public array $experienceStarts = [];

    /** @var array<int, string> */
    public array $educationStarts = [];

    public function mount(Document $document): void
    {
        // Someone else's file, a work sample or a certificate, or a format
        // that cannot be read are all simply not a page here.
        abort_unless(
            $document->document_type === DocumentType::Cv
                && auth()->user()->can('update', $document)
                && CvText::supports($document),
            404,
        );

        $this->document = $document;

        $text = CvText::of($document);
        $this->readable = $text !== '';
        $this->draft = ResumeDraft::fromText($text)->toArray();

        // Coming back to the page within the hour shows the AI's reading
        // again rather than asking for (and paying for) a new one.
        $result = Cache::get(ParseResumeWithAi::resultKey($document->id));

        if (($result['status'] ?? null) === 'done') {
            $this->aiDraft = $result['draft'];
            $this->aiStatus = 'done';
        } elseif (Cache::has(ParseResumeWithAi::runningKey($document->id))) {
            $this->aiStatus = 'running';
        }

        $this->tickDefaults();
    }

    #[Computed]
    public function aiAvailability(): AiAvailability
    {
        return AiQuota::availability(AiFeature::ResumeParser, auth()->user());
    }

    /**
     * The AI can read a PDF even when it is a scan, but a Word file only
     * through the text the product already reads.
     */
    #[Computed]
    public function aiCanRead(): bool
    {
        return str_ends_with(strtolower($this->document->file_path), '.pdf') || $this->readable;
    }

    public function readWithAi(): void
    {
        if ($this->aiStatus === 'running' || ! $this->aiCanRead) {
            return;
        }

        if ($this->aiAvailability !== AiAvailability::Available) {
            $this->aiStatus = 'unavailable';

            return;
        }

        $key = ParseResumeWithAi::attemptsKey(auth()->user());

        if (RateLimiter::tooManyAttempts($key, ParseResumeWithAi::DAILY_ATTEMPTS)) {
            Flux::toast(variant: 'warning', text: __('You can have the AI read a CV :count times a day. Try again tomorrow.', [
                'count' => ParseResumeWithAi::DAILY_ATTEMPTS,
            ]));

            return;
        }

        RateLimiter::hit($key, 24 * 60 * 60);

        Cache::forget(ParseResumeWithAi::resultKey($this->document->id));
        Cache::put(ParseResumeWithAi::runningKey($this->document->id), true, ParseResumeWithAi::RUNNING_SECONDS);

        ParseResumeWithAi::dispatch($this->document->id, auth()->id());

        $this->aiStatus = 'running';
        $this->checkAi();
    }

    /**
     * Polled while the AI reading runs. When the running mark has expired
     * without a result, the job never finished, and the page says so
     * instead of waiting for ever.
     */
    public function checkAi(): void
    {
        if ($this->aiStatus !== 'running') {
            return;
        }

        $result = Cache::get(ParseResumeWithAi::resultKey($this->document->id));

        if ($result === null) {
            if (! Cache::has(ParseResumeWithAi::runningKey($this->document->id))) {
                $this->aiStatus = 'failed';
            }

            return;
        }

        if ($result['status'] !== 'done') {
            $this->aiStatus = $result['status'] === 'unavailable' ? 'unavailable' : 'failed';

            return;
        }

        $skillsBefore = array_column($this->skills, 'id');
        $linksBefore = array_keys($this->links);

        $this->aiDraft = $result['draft'];
        $this->aiStatus = 'done';
        $this->forgetComputed();
        $this->tickNewSuggestions($skillsBefore, $linksBefore);
    }

    /**
     * Each suggested link with what the profile has now. Where the profile
     * is empty the link starts ticked; where it already has a different
     * address, replacing it is offered but starts unticked; where it has
     * the same address there is nothing to do.
     *
     * @return array<string, array{label: string, suggested: string, current: ?string, same: bool}>
     */
    #[Computed]
    public function links(): array
    {
        $profile = $this->profile();
        $links = [];

        foreach ($this->suggestions()->links as $field => $suggested) {
            if ($suggested === null) {
                continue;
            }

            $current = $profile->{$field};

            $links[$field] = [
                'label' => self::LINK_LABELS[$field],
                'suggested' => $suggested,
                'current' => $current,
                'same' => $current !== null && self::sameAddress($current, $suggested),
            ];
        }

        return $links;
    }

    /**
     * @return array<int, array{id: int, name: string, has: bool}>
     */
    #[Computed]
    public function skills(): array
    {
        $has = $this->profile()->skills()->pluck('skills.id')->all();

        return array_map(
            fn (array $skill) => $skill + ['has' => in_array($skill['id'], $has, true)],
            $this->suggestions()->skills,
        );
    }

    /**
     * The headline and summary the AI suggested, with what the profile
     * has now; the same ticking rules as links.
     *
     * @return array<string, array{label: string, suggested: string, current: ?string, same: bool}>
     */
    #[Computed]
    public function profileText(): array
    {
        $profile = $this->profile();
        $suggestions = $this->suggestions();
        $text = [];

        foreach (['headline' => $suggestions->headline, 'bio' => $suggestions->summary] as $field => $suggested) {
            if ($suggested === null) {
                continue;
            }

            $current = filled($profile->{$field}) ? $profile->{$field} : null;

            $text[$field] = [
                'label' => $field === 'headline' ? __('Headline') : __('Summary'),
                'suggested' => $suggested,
                'current' => $current,
                'same' => $current !== null && trim($current) === $suggested,
            ];
        }

        return $text;
    }

    /**
     * The phone number and location the AI found, offered only where the
     * profile has none: what the candidate typed on their profile is
     * newer than an old CV, and replacing it is a profile edit, not an
     * import.
     *
     * @return array<string, array{label: string, suggested: string, current: null, same: false}>
     */
    #[Computed]
    public function contact(): array
    {
        $profile = $this->profile();
        $suggestions = $this->suggestions();
        $contact = [];

        foreach (['phone' => $suggestions->phone, 'location' => $suggestions->location] as $field => $suggested) {
            if ($suggested === null || filled($profile->{$field})) {
                continue;
            }

            $contact[$field] = [
                'label' => $field === 'phone' ? __('Phone') : __('Location'),
                'suggested' => $suggested,
                'current' => null,
                'same' => false,
            ];
        }

        return $contact;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function experience(): array
    {
        $existing = $this->profile()->experienceRecords()->get()
            ->map(fn ($record) => ImportResumeToProfile::key($record->company_name, $record->job_title, $record->start_date))
            ->all();

        return array_map(fn (array $entry) => $entry + [
            'has' => $entry['start_month'] !== null
                && in_array(ImportResumeToProfile::key($entry['company_name'], $entry['job_title'], self::month($entry['start_month'])), $existing, true),
        ], $this->suggestions()->experience);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function education(): array
    {
        $existing = $this->profile()->educationRecords()->get()
            ->map(fn ($record) => ImportResumeToProfile::key($record->institution_name, (string) $record->degree, $record->start_date))
            ->all();

        return array_map(fn (array $entry) => $entry + [
            'has' => $entry['start_month'] !== null
                && in_array(ImportResumeToProfile::key($entry['institution_name'], (string) $entry['degree'], self::month($entry['start_month'])), $existing, true),
        ], $this->suggestions()->education);
    }

    public function import(ImportResumeToProfile $import): void
    {
        $this->resetErrorBag();

        $profile = collect([...$this->links, ...$this->profileText, ...$this->contact])
            ->reject(fn (array $item) => $item['same'])
            ->only([...$this->chosenLinks, ...$this->chosenText, ...$this->chosenContact])
            ->map(fn (array $item) => $item['suggested'])
            ->all();

        $skillIds = collect($this->skills)
            ->reject(fn (array $skill) => $skill['has'])
            ->pluck('id')
            ->intersect(array_map('intval', $this->chosenSkills))
            ->values()
            ->all();

        $experience = $this->chosenEntries($this->experience, $this->chosenExperience, $this->experienceStarts, 'experienceStarts');
        $education = $this->chosenEntries($this->education, $this->chosenEducation, $this->educationStarts, 'educationStarts');

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        if ($profile === [] && $skillIds === [] && $experience === [] && $education === []) {
            Flux::toast(variant: 'warning', text: __('Tick at least one thing to add.'));

            return;
        }

        $added = $import($this->profile(), [
            'profile' => $profile,
            'skill_ids' => $skillIds,
            'experience' => $experience,
            'education' => $education,
        ]);

        $this->forgetComputed();
        $this->tickDefaults();

        Flux::toast(variant: 'success', text: trans_choice(
            '{1} Added 1 item to your profile.|[2,*] Added :count items to your profile.',
            array_sum($added),
        ));
    }

    /**
     * The ticked entries, as rows the import can write. An entry without a
     * start date needs one typed in first, and an end date cannot come
     * before the start; either problem is shown on that entry and nothing
     * is added.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<int, int|string>  $chosen
     * @param  array<int, string>  $typedStarts
     * @return array<int, array<string, ?string>>
     */
    private function chosenEntries(array $entries, array $chosen, array $typedStarts, string $errorKey): array
    {
        $rows = [];

        foreach (array_map('intval', $chosen) as $index) {
            $entry = $entries[$index] ?? null;

            if ($entry === null || $entry['has']) {
                continue;
            }

            $start = $entry['start_month'] ?? ($typedStarts[$index] ?? null);

            if (! is_string($start) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $start)) {
                $this->addError("{$errorKey}.{$index}", __('Add the month this started to include it.'));

                continue;
            }

            if ($entry['end_month'] !== null && $entry['end_month'] < $start) {
                $this->addError("{$errorKey}.{$index}", __('This ends before it starts. Fix it on your profile after adding, or leave it out.'));

                continue;
            }

            $row = collect($entry)->except(['has', 'start_month', 'end_month'])->all();
            $row['start_date'] = $start.'-01';
            $row['end_date'] = $entry['end_month'] !== null ? $entry['end_month'].'-01' : null;
            $rows[] = $row;
        }

        return $rows;
    }

    private function suggestions(): ResumeDraft
    {
        $draft = ResumeDraft::fromArray($this->draft);

        return $this->aiDraft === [] ? $draft : $draft->merge(ResumeDraft::fromArray($this->aiDraft));
    }

    private function tickDefaults(): void
    {
        $this->chosenLinks = collect($this->links)->filter(fn (array $item) => $item['current'] === null)->keys()->all();
        $this->chosenSkills = collect($this->skills)->reject(fn (array $skill) => $skill['has'])->pluck('id')->all();
        $this->tickAiOnlyDefaults();
    }

    /**
     * When the AI's reading arrives, what the candidate already ticked or
     * unticked stays as it is; only suggestions that are new get their
     * default.
     *
     * @param  array<int, int>  $skillsBefore
     * @param  array<int, string>  $linksBefore
     */
    private function tickNewSuggestions(array $skillsBefore, array $linksBefore): void
    {
        $newLinks = collect($this->links)
            ->filter(fn (array $item, string $field) => $item['current'] === null && ! in_array($field, $linksBefore, true))
            ->keys();

        $newSkills = collect($this->skills)
            ->filter(fn (array $skill) => ! $skill['has'] && ! in_array($skill['id'], $skillsBefore, true))
            ->pluck('id');

        $this->chosenLinks = [...$this->chosenLinks, ...$newLinks];
        $this->chosenSkills = [...$this->chosenSkills, ...$newSkills];
        $this->tickAiOnlyDefaults();
    }

    /**
     * Headline and summary start ticked only where the profile has none,
     * and so do phone and location, which are only offered then; roles
     * and courses start ticked unless already on the profile or still
     * missing a start date.
     */
    private function tickAiOnlyDefaults(): void
    {
        $this->chosenText = collect($this->profileText)->filter(fn (array $item) => $item['current'] === null)->keys()->all();
        $this->chosenContact = array_keys($this->contact);
        $this->chosenExperience = self::tickableEntries($this->experience);
        $this->chosenEducation = self::tickableEntries($this->education);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, int>
     */
    private static function tickableEntries(array $entries): array
    {
        return collect($entries)
            ->filter(fn (array $entry) => ! $entry['has'] && $entry['start_month'] !== null)
            ->keys()
            ->all();
    }

    private function forgetComputed(): void
    {
        unset($this->links, $this->skills, $this->profileText, $this->contact, $this->experience, $this->education);
    }

    private function profile()
    {
        return auth()->user()->candidateProfile;
    }

    private static function month(string $month): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $month);
    }

    private static function sameAddress(string $a, string $b): bool
    {
        $normalise = fn (string $url) => rtrim(strtolower(preg_replace('#^https?://(www\.)?#i', '', $url)), '/');

        return $normalise($a) === $normalise($b);
    }
}; ?>

@php
    $links = $this->links;
    $skills = $this->skills;
    $profileText = $this->profileText;
    $contact = $this->contact;
    $experience = $this->experience;
    $education = $this->education;
    $unmatchedSkills = $aiDraft['unmatched_skills'] ?? [];

    $hasSomething = ! \App\Support\ResumeDraft::fromArray($draft)
        ->merge(\App\Support\ResumeDraft::fromArray($aiDraft))
        ->isEmpty();

    $nothingLeft = $contact === []
        && collect([...$links, ...$profileText])->every(fn ($item) => $item['same'])
        && collect($skills)->every(fn ($skill) => $skill['has'])
        && collect([...$experience, ...$education])->every(fn ($entry) => $entry['has']);

    $offerAi = $this->aiCanRead && $this->aiAvailability === \App\Enums\AiAvailability::Available;
    $monthLabel = fn (?string $month) => $month ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month)->format(\App\Support\DateFormat::MONTH) : null;
@endphp

<div class="mx-auto max-w-2xl px-6 py-10">
    <a href="{{ route('candidate.documents.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200">
        <flux:icon name="arrow-left" variant="micro" />
        {{ __('Documents') }}
    </a>

    <flux:heading size="xl" level="1" class="mt-4">{{ __('Fill your profile from your CV') }}</flux:heading>
    @if ($hasSomething)
        <flux:subheading>
            {{ __('We read :file and found the things below. Nothing is added until you choose it.', ['file' => $document->original_filename]) }}
        </flux:subheading>
    @endif

    {{-- The AI reading: offered, running, failed, or out of allowance. --}}
    @if ($aiStatus === 'running')
        <div wire:poll.2s="checkAi" role="status" class="mt-6 flex items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-5 text-brand-900 dark:border-brand-800 dark:bg-brand-950 dark:text-brand-100">
            <flux:icon.loading variant="mini" />
            <div>
                <p class="font-medium">{{ __('Reading your CV…') }}</p>
                <p class="text-sm">{{ __('This usually takes under a minute. You can stay on this page.') }}</p>
            </div>
        </div>
    @elseif ($aiStatus === 'unavailable' || ($aiStatus === null && $this->aiCanRead && $this->aiAvailability === \App\Enums\AiAvailability::LimitReached))
        <flux:text size="sm" class="mt-6">{{ __("You've used this month's AI readings. What we found ourselves is below.") }}</flux:text>
    @elseif ($offerAi && in_array($aiStatus, [null, 'failed'], true))
        <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            @if ($aiStatus === 'failed')
                <flux:heading>{{ __("The AI couldn't read your CV this time") }}</flux:heading>
                <flux:text size="sm" class="mt-1">{{ __('Nothing was used from your allowance. You can try again, or use what we found ourselves.') }}</flux:text>
            @else
                <flux:heading>{{ __('Have the AI read the rest') }}</flux:heading>
                <flux:text size="sm" class="mt-1">{{ __('It can suggest your headline, summary, phone number, location, roles and education from this CV. You choose what is added.') }}</flux:text>
            @endif
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <flux:button wire:click="readWithAi" icon="sparkles" size="sm">
                    {{ $aiStatus === 'failed' ? __('Try again') : __('Read with AI') }}
                </flux:button>
                <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __("Your CV is sent to Anthropic to be read. Anthropic doesn't train on it and, by default, deletes it within 30 days.") }}
                </flux:text>
            </div>
        </div>
    @elseif ($aiStatus === 'done')
        <flux:text size="sm" class="mt-6">{{ __('The AI read your CV. Its suggestions are included below; check each one before adding it.') }}</flux:text>
    @elseif ($hasSomething)
        <flux:text size="sm" class="mt-6">
            {{ __('We pick out skills and profile links. Add your work history and education yourself, on the') }}
            <flux:link :href="route('candidate.experience.index')" wire:navigate>{{ __('Experience') }}</flux:link>
            {{ __('and') }}
            <flux:link :href="route('candidate.education.index')" wire:navigate>{{ __('Education') }}</flux:link>
            {{ __('pages.') }}
        </flux:text>
    @endif

    @if (! $hasSomething)
        @if (! $readable)
            <div class="mt-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                <flux:heading size="lg">{{ __("We couldn't read any text in this CV") }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('It may be a scanned image, or protected with a password. You can still fill your profile in by hand.') }}
                </flux:text>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <flux:button :href="route('candidate.profile.edit')" wire:navigate size="sm">{{ __('Profile') }}</flux:button>
                    <flux:button :href="route('candidate.experience.index')" wire:navigate size="sm">{{ __('Experience') }}</flux:button>
                    <flux:button :href="route('candidate.skills.edit')" wire:navigate size="sm">{{ __('Skills') }}</flux:button>
                </div>
            </div>
        @elseif ($aiStatus !== 'running')
            <div class="mt-6 rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                <flux:heading size="lg">{{ __('No skills or profile links found') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __("We looked for skills from our list and for LinkedIn, GitHub and portfolio links, and didn't find any. You can add them yourself.") }}
                </flux:text>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <flux:button :href="route('candidate.skills.edit')" wire:navigate size="sm">{{ __('Add skills') }}</flux:button>
                    <flux:button :href="route('candidate.profile.edit')" wire:navigate size="sm">{{ __('Add links') }}</flux:button>
                </div>
            </div>
        @endif
    @else
        <form wire:submit="import" class="mt-6 space-y-6">
            @if ($profileText !== [])
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ __('About you') }}</flux:heading>

                    <ul class="mt-4 space-y-4">
                        @foreach ($profileText as $field => $item)
                            <li wire:key="text-{{ $field }}">
                                @if ($item['same'])
                                    <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        <flux:icon name="check" variant="micro" />
                                        {{ $item['label'] }} <span>· {{ __('already on your profile') }}</span>
                                    </div>
                                @else
                                    <flux:checkbox wire:model="chosenText" value="{{ $field }}"
                                        :label="$item['current'] === null ? $item['label'] : __('Replace your :label', ['label' => strtolower($item['label'])])" />
                                    <p class="mt-1 ml-7 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $item['suggested'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($contact !== [])
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ __('Contact for your CVs') }}</flux:heading>
                    <flux:text size="sm">{{ __("Shown on CVs you build here. Companies don't see these on your profile.") }}</flux:text>

                    <ul class="mt-4 space-y-3">
                        @foreach ($contact as $field => $item)
                            <li wire:key="contact-{{ $field }}">
                                <flux:checkbox wire:model="chosenContact" value="{{ $field }}" :label="$item['label']" :description="$item['suggested']" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @foreach ([
                'experience' => ['title' => __('Experience'), 'entries' => $experience, 'chosen' => 'chosenExperience', 'starts' => 'experienceStarts'],
                'education' => ['title' => __('Education'), 'entries' => $education, 'chosen' => 'chosenEducation', 'starts' => 'educationStarts'],
            ] as $kind => $group)
                @if ($group['entries'] !== [])
                    <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading>{{ $group['title'] }}</flux:heading>

                        <ul class="mt-4 space-y-4">
                            @foreach ($group['entries'] as $index => $entry)
                                @php
                                    $title = $kind === 'experience'
                                        ? __(':title at :company', ['title' => $entry['job_title'], 'company' => $entry['company_name']])
                                        : collect([$entry['degree'], $entry['field_of_study']])->filter()->implode(', ').($entry['degree'] || $entry['field_of_study'] ? ' · ' : '').$entry['institution_name'];
                                    $dates = $entry['start_month']
                                        ? $monthLabel($entry['start_month']).' – '.($monthLabel($entry['end_month']) ?? __('Present'))
                                        : null;
                                @endphp
                                <li wire:key="{{ $kind }}-{{ $index }}">
                                    @if ($entry['has'])
                                        <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                            <flux:icon name="check" variant="micro" />
                                            {{ $title }} <span>· {{ __('already on your profile') }}</span>
                                        </div>
                                    @else
                                        <flux:checkbox wire:model="{{ $group['chosen'] }}" value="{{ $index }}" :label="$title" :description="$dates" />
                                        @if ($entry['start_month'] === null)
                                            <div class="mt-2 ml-7 max-w-48">
                                                <flux:input type="month" size="sm" wire:model="{{ $group['starts'] }}.{{ $index }}" :label="__('Started')" />
                                                <flux:text size="sm" class="mt-1">{{ __('Your CV gives no start date. Add it to include this.') }}</flux:text>
                                            </div>
                                        @endif
                                        @error($group['starts'].'.'.$index)
                                            <p class="mt-1 ml-7 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror
                                        @if ($kind === 'experience' && $entry['description'])
                                            <p class="mt-1 ml-7 whitespace-pre-line text-sm text-zinc-600 dark:text-zinc-400">{{ $entry['description'] }}</p>
                                        @endif
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach

            @if ($skills !== [] || $unmatchedSkills !== [])
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ __('Skills') }}</flux:heading>
                    <flux:text size="sm">{{ __('Skills from our list that your CV mentions. New ones are added at intermediate level; you can change that on the Skills page.') }}</flux:text>

                    <ul class="mt-4 space-y-3">
                        @foreach ($skills as $skill)
                            <li wire:key="skill-{{ $skill['id'] }}">
                                @if ($skill['has'])
                                    <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        <flux:icon name="check" variant="micro" />
                                        {{ $skill['name'] }}
                                        <span>· {{ __('already on your profile') }}</span>
                                    </div>
                                @else
                                    <flux:checkbox wire:model="chosenSkills" value="{{ $skill['id'] }}" :label="$skill['name']" />
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if ($unmatchedSkills !== [])
                        <flux:text size="sm" class="mt-4">
                            {{ __('Not on our list, so not added: :skills.', ['skills' => implode(', ', $unmatchedSkills)]) }}
                        </flux:text>
                    @endif
                </section>
            @endif

            @if ($links !== [])
                <section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>{{ __('Links') }}</flux:heading>

                    <ul class="mt-4 space-y-3">
                        @foreach ($links as $field => $link)
                            <li wire:key="link-{{ $field }}">
                                @if ($link['same'])
                                    <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        <flux:icon name="check" variant="micro" />
                                        {{ $link['label'] }}: <span class="break-all">{{ $link['suggested'] }}</span>
                                        <span>· {{ __('already on your profile') }}</span>
                                    </div>
                                @elseif ($link['current'] === null)
                                    <flux:checkbox wire:model="chosenLinks" value="{{ $field }}"
                                        :label="$link['label']" :description="$link['suggested']" />
                                @else
                                    <flux:checkbox wire:model="chosenLinks" value="{{ $field }}"
                                        :label="__('Replace your :label link', ['label' => $link['label']])"
                                        :description="__(':current → :suggested', ['current' => $link['current'], 'suggested' => $link['suggested']])" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($nothingLeft)
                    <flux:text size="sm" class="mr-auto">{{ __('Everything this CV suggests is already on your profile.') }}</flux:text>
                @endif
                <flux:button :href="route('candidate.profile.edit')" wire:navigate variant="ghost">{{ __('View profile') }}</flux:button>
                @unless ($nothingLeft)
                    <flux:button type="submit" variant="primary">{{ __('Add to my profile') }}</flux:button>
                @endunless
            </div>
        </form>
    @endif
</div>
