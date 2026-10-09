<?php

use App\Enums\AlertFrequency;
use App\Enums\EmploymentType;
use App\Enums\WorkplaceType;
use App\Models\Category;
use App\Models\JobAlert;
use App\Models\Skill;
use App\Rules\CurrencyInUse;
use App\Support\JobSearchCriteria;
use App\Support\PublicCache;
use App\Support\SalaryCurrencies;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Job alerts')] class extends Component {
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $frequency = 'daily';

    public string $q = '';

    public ?int $skill = null;

    public ?int $category = null;

    public string $location = '';

    public ?string $workplaceType = null;

    public ?string $employmentType = null;

    public ?string $currency = null;

    public ?int $salaryMin = null;

    public ?int $salaryMax = null;

    public ?int $experience = null;

    /**
     * "Create job alert" on the search page lands here with its filters in
     * the address, so the form opens already describing that search.
     */
    public function mount(): void
    {
        if (request()->boolean('create')) {
            $this->create();
            $this->fillCriteria(JobSearchCriteria::from(request()->query()));
            [$skillNames, $categoryNames] = JobSearchCriteria::names([$this->criteria()]);
            $this->name = Str::limit(Str::ucfirst(implode(', ', JobSearchCriteria::describe($this->criteria(), $skillNames, $categoryNames))), 90);
        }
    }

    public function with(): array
    {
        $jobAlerts = auth()->user()->jobAlerts()->latest()->latest('id')->get();
        [$skillNames, $categoryNames] = JobSearchCriteria::names($jobAlerts->pluck('criteria'));

        return [
            'jobAlerts' => $jobAlerts,
            'atLimit' => $jobAlerts->count() >= JobAlert::MAX_PER_CANDIDATE,
            'descriptions' => $jobAlerts->mapWithKeys(fn (JobAlert $jobAlert) => [
                $jobAlert->id => implode(' · ', JobSearchCriteria::describe($jobAlert->criteria, $skillNames, $categoryNames)),
            ]),
            'skills' => PublicCache::lookupModels('skills', Skill::class, fn () => Skill::orderBy('name')->get()),
            'categories' => PublicCache::lookupModels('categories', Category::class, fn () => Category::orderBy('name')->get()),
            'workplaceTypes' => WorkplaceType::cases(),
            'employmentTypes' => EmploymentType::cases(),
            'frequencies' => AlertFrequency::cases(),
        ];
    }

    public function create(): void
    {
        $this->resetForm();

        if (auth()->user()->jobAlerts()->count() >= JobAlert::MAX_PER_CANDIDATE) {
            Flux::toast(variant: 'warning', text: __('You can keep up to :limit job alerts. Remove one you no longer need to add another.', [
                'limit' => JobAlert::MAX_PER_CANDIDATE,
            ]));

            return;
        }

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $jobAlert = auth()->user()->jobAlerts()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $jobAlert->id;
        $this->name = $jobAlert->name;
        $this->frequency = $jobAlert->frequency->value;
        $this->fillCriteria($jobAlert->criteria);
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'frequency' => ['required', Rule::enum(AlertFrequency::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'skill' => ['nullable', 'integer', Rule::exists('skills', 'id')->withoutTrashed()],
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')->withoutTrashed()],
            'location' => ['nullable', 'string', 'max:100'],
            'workplaceType' => ['nullable', Rule::enum(WorkplaceType::class)],
            'employmentType' => ['nullable', Rule::enum(EmploymentType::class)],
            // Amounts are only ever compared within one currency.
            'currency' => ['required_with:salaryMin,salaryMax', 'nullable', 'string', new CurrencyInUse],
            'salaryMin' => ['nullable', 'integer', 'min:1'],
            'salaryMax' => ['nullable', 'integer', 'min:1', 'gte:salaryMin'],
            'experience' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], attributes: [
            'q' => __('keywords'),
            'currency' => __('pay currency'),
            'salaryMin' => __('minimum pay'),
            'salaryMax' => __('maximum pay'),
            'experience' => __('years of experience'),
        ]);

        $data = [
            'name' => $this->name,
            'frequency' => $this->frequency,
            'criteria' => $this->criteria(),
        ];

        if ($this->editingId) {
            auth()->user()->jobAlerts()->findOrFail($this->editingId)->update($data);
            Flux::toast(variant: 'success', text: __('Job alert updated.'));
        } else {
            // Checked again here, not only when the form opened: two tabs
            // could each have opened it while one slot was left.
            if (auth()->user()->jobAlerts()->count() >= JobAlert::MAX_PER_CANDIDATE) {
                $this->addError('name', __('You can keep up to :limit job alerts.', ['limit' => JobAlert::MAX_PER_CANDIDATE]));

                return;
            }

            auth()->user()->jobAlerts()->create($data);
            Flux::toast(variant: 'success', text: __("Job alert saved. We'll email you when new jobs match."));
        }

        $this->closeModal();
    }

    public function toggle(int $id): void
    {
        $jobAlert = auth()->user()->jobAlerts()->findOrFail($id);
        $jobAlert->update(['is_active' => ! $jobAlert->is_active]);

        Flux::toast(text: $jobAlert->is_active ? __('Emails for :name are on again.', ['name' => $jobAlert->name]) : __('Emails for :name are paused.', ['name' => $jobAlert->name]));
    }

    public function delete(int $id): void
    {
        auth()->user()->jobAlerts()->findOrFail($id)->delete();

        $this->closeModal();
        Flux::toast(text: __('Job alert deleted.'));
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function criteria(): array
    {
        return JobSearchCriteria::from([
            'q' => $this->q,
            'skill' => $this->skill,
            'category' => $this->category,
            'location' => $this->location,
            'workplaceType' => $this->workplaceType,
            'employmentType' => $this->employmentType,
            'currency' => $this->currency,
            'salaryMin' => $this->salaryMin,
            'salaryMax' => $this->salaryMax,
            'experience' => $this->experience,
        ]);
    }

    private function fillCriteria(array $criteria): void
    {
        $this->q = $criteria['q'] ?? '';
        $this->skill = $criteria['skill'] ?? null;
        $this->category = $criteria['category'] ?? null;
        $this->location = $criteria['location'] ?? '';
        $this->workplaceType = $criteria['workplaceType'] ?? null;
        $this->employmentType = $criteria['employmentType'] ?? null;
        $this->currency = $criteria['currency'] ?? null;
        $this->salaryMin = $criteria['salaryMin'] ?? null;
        $this->salaryMax = $criteria['salaryMax'] ?? null;
        $this->experience = $criteria['experience'] ?? null;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'frequency', 'q', 'skill', 'category', 'location', 'workplaceType', 'employmentType', 'currency', 'salaryMin', 'salaryMax', 'experience']);
        $this->resetValidation();
    }
}; ?>

