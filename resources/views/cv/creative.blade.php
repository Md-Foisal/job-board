{{--
    The Creative CV: a coloured side panel with the photo, contact lines,
    skills, languages, certifications, interests and personal details, and the main column with the name, summary
    and work. For roles where the CV is itself a sample of the person's
    eye. Two columns can be read out of order by tracking systems, which
    the builder says before anyone picks it.

    DOMPDF reads CSS 2.1 only, so the two columns are a table, the one
    layout it splits across pages reliably.
--}}
@php
    $design ??= new \App\Support\CvDesign(\App\Enums\CvTemplate::Creative, 'teal');
    $pageMargins = '12mm 12mm';
    $accent = $design->colour();
    $tint = $design->tint();
    $side = [\App\Enums\CvSection::Skills, \App\Enums\CvSection::Languages, \App\Enums\CvSection::Certifications, \App\Enums\CvSection::Interests, \App\Enums\CvSection::Personal];
    $sideSections = array_values(array_filter($design->sections(), fn ($section) => in_array($section, $side, true)));
    $mainSections = array_values(array_filter($design->sections(), fn ($section) => ! in_array($section, $side, true)));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $cv->name }} – {{ __('CV') }}</title>
    <style>
        @include('cv.partials.style')
        @page { margin: {{ $design->page($pageMargins) }}; }
        body, h1, h2, h3, h4, p, ul, ol, li { margin: 0; padding: 0; }
        body {
            font-family: {!! $design->typeface()->stack() !!};
            font-size: {{ $design->pt(9.5) }};
            line-height: 1.4;
            color: #1f2937;
            background: #ffffff;
        }
        body.preview { padding: {{ $design->page($pageMargins) }}; }
        table.layout { width: 100%; border-collapse: collapse; }
        td.side {
            width: 32%;
            vertical-align: top;
            background: {{ $tint }};
            padding: 6mm 5mm;
        }
        td.main { vertical-align: top; padding: 2mm 0 0 7mm; }
        .photo { width: 30mm; height: 30mm; margin-bottom: {{ $design->gap(4) }}; }
        h1 { font-size: {{ $design->pt(22) }}; line-height: 1.1; color: {{ $accent }}; }
        .headline { font-size: {{ $design->pt(11) }}; color: #374151; margin-top: {{ $design->gap(1.5) }}; }
        .contact { font-size: {{ $design->pt(8.5) }}; color: #1f2937; margin-bottom: {{ $design->gap(1) }}; }
        .contact a { color: #1f2937; text-decoration: none; }
        .separator { color: #9ca3af; }
        h2 {
            font-size: {{ $design->pt(10.5) }};
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: {{ $accent }};
            margin-top: {{ $design->gap(6) }};
            margin-bottom: {{ $design->gap(2) }};
        }
        td.side h2 { margin-top: {{ $design->gap(5) }}; font-size: {{ $design->pt(9.5) }}; }
        td.side h2.first { margin-top: 0; }
        .entry { margin-bottom: {{ $design->gap(3) }}; page-break-inside: avoid; }
        h3 { font-size: {{ $design->pt(10) }}; color: #111827; }
        td.side h3 { font-size: {{ $design->pt(9) }}; }
        .at { font-weight: normal; color: #374151; }
        .dates { font-size: {{ $design->pt(8.5) }}; color: #6b7280; }
        .description { margin-top: {{ $design->gap(1) }}; }
        .description p { margin-bottom: {{ $design->gap(1) }}; }
        .description ul, .description ol { margin: 1mm 0 1mm 5mm; }
        .description ul { list-style-type: disc; }
        .description li { margin-bottom: {{ $design->gap(0.5) }}; }
        .description h3, .description h4 { font-size: {{ $design->pt(9.5) }}; margin: 1.5mm 0 0.5mm; }
        .description a { color: #1f2937; }
        .links { font-size: {{ $design->pt(8.5) }}; color: #374151; margin-top: {{ $design->gap(1) }}; }
        .links a { color: {{ $accent }}; }
    </style>
</head>
<body @class(['preview' => $preview ?? false]) data-margins="{{ implode(',', $design->pageMargins($pageMargins)) }}">
    <table class="layout">
        <tr>
            <td class="side">
                @if ($photo)
                    <img class="photo" src="{{ $photo }}" alt="{{ $cv->name }}">
                @endif

                <h2 class="first">{{ $design->label('Contact', 'যোগাযোগ') }}</h2>
                @include('cv.partials.contact', ['stacked' => true])

                @foreach ($sideSections as $section)
                    @include('cv.partials.section')
                @endforeach
            </td>
            <td class="main">
                <h1>{{ $cv->name }}</h1>
                @if ($cv->headline)
                    <p class="headline">{{ $cv->headline }}</p>
                @endif

                @foreach ($mainSections as $section)
                    @include('cv.partials.section')
                @endforeach

                @include('cv.partials.qr')
            </td>
        </tr>
    </table>
</body>
</html>
