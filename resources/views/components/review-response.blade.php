@props(['review', 'company'])

{{-- A company's published answer under a review. Only the company's
     name is shown, never the person who wrote it: the answer speaks for
     the company. --}}
@if ($review->hasPublishedResponse())
    <div {{ $attributes->class('mt-4 rounded-lg border-s-2 border-brand-500 bg-zinc-50 px-4 py-3 dark:bg-zinc-800/50') }}>
        <p class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
            {{ __('Response from :company', ['company' => $company->name]) }}
            <span class="mx-1 font-normal text-zinc-500">·</span>
            <time class="font-normal text-zinc-500" datetime="{{ $review->responded_at->format('Y-m') }}">{{ $review->responded_at->format('F Y') }}</time>
        </p>
        @if ($review->responseAnswersEarlierVersion())
            <p class="mt-1 text-xs italic text-zinc-500 dark:text-zinc-400">{{ __('Written to an earlier version of this review.') }}</p>
        @endif
        <p class="mt-2 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $review->response_body }}</p>
    </div>
@endif
