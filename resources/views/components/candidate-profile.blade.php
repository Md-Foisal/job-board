{{--
    A candidate's profile, in one of two modes.

      owner   the candidate's own page: every part edited where it is
              read, a pencil or an Add button opening a dialog, as on
              LinkedIn. Location and the contact details for CVs show
              here and nowhere else.
      viewer  how a company reads it when the candidate applies: the
              same parts in the same order, nothing to edit, and none of
              what stays private (phone, location, preferences).

    The order is LinkedIn's: the card with photo, name, headline and
    links, then About, Experience, Education, Licences & certifications,
    Projects and Skills. A viewer can pass the skill ids a posting asks
    for, and those skills show as matched.

    On the candidate's own page and on the preview the name is the page's
    title, as on LinkedIn, so those pages pass headingLevel 1; where the
    profile sits inside another page it stays a level-2 heading.

    A page that already shows the person at its top -- the applicant
    page, whose header carries the photo, name, headline and links --
    passes intro false and starts at About. It can also pass the skills
    a posting asks for that the candidate does not list (missingSkills),
    which the Skills section then names under their own.
--}}
@props([
    'profile',
    'mode' => 'viewer',
    'wantedSkillIds' => null,
    'missingSkills' => null,
    'intro' => true,
    'headingLevel' => 2,
])

@php
    $owner = $mode === 'owner';
    $user = $profile->user;
    $coverUrl = $profile->cover_photo_path ? \Illuminate\Support\Facades\Storage::url($profile->cover_photo_path) : null;
    $avatarUrl = $user?->avatar ? \Illuminate\Support\Facades\Storage::url($user->avatar) : null;

@endphp

