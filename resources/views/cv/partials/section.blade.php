{{--
    One section of a built CV, the same markup in every template: each
    template only styles it. Nothing is printed for a section the CV has
    nothing in.

    Every block is an .entry or a p directly under the heading, which is
    what the Timeline template lays out beside its headings.

    @param \App\Enums\CvSection $section
    @param \App\Support\CvData $cv
    @param \App\Support\CvDesign $design
--}}
@php
    $bengali = $design->headings === 'bn';
@endphp
@switch ($section)
    @case (\App\Enums\CvSection::Summary)
        @if ($cv->summary)
            <h2>{{ $design->heading($section) }}</h2>
            <p>{!! nl2br(e($cv->summary)) !!}</p>
        @endif
        @break

    @case (\App\Enums\CvSection::Experience)
        @if ($cv->experience !== [])
            <h2>{{ $design->heading($section) }}</h2>
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
        @break

    @case (\App\Enums\CvSection::Projects)
        @if ($cv->projects !== [])
            <h2>{{ $design->heading($section) }}</h2>
            @foreach ($cv->projects as $project)
                <div class="entry">
                    <h3>{{ $project['name'] }}</h3>
                    @if ($project['dates'])
                        <p class="dates">{{ $project['dates'] }}</p>
                    @endif
                    @if ($project['description'])
                        <div class="description">{!! $project['description'] !!}</div>
                    @endif
                    @if ($project['links'] !== [])
                        <p class="links">
                            @foreach ($project['links'] as $link)
                                @unless ($loop->first)<span class="separator"> · </span>@endunless
                                {{ __($link['label']) }}:
                                @if ($link['url'])
                                    <a href="{{ $link['url'] }}">{{ $link['text'] }}</a>
                                @else
                                    {{ $link['text'] }}
                                @endif
                            @endforeach
                        </p>
                    @endif
                </div>
            @endforeach
        @endif
        @break

    @case (\App\Enums\CvSection::Education)
        @if ($cv->education !== [])
            <h2>{{ $design->heading($section) }}</h2>
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
        @break

    @case (\App\Enums\CvSection::Skills)
        @if ($cv->skills !== [])
            <h2>{{ $design->heading($section) }}</h2>
            @if ($design->skillLayout === 'list')
                <div class="entry description">
                    <ul>
                        @foreach ($cv->skills as $skill)
                            <li>{{ $skill }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p>{{ implode(', ', $cv->skills) }}</p>
            @endif
        @endif
        @break

    @case (\App\Enums\CvSection::Certifications)
        @if ($cv->certifications !== [])
            <h2>{{ $design->heading($section) }}</h2>
            @foreach ($cv->certifications as $certification)
                <div class="entry">
                    <h3>{{ $certification['name'] }} <span class="at">· {{ $certification['issuer'] }}</span></h3>
                    @if ($certification['dates'] || $certification['credentialId'])
                        <p class="dates">{{ collect([$certification['dates'], $certification['credentialId'] ? __('ID :id', ['id' => $certification['credentialId']]) : null])->filter()->implode(' · ') }}</p>
                    @endif
                    @if ($certification['link'])
                        <p class="links">
                            @if ($certification['link']['url'])
                                <a href="{{ $certification['link']['url'] }}">{{ $certification['link']['text'] }}</a>
                            @else
                                {{ $certification['link']['text'] }}
                            @endif
                        </p>
                    @endif
                </div>
            @endforeach
        @endif
        @break

    @case (\App\Enums\CvSection::Languages)
        @if ($cv->languages !== [])
            <h2>{{ $design->heading($section) }}</h2>
            <p>
                @foreach ($cv->languages as $language)
                    @unless ($loop->first)<span class="separator"> · </span>@endunless
                    {{ $language['name'] }} <span class="dates">({{ $bengali ? $language['level']->bengaliLabel() : __($language['level']->label()) }})</span>
                @endforeach
            </p>
        @endif
        @break

    @case (\App\Enums\CvSection::Awards)
        @if ($cv->awards !== [])
            <h2>{{ $design->heading($section) }}</h2>
            @foreach ($cv->awards as $award)
                <div class="entry">
                    <h3>{{ $award['title'] }}@if ($award['issuer']) <span class="at">· {{ $award['issuer'] }}</span>@endif</h3>
                    @if ($award['date'])
                        <p class="dates">{{ $award['date'] }}</p>
                    @endif
                    @if ($award['description'])
                        <div class="description"><p>{!! nl2br(e($award['description'])) !!}</p></div>
                    @endif
                </div>
            @endforeach
        @endif
        @break

    @case (\App\Enums\CvSection::Volunteering)
        @if ($cv->volunteering !== [])
            <h2>{{ $design->heading($section) }}</h2>
            @foreach ($cv->volunteering as $role)
                <div class="entry">
                    <h3>{{ $role['role'] }} <span class="at">· {{ $role['organization'] }}</span></h3>
                    @if ($role['dates'])
                        <p class="dates">{{ $role['dates'] }}</p>
                    @endif
                    @if ($role['description'])
                        <div class="description"><p>{!! nl2br(e($role['description'])) !!}</p></div>
                    @endif
                </div>
            @endforeach
        @endif
        @break

    @case (\App\Enums\CvSection::Publications)
        @if ($cv->publications !== [])
            <h2>{{ $design->heading($section) }}</h2>
            @foreach ($cv->publications as $publication)
                <div class="entry">
                    <h3>{{ $publication['title'] }}@if ($publication['publisher']) <span class="at">· {{ $publication['publisher'] }}</span>@endif</h3>
                    @if ($publication['date'])
                        <p class="dates">{{ $publication['date'] }}</p>
                    @endif
                    @if ($publication['description'])
                        <div class="description"><p>{!! nl2br(e($publication['description'])) !!}</p></div>
                    @endif
                    @if ($publication['link'])
                        <p class="links">
                            @if ($publication['link']['url'])
                                <a href="{{ $publication['link']['url'] }}">{{ $publication['link']['text'] }}</a>
                            @else
                                {{ $publication['link']['text'] }}
                            @endif
                        </p>
                    @endif
                </div>
            @endforeach
        @endif
        @break

    @case (\App\Enums\CvSection::Custom)
        {{-- Each of the candidate's own sections under its own heading. --}}
        @foreach ($cv->extras->custom as $custom)
            <h2>{{ $custom['title'] }}</h2>
            @foreach ($custom['items'] as $item)
                <div class="entry">
                    @if ($item['title'] !== '')
                        <h3>{{ $item['title'] }}@if ($item['subtitle'] !== '') <span class="at">· {{ $item['subtitle'] }}</span>@endif</h3>
                    @endif
                    @if ($item['dates'] !== '')
                        <p class="dates">{{ $item['dates'] }}</p>
                    @endif
                    @if ($item['description'] !== '')
                        <div class="description"><p>{!! nl2br(e($item['description'])) !!}</p></div>
                    @endif
                </div>
            @endforeach
        @endforeach
        @break

    @case (\App\Enums\CvSection::Interests)
        @if ($cv->extras->interests !== '')
            <h2>{{ $design->heading($section) }}</h2>
            <p>{{ $cv->extras->interests }}</p>
        @endif
        @break

    @case (\App\Enums\CvSection::Personal)
        @php
            $rows = $cv->extras->personalRows($design->headings);
        @endphp
        @if ($rows !== [])
            <h2>{{ $design->heading($section) }}</h2>
            <div class="entry">
                <table class="details">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="key">{{ $row['label'] }}</td>
                            <td>{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
        @break

    @case (\App\Enums\CvSection::References)
        @if ($cv->extras->referenceMode === 'on_request' || ($cv->extras->referenceMode === 'list' && $cv->extras->references !== []))
            <h2>{{ $design->heading($section) }}</h2>
            @if ($cv->extras->referenceMode === 'on_request')
                <p>{{ $design->label('Available on request.', 'চাহিবামাত্র দেওয়া হবে।') }}</p>
            @else
                @foreach ($cv->extras->references as $person)
                    <div class="entry">
                        <h3>{{ $person['name'] }}</h3>
                        @if ($person['position'] !== '' || $person['organization'] !== '')
                            <p class="subtitle">{{ collect([$person['position'], $person['organization']])->filter()->implode(', ') }}</p>
                        @endif
                        @if ($person['relationship'] !== '')
                            <p class="dates">{{ $person['relationship'] }}</p>
                        @endif
                        @if ($person['email'] !== '' || $person['phone'] !== '')
                            <p class="links">{{ collect([$person['email'], $person['phone']])->filter()->implode(' · ') }}</p>
                        @endif
                    </div>
                @endforeach
            @endif
        @endif
        @break

    @case (\App\Enums\CvSection::Declaration)
        @if ($cv->extras->declaration)
            <h2>{{ $design->heading($section) }}</h2>
            <div class="entry">
                <p>{{ $cv->extras->declarationFor($design->headings) }}</p>
                <div class="signature">
                    <p class="signature-line">{{ $design->label('Signature', 'স্বাক্ষর') }} ({{ $cv->name }})</p>
                    <p class="declaration-place">
                        @if ($cv->extras->declarationPlace !== '')
                            {{ $design->label('Place', 'স্থান') }}: {{ $cv->extras->declarationPlace }}
                            <span class="separator"> · </span>
                        @endif
                        {{ $design->label('Date', 'তারিখ') }}: ____________
                    </p>
                </div>
            </div>
        @endif
        @break
@endswitch
