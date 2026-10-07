{{--
    The one way to reach the people behind the board. The quicker answers
    come first, so someone with a forgotten password or a dodgy posting
    does not wait two days for a reply that only points them to a button.
    After sending, the form gives way to a confirmation that says where
    the answer will go and when.
--}}
@php
    $days = \App\Models\ContactMessage::REPLY_WITHIN_WORKING_DAYS;
    $sentTo = session('contactSentTo');
@endphp

<x-static-page
    :heading="__('Contact us')"
    :lead="__('Questions about your account, a job, a company or your data: write to us, and we will answer by email within :days working days.', ['days' => $days])"
>
    @if ($sentTo)
        <x-card role="status" class="flex flex-col items-start gap-4">
            <x-icon-tile icon="check-circle" />
            <div>
                <h2 class="text-heading text-ink">{{ __('Message sent') }}</h2>
                <p class="mt-2">
                    {{ __('We will reply to :email within :days working days. Look out for an email from :app.', [
                        'email' => $sentTo,
                        'days' => $days,
                        'app' => config('app.name'),
                    ]) }}
                </p>
            </div>
            <a href="{{ route('contact') }}" class="text-sm font-medium text-sunset-small hover:underline">{{ __('Send another message') }}</a>
        </x-card>
    @else
        <section aria-labelledby="quicker-answers" class="space-y-3">
            <h2 id="quicker-answers" class="text-heading text-ink">{{ __('Quicker answers') }}</h2>
            <ul class="space-y-2 text-sm">
                <li>
                    {{ __('Forgot your password?') }}
                    <a href="{{ route('password.request') }}" class="font-medium text-sunset-small hover:underline">{{ __('Reset it now') }}</a>
                </li>
                <li>{{ __('Something wrong with a job, a company or a review? Use Report on its page while signed in: our team reads every report, and it reaches them with the page attached.') }}</li>
                <li>
                    {{ __('Want to delete your account?') }}
                    <a href="{{ route('profile.edit') }}" class="font-medium text-sunset-small hover:underline">{{ __('You can do it yourself in your settings') }}</a>
                </li>
            </ul>
        </section>

        <section aria-labelledby="write-to-us" class="space-y-4">
            <h2 id="write-to-us" class="text-heading text-ink">{{ __('Write to us') }}</h2>

            <x-card as="form" method="POST" action="{{ route('contact.store') }}" class="relative flex flex-col gap-6">
                @csrf

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input name="name" :label="__('Your name')" :value="old('name', $name)" required autocomplete="name" />
                    <flux:input name="email" type="email" :label="__('Email address')" :value="old('email', $email)" required autocomplete="email" />
                </div>

                <flux:select name="topic" :label="__('What is it about?')" :placeholder="__('Choose one')" required>
                    @foreach (\App\Enums\ContactTopic::cases() as $case)
                        <flux:select.option :value="$case->value" :selected="old('topic', $topic) === $case->value">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea
                    name="body"
                    :label="__('Message')"
                    :description="__('If it is about a job, a company or an application, say which one.')"
                    rows="6"
                    required
                >{{ old('body') }}</flux:textarea>

                {{-- Off screen and out of the tab order, so only a script
                     filling in every field finds it. --}}
                <div class="absolute -left-[9999px] top-0" aria-hidden="true">
                    <label>
                        {{ __('Leave this empty') }}
                        <input type="text" name="{{ \App\Http\Controllers\ContactController::HONEYPOT }}" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-meta text-ink-muted">
                        {{ __('We use what you write only to answer you.') }}
                        <a href="{{ route('privacy') }}#writing-to-us" class="text-sunset-small hover:underline">{{ __('How we keep it') }}</a>
                    </p>
                    <flux:button type="submit" variant="primary" class="btn-sunset" icon:trailing="paper-airplane">
                        {{ __('Send message') }}
                    </flux:button>
                </div>
            </x-card>
        </section>
    @endif
</x-static-page>
