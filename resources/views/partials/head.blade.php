<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

{{-- What a link to this page shows when it is shared on LinkedIn,
     Facebook, X or in a chat: the page's title, one line about it and
     one picture of the site (public/images/og-image.jpg, 1200x630). A
     public page gives its own line through the guest layout's
     "description"; every other page shares the site's. --}}
@php
    $previewTitle = filled($title ?? null) ? $title : config('app.name', 'Laravel');
    $previewDescription = filled($description ?? null)
        ? $description
        : __('Find work that fits your skills. Every job shows how well you match, before you apply.');
@endphp
<meta name="description" content="{{ $previewDescription }}">
<meta property="og:site_name" content="{{ config('app.name', 'Laravel') }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $previewTitle }}">
<meta property="og:description" content="{{ $previewDescription }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('images/og-image.jpg') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ __('HearBack: find work that fits your skills') }}">
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=geist:400,500,600,700|geist-mono:500&amp;display=swap" rel="stylesheet" />

@include('partials.timezone-cookie')

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
