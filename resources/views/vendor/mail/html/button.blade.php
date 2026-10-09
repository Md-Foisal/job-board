@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    // Outlook on Windows ignores CSS backgrounds and reads only bgcolor,
    // so each button names its solid colour here as well.
    $solid = match ($color) {
        'success', 'green' => '#1e6626',
        'error', 'red' => '#971a20',
        default => '#c2410c',
    };
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="button-cell button-cell-{{ $color }}" bgcolor="{{ $solid }}">
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
