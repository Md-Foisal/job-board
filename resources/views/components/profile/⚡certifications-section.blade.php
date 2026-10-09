<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * The Certifications section of the candidate's own profile, with the
 * fields LinkedIn asks for on a licence or certificate: who issued it,
 * when, until when, its ID and where it can be checked.
 */
new class extends Component {
    public int $level = 2;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $issuer = '';

    public ?string $issuedOn = null;

    public ?string $expiresOn = null;

    public ?string $credentialId = null;

    public ?string $credentialUrl = null;

    #[Computed]
    public function certifications()
    {
        return auth()->user()->candidateProfile
            ->certifications()
            ->newestFirst()
            ->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $certification = auth()->user()->candidateProfile->certifications()->findOrFail($id);
        $this->authorize('update', $certification);

        $this->resetValidation();
        $this->editingId = $certification->id;
        $this->name = $certification->name;
        $this->issuer = $certification->issuer;
        $this->issuedOn = $certification->issued_on?->format('Y-m-d');
        $this->expiresOn = $certification->expires_on?->format('Y-m-d');
        $this->credentialId = $certification->credential_id;
        $this->credentialUrl = $certification->credential_url;

        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'issuer' => ['required', 'string', 'max:255'],
            'issuedOn' => ['nullable', 'date', 'before_or_equal:today'],
            'expiresOn' => ['nullable', 'date', 'after_or_equal:issuedOn'],
            'credentialId' => ['nullable', 'string', 'max:100'],
            'credentialUrl' => ['nullable', 'url:http,https', 'max:255'],
        ], [], [
            'issuedOn' => __('issue date'),
            'expiresOn' => __('expiry date'),
            'credentialUrl' => __('credential link'),
        ]);

        $data = [
            'name' => $validated['name'],
            'issuer' => $validated['issuer'],
            'issued_on' => $validated['issuedOn'],
            'expires_on' => $validated['expiresOn'],
            'credential_id' => $validated['credentialId'],
            'credential_url' => $validated['credentialUrl'],
        ];

        if ($this->editingId) {
            $certification = auth()->user()->candidateProfile->certifications()->findOrFail($this->editingId);
            $this->authorize('update', $certification);
            $certification->update($data);
        } else {
            auth()->user()->candidateProfile->certifications()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('profile-updated');
    }

    public function delete(): void
    {
        $certification = auth()->user()->candidateProfile->certifications()->findOrFail($this->editingId);
        $this->authorize('delete', $certification);
        $certification->delete();

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('profile-updated');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->issuer = '';
        $this->issuedOn = null;
        $this->expiresOn = null;
        $this->credentialId = null;
        $this->credentialUrl = null;
        $this->resetValidation();
    }
}; ?>

<div>
    <x-profile.section id="certifications" :heading="__('Licences & certifications')" :level="$level">
        {{-- The empty state below carries the same button. --}}
        @if ($this->certifications->isNotEmpty())
            <x-slot:actions>
                <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add certification') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($this->certifications->isEmpty())
            <div class="px-5 pb-5 sm:px-6">
                <x-empty-state icon="check-badge" :level="$level + 1" :heading="__('No certifications yet')">
                    {{ __('Courses and licences you hold, with a link where a reader can check them.') }}

                    <x-slot:actions>
                        <flux:button wire:click="create" variant="primary" size="sm" icon="plus">{{ __('Add certification') }}</flux:button>
                    </x-slot:actions>
                </x-empty-state>
            </div>
        @else
            <ul class="divide-y divide-line border-t border-line">
                @foreach ($this->certifications as $certification)
                    <x-profile.certification-item :certification="$certification" wire:key="certification-{{ $certification->id }}">
                        <x-slot:actions>
                            <flux:button wire:click="edit({{ $certification->id }})" variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit :title', ['title' => $certification->name])" />
                        </x-slot:actions>
                    </x-profile.certification-item>
                @endforeach
            </ul>
        @endif
    </x-profile.section>

    <flux:modal wire:model="showModal" class="w-full max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit certification') : __('Add certification') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" placeholder="AWS Certified Cloud Practitioner" />
            <flux:input wire:model="issuer" :label="__('Issued by')" placeholder="Amazon Web Services" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="issuedOn" :label="__('Issue date')" :description="__('Optional')" />
                <flux:input type="date" wire:model="expiresOn" :label="__('Expiry date')" :description="__('Leave empty if it does not expire')" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="credentialId" :label="__('Credential ID')" :description="__('Optional')" maxlength="100" />
                <flux:input type="url" wire:model="credentialUrl" :label="__('Credential link')" placeholder="https://" :description="__('Optional')" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($editingId)
                    <flux:button wire:click="delete" wire:confirm="{{ __('Delete this certification from your profile?') }}" variant="ghost" icon="trash">{{ __('Delete') }}</flux:button>
                @endif

                <div class="ms-auto flex gap-2">
                    <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
