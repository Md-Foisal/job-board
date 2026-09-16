<?php

use App\Actions\ReplaceDocument;
use App\Enums\DocumentType;
use App\Models\Document;
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

        $this->validate([
            'file' => $this->fileRules($type),
        ]);

        if ($this->replacingId) {
            app(ReplaceDocument::class)($candidateProfile, $old, $this->file);
        } else {
            $path = $this->file->store('documents', 'local');

            $candidateProfile->documents()->create([
                'document_type' => $type,
                'file_path' => $path,
                'original_filename' => $this->file->getClientOriginalName(),
            ]);
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

    private function fileRules(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::Cv => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            DocumentType::WorkSample => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,zip', 'max:10240'],
            DocumentType::Certificate => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        };
    }

    private function resetForm(): void
    {
        $this->replacingId = null;
        $this->documentType = DocumentType::Cv->value;
        $this->file = null;
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto max-w-2xl px-6 py-10">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Documents') }}</flux:heading>
            <flux:subheading>{{ __('Your CV, work samples and certificates — used when you apply.') }}</flux:subheading>
        </div>

        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
    </div>

    <div class="mt-6 space-y-4">
        @forelse ($documents as $document)
            <div wire:key="document-{{ $document->id }}" class="flex items-start justify-between gap-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                        <flux:icon :name="match ($document->document_type) {
                            \App\Enums\DocumentType::Cv => 'document-text',
                            \App\Enums\DocumentType::WorkSample => 'folder',
                            \App\Enums\DocumentType::Certificate => 'shield-check',
                        }" variant="mini" />
                    </div>

                    <div>
                        <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $document->original_filename }}</p>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $document->document_type->label() }}</p>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-500">{{ $document->created_at->format('M j, Y') }}</p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <flux:button href="{{ route('candidate.documents.download', $document) }}" variant="ghost" size="sm" icon="arrow-down-tray" :aria-label="__('Download')" />
                    <flux:button wire:click="replace({{ $document->id }})" variant="ghost" size="sm" icon="arrow-path" :aria-label="__('Replace')" />
                    <flux:button wire:click="delete({{ $document->id }})" wire:confirm="{{ __('Remove this document?') }}" variant="ghost" size="sm" icon="trash" :aria-label="__('Remove')" />
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __("You haven't added any documents yet.") }}</p>
            </div>
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
                    accept="{{ match ($documentType) {
                        'cv' => '.pdf,.doc,.docx',
                        'work_sample' => '.pdf,.doc,.docx,.jpg,.jpeg,.png,.zip',
                        'certificate' => '.pdf,.jpg,.jpeg,.png',
                        default => '',
                    } }}" />
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">
                    {{ match ($documentType) {
                        'cv' => __('PDF or Word, up to 5 MB.'),
                        'work_sample' => __('PDF, Word, image or zip, up to 10 MB.'),
                        'certificate' => __('PDF or image, up to 5 MB.'),
                        default => '',
                    } }}
                </p>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="closeModal" variant="ghost">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
