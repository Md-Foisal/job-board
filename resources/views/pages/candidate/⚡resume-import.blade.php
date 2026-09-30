<?php

use App\Actions\ImportResumeToProfile;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Support\CvText;
use App\Support\ResumeDraft;
use Flux\Flux;
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
     * What the CV suggested, fixed on the server so the only thing the
     * browser can change is which of these are ticked.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $draft = [];

    /** @var array<int, string> */
    public array $chosenLinks = [];

    /** @var array<int, int> */
    public array $chosenSkills = [];

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

        $this->tickDefaults();
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

        foreach ($this->draft['links'] as $field => $suggested) {
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
            $this->draft['skills'],
        );
    }

    public function import(ImportResumeToProfile $import): void
    {
        $links = collect($this->links)
            ->reject(fn (array $link) => $link['same'])
            ->only($this->chosenLinks)
            ->map(fn (array $link) => $link['suggested'])
            ->all();

        $skillIds = collect($this->skills)
            ->reject(fn (array $skill) => $skill['has'])
            ->pluck('id')
            ->intersect(array_map('intval', $this->chosenSkills))
            ->values()
            ->all();

        if ($links === [] && $skillIds === []) {
            Flux::toast(variant: 'warning', text: __('Tick at least one thing to add.'));

            return;
        }

        $added = $import($this->profile(), ['profile' => $links, 'skill_ids' => $skillIds]);

        unset($this->links, $this->skills);
        $this->tickDefaults();

        Flux::toast(variant: 'success', text: trans_choice(
            '{1} Added 1 item to your profile.|[2,*] Added :count items to your profile.',
            array_sum($added),
        ));
    }

    private function tickDefaults(): void
    {
        $this->chosenLinks = collect($this->links)
            ->filter(fn (array $link) => $link['current'] === null)
            ->keys()
            ->all();

        $this->chosenSkills = collect($this->skills)
            ->reject(fn (array $skill) => $skill['has'])
            ->pluck('id')
            ->all();
    }

    private function profile()
    {
        return auth()->user()->candidateProfile;
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
    $newSkills = collect($skills)->reject(fn ($skill) => $skill['has']);
    $nothingFound = \App\Support\ResumeDraft::fromArray($draft)->isEmpty();
    $nothingLeft = collect($links)->every(fn ($link) => $link['same']) && $newSkills->isEmpty();
@endphp

<div class="mx-auto max-w-2xl px-6 py-10">
    <a href="{{ route('candidate.documents.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200">
        <flux:icon name="arrow-left" variant="micro" />
        {{ __('Documents') }}
    </a>

    <flux:heading size="xl" level="1" class="mt-4">{{ __('Fill your profile from your CV') }}</flux:heading>
    @if ($readable && ! $nothingFound)
        <flux:subheading>
            {{ __('We read :file and found the things below. Nothing is added until you choose it.', ['file' => $document->original_filename]) }}
        </flux:subheading>
    @endif

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
    @elseif ($nothingFound)
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
    @else
        <form wire:submit="import" class="mt-6 space-y-6">
            @if ($skills !== [])
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
