{{--
    The candidate's own profile. Like LinkedIn's, it starts with the
    person's card -- no title bar or explanation above it: the name is
    the page's title, and what a company sees is one click away in
    Preview as employer.
--}}
<x-layouts::app :title="__('My profile')">
    <x-page>
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <x-candidate-profile :profile="$candidateProfile" mode="owner" :heading-level="1" class="lg:col-span-2" />

            {{-- What sits next to a profile without being part of it: the CV
                 library and the private preferences. --}}
            <aside class="lg:sticky lg:top-6">
                <x-card padding="none" class="overflow-hidden">
                    <ul class="divide-y divide-line">
                        <li>
                            <a href="{{ route('candidate.documents.index') }}" wire:navigate class="group flex items-center gap-3 px-5 py-4 transition hover:bg-surface">
                                <x-icon-tile icon="document-text" size="sm" />
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-ink group-hover:text-sunset-small">{{ __('Documents') }}</span>
                                    <span class="block text-sm text-ink-muted">{{ trans_choice('{0} No CV yet|{1} 1 document|[2,*] :count documents', $documentCount) }}</span>
                                </span>
                                <flux:icon.chevron-right variant="mini" class="text-ink-muted" aria-hidden="true" />
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('candidate.preferences.edit') }}" wire:navigate class="group flex items-center gap-3 px-5 py-4 transition hover:bg-surface">
                                <x-icon-tile icon="adjustments-horizontal" size="sm" />
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-ink group-hover:text-sunset-small">{{ __('Job preferences') }}</span>
                                    <span class="flex items-center gap-1 text-sm text-ink-muted">
                                        <flux:icon.lock-closed variant="micro" aria-hidden="true" />
                                        {{ __('Private to you') }}
                                    </span>
                                </span>
                                <flux:icon.chevron-right variant="mini" class="text-ink-muted" aria-hidden="true" />
                            </a>
                        </li>
                    </ul>
                </x-card>
            </aside>
        </div>
    </x-page>
</x-layouts::app>