<div {{ $attributes }}>
    <div class="flex flex-col gap-6">
        {{-- The intro card: photo, name, headline, links. The cover photo is
             the candidate's own decoration and stays on their side, so a
             company's view starts with the photo. --}}
        @if ($intro || $owner)
        <x-card padding="none" class="overflow-hidden">
            @if ($owner)
                <div class="relative">
                    <div class="relative h-28 w-full overflow-hidden bg-surface sm:h-40">
                        @if ($coverUrl)
                            <img src="{{ $coverUrl }}" alt="" class="size-full object-cover">
                        @else
                            <div class="bg-sunset absolute inset-0">
                                <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle, white 1.5px, transparent 1.5px); background-size: 22px 22px;"></div>
                            </div>
                        @endif
                    </div>

                    <flux:modal.trigger name="edit-cover">
                        <button
                            type="button"
                            class="absolute right-3 top-3 flex cursor-pointer items-center gap-1.5 rounded-full bg-black/40 px-3 py-1.5 text-xs font-medium text-white shadow-sm backdrop-blur transition hover:bg-black/55"
                        >
                            <flux:icon.camera variant="mini" class="size-3.5" aria-hidden="true" />
                            {{ __('Edit cover') }}
                        </button>
                    </flux:modal.trigger>
                </div>
            @endif

            <div @class([
                'flex gap-4 px-5 pb-6 sm:px-6',
                '-mt-10 sm:-mt-12' => $owner,
                'pt-6' => ! $owner,
            ])>
                <div class="flex min-w-0 flex-1 flex-col gap-4 {{ $owner ? '' : 'sm:flex-row sm:items-center' }}">
                    <div class="relative w-fit shrink-0">
                        <div @class([
                            'flex items-center justify-center overflow-hidden rounded-full bg-brand-50 font-semibold dark:bg-brand-950',
                            'size-20 border-4 border-canvas text-xl shadow-md sm:size-24 sm:text-2xl' => $owner,
                            'size-16 text-lg sm:size-20 sm:text-xl' => ! $owner,
                        ])>
                            @if ($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ $user->name }}" class="size-full object-cover">
                            @else
                                <span class="text-sunset-small">{{ $user?->initials() }}</span>
                            @endif
                        </div>

                        @if ($owner)
                            <flux:modal.trigger name="edit-photo">
                                <button
                                    type="button"
                                    class="absolute -bottom-1 -right-1 flex size-8 cursor-pointer items-center justify-center rounded-full border-2 border-canvas bg-ink text-canvas shadow-sm transition hover:bg-ink-soft"
                                    aria-label="{{ $avatarUrl ? __('Change photo') : __('Add a photo') }}"
                                >
                                    <flux:icon.camera variant="mini" class="size-4" aria-hidden="true" />
                                </button>
                            </flux:modal.trigger>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <flux:heading size="xl" :level="$headingLevel" class="font-display">{{ $user?->name }}</flux:heading>

                        @if ($profile->headline)
                            <p class="mt-1 text-ink-soft">{{ $profile->headline }}</p>
                        @elseif ($owner)
                            <p class="mt-1 text-ink-muted">{{ __('No headline yet — say in a few words what you do.') }}</p>
                        @endif

                        @if ($owner && $profile->location)
                            <p class="mt-1 flex items-center gap-1.5 text-sm text-ink-muted">
                                <flux:icon.map-pin variant="micro" class="size-4 shrink-0" aria-hidden="true" />
                                {{ $profile->location }}
                                <span class="sr-only">{{ __('(only on your CVs)') }}</span>
                            </p>
                        @endif

                        <x-candidate-links :profile="$profile" class="mt-4" />

                        {{-- A link only shows once it is filled in, so the owner
                             is offered the ones still missing. --}}
                        @php
                            $missingLinks = collect(['portfolio_url' => __('portfolio'), 'github_url' => 'GitHub', 'linkedin_url' => 'LinkedIn'])
                                ->reject(fn ($label, $field) => filled($profile->{$field}));
                        @endphp
                        @if ($owner && $missingLinks->isNotEmpty())
                            <flux:modal.trigger name="edit-intro">
                                <button type="button" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-ink-muted transition hover:text-ink">
                                    <flux:icon.plus variant="micro" aria-hidden="true" />
                                    {{ __('Add your :links link', ['links' => $missingLinks->join(', ', ' or ')]) }}
                                </button>
                            </flux:modal.trigger>
                        @endif

                        {{-- The card's own actions, under the name as on LinkedIn,
                             instead of a title bar above the page. --}}
                        @if ($owner)
                            <div class="mt-5 flex flex-wrap gap-2">
                                <flux:button :href="route('candidate.profile.preview')" wire:navigate size="sm" icon="eye">{{ __('Preview as employer') }}</flux:button>
                                <flux:button :href="route('candidate.cv-builder')" wire:navigate size="sm" icon="document-plus">{{ __('Build a CV') }}</flux:button>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($owner)
                    <div class="mt-12 shrink-0 sm:mt-14">
                        <flux:modal.trigger name="edit-intro">
                            <flux:button variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit headline and links')" />
                        </flux:modal.trigger>
                    </div>
                @endif
            </div>
        </x-card>
        @endif

        {{-- About --}}
        @if ($owner || filled($profile->bio))
            <x-profile.section id="about" :heading="__('About')">
                @if ($owner && filled($profile->bio))
                    <x-slot:actions>
                        <flux:modal.trigger name="edit-about">
                            <flux:button variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit About')" />
                        </flux:modal.trigger>
                    </x-slot:actions>
                @endif

                <div class="px-5 pb-5 sm:px-6">
                    @if (filled($profile->bio))
                        <p class="whitespace-pre-line text-body text-ink-soft">{{ $profile->bio }}</p>
                    @else
                        <x-empty-state icon="user" :level="3" :heading="__('Nothing here yet')">
                            {{ __('A few lines on what you do and what you are looking for. It is the first thing a company reads.') }}

                            <x-slot:actions>
                                <flux:modal.trigger name="edit-about">
                                    <flux:button variant="primary" size="sm" icon="plus">{{ __('Add About') }}</flux:button>
                                </flux:modal.trigger>
                            </x-slot:actions>
                        </x-empty-state>
                    @endif
                </div>
            </x-profile.section>
        @endif

        @if ($owner)
            <livewire:profile.experience-section />
            <livewire:profile.education-section />
            <livewire:profile.certifications-section />
            <livewire:profile.projects-section />
            <livewire:profile.skills-section />

            {{-- Kept for the CVs the candidate builds here; never on the profile
                 a company reads. --}}
            <x-profile.section id="contact" :heading="__('Contact for your CVs')">
                <x-slot:actions>
                    <flux:modal.trigger name="edit-contact">
                        <flux:button variant="ghost" size="sm" icon="pencil" :aria-label="__('Edit contact details')" />
                    </flux:modal.trigger>
                </x-slot:actions>

                <div class="px-5 pb-5 sm:px-6">
                    <flux:text size="sm">{{ __("Only on CVs you build here. Companies don't see these on your profile.") }}</flux:text>

                    <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                        <div class="flex items-center gap-2">
                            <dt><flux:icon.phone variant="mini" class="size-4 text-ink-muted" aria-hidden="true" /><span class="sr-only">{{ __('Phone') }}</span></dt>
                            <dd class="{{ $profile->phone ? 'text-ink-soft' : 'text-ink-muted' }}">{{ $profile->phone ?: __('No phone yet') }}</dd>
                        </div>
                        <div class="flex items-center gap-2">
                            <dt><flux:icon.map-pin variant="mini" class="size-4 text-ink-muted" aria-hidden="true" /><span class="sr-only">{{ __('Location') }}</span></dt>
                            <dd class="{{ $profile->location ? 'text-ink-soft' : 'text-ink-muted' }}">{{ $profile->location ?: __('No location yet') }}</dd>
                        </div>
                    </dl>
                </div>
            </x-profile.section>
        @else
            @php
                $experience = $profile->experienceRecords()->orderByDesc('start_date')->orderByDesc('id')->get();
                $education = $profile->educationRecords()->orderByDesc('start_date')->orderByDesc('id')->get();
                $certifications = $profile->certifications()->newestFirst()->get();
                $projects = $profile->projects()->newestFirst()->get();
                $skills = $profile->skills()->orderBy('name')->get();
            @endphp

            <x-candidate-history :experience="$experience" :education="$education" :certifications="$certifications" :projects="$projects" />

            <x-profile.section :heading="__('Skills')">
                <div class="flex flex-col gap-4 border-t border-line px-5 py-4 sm:px-6">
                    @if ($skills->isEmpty())
                        <p class="text-sm text-ink-muted">{{ __('No skills on their profile.') }}</p>
                    @else
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($skills as $skill)
                                <li><x-profile.skill-chip :skill="$skill" :variant="$wantedSkillIds?->contains($skill->id) ? 'matched' : 'skill'" /></li>
                            @endforeach
                        </ul>

                        {{-- A legend rather than a note in brackets: when every
                             skill matches, the chips alone look the same as
                             when none does. --}}
                        @if ($wantedSkillIds?->isNotEmpty() && $skills->contains(fn ($skill) => $wantedSkillIds->contains($skill->id)))
                            <p class="flex items-center gap-2 text-xs text-ink-muted">
                                <flux:icon.check variant="micro" class="size-3.5 shrink-0 text-success-700 dark:text-success-300" aria-hidden="true" />
                                {{ __('Ticked: a skill this job asks for') }}
                            </p>
                        @endif
                    @endif

                    @if ($missingSkills?->isNotEmpty())
                        <div>
                            <h3 class="text-xs font-medium text-ink-muted">{{ __('Asked for, not on their profile') }}</h3>

                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($missingSkills as $skill)
                                    @php $required = $skill->pivot->importance === \App\Enums\SkillImportance::Required; @endphp
                                    <li>
                                        <x-chip variant="missing" :required="$required">
                                            {{ $skill->name }}
                                            @if ($required)
                                                <span class="font-normal">{{ __('(required)') }}</span>
                                            @endif
                                        </x-chip>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </x-profile.section>
        @endif
    </div>

    {{-- Inside the root but outside the stack of sections, so a page laying
         the profile out in a grid gets one item, and no dialog adds a gap. --}}
    @if ($owner)
        @include('candidate.profile.partials.dialogs', ['profile' => $profile, 'user' => $user])
    @endif
</div>
