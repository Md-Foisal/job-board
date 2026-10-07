{{--
    The Creative CV: a coloured side panel with the photo, contact lines,
    skills and certifications, and the main column with the name, summary
    and work. For roles where the CV is itself a sample of the person's
    eye. Two columns can be read out of order by tracking systems, which
    the builder says before anyone picks it.

    DOMPDF reads CSS 2.1 only, so the two columns are a table, the one
    layout it splits across pages reliably.
--}}
@php
    $design ??= new \App\Support\CvDesign(\App\Enums\CvTemplate::Creative, 'teal');
    $accent = $design->colour();
    $tint = $design->tint();
    $side = [\App\Enums\CvSection::Skills, \App\Enums\CvSection::Certifications];
    $sideSections = array_values(array_filter($design->sections(), fn ($section) => in_array($section, $side, true)));
    $mainSections = array_values(array_filter($design->sections(), fn ($section) => ! in_array($section, $side, true)));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $cv->name }} – {{ __('CV') }}</title>
    <style>
        @page { margin: 12mm 12mm; }
        body, h1, h2, h3, h4, p, ul, ol, li { margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            line-height: 1.4;
            color: #1f2937;
            background: #ffffff;
        }
        body.preview { padding: 12mm; }
        table.layout { width: 100%; border-collapse: collapse; }
        td.side {
            width: 32%;
            vertical-align: top;
            background: {{ $tint }};
            padding: 6mm 5mm;
        }
        td.main { vertical-align: top; padding: 2mm 0 0 7mm; }
        .photo { width: 30mm; height: 30mm; margin-bottom: 4mm; }
        h1 { font-size: 22pt; line-height: 1.1; color: {{ $accent }}; }
        .headline { font-size: 11pt; color: #374151; margin-top: 1.5mm; }
        .contact { font-size: 8.5pt; color: #1f2937; margin-bottom: 1mm; }
        .contact a { color: #1f2937; text-decoration: none; }
        .separator { color: #9ca3af; }
        h2 {
            font-size: 10.5pt;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: {{ $accent }};
            margin-top: 6mm;
            margin-bottom: 2mm;
        }
        td.side h2 { margin-top: 5mm; font-size: 9.5pt; }
        td.side h2.first { margin-top: 0; }
        .entry { margin-bottom: 3mm; page-break-inside: avoid; }
        h3 { font-size: 10pt; color: #111827; }
        td.side h3 { font-size: 9pt; }
        .at { font-weight: normal; color: #374151; }
        .dates { font-size: 8.5pt; color: #6b7280; }
        .description { margin-top: 1mm; }
        .description p { margin-bottom: 1mm; }
        .description ul, .description ol { margin: 1mm 0 1mm 5mm; }
        .description ul { list-style-type: disc; }
        .description li { margin-bottom: 0.5mm; }
        .description h3, .description h4 { font-size: 9.5pt; margin: 1.5mm 0 0.5mm; }
        .description a { color: #1f2937; }
        .links { font-size: 8.5pt; color: #374151; margin-top: 1mm; }
        .links a { color: {{ $accent }}; }
    </style>
</head>
<body @class(['preview' => $preview ?? false])>
    <table class="layout">
        <tr>
            <td class="side">
                @if ($photo)
                    <img class="photo" src="{{ $photo }}" alt="{{ $cv->name }}">
                @endif

                <h2 class="first">{{ __('Contact') }}</h2>
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
            </td>
        </tr>
    </table>
</body>
</html>