<x-page>
    <x-page-header :title="__('Job alerts')">
        <x-slot:actions>
            {{-- Shut, with the reason on hover, once the limit is reached:
                 better than a button that opens only to say no. --}}
            @if ($atLimit)
                <flux:tooltip :content="__('You can keep up to :limit job alerts. Delete one to add another.', ['limit' => JobAlert::MAX_PER_CANDIDATE])">
                    <div>
                        <flux:button variant="primary" icon="plus" disabled>{{ __('New alert') }}</flux:button>
                    </div>
                </flux:tooltip>
            @else
                <flux:button wire:click="create" variant="primary" class="btn-sunset" icon="plus">{{ __('New alert') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($jobAlerts->isEmpty())
        <x-empty-state icon="bell" :heading="__('No job alerts yet')">
            {{ __('We email you when new jobs match a search you keep. Search for jobs and choose "Create job alert", or add one here.') }}
            <x-slot:actions>
                <flux:button wire:click="create" size="sm" icon="plus">{{ __('New alert') }}</flux:button>
            </x-slot:actions>
        </x-empty-state>
    @else
        {{-- One row per alert, after LinkedIn's: an on/off switch for the
             emails, the search it runs, and Edit -- with Delete inside the
             edit dialog, as on the profile, rather than a row of bare
             icons. A paused alert keeps its full colour: the switch and
             the word under it say it is off. --}}
        <x-card padding="none" class="divide-y divide-line overflow-hidden">
            @foreach ($jobAlerts as $jobAlert)
                <div wire:key="job-alert-{{ $jobAlert->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4 sm:flex-nowrap sm:px-6">
                    <x-icon-tile icon="bell" size="sm" />

                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-ink">{{ $jobAlert->name }}</p>
                        @if (filled($descriptions[$jobAlert->id]))
                            <p class="text-sm text-ink-muted">{{ $descriptions[$jobAlert->id] }}</p>
                        @endif
                        <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-meta text-ink-muted">
                            <span>{{ $jobAlert->is_active ? __(':frequency email', ['frequency' => $jobAlert->frequency->label()]) : __('Paused — no emails') }}</span>
                            <flux:link :href="route('jobs.index', $jobAlert->criteria)" wire:navigate>{{ __('See matching jobs') }}</flux:link>
                        </p>
                    </div>

                    {{-- A row of its own on a phone, so the alert's name and
                         search keep the width. --}}
                    <div class="flex w-full shrink-0 items-center justify-end gap-3 sm:w-auto">
                        <flux:switch
                            :checked="$jobAlert->is_active"
                            wire:change="toggle({{ $jobAlert->id }})"
                            :aria-label="__('Emails for :name', ['name' => $jobAlert->name])"
                        />
                        <flux:button wire:click="edit({{ $jobAlert->id }})" variant="ghost" size="sm" icon="pencil-square">{{ __('Edit') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </x-card>
    @endif

    <flux:modal wire:model="showModal" class="max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit job alert') : __('New job alert') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('Laravel jobs in Dhaka')" />

            <flux:radio.group wire:model="frequency" variant="segmented" :label="__('Email me')" :description="__('Sent around 8 in the morning, your time.')">
                @foreach ($frequencies as $option)
                    <flux:radio value="{{ $option->value }}" :label="$option->label()" />
                @endforeach
            </flux:radio.group>

            <flux:separator :text="__('Jobs that match')" />

            <flux:input wire:model="q" :label="__('Keywords')" :placeholder="__('Job title...')" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:select wire:model="skill" :label="__('Skill')">
                    <flux:select.option value="">{{ __('Any skill') }}</flux:select.option>
                    @foreach ($skills as $option)
                        <flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="category" :label="__('Category')">
                    <flux:select.option value="">{{ __('Any category') }}</flux:select.option>
                    @foreach ($categories as $option)
                        <flux:select.option value="{{ $option->id }}">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="workplaceType" :label="__('Workplace')">
                    <flux:select.option value="">{{ __('Any workplace type') }}</flux:select.option>
                    @foreach ($workplaceTypes as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="employmentType" :label="__('Employment')">
                    <flux:select.option value="">{{ __('Any employment type') }}</flux:select.option>
                    @foreach ($employmentTypes as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input wire:model="location" :label="__('City')" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:select wire:model="currency" :label="__('Pay currency')">
                    <flux:select.option value="">{{ __('Any currency') }}</flux:select.option>
                    @foreach (SalaryCurrencies::options($currency) as $code => $label)
                        <flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="salaryMin" type="number" :label="__('Min pay / month')" />
                <flux:input wire:model="salaryMax" type="number" :label="__('Max pay / month')" />
            </div>

            <flux:input wire:model="experience" type="number" :label="__('Your years of experience')" class="sm:max-w-xs" />

            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($editingId)
                    <flux:button wire:click="delete({{ $editingId }})" wire:confirm="{{ __('Delete this job alert?') }}" variant="ghost" icon="trash">{{ __('Delete') }}</flux:button>
                @endif

                <div class="ms-auto flex gap-2">
                    <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</x-page>
