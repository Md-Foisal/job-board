@props(['review', 'company'])

{{-- A company's published answer under a review. Only the company's
     name is shown, never the person who wrote it: the answer speaks for
     the company. --}}
@if ($review->hasPublishedResponse())
    <div {{ $attributes->class('relative mt-4 overflow-hidden rounded-control bg-surface py-3 ps-5 pe-4') }}>
        <span class="bg-sunset absolute inset-y-0 start-0 w-0.5" aria-hidden="true"></span>
        <p class="text-xs font-medium text-ink-soft">
            {{ __('Response from :company', ['company' => $company->name]) }}
            <span class="mx-1 font-normal text-ink-muted">·</span>
            @php($respondedAt = \App\Support\LocalTime::of($review->responded_at))
            <time class="font-normal text-ink-muted" datetime="{{ $respondedAt->format('Y-m') }}">{{ $respondedAt->format(\App\Support\DateFormat::MONTH) }}</time>
        </p>
        @if ($review->responseAnswersEarlierVersion())
            <p class="mt-1 text-xs italic text-ink-muted">{{ __('Written to an earlier version of this review.') }}</p>
        @endif
        <p class="mt-2 whitespace-pre-line text-sm text-ink-soft">{{ $review->response_body }}</p>
    </div>
@endif
