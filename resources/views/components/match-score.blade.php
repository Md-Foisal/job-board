@props(['score' => null, 'size' => 'sm'])

{{-- How well the candidate's skills cover what the posting asks for.

     Lived three times -- the job card a candidate browses, the applicant
     list an employer reads, and the application page -- each hardcoded to
     the success colour, which said "good fit" just as loudly at 8% as at
     92%. The number is the message; the colour has to agree with it or it
     is worse than no colour at all.

     A null score is not a bad score: it means there is nothing to compare
     (the posting lists no skills, or the candidate has listed none), and a
     blank is honest where "0% match" would read as a verdict. --}}
@php
    $score = $score === null ? null : (int) $score;

    $tone = match (true) {
        $score === null => null,
        $score >= 70 => 'bg-success-50 text-success-700 dark:bg-success-950 dark:text-success-300',
        $score >= 35 => 'bg-warning-50 text-warning-700 dark:bg-warning-950 dark:text-warning-300',
        default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
    };

    $padding = $size === 'md' ? 'px-2.5 py-1' : 'px-2 py-0.5';
@endphp

@if ($score !== null)
    <span {{ $attributes->class([
        'shrink-0 rounded-full text-xs font-semibold tabular-nums',
        $padding,
        $tone,
    ]) }}>
        {{ __(':percent% match', ['percent' => $score]) }}
    </span>
@endif
