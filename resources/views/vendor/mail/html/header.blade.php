@props(['url'])
{{-- The product's mark and name, as in the app's top bar. The mark is a
     PNG (public/images/mail-logo.png, drawn from components/logo): Gmail
     does not show SVG images. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/mail-logo.png') }}" class="logo" width="32" height="32" alt=""><span class="wordmark">{{ $slot }}</span>
</a>
</td>
</tr>
