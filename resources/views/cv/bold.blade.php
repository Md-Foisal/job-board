{{--
    The Bold CV: the name, headline and contact lines in white on a band
    of the chosen colour across the top, then one column of work. The
    band is part of the page's text, not a page header, so tracking
    systems still read the contact details and every section in order.
    The band sits inside the page margins, so a second page starts with the
    same margins as the first. CSS 2.1 only.
--}}
@php
    $design ??= new \App\Support\CvDesign(\App\Enums\CvTemplate::Bold, 'sunset');
    $pageMargins = '12mm 14mm';
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
        .band { background: {{ $accent }}; color: #ffffff; padding: 8mm 9mm 7mm; }
        .photo { float: right; width: 26mm; height: 26mm; margin-left: 6mm; border: 1.5pt solid #ffffff; }
        h1 { font-size: {{ $design->pt(24) }}; line-height: 1.1; color: #ffffff; }
        .headline { font-size: {{ $design->pt(11) }}; color: #ffffff; margin-top: {{ $design->gap(1) }}; }
        .band .contact { font-size: {{ $design->pt(8.5) }}; color: #ffffff; margin-top: {{ $design->gap(1.5) }}; }
        .band .contact a { color: #ffffff; text-decoration: none; }
        .band .separator { color: #ffffff; }
        .band .end { clear: both; }
        .main { padding: 0 4mm; }
        h2 {
            font-size: {{ $design->pt(11) }};
            color: {{ $accent }};
            border-bottom: 1.2pt solid {{ $accent }};
            padding-bottom: {{ $design->gap(1) }};
            margin-top: {{ $design->gap(6) }};
            margin-bottom: {{ $design->gap(2.5) }};
        }
        .entry { margin-bottom: {{ $design->gap(3.5) }}; page-break-inside: avoid; }
        h3 { font-size: {{ $design->pt(10.5) }}; color: #111827; }
        .at { font-weight: normal; color: #374151; }
        .dates { font-size: {{ $design->pt(9) }}; color: #6b7280; }
        .description { margin-top: {{ $design->gap(1) }}; }
        .description p { margin-bottom: {{ $design->gap(1) }}; }
        .description ul, .description ol { margin: 1mm 0 1mm 5mm; }
        .description ul { list-style-type: disc; }
        .description li { margin-bottom: {{ $design->gap(0.5) }}; }
        .description h3, .description h4 { font-size: {{ $design->pt(10) }}; margin: 1.5mm 0 0.5mm; }
        .description a { color: #1f2937; }
        .links { font-size: {{ $design->pt(9) }}; color: #374151; margin-top: {{ $design->gap(1) }}; }
        .links a { color: {{ $accent }}; }
    </style>
</head>
<body @class(['preview' => $preview ?? false]) data-margins="{{ implode(',', $design->pageMargins($pageMargins)) }}">
    <div class="band">
        @if ($photo)
            <img class="photo" src="{{ $photo }}" alt="{{ $cv->name }}">
        @endif

        <h1>{{ $cv->name }}</h1>
        @if ($cv->headline)
            <p class="headline">{{ $cv->headline }}</p>
        @endif

        @include('cv.partials.contact')
        <div class="end"></div>
    </div>

    <div class="main">
        @foreach ($design->sections() as $section)
            @include('cv.partials.section')
        @endforeach
    </div>

    @include('cv.partials.qr')
</body>
</html>
