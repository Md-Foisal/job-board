{{--
    A QR code to the CV's web page, at the end of a printed CV, when the
    candidate shares the CV as a web page and asks for it. The address is
    printed too, for anyone who cannot scan.

    @param \App\Support\CvData $cv
    @param \App\Support\CvDesign $design
--}}
@if ($cv->qr)
    <div class="qr">
        <img src="{{ $cv->qr }}" alt="{{ __('QR code to the online version of this CV') }}">
        <p>{{ $design->label('Online version', 'অনলাইন সংস্করণ') }}: {{ $cv->qrUrl }}</p>
    </div>
@endif
