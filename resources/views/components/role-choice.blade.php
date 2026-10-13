{{--
    "I want to: Find a job / Hire" -- the side someone signs up for, on the
    sign-up form and on the last step of signing up with Google or GitHub.
    Posted as `role` (CreateNewUser, SocialSignUpController).
--}}
@props(['selected' => 'candidate'])

<flux:radio.group
    name="role"
    :label="__('I want to')"
    :description="__('You can add the other one later, on the same account.')"
    variant="cards"
    class="grid grid-cols-2"
>
    <flux:radio value="candidate" :checked="$selected === 'candidate'">
        <flux:radio.indicator />
        <div class="flex flex-col items-center gap-1.5 text-center">
            <flux:icon.magnifying-glass class="size-6 icon-sunset" />
            <flux:heading>{{ __('Find a job') }}</flux:heading>
            <flux:text size="sm">{{ __('Build a profile and apply') }}</flux:text>
        </div>
    </flux:radio>
    <flux:radio value="employer" :checked="$selected === 'employer'">
        <flux:radio.indicator />
        <div class="flex flex-col items-center gap-1.5 text-center">
            <flux:icon.building-office-2 class="size-6 icon-sunset" />
            <flux:heading>{{ __('Hire') }}</flux:heading>
            <flux:text size="sm">{{ __('Post jobs and review applicants') }}</flux:text>
        </div>
    </flux:radio>
</flux:radio.group>
