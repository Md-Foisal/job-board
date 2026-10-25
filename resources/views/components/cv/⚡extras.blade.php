<?php

use App\Support\CvExtras;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/*
 * The parts of one saved CV that are kept with that CV, not on the
 * profile: interests, references, personal details, a declaration and
 * sections of the candidate's own. Each change is saved as it is made
 * and the builder's preview follows.
 */
new class extends Component {
    #[Locked]
    public int $versionId;

    public string $interests = '';

    public string $referenceMode = 'none';

    /** @var list<array<string, string>> */
    public array $references = [];

    /** @var array<string, string> */
    public array $personal = [];

    public bool $declaration = false;

    public string $declarationText = '';

    public string $declarationPlace = '';

    /** @var list<array{title: string, items: list<array<string, string>>}> */
    public array $custom = [];

    public function mount(): void
    {
        $this->load();
    }

    /**
     * Another part of the builder (a preset) changed these on the saved
     * CV.
     */
    #[On('cv-extras-changed')]
    public function load(): void
    {
        $extras = $this->version()->cvExtras();

        $this->interests = $extras->interests;
        $this->referenceMode = $extras->referenceMode;
        $this->references = $extras->references;
        $this->personal = [...array_fill_keys(array_keys(CvExtras::PERSONAL_FIELDS), ''), ...$extras->personal];
        $this->declaration = $extras->declaration;
        $this->declarationText = $extras->declarationText;
        $this->declarationPlace = $extras->declarationPlace;
        $this->custom = $extras->custom;
        $this->resetValidation();
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property, $this->rules(), [], $this->attributes());
        $this->persist();
    }

    public function addReference(): void
    {
        if (count($this->references) < CvExtras::MAX_REFERENCES) {
            $this->references[] = ['name' => '', 'position' => '', 'organization' => '', 'email' => '', 'phone' => '', 'relationship' => ''];
        }

        $this->referenceMode = 'list';
    }

    public function removeReference(int $index): void
    {
        unset($this->references[$index]);
        $this->references = array_values($this->references);
        $this->persist();
    }

    public function addSection(): void
    {
        if (count($this->custom) < CvExtras::MAX_CUSTOM_SECTIONS) {
            $this->custom[] = ['title' => '', 'items' => [['title' => '', 'subtitle' => '', 'dates' => '', 'description' => '']]];
        }
    }

    public function removeSection(int $index): void
    {
        unset($this->custom[$index]);
        $this->custom = array_values($this->custom);
        $this->persist();
    }

    public function addItem(int $section): void
    {
        if (isset($this->custom[$section]) && count($this->custom[$section]['items']) < CvExtras::MAX_CUSTOM_ITEMS) {
            $this->custom[$section]['items'][] = ['title' => '', 'subtitle' => '', 'dates' => '', 'description' => ''];
        }
    }

    public function removeItem(int $section, int $item): void
    {
        if (isset($this->custom[$section]['items'][$item])) {
            unset($this->custom[$section]['items'][$item]);
            $this->custom[$section]['items'] = array_values($this->custom[$section]['items']);
            $this->persist();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'interests' => ['string', 'max:300'],
            'referenceMode' => ['in:'.implode(',', CvExtras::REFERENCE_MODES)],
            'references' => ['array', 'max:'.CvExtras::MAX_REFERENCES],
            'references.*.name' => ['nullable', 'string', 'max:100'],
            'references.*.position' => ['nullable', 'string', 'max:100'],
            'references.*.organization' => ['nullable', 'string', 'max:150'],
            'references.*.email' => ['nullable', 'email', 'max:150'],
            'references.*.phone' => ['nullable', 'string', 'max:30'],
            'references.*.relationship' => ['nullable', 'string', 'max:100'],
            'personal.date_of_birth' => ['nullable', 'date', 'before:today'],
            'personal.permanent_address' => ['nullable', 'string', 'max:255'],
            'personal.*' => ['nullable', 'string', 'max:255'],
            'declaration' => ['boolean'],
            'declarationText' => ['string', 'max:500'],
            'declarationPlace' => ['string', 'max:100'],
            'custom' => ['array', 'max:'.CvExtras::MAX_CUSTOM_SECTIONS],
            'custom.*.title' => ['nullable', 'string', 'max:60'],
            'custom.*.items' => ['array', 'max:'.CvExtras::MAX_CUSTOM_ITEMS],
            'custom.*.items.*.title' => ['nullable', 'string', 'max:150'],
            'custom.*.items.*.subtitle' => ['nullable', 'string', 'max:150'],
            'custom.*.items.*.dates' => ['nullable', 'string', 'max:60'],
            'custom.*.items.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return [
            'personal.date_of_birth' => __('date of birth'),
            'references.*.email' => __('email'),
            'custom.*.title' => __('section title'),
        ];
    }

    private function persist(): void
    {
        $extras = CvExtras::fromArray([
            'interests' => $this->interests,
            'reference_mode' => $this->referenceMode,
            'references' => $this->references,
            'personal' => $this->personal,
            'declaration' => $this->declaration,
            'declaration_text' => $this->declarationText,
            'declaration_place' => $this->declarationPlace,
            'custom' => $this->custom,
        ]);

        $this->version()->update(['extras' => $extras->toArray()]);
        $this->dispatch('cv-updated');
    }

    private function version()
    {
        return auth()->user()->candidateProfile->cvVersions()->findOrFail($this->versionId);
    }
}; ?>

