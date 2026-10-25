<?php

return [
    /*
     * How CV and cover letter PDFs are drawn.
     *
     * "dompdf" draws them on our own server with no outside service, but
     * only with its DejaVu fonts and only in Latin, Greek and Cyrillic.
     *
     * "cloudflare" has Chromium draw them in Cloudflare Browser Rendering
     * (laravel-pdf's cloudflare driver; set CLOUDFLARE_API_TOKEN and
     * CLOUDFLARE_ACCOUNT_ID): the CV's own typeface, Bengali, and a
     * tagged PDF screen readers can follow. When Cloudflare cannot be
     * reached or the day's free browser time is used up, a CV in Latin
     * script is drawn with DOMPDF instead.
     */
    'pdf_engine' => env('CV_PDF_ENGINE', 'dompdf'),
];
