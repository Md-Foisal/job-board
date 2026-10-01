{{--
    One template for the preview and the PDF. DOMPDF reads CSS 2.1 only
    (no flexbox or grid), so the layout is plain blocks and one float.

    Written for applicant tracking systems: a single column, contact
    details in the body rather than a page header, the section names
    parsers look for, round bullets, and real text throughout. Each role
    and course is kept on one page where it fits.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $cv->name }} – {{ __('CV') }}</title>
    <style>
        @page { margin: 16mm 18mm; }
        body, h1, h2, h3, h4, p, ul, ol, li { margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #1f2937;
            background: #ffffff;
        }
        .photo {
            float: right;
            width: 26mm;
            height: 26mm;
            margin-left: 6mm;
        }
        h1 { font-size: 20pt; line-height: 1.15; color: #111827; }
        .headline { font-size: 11pt; color: #374151; margin-top: 1mm; }
        .contact { font-size: 9pt; color: #374151; margin-top: 1.5mm; }
        .contact a { color: #374151; text-decoration: none; }
        .separator { color: #9ca3af; }
        h2 {
            clear: both;
            font-size: 11pt;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #111827;
            border-bottom: 0.6pt solid #9ca3af;
            padding-bottom: 1mm;
            margin-top: 6mm;
            margin-bottom: 2.5mm;
        }
        .entry { margin-bottom: 3.5mm; page-break-inside: avoid; }
        h3 { font-size: 10.5pt; color: #111827; }
        .at { font-weight: normal; }
        .dates { font-size: 9pt; color: #6b7280; }
        .description { margin-top: 1mm; }
        .description p { margin-bottom: 1mm; }
        .description ul, .description ol { margin: 1mm 0 1mm 5mm; }
        .description ul { list-style-type: disc; }
        .description li { margin-bottom: 0.5mm; }
        .description h3, .description h4 { font-size: 10pt; margin: 1.5mm 0 0.5mm; }
        .description a { color: #1f2937; }
    </style>
</head>
<body>
    @if ($photo)
        <img class="photo" src="{{ $photo }}" alt="{{ $cv->name }}">
    @endif

    <h1>{{ $cv->name }}</h1>
    @if ($cv->headline)
        <p class="headline">{{ $cv->headline }}</p>
    @endif

    <p class="contact">
        {{ collect([$cv->email, $cv->phone, $cv->location])->filter()->implode(' · ') }}
    </p>
    @if ($cv->links !== [])
        <p class="contact">
            @foreach ($cv->links as $link)
                @unless ($loop->first)<span class="separator"> · </span>@endunless
                <a href="{{ $link['url'] }}">{{ $link['text'] }}</a>
            @endforeach
        </p>
    @endif

    @if ($cv->summary)
        <h2>{{ __('Summary') }}</h2>
        <p>{!! nl2br(e($cv->summary)) !!}</p>
    @endif

    @if ($cv->experience !== [])
        <h2>{{ __('Work Experience') }}</h2>
        @foreach ($cv->experience as $role)
            <div class="entry">
                <h3>{{ $role['title'] }} <span class="at">· {{ $role['company'] }}</span></h3>
                <p class="dates">{{ $role['dates'] }}</p>
                @if ($role['description'])
                    {{-- Cleaned to a short list of tags when it was saved. --}}
                    <div class="description">{!! $role['description'] !!}</div>
                @endif
            </div>
        @endforeach
    @endif

    @if ($cv->education !== [])
        <h2>{{ __('Education') }}</h2>
        @foreach ($cv->education as $course)
            <div class="entry">
                <h3>
                    @if ($course['title'] !== '')
                        {{ $course['title'] }} <span class="at">· {{ $course['institution'] }}</span>
                    @else
                        {{ $course['institution'] }}
                    @endif
                </h3>
                <p class="dates">{{ $course['dates'] }}</p>
            </div>
        @endforeach
    @endif

    @if ($cv->skills !== [])
        <h2>{{ __('Skills') }}</h2>
        <p>{{ implode(', ', $cv->skills) }}</p>
    @endif
</body>
</html>
