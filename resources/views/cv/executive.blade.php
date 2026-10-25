{{--
    The Executive CV: a centred letterhead in a serif face, uppercase
    section headings between thin rules, and the chosen colour only on
    those rules. For formal and senior roles. One column of real text, so
    tracking systems read it in order. The serif is DejaVu Serif, which
    ships with DOMPDF, so no font files are added. CSS 2.1 only.
--}}
@php
    $design ??= new \App\Support\CvDesign(\App\Enums\CvTemplate::Executive, 'navy');
    $accent = $design->colour();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $cv->name }} – {{ __('CV') }}</title>
    <style>
        @page { margin: 16mm 20mm; }
        body, h1, h2, h3, h4, p, ul, ol, li { margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Serif', serif;
            font-size: 10pt;
            line-height: 1.45;
            color: #1f2937;
            background: #ffffff;
        }
        body.preview { padding: 16mm 20mm; }
        .letterhead { text-align: center; }
        .photo { width: 24mm; height: 24mm; margin-bottom: 3mm; }
        h1 { font-size: 22pt; line-height: 1.1; letter-spacing: 0.04em; color: #111827; font-weight: normal; }
        .headline { font-size: 11pt; color: #374151; margin-top: 1.5mm; font-style: italic; }
        .contact { font-size: 9pt; color: #374151; margin-top: 1.5mm; }
        .contact a { color: #374151; text-decoration: none; }
        .separator { color: #9ca3af; }
        .rule { border-bottom: 1pt solid {{ $accent }}; margin-top: 4mm; }
        h2 {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            text-align: center;
            color: #111827;
            border-top: 0.6pt solid {{ $accent }};
            border-bottom: 0.6pt solid {{ $accent }};
            padding: 1mm 0;
            margin-top: 6mm;
            margin-bottom: 3mm;
        }
        .entry { margin-bottom: 3.5mm; page-break-inside: avoid; }
        h3 { font-size: 10.5pt; color: #111827; }
        .at { font-weight: normal; font-style: italic; color: #374151; }
        .dates { font-size: 9pt; color: #6b7280; }
        .description { margin-top: 1mm; }
        .description p { margin-bottom: 1mm; }
        .description ul, .description ol { margin: 1mm 0 1mm 5mm; }
        .description ul { list-style-type: disc; }
        .description li { margin-bottom: 0.5mm; }
        .description h3, .description h4 { font-size: 10pt; margin: 1.5mm 0 0.5mm; }
        .description a { color: #1f2937; }
        .links { font-size: 9pt; color: #374151; margin-top: 1mm; }
        .links a { color: #1f2937; }
    </style>
</head>
<body @class(['preview' => $preview ?? false])>
    <div class="letterhead">
        @if ($photo)
            <img class="photo" src="{{ $photo }}" alt="{{ $cv->name }}">
        @endif

        <h1>{{ $cv->name }}</h1>
        @if ($cv->headline)
            <p class="headline">{{ $cv->headline }}</p>
        @endif

        @include('cv.partials.contact')
    </div>
    <div class="rule"></div>

    @foreach ($design->sections() as $section)
        @include('cv.partials.section')
    @endforeach
</body>
</html>
