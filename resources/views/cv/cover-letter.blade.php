{{--
    A cover letter in the look of one of the candidate's CVs: the same
    typeface and colour, the name and contact lines as a letterhead, then
    the date, who it is for, and the letter. One template for the preview
    and the PDF, CSS 2.1 only so DOMPDF draws it too.

    @param \App\Support\CvData $cv  the letterhead
    @param \App\Support\CvDesign $design
    @param \App\Models\CoverLetter $letter
    @param string $date  written out, in the candidate's zone
--}}
@php
    $pageMargins = '20mm 22mm';
    $accent = $design->colour();
    $paragraphs = preg_split('/\R{2,}/u', trim((string) $letter->body)) ?: [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $cv->name }} – {{ __('Cover letter') }}</title>
    <style>
        @include('cv.partials.style')
        @page { margin: {{ $design->page($pageMargins) }}; }
        body, h1, p { margin: 0; padding: 0; }
        body {
            font-family: {!! $design->typeface()->stack() !!};
            font-size: {{ $design->pt(10.5) }};
            line-height: 1.5;
            color: #1f2937;
            background: #ffffff;
        }
        body.preview { padding: {{ $design->page($pageMargins) }}; }
        h1 { font-size: {{ $design->pt(20) }}; line-height: 1.15; color: {{ $accent }}; }
        .headline { font-size: {{ $design->pt(11) }}; color: #374151; margin-top: {{ $design->gap(1) }}; }
        .contact { font-size: {{ $design->pt(9) }}; color: #374151; margin-top: {{ $design->gap(1.5) }}; }
        .contact a { color: #374151; text-decoration: none; }
        .separator { color: #9ca3af; }
        .rule { border-bottom: 1.2pt solid {{ $accent }}; margin-top: {{ $design->gap(5) }}; margin-bottom: {{ $design->gap(8) }}; }
        .meta { margin-bottom: {{ $design->gap(6) }}; }
        .meta p { margin-bottom: 0.5mm; }
        .subject { font-weight: bold; margin-bottom: {{ $design->gap(5) }}; }
        .letter p { margin-bottom: {{ $design->gap(4) }}; }
    </style>
</head>
<body @class(['preview' => $preview ?? false]) data-margins="{{ implode(',', $design->pageMargins($pageMargins)) }}">
    <h1>{{ $cv->name }}</h1>
    @if ($cv->headline)
        <p class="headline">{{ $cv->headline }}</p>
    @endif
    @include('cv.partials.contact')
    <div class="rule"></div>

    <div class="meta">
        <p>{{ $date }}</p>
        @if ($letter->recipient)
            <p>{{ $letter->recipient }}</p>
        @endif
        @if ($letter->companyName())
            <p>{{ $letter->companyName() }}</p>
        @endif
    </div>

    @if ($letter->roleTitle())
        <p class="subject">{{ __('Application for :role', ['role' => $letter->roleTitle()]) }}</p>
    @endif

    <div class="letter">
        @foreach ($paragraphs as $paragraph)
            <p>{!! nl2br(e($paragraph)) !!}</p>
        @endforeach
    </div>
</body>
</html>