<x-profile.section id="cv-extras" :heading="__('Only on this CV')">
    <div class="space-y-8 border-t border-line px-5 py-5 sm:px-6">
        <flux:text size="sm">{{ __('Kept with this CV, not on your profile, so companies reading your profile never see them. Each part prints only when it has something in it.') }}</flux:text>

        {{-- Personal details: customary on Bangladeshi and other South Asian CVs. --}}
        <section class="space-y-4" aria-labelledby="cv-extras-personal">
            <div>
                <flux:heading id="cv-extras-personal">{{ __('Personal information') }}</flux:heading>
                <flux:text size="sm" class="mt-1">{{ __('Expected on many CVs in Bangladesh and South Asia, and left off in the US, UK and Canada. Leave any of it empty. Never add an NID or passport number: a CV is passed around.') }}</flux:text>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model.blur.live="personal.date_of_birth" :label="__('Date of birth')" />
                <flux:input wire:model.blur.live="personal.nationality" :label="__('Nationality')" placeholder="Bangladeshi" maxlength="100" />
                <flux:input wire:model.blur.live="personal.father_name" :label="__('Father\'s name')" maxlength="100" />
                <flux:input wire:model.blur.live="personal.mother_name" :label="__('Mother\'s name')" maxlength="100" />
                <flux:input wire:model.blur.live="personal.marital_status" :label="__('Marital status')" maxlength="100" />
                <flux:input wire:model.blur.live="personal.religion" :label="__('Religion')" maxlength="100" />
                <flux:input wire:model.blur.live="personal.gender" :label="__('Gender')" maxlength="100" />
            </div>
            <flux:textarea wire:model.blur.live="personal.permanent_address" :label="__('Permanent address')" rows="2" maxlength="255" />
        </section>

        <section class="space-y-4">
            <flux:textarea wire:model.blur.live="interests" :label="__('Interests')" :placeholder="__('Open-source, chess, photography')" rows="2" maxlength="300" />
        </section>

        <section class="space-y-4">
            <flux:radio.group wire:model.live="referenceMode" :label="__('References')">
                <flux:radio value="none" :label="__('Leave them off')" />
                <flux:radio value="on_request" :label="__('Say “Available on request”')" />
                <flux:radio value="list" :label="__('List them')" :description="__('Ask each person first.')" />
            </flux:radio.group>

            @if ($referenceMode === 'list')
                @foreach ($references as $index => $person)
                    <div wire:key="reference-{{ $index }}" class="space-y-3 rounded-control p-4 ring-1 ring-line">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <flux:input wire:model.blur.live="references.{{ $index }}.name" :label="__('Name')" maxlength="100" />
                            <flux:input wire:model.blur.live="references.{{ $index }}.position" :label="__('Position')" maxlength="100" />
                            <flux:input wire:model.blur.live="references.{{ $index }}.organization" :label="__('Organisation')" maxlength="150" />
                            <flux:input wire:model.blur.live="references.{{ $index }}.relationship" :label="__('How they know you')" :placeholder="__('My manager at Acme')" maxlength="100" />
                            <flux:input type="email" wire:model.blur.live="references.{{ $index }}.email" :label="__('Email')" maxlength="150" />
                            <flux:input wire:model.blur.live="references.{{ $index }}.phone" :label="__('Phone')" maxlength="30" />
                        </div>
                        <flux:button wire:click="removeReference({{ $index }})" variant="ghost" size="sm" icon="trash">{{ __('Remove') }}</flux:button>
                    </div>
                @endforeach

                @if (count($references) < \App\Support\CvExtras::MAX_REFERENCES)
                    <flux:button wire:click="addReference" size="sm" icon="plus">{{ __('Add a reference') }}</flux:button>
                @endif
            @endif
        </section>

        <section class="space-y-4" aria-labelledby="cv-extras-declaration">
            <flux:heading id="cv-extras-declaration">{{ __('Declaration') }}</flux:heading>
            <flux:switch wire:model.live="declaration" :label="__('End with a signed declaration')" :description="__('A line that the CV is true, a signature line and the date. Usual on CVs in Bangladesh.')" />
            @if ($declaration)
                <flux:textarea wire:model.blur.live="declarationText" :label="__('Wording')" :placeholder="__(\App\Support\CvExtras::DECLARATION_EN)" :description="__('Leave empty for the usual wording, in the language of the headings.')" rows="2" maxlength="500" />
                <flux:input wire:model.blur.live="declarationPlace" :label="__('Place')" :placeholder="__('Dhaka')" maxlength="100" />
            @endif
        </section>

        <section class="space-y-4" aria-labelledby="cv-extras-custom">
            <div>
                <flux:heading id="cv-extras-custom">{{ __('Your own sections') }}</flux:heading>
                <flux:text size="sm" class="mt-1">{{ __('Anything else under a heading of your choice: training, conferences, extracurricular activities.') }}</flux:text>
            </div>

            @foreach ($custom as $index => $section)
                <div wire:key="custom-{{ $index }}" class="space-y-4 rounded-control p-4 ring-1 ring-line">
                    <flux:input wire:model.blur.live="custom.{{ $index }}.title" :label="__('Section heading')" :placeholder="__('Training')" maxlength="60" />

                    @foreach ($section['items'] as $itemIndex => $item)
                        <div wire:key="custom-{{ $index }}-{{ $itemIndex }}" class="space-y-3 border-t border-line pt-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <flux:input wire:model.blur.live="custom.{{ $index }}.items.{{ $itemIndex }}.title" :label="__('Title')" maxlength="150" />
                                <flux:input wire:model.blur.live="custom.{{ $index }}.items.{{ $itemIndex }}.subtitle" :label="__('Where or with whom')" maxlength="150" />
                                <flux:input wire:model.blur.live="custom.{{ $index }}.items.{{ $itemIndex }}.dates" :label="__('When')" :placeholder="__('2025')" maxlength="60" />
                            </div>
                            <flux:textarea wire:model.blur.live="custom.{{ $index }}.items.{{ $itemIndex }}.description" :label="__('Details')" rows="2" maxlength="1000" />
                            <flux:button wire:click="removeItem({{ $index }}, {{ $itemIndex }})" variant="ghost" size="sm" icon="minus">{{ __('Remove this entry') }}</flux:button>
                        </div>
                    @endforeach

                    <div class="flex flex-wrap gap-2">
                        @if (count($section['items']) < \App\Support\CvExtras::MAX_CUSTOM_ITEMS)
                            <flux:button wire:click="addItem({{ $index }})" size="sm" icon="plus">{{ __('Add an entry') }}</flux:button>
                        @endif
                        <flux:button wire:click="removeSection({{ $index }})" variant="ghost" size="sm" icon="trash">{{ __('Remove this section') }}</flux:button>
                    </div>
                </div>
            @endforeach

            @if (count($custom) < \App\Support\CvExtras::MAX_CUSTOM_SECTIONS)
                <flux:button wire:click="addSection" size="sm" icon="plus">{{ __('Add a section') }}</flux:button>
            @endif
        </section>
    </div>
</x-profile.section>
