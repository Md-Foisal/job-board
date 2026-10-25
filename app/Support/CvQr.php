<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * A QR code for a CV's web page, as an SVG data URI both PDF engines and
 * browsers draw. Made on our own server with the QR library the sign-in
 * codes already use; nothing is sent anywhere.
 */
final class CvQr
{
    public static function for(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd)))->writeString($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
