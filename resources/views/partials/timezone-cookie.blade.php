{{--
    Tells the server which time zone this browser is in, so times can be
    shown in it (App\Support\LocalTime). A cookie rather than a request of
    its own: it rides along with every page and Livewire call from here on,
    and costs nothing when it has not changed. Read by the server only, so
    it is left out of cookie encryption (bootstrap/app.php).
--}}
<script>
    (() => {
        try {
            const zone = Intl.DateTimeFormat().resolvedOptions().timeZone
            if (! zone) return

            const pair = @js(\App\Support\LocalTime::COOKIE) + '=' + encodeURIComponent(zone)
            if (document.cookie.split('; ').includes(pair)) return

            document.cookie = pair + '; path=/; max-age=31536000; samesite=lax' + (location.protocol === 'https:' ? '; secure' : '')
        } catch (e) {}
    })()
</script>
