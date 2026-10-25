{{--
    The bar across the top of a public web CV: whose CV it is, the PDF,
    and where it was made. Plain HTML styled by cv/public/head.

    @param \App\Models\CvVersion $version
    @param \App\Support\CvData $cv
--}}
<div class="hb-bar" role="banner">
    <div>
        <span class="hb-name">{{ __(':name’s CV', ['name' => $cv->name]) }}</span>
        @unless ($version->public_shows_contact)
            <span class="hb-note">· {{ __('Contact details are not shown on the web version.') }}</span>
        @endunless
    </div>
    <div>
        <a class="hb-download" href="{{ route('cv.public.pdf', $version->public_slug) }}" rel="nofollow">{{ __('Download PDF') }}</a>
        <a class="hb-note" href="{{ route('home') }}" style="margin-left: 12px">{{ __('Made with :app', ['app' => config('app.name')]) }}</a>
    </div>
</div>
