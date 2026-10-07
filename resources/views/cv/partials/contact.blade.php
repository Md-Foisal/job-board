{{--
    The contact lines of a built CV: email, phone and place, then the
    links, printed as text as well as linked. In the body of the page in
    every template, since tracking systems often skip page headers.

    @param \App\Support\CvData $cv
    @param string $class  the lines' class, so a template can style them
--}}
@php
    $class ??= 'contact';
    $stacked ??= false;
    $lines = collect([$cv->email, $cv->phone, $cv->location])->filter();
@endphp

@if ($stacked)
    @foreach ($lines as $line)
        <p class="{{ $class }}">{{ $line }}</p>
    @endforeach
    @foreach ($cv->links as $link)
        <p class="{{ $class }}">
            @if ($link['url'])
                <a href="{{ $link['url'] }}">{{ $link['text'] }}</a>
            @else
                {{ $link['text'] }}
            @endif
        </p>
    @endforeach
@else
    <p class="{{ $class }}">{{ $lines->implode(' · ') }}</p>
    @if ($cv->links !== [])
        <p class="{{ $class }}">
            @foreach ($cv->links as $link)
                @unless ($loop->first)<span class="separator"> · </span>@endunless
                @if ($link['url'])
                    <a href="{{ $link['url'] }}">{{ $link['text'] }}</a>
                @else
                    {{ $link['text'] }}
                @endif
            @endforeach
        </p>
    @endif
@endif
