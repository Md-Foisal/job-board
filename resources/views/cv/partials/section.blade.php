{{--
    One section of a built CV, the same markup in every template: each
    template only styles it. Nothing is printed for a section the profile
    has nothing in.

    @param \App\Enums\CvSection $section
    @param \App\Support\CvData $cv
--}}
@switch ($section)
    @case (\App\Enums\CvSection::Summary)
        @if ($cv->summary)
            <h2>{{ __($section->heading()) }}</h2>
            <p>{!! nl2br(e($cv->summary)) !!}</p>
        @endif
        @break

    @case (\App\Enums\CvSection::Experience)
        @if ($cv->experience !== [])
            <h2>{{ __($section->heading()) }}</h2>
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
            <h2>{{ __($section->heading()) }}</h2>
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
            <h2>{{ __($section->heading()) }}</h2>
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
            <h2>{{ __($section->heading()) }}</h2>
            <p>{{ implode(', ', $cv->skills) }}</p>
        @endif
        @break

    @case (\App\Enums\CvSection::Certifications)
        @if ($cv->certifications !== [])
            <h2>{{ __($section->heading()) }}</h2>
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
@endswitch
