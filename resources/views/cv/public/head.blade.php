{{--
    Added to a CV template's head on the public web CV: no listing by
    search engines, a phone-width viewport, the sheet on a grey desk,
    and the bar's own look (the template's styles never reach it, since
    every rule here is scoped to .hb-bar).
--}}
<meta name="robots" content="noindex, nofollow">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    html { background: #f3f4f6; }
    body.preview { max-width: 210mm; margin: 64px auto 32px; box-shadow: 0 1px 3px rgba(17, 24, 39, 0.12); box-sizing: border-box; }
    @media (max-width: 640px) { body.preview { margin-top: 120px; padding: 8mm !important; } }
    .hb-bar { position: fixed; inset: 0 0 auto 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 16px; background: #ffffff; border-bottom: 1px solid #e5e7eb; font: 14px/1.4 system-ui, -apple-system, 'Segoe UI', sans-serif; color: #111827; }
    .hb-bar a { color: inherit; }
    .hb-bar .hb-name { font-weight: 600; }
    .hb-bar .hb-note { color: #6b7280; font-size: 13px; }
    .hb-bar .hb-download { display: inline-block; padding: 7px 14px; border-radius: 8px; background: #111827; color: #ffffff; text-decoration: none; font-weight: 500; }
    .hb-bar .hb-download:focus-visible { outline: 2px solid #C2410C; outline-offset: 2px; }
    @media print { .hb-bar { display: none; } html { background: #ffffff; } body.preview { margin: 0; box-shadow: none; } }
</style>
