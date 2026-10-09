<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
{{-- The app's dark colours, for the clients that honour the reader's
     setting (Apple Mail, iOS Mail, some Outlooks). Every rule needs
     !important: the theme has already been written into each tag's own
     style attribute, which otherwise wins. --}}
<style>
@media (prefers-color-scheme: dark) {
body, .wrapper, .body {
background-color: #0a0a0b !important;
color: #c9c9d1 !important;
}

.inner-body {
background-color: #121214 !important;
border-color: #232327 !important;
}

h1, h2, h3, strong, .header a, .table th {
color: #ededef !important;
}

p, .table td, .panel-content, .panel-content p {
color: #c9c9d1 !important;
}

a {
color: #fbbf24 !important;
}

.button {
color: #ffffff !important;
}

.subcopy {
border-top-color: #232327 !important;
}

.subcopy p, .footer p, .footer a {
color: #a1a1aa !important;
}

.panel-content {
background-color: #0a0a0b !important;
}
}

@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
