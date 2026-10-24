{{--
    The Compact CV: the most on one page. Smaller type and spacing, and
    each role's title, company and dates on a single line, the way
    experienced people keep a long history to one page. One column of
    real text, so tracking systems read it in order. CSS 2.1 only.
--}}
@php
    $design ??= new \App\Support\CvDesign(\App\Enums\CvTemplate::Compact, 'ink');
    $accent = $design->colour();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $cv->name }} – {{ __('CV') }}</title>
    <style>
        @page { margin: 12mm 15mm; }
        body, h1, h2, h3, h4, p, ul, ol, li { margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            line-height: 1.35;
            color: #1f2937;
            background: #ffffff;
        }
        body.preview { padding: 12mm 15mm; }
        .photo { float: right; width: 20mm; height: 20mm; margin-left: 5mm; }
        h1 { font-size: 17pt; line-height: 1.1; color: {{ $accent }}; }
        .headline { font-size: 10pt; color: #374151; margin-top: 0.5mm; }
        .contact { font-size: 8.5pt; color: #374151; margin-top: 1mm; }
        .contact a { color: #374151; text-decoration: none; }
        .separator { color: #9ca3af; }
        h2 {
            clear: both;
            font-size: 9.5pt;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: {{ $accent }};
            border-bottom: 0.5pt solid #d1d5db;
            padding-bottom: 0.5mm;
            margin-top: 4mm;
            margin-bottom: 1.5mm;
        }
        .entry { margin-bottom: 2mm; page-break-inside: avoid; }
        h3 { font-size: 9.5pt; color: #111827; display: inline; }
        .at { font-weight: normal; color: #374151; }
        .entry .dates { display: inline; font-size: 8.5pt; color: #6b7280; padding-left: 2mm; }
        .description { margin-top: 0.5mm; }
        .description p { margin-bottom: 0.5mm; }
        .description ul, .description ol { margin: 0.5mm 0 0.5mm 4.5mm; }
        .description ul { list-style-type: disc; }
        .description li { margin-bottom: 0.3mm; }
        .description h3, .description h4 { display: block; font-size: 9pt; margin: 1mm 0 0.3mm; }
        .description a { color: #1f2937; }
        .links { font-size: 8.5pt; color: #374151; margin-top: 0.5mm; }
        .links a { color: {{ $accent }}; }
    </style>
</head>
<body @class(['preview' => $preview ?? false])>
    @if ($photo)
        <img class="photo" src="{{ $photo }}" alt="{{ $cv->name }}">
    @endif

    <h1>{{ $cv->name }}</h1>
    @if ($cv->headline)
        <p class="headline">{{ $cv->headline }}</p>
    @endif

    @include('cv.partials.contact')

    @foreach ($design->sections() as $section)
        @include('cv.partials.section')
    @endforeach
</body>
</html>
