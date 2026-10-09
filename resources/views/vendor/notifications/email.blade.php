{{--
    Every notification email that is built from lines (MailMessage), in
    the product's voice: Laravel's "Whoops!" and "Regards, JobBoard" give
    way to a plain greeting and a sign-off from the team, and the note
    under the button is shorter.
--}}
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
# @lang('Hello,')
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('The :app team', ['app' => config('app.name')])
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
@lang('If the button does not work, paste this link into your browser:') <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
