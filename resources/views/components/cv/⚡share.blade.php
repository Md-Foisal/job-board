<?php

use App\Models\CvVersion;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Sharing one saved CV as a web page: a private address anyone with the
 * link can open, never listed by search engines, until the candidate
 * turns it off or the date they chose passes. Contact details stay off
 * the page unless the candidate allows them. The page counts how often
 * it is opened and the PDF downloaded.
 */
new class extends Component {
    #[Locked]
    public int $versionId;

    public bool $published = false;

    public string $expires = 'never';

    public bool $showContact = false;

    public bool $qr = false;

    public const EXPIRY_DAYS = ['never' => null, '7' => 7, '30' => 30, '90' => 90];

    public function mount(): void
    {
        $version = $this->version();
        $this->published = $version->isPublic();
        $this->showContact = $version->public_shows_contact;
        $this->qr = (bool) ($version->design['qr'] ?? false);
        $this->expires = 'never';

        if ($version->public_expires_at && $version->public_expires_at->isFuture()) {
            $days = (int) ceil(now()->diffInDays($version->public_expires_at));
            $this->expires = collect(self::EXPIRY_DAYS)->filter()->keys()->first(fn ($key) => (int) $key >= $days) ?? '90';
        }
    }

    public function updatedPublished(): void
    {
        $version = $this->version();

        if ($this->published) {
            $version->update([
                'public_slug' => $version->public_slug ?? CvVersion::newSlug(),
                'published_at' => now(),
                'public_expires_at' => $this->expiryDate(),
            ]);
        } else {
            $version->update(['published_at' => null]);
        }

        $this->changed();
    }

    public function updatedExpires(): void
    {
        if (! array_key_exists($this->expires, self::EXPIRY_DAYS)) {
            $this->expires = 'never';
        }

        $this->version()->update(['public_expires_at' => $this->expiryDate()]);
        $this->changed();
    }

    public function updatedShowContact(): void
    {
        $this->version()->update(['public_shows_contact' => $this->showContact]);
        $this->changed();
    }

    public function updatedQr(): void
    {
        $version = $this->version();
        $version->update(['design' => [...($version->design ?? []), 'qr' => $this->qr]]);
        $this->changed();
    }

    /**
     * A new address, for when the old one went somewhere it should not
     * have: the old one stops working at once.
     */
    public function newLink(): void
    {
        $this->version()->forceFill(['public_slug' => CvVersion::newSlug(), 'view_count' => 0, 'download_count' => 0])->save();
        $this->changed();
    }

    #[Computed]
    public function shared(): CvVersion
    {
        return $this->version();
    }

    private function changed(): void
    {
        unset($this->shared);
        $this->dispatch('cv-updated');
    }

    private function expiryDate(): ?\Carbon\CarbonInterface
    {
        $days = self::EXPIRY_DAYS[$this->expires] ?? null;

        return $days === null ? null : now()->addDays($days)->endOfDay();
    }

    private function version(): CvVersion
    {
        return auth()->user()->candidateProfile->cvVersions()->findOrFail($this->versionId);
    }
}; ?>

@php
    $shared = $this->shared;
@endphp

<x-card as="section" class="space-y-5" aria-labelledby="cv-share-heading">
    <div>
        <flux:heading id="cv-share-heading">{{ __('Share as a web page') }}</flux:heading>
        <flux:text size="sm" class="mt-1">{{ __('A private link to put on LinkedIn, in an email or on a QR code. Only people with the link can open it, and search engines are asked not to list it. The page and its PDF follow this CV as you change it.') }}</flux:text>
    </div>

    <flux:switch wire:model.live="published" :label="__('Share this CV as a web page')" />

    @if ($shared->isPublic())
        <div x-data="{ copied: false, url: @js($shared->publicUrl()) }" class="flex flex-wrap items-center gap-2">
            <flux:input readonly :value="$shared->publicUrl()" class="min-w-0 flex-1" :aria-label="__('Link to this CV')" />
            <flux:button size="sm" icon="clipboard" x-on:click="navigator.clipboard.writeText(url).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                <span x-show="! copied">{{ __('Copy link') }}</span>
                <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$shared->publicUrl()" target="_blank">{{ __('Open') }}</flux:button>
        </div>

        <p class="text-sm text-ink-muted">
            {{ trans_choice('{0} Not opened yet|{1} Opened once|[2,*] Opened :count times', $shared->view_count) }}
            <span aria-hidden="true">·</span>
            {{ trans_choice('{0} not downloaded yet|{1} downloaded once|[2,*] downloaded :count times', $shared->download_count) }}
        </p>

        <flux:select wire:model.live="expires" :label="__('Stop sharing')">
            <flux:select.option value="never">{{ __('Only when I turn it off') }}</flux:select.option>
            <flux:select.option value="7">{{ __('After 7 days') }}</flux:select.option>
            <flux:select.option value="30">{{ __('After 30 days') }}</flux:select.option>
            <flux:select.option value="90">{{ __('After 90 days') }}</flux:select.option>
        </flux:select>

        <flux:switch wire:model.live="showContact" :label="__('Show my contact details on the page')"
            :description="__('Your email, phone, city, personal information and references, on the page and its PDF. Off: readers reach you through the links on your CV.')" />

        <flux:switch wire:model.live="qr" :label="__('Print a QR code to this page on the PDF')"
            :description="__('Handy on a printed CV at a job fair: one scan opens the web version.')" />

        <flux:button wire:click="newLink" wire:confirm="{{ __('Make a new link? The old one stops working at once.') }}" variant="ghost" size="sm" icon="arrow-path">{{ __('Make a new link') }}</flux:button>
    @elseif ($shared->public_slug && $shared->published_at && $shared->public_expires_at?->isPast())
        <flux:text size="sm" class="text-warning-700 dark:text-warning-300">{{ __('Sharing stopped on :date, as you set. Turn it on again to share the same link.', ['date' => \App\Support\LocalTime::of($shared->public_expires_at)->format(\App\Support\DateFormat::DAY)]) }}</flux:text>
    @endif
</x-card>
