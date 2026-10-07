<?php

use App\Actions\ReplaceDocument;
use App\Actions\StoreCandidateDocument;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Support\DocumentUploads;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] #[Title('Documents')] class extends Component {
    use WithFileUploads;

    public bool $showModal = false;

    public ?int $replacingId = null;

    public string $documentType = 'cv';

    public $file = null;

    public function with(): array
    {
        return [
            'documents' => auth()->user()->candidateProfile
                ->documents()
                ->latest()
                ->latest('id')
                ->get(),
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function replace(int $id): void
    {
        $document = auth()->user()->candidateProfile->documents()->findOrFail($id);
        $this->authorize('update', $document);

        $this->replacingId = $document->id;
        $this->documentType = $document->document_type->value;
        $this->file = null;

        $this->showModal = true;
    }

    public function save(): void
    {
        $candidateProfile = auth()->user()->candidateProfile;

        if ($this->replacingId) {
            $old = $candidateProfile->documents()->findOrFail($this->replacingId);
            $this->authorize('update', $old);
            $type = $old->document_type;
        } else {
            $this->validate([
                'documentType' => ['required', Rule::enum(DocumentType::class)],
            ]);
            $type = DocumentType::from($this->documentType);
        }

        // CVs rotate (the oldest leaves on its own); the others are kept on
        // purpose, so a full shelf is said up front instead.
        if (! $this->replacingId
            && $type !== DocumentType::Cv
            && $candidateProfile->documents()->where('document_type', $type)->count() >= DocumentUploads::MAX_OTHER_DOCUMENTS_PER_TYPE) {
            $this->addError('file', __('You can keep up to :count :type files. Remove one to add another.', [
                'count' => DocumentUploads::MAX_OTHER_DOCUMENTS_PER_TYPE,
                'type' => strtolower($type->label()),
            ]));

            return;
        }

        $this->validate([
            'file' => DocumentUploads::rules($type),
        ]);

        if ($this->replacingId) {
            app(ReplaceDocument::class)($candidateProfile, $old, $this->file);
        } else {
            app(StoreCandidateDocument::class)($candidateProfile, $type, $this->file);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $document = auth()->user()->candidateProfile->documents()->findOrFail($id);
        $this->authorize('delete', $document);
        $document->delete();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->replacingId = null;
        $this->documentType = DocumentType::Cv->value;
        $this->file = null;
        $this->resetValidation();
    }
}; ?>

<x-page>
    <x-page-header :title="__('Documents')" :description="__('Your CV, work samples and certificates — used when you apply.')">
        <x-slot:actions>
            <flux:button :href="route('candidate.cv-builder')" wire:navigate icon="document-plus">{{ __('Build a CV from your profile') }}</flux:button>
            <flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add document') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-4">
        @forelse ($documents as $document)
            <x-card wire:key="document-{{ $document->id }}" class="flex items-start justify-between gap-4">
                <div class="flex gap-4">
                    <x-icon-tile :icon="$document->document_type->icon()" />

                    <div>
                        <p class="font-medium text-ink">{{ $document->original_filename }}</p>
                        <p class="text-sm text-ink-muted">{{ $document->document_type->label() }}</p>
                        <p class="mt-1 text-sm text-ink-muted">{{ \App\Support\LocalTime::of($document->created_at)->format(\App\Support\DateFormat::DAY) }}</p>

                        @if ($document->document_type === \App\Enums\DocumentType::Cv)
                            @if (\App\Support\CvText::supports($document))
                                <flux:button :href="route('candidate.resume-import', $document)" wire:navigate size="xs" icon="user-plus" class="mt-3">
                                    {{ __('Fill my profile from this CV') }}
                                </flux:button>
                            @else
                                <p class="mt-2 text-xs text-ink-muted">
                                    {{ __('Upload this CV as PDF or DOCX to fill your profile from it.') }}
                                </p>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <flux:button href="{{ route('candidate.documents.download', $document) }}" variant="ghost" size="sm" icon="arrow-down-tray" :aria-label="__('Download')" />
                    <flux:button wire:click="replace({{ $document->id }})" variant="ghost" size="sm" icon="arrow-path" :aria-label="__('Replace')" />
                    <flux:button wire:click="delete({{ $document->id }})" wire:confirm="{{ __('Remove this document?') }}" variant="ghost" size="sm" icon="trash" :aria-label="__('Remove')" />
                </div>
            </x-card>
        @empty
            <x-empty-state icon="document" :heading="__('You haven\'t added any documents yet.')">
                <x-slot:actions>
                    <flux:button wire:click="create" size="sm" icon="plus">{{ __('Add document') }}</flux:button>
                </x-slot:actions>
            </x-empty-state>
        @endforelse
    </div>

    <flux:modal wire:model="showModal" class="max-w-lg" @close="closeModal">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $replacingId ? __('Replace document') : __('Add document') }}</flux:heading>

            @if ($replacingId)
                <flux:text>{{ __('Uploading a new file for this :type. The old file stays attached to any application you already submitted with it.', ['type' => \App\Enums\DocumentType::from($documentType)->label()]) }}</flux:text>
            @else
                <flux:select wire:model="documentType" :label="__('Type')">
                    @foreach (\App\Enums\DocumentType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div>
                <flux:input type="file" wire:model="file" :label="__('File')"
                    accept="{{ \App\Support\DocumentUploads::accept(\App\Enums\DocumentType::from($documentType)) }}" />
                <p class="mt-1 text-xs text-ink-muted">
                    {{ \App\Support\DocumentUploads::hint(\App\Enums\DocumentType::from($documentType)) }}
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-page>
