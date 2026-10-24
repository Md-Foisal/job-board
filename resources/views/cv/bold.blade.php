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
    $accent = $design->colour();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $cv->name }} – {{ __('CV') }}</title>
    <style>
        @page { margin: 12mm 14mm; }
        body, h1, h2, h3, h4, p, ul, ol, li { margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            line-height: 1.4;
            color: #1f2937;
            background: #ffffff;
        }
        body.preview { padding: 12mm 14mm; }
        .band { background: {{ $accent }}; color: #ffffff; padding: 8mm 9mm 7mm; }
        .photo { float: right; width: 26mm; height: 26mm; margin-left: 6mm; border: 1.5pt solid #ffffff; }
        h1 { font-size: 24pt; line-height: 1.1; color: #ffffff; }
        .headline { font-size: 11pt; color: #ffffff; margin-top: 1mm; }
        .band .contact { font-size: 8.5pt; color: #ffffff; margin-top: 1.5mm; }
        .band .contact a { color: #ffffff; text-decoration: none; }
        .band .separator { color: #ffffff; }
        .band .end { clear: both; }
        .main { padding: 0 4mm; }
        h2 {
            font-size: 11pt;
            color: {{ $accent }};
            border-bottom: 1.2pt solid {{ $accent }};
            padding-bottom: 1mm;
            margin-top: 6mm;
            margin-bottom: 2.5mm;
        }
        .entry { margin-bottom: 3.5mm; page-break-inside: avoid; }
        h3 { font-size: 10.5pt; color: #111827; }
        .at { font-weight: normal; color: #374151; }
        .dates { font-size: 9pt; color: #6b7280; }
        .description { margin-top: 1mm; }
        .description p { margin-bottom: 1mm; }
        .description ul, .description ol { margin: 1mm 0 1mm 5mm; }
        .description ul { list-style-type: disc; }
        .description li { margin-bottom: 0.5mm; }
        .description h3, .description h4 { font-size: 10pt; margin: 1.5mm 0 0.5mm; }
        .description a { color: #1f2937; }
        .links { font-size: 9pt; color: #374151; margin-top: 1mm; }
        .links a { color: {{ $accent }}; }
    </style>
</head>
<body @class(['preview' => $preview ?? false])>
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
</body>
</html>
