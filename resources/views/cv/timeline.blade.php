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
    $pageMargins = '15mm 17mm';
    $accent = $design->colour();
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
        .photo { float: right; width: 24mm; height: 24mm; margin-left: 6mm; }
        h1 { font-size: {{ $design->pt(22) }}; line-height: 1.1; color: #111827; }
        .headline { font-size: {{ $design->pt(11) }}; color: {{ $accent }}; margin-top: {{ $design->gap(1) }}; }
        .contact { font-size: {{ $design->pt(8.5) }}; color: #374151; margin-top: {{ $design->gap(1.5) }}; }
        .contact a { color: #374151; text-decoration: none; }
        .separator { color: #9ca3af; }
        .sections { clear: both; margin-top: {{ $design->gap(5) }}; }
        .sections h2 {
            clear: both;
            float: left;
            width: 30mm;
            font-size: {{ $design->pt(8.5) }};
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: {{ $accent }};
            padding-top: {{ $design->gap(4.5) }};
        }
        .sections > p,
        .sections > .entry {
            margin-left: 35mm;
            padding-left: 4mm;
            border-left: 0.8pt solid #e5e7eb;
        }
        .sections > p { padding-top: {{ $design->gap(4) }}; padding-bottom: {{ $design->gap(1) }}; }
        .entry { padding-top: {{ $design->gap(4) }}; page-break-inside: avoid; }
        h3 { font-size: {{ $design->pt(10) }}; color: #111827; }
        .at { font-weight: normal; color: #374151; }
        .dates { font-size: {{ $design->pt(8.5) }}; color: {{ $accent }}; }
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

    @include('cv.partials.qr')
</body>
</html>
