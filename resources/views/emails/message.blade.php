{{-- The short account emails: sign-in code, password reset, set your
     password, confirm your email, sign-up attempt. One template so they read
     as one sender. The words are written where each email is sent from and
     passed in; see App\Support\GuestEmail::message() for the list. --}}
@extends('emails.layout')

@section('content')
    <p>Hi {{ $name ?: 'there' }},</p>

    @foreach ($intro ?? [] as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach

    @if (!empty($code))
        <div class="panel">
            <div class="panel-code">{{ $code }}</div>
            @if (!empty($codeNote))
                <div class="panel-sub">{{ $codeNote }}</div>
            @endif
        </div>
    @endif

    @if (!empty($actionUrl))
        <p style="margin:6px 0 22px;">
            <a href="{{ $actionUrl }}"
                style="display:inline-block;background:#2c2416;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 26px;border-radius:8px;">{{ $actionLabel }}</a>
        </p>
    @endif

    @foreach ($outro ?? [] as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach

    @if (!empty($warning))
        <div class="notice">
            {{ $warning }}
            @if (!empty($warningLinkUrl))
                <a href="{{ $warningLinkUrl }}" style="color:#854d0e;font-weight:700;">{{ $warningLinkLabel }}</a>
            @endif
        </div>
    @endif

    @if (!empty($actionUrl) && !empty($showUrl))
        <p class="small" style="margin-bottom:0;">
            If the button does not work, copy this link into your browser:<br>
            <a href="{{ $actionUrl }}" class="link">{{ $actionUrl }}</a>
        </p>
    @endif
@endsection

@if (!empty($footerNote))
    @section('footer_note'){{ $footerNote }}@endsection
@endif
