{{-- The plain-text copy of emails/layout. Nothing here is HTML, so values
     are printed unescaped: an escaped apostrophe would arrive as "&#039;". --}}
@php $resort = \App\Support\GuestEmail::contact(); @endphp
{!! $heading !!}

@yield('content')

--
{!! $resort['name'] !!}
@if ($resort['address'])
{!! $resort['address'] !!}
@endif
@if ($resort['map_url'])
Map: {!! $resort['map_url'] !!}
@endif
@if ($resort['phone'])
Call: {!! $resort['phone'] !!}
@endif
@if ($resort['email'])
Email: {!! $resort['email'] !!}
@endif
@hasSection('footer_note')

@yield('footer_note')
@endif
