{{--
    Favicon + iOS touch icon, shared by every layout.

    The ?v= is the icon file's own mtime, so replacing public/favicon.ico
    busts every browser's copy automatically. This is not optional polish:
    nginx serves .ico with `expires 7d` (docker/nginx.conf.template), and on
    top of that Chrome keeps favicons in a store of its own that a hard
    refresh does NOT clear — so without a changing URL, a swapped icon can
    stay invisible for days. Regular <img> assets don't need this; a hard
    refresh does reach those.
--}}
<link rel="icon" href="{{ asset('favicon.ico') }}?v={{ @filemtime(public_path('favicon.ico')) ?: 0 }}" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ @filemtime(public_path('apple-touch-icon.png')) ?: 0 }}">
