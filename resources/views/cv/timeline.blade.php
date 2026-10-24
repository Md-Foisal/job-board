{{--
    The Timeline CV: each section's name in a narrow column on the left
    and its entries beside it, like labelled rows. The heading comes first
    in the page's text and the entries follow it, so tracking systems
    still read every section in order; it only looks like two columns.
    The heading column is a float, which DOMPDF draws reliably across
    pages. CSS 2.1 only.
--}}
@php
    $design ??= new \App\Support\CvDesign(\App\Enums\CvTemplate::Timeline, 'teal');
    $accent = $design->colour();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $cv->name }} – {{ __('CV') }}</title>
    <style>
        @page { margin: 15mm 17mm; }
        body, h1, h2, h3, h4, p, ul, ol, li { margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            line-height: 1.4;
            color: #1f2937;
            background: #ffffff;
        }
        body.preview { padding: 15mm 17mm; }
        .photo { float: right; width: 24mm; height: 24mm; margin-left: 6mm; }
        h1 { font-size: 22pt; line-height: 1.1; color: #111827; }
        .headline { font-size: 11pt; color: {{ $accent }}; margin-top: 1mm; }
        .contact { font-size: 8.5pt; color: #374151; margin-top: 1.5mm; }
        .contact a { color: #374151; text-decoration: none; }
        .separator { color: #9ca3af; }
        .sections { clear: both; margin-top: 5mm; }
        .sections h2 {
            clear: both;
            float: left;
            width: 30mm;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: {{ $accent }};
            padding-top: 4.5mm;
        }
        .sections > p,
        .sections > .entry {
            margin-left: 35mm;
            padding-left: 4mm;
            border-left: 0.8pt solid #e5e7eb;
        }
        .sections > p { padding-top: 4mm; padding-bottom: 1mm; }
        .entry { padding-top: 4mm; page-break-inside: avoid; }
        h3 { font-size: 10pt; color: #111827; }
        .at { font-weight: normal; color: #374151; }
        .dates { font-size: 8.5pt; color: {{ $accent }}; }
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
    @if ($photo)
        <img class="photo" src="{{ $photo }}" alt="{{ $cv->name }}">
    @endif

    <h1>{{ $cv->name }}</h1>
    @if ($cv->headline)
        <p class="headline">{{ $cv->headline }}</p>
    @endif

    @include('cv.partials.contact')

    <div class="sections">
        @foreach ($design->sections() as $section)
            @include('cv.partials.section')
        @endforeach
    </div>
</body>
</html>
