{{--
    A small drawing of a CV template's layout for the template picker, so
    the choice is seen rather than read. It stands for a printed page, so
    the paper is white and the greys are fixed in both themes, like the
    CV preview itself; the accent is the colour the CV would print with.

    @param \App\Enums\CvTemplate $template
    @param string $accent  hex colour
--}}
@props(['template', 'accent'])

@php
    $ink = '#374151';
    $line = '#d1d5db';
    $soft = '#e5e7eb';
    $bar = fn (string $width, string $colour = '#d1d5db', string $height = '3px') => "display:block;height:{$height};width:{$width};background:{$colour};border-radius:2px";
    $tint = \App\Support\CvDesign::mixWithWhite($accent, 0.88);
@endphp

<span aria-hidden="true" {{ $attributes->class('block aspect-[3/4] w-full overflow-hidden rounded-sm shadow-xs ring-1 ring-line') }} style="background:#ffffff">
    @switch ($template)
        @case (\App\Enums\CvTemplate::Creative)
            <span style="display:flex;height:100%">
                <span style="display:flex;flex-direction:column;gap:4px;width:34%;padding:8px 6px;background:{{ $tint }}">
                    <span style="display:block;width:16px;height:16px;border-radius:999px;background:{{ $accent }};opacity:.55"></span>
                    <span style="{{ $bar('80%', $accent, '2px') }}"></span>
                    <span style="{{ $bar('90%', $line, '2px') }}"></span>
                    <span style="{{ $bar('70%', $line, '2px') }}"></span>
                    <span style="{{ $bar('60%', $accent, '2px') }};margin-top:4px"></span>
                    <span style="{{ $bar('85%', $line, '2px') }}"></span>
                </span>
                <span style="display:flex;flex-direction:column;gap:4px;flex:1;padding:8px 7px">
                    <span style="{{ $bar('75%', $accent, '5px') }}"></span>
                    <span style="{{ $bar('50%', $ink, '2px') }}"></span>
                    @foreach ([1, 2] as $block)
                        <span style="{{ $bar('40%', $accent, '2px') }};margin-top:5px"></span>
                        <span style="{{ $bar('95%') }}"></span>
                        <span style="{{ $bar('88%') }}"></span>
                        <span style="{{ $bar('70%') }}"></span>
                    @endforeach
                </span>
            </span>
            @break

        @case (\App\Enums\CvTemplate::Bold)
            <span style="display:flex;flex-direction:column;gap:4px;height:100%;padding:7px">
                <span style="display:flex;flex-direction:column;gap:3px;padding:7px 6px;background:{{ $accent }};border-radius:1px">
                    <span style="{{ $bar('65%', '#ffffff', '5px') }}"></span>
                    <span style="{{ $bar('45%', '#ffffff', '2px') }};opacity:.8"></span>
                    <span style="{{ $bar('80%', '#ffffff', '2px') }};opacity:.6"></span>
                </span>
                @foreach ([1, 2, 3] as $block)
                    <span style="{{ $bar('35%', $accent, '2px') }};margin-top:5px"></span>
                    <span style="{{ $bar('100%', $accent, '1px') }}"></span>
                    <span style="{{ $bar('95%') }}"></span>
                    <span style="{{ $bar('80%') }}"></span>
                @endforeach
            </span>
            @break

        @case (\App\Enums\CvTemplate::Timeline)
            <span style="display:flex;flex-direction:column;gap:4px;height:100%;padding:8px 7px">
                <span style="{{ $bar('60%', $ink, '5px') }}"></span>
                <span style="{{ $bar('40%', $accent, '2px') }}"></span>
                <span style="{{ $bar('75%', $line, '2px') }}"></span>
                @foreach ([1, 2, 3] as $block)
                    <span style="display:flex;gap:5px;margin-top:5px">
                        <span style="{{ $bar('24%', $accent, '2px') }};flex:none"></span>
                        <span style="display:flex;flex-direction:column;gap:3px;flex:1;padding-left:4px;border-left:1px solid {{ $soft }}">
                            <span style="{{ $bar('70%', $ink, '2px') }}"></span>
                            <span style="{{ $bar('95%') }}"></span>
                            <span style="{{ $bar('80%') }}"></span>
                        </span>
                    </span>
                @endforeach
            </span>
            @break

        @case (\App\Enums\CvTemplate::Executive)
            <span style="display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;padding:9px 8px">
                <span style="{{ $bar('55%', $ink, '4px') }}"></span>
                <span style="{{ $bar('35%', $line, '2px') }}"></span>
                <span style="{{ $bar('70%', $line, '2px') }}"></span>
                @foreach ([1, 2, 3] as $block)
                    <span style="display:flex;flex-direction:column;align-items:center;width:100%;padding:2px 0;margin-top:5px;border-top:1px solid {{ $accent }};border-bottom:1px solid {{ $accent }}">
                        <span style="{{ $bar('30%', $ink, '2px') }}"></span>
                    </span>
                    <span style="{{ $bar('95%') }};align-self:flex-start"></span>
                    <span style="{{ $bar('82%') }};align-self:flex-start"></span>
                @endforeach
            </span>
            @break

        @case (\App\Enums\CvTemplate::Compact)
            <span style="display:flex;flex-direction:column;gap:3px;height:100%;padding:7px 7px">
                <span style="{{ $bar('50%', $accent, '4px') }}"></span>
                <span style="{{ $bar('80%', $line, '2px') }}"></span>
                @foreach ([1, 2, 3, 4] as $block)
                    <span style="{{ $bar('30%', $accent, '2px') }};margin-top:4px"></span>
                    <span style="display:flex;justify-content:space-between;gap:4px">
                        <span style="{{ $bar('55%', $ink, '2px') }}"></span>
                        <span style="{{ $bar('20%', $line, '2px') }}"></span>
                    </span>
                    <span style="{{ $bar('95%') }}"></span>
                    <span style="{{ $bar('85%') }}"></span>
                @endforeach
            </span>
            @break

        @default
            {{-- Classic and Modern: one column, the colour on the name and
                 headings for Modern only. --}}
            @php $colour = $template === \App\Enums\CvTemplate::Modern ? $accent : $ink; @endphp
            <span style="display:flex;flex-direction:column;gap:4px;height:100%;padding:8px 7px">
                <span style="{{ $bar('60%', $colour, '5px') }}"></span>
                <span style="{{ $bar('40%', $line, '2px') }}"></span>
                <span style="{{ $bar('75%', $line, '2px') }}"></span>
                @foreach ([1, 2, 3] as $block)
                    <span style="{{ $bar('35%', $colour, '2px') }};margin-top:5px"></span>
                    <span style="{{ $bar('100%', $line, '1px') }}"></span>
                    <span style="{{ $bar('95%') }}"></span>
                    <span style="{{ $bar('80%') }}"></span>
                @endforeach
            </span>
    @endswitch
</span>
