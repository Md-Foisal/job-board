{{--
    The Modern CV: the Classic one's single column of real text, with the
    candidate's chosen colour on the name, a rule under the contact lines
    and the section headings. Still one column, so tracking systems read
    it in order. CSS 2.1 only, for DOMPDF.
--}}
@php
    $design ??= new \App\Support\CvDesign(\App\Enums\CvTemplate::Modern, 'navy');
    $pageMargins = '16mm 18mm';
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
            font-size: {{ $design->pt(10) }};
            line-height: 1.4;
            color: #1f2937;
            background: #ffffff;
        }
        body.preview { padding: {{ $design->page($pageMargins) }}; }
        .photo {
            float: right;
            width: 26mm;
            height: 26mm;
            margin-left: 6mm;
        }
        h1 { font-size: {{ $design->pt(24) }}; line-height: 1.1; color: {{ $accent }}; }
        .headline { font-size: {{ $design->pt(11) }}; color: #374151; margin-top: {{ $design->gap(1) }}; }
        .contact { font-size: {{ $design->pt(9) }}; color: #374151; margin-top: {{ $design->gap(1.5) }}; }
        .rule { clear: both; border-bottom: 1.5pt solid {{ $accent }}; margin-top: {{ $design->gap(4) }}; }
        .contact a { color: #374151; text-decoration: none; }
        .separator { color: #9ca3af; }
        h2 {
            clear: both;
            font-size: {{ $design->pt(12) }};
            color: {{ $accent }};
            border-bottom: 0.6pt solid #d1d5db;
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
    @if ($photo)
        <img class="photo" src="{{ $photo }}" alt="{{ $cv->name }}">
    @endif

    <h1>{{ $cv->name }}</h1>
    @if ($cv->headline)
        <p class="headline">{{ $cv->headline }}</p>
    @endif

    @include('cv.partials.contact')
    <div class="rule"></div>

    @foreach ($design->sections() as $section)
        @include('cv.partials.section')
    @endforeach

    @include('cv.partials.qr')
</body>
</html>
