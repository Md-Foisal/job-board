{{--
    One licence or certificate: what it is, who issued it, when, whether
    it has run out, and where a reader can check it.
--}}
@props(['certification'])

@php
    $verifiable = filled($certification->credential_url) && preg_match('#^https?://#i', $certification->credential_url) === 1;
@endphp

<li {{ $attributes->class('flex gap-4 px-5 py-4 sm:px-6') }}>
    <x-icon-tile icon="check-badge" size="sm" />

    <div class="min-w-0 flex-1">
        <p class="font-medium text-ink">{{ $certification->name }}</p>
        <p class="text-sm text-ink-soft">{{ $certification->issuer }}</p>
        @if ($certification->issued_on || $certification->expires_on)
            <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-sm text-ink-muted">
                @if ($certification->issued_on)
                    <span>{{ __('Issued :date', ['date' => $certification->issued_on->format(\App\Support\DateFormat::MONTH)]) }}</span>
                @endif
                @if ($certification->expires_on)
                    @if ($certification->issued_on)<span aria-hidden="true">&middot;</span>@endif
                    @if ($certification->hasExpired())
                        <flux:badge size="sm" color="zinc">{{ __('Expired :date', ['date' => $certification->expires_on->format(\App\Support\DateFormat::MONTH)]) }}</flux:badge>
                    @else
                        <span>{{ __('Expires :date', ['date' => $certification->expires_on->format(\App\Support\DateFormat::MONTH)]) }}</span>
                    @endif
                @endif
            </p>
        @endif
        @if ($certification->credential_id)
            <p class="mt-0.5 text-sm text-ink-muted">{{ __('Credential ID :id', ['id' => $certification->credential_id]) }}</p>
        @endif
        @if ($verifiable)
            <a href="{{ $certification->credential_url }}" target="_blank" rel="noopener noreferrer nofollow" class="mt-1 inline-flex items-center gap-1 text-sm text-sunset-small hover:underline">
                {{ __('Show credential') }}
                <flux:icon.arrow-top-right-on-square variant="micro" aria-hidden="true" />
            </a>
        @endif
    </div>

    @isset($actions)
        <div class="shrink-0">{{ $actions }}</div>
    @endisset
</li>
