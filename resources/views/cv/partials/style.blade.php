{{--
    What every CV template shares at the top of its styles: the typeface's
    @font-face rules (see App\Support\CvFonts for the three modes), and
    the look of the parts every template prints the same way -- personal
    details, the signature line, the QR code, tags. Each template's own
    rules come after and can change any of it.

    @param \App\Support\CvDesign $design
    @param \App\Support\CvData $cv
    @param string $fonts  url, embed or none; the preview defaults to url
--}}
@php
    $fonts ??= ($preview ?? false) ? 'url' : 'none';
@endphp
{!! \App\Support\CvFonts::css($design->typeface(), $fonts, $fonts !== 'embed' || $cv->hasBengali()) !!}
        table.details { border-collapse: collapse; }
        table.details td { padding: 0.4mm 4mm 0.4mm 0; vertical-align: top; }
        table.details td.key { color: #6b7280; white-space: nowrap; }
        .signature { margin-top: {{ $design->gap(10) }}; }
        .signature-line { border-top: 0.6pt solid #6b7280; width: 55mm; padding-top: 1mm; }
        .declaration-place { margin-top: {{ $design->gap(2) }}; }
        .qr { margin-top: {{ $design->gap(6) }}; page-break-inside: avoid; }
        .qr img { width: 20mm; height: 20mm; }
        .qr p { font-size: {{ $design->pt(8) }}; color: #6b7280; }
        .subtitle { color: #374151; }
@if ($design->photoShape === 'round')
        .photo { border-radius: 50%; }
@endif
