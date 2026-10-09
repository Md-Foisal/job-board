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
    {{-- One preview window for every file: opened with the file's address,
         emptied again on close so a large PDF stops loading. --}}
    <div
        x-data="{
            src: null,
            image: false,
            name: '',
            download: null,
            open(src, image, name, download) {
                [this.src, this.image, this.name, this.download] = [src, image, name, download];
                $flux.modal('document-preview').show();
            },
        }"
        class="contents"
    >
    <x-page-header :title="__('Documents')">
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
                                <flux:button :href="route('candidate.resume-import', $document)" wire:navigate size="sm" icon="document-arrow-down" class="mt-3">
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

                {{-- Icons alone, with their names on hover and focus: three
                     words side by side would crowd the file name on a phone. --}}
                <div class="flex shrink-0 items-center gap-1">
                    @if (\App\Http\Controllers\DocumentPreviewController::canPreview($document))
                        <flux:tooltip :content="__('Preview')">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="eye"
                                :aria-label="__('Preview')"
                                data-src="{{ route('candidate.documents.preview', $document) }}"
                                data-image="{{ \App\Http\Controllers\DocumentPreviewController::isImage($document) ? '1' : '' }}"
                                data-name="{{ $document->original_filename }}"
                                data-download="{{ route('candidate.documents.download', $document) }}"
                                x-on:click="open($el.dataset.src, $el.dataset.image === '1', $el.dataset.name, $el.dataset.download)"
                            />
                        </flux:tooltip>
                    @else
                        <flux:tooltip :content="__('Word files and archives open once downloaded')">
                            <flux:button variant="ghost" size="sm" icon="eye-slash" :aria-label="__('No preview for this file type')" disabled />
                        </flux:tooltip>
                    @endif
                    <flux:tooltip :content="__('Download')">
                        <flux:button href="{{ route('candidate.documents.download', $document) }}" variant="ghost" size="sm" icon="arrow-down-tray" :aria-label="__('Download')" />
                    </flux:tooltip>
                    <flux:tooltip :content="__('Replace')">
                        <flux:button wire:click="replace({{ $document->id }})" variant="ghost" size="sm" icon="arrow-path" :aria-label="__('Replace')" />
                    </flux:tooltip>
                    <flux:tooltip :content="__('Remove')">
                        <flux:button wire:click="delete({{ $document->id }})" wire:confirm="{{ __('Remove this document?') }}" variant="ghost" size="sm" icon="trash" :aria-label="__('Remove')" />
                    </flux:tooltip>
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

    <flux:modal name="document-preview" class="w-full max-w-4xl" x-on:close="src = null">
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center gap-3 pe-10">
                <flux:heading size="lg" class="min-w-0 truncate" x-text="name"></flux:heading>
                <div class="ms-auto flex gap-2">
                    <flux:button size="sm" icon="arrow-top-right-on-square" href="#" x-bind:href="src" target="_blank" rel="noopener">{{ __('Open in new tab') }}</flux:button>
                    <flux:button size="sm" icon="arrow-down-tray" href="#" x-bind:href="download">{{ __('Download') }}</flux:button>
                </div>
            </div>
            <template x-if="src && ! image">
                <iframe :src="src" :title="name" class="h-[75vh] w-full rounded-control bg-surface ring-1 ring-line"></iframe>
            </template>
            <template x-if="src && image">
                <img :src="src" :alt="name" class="mx-auto max-h-[75vh] w-auto rounded-control ring-1 ring-line">
            </template>
        </div>
    </flux:modal>
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
