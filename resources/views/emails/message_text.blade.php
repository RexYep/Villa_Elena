@extends('emails.layout_text')

@section('content')
Hi {!! $name ?: 'there' !!},

@foreach ($intro ?? [] as $paragraph)
{!! $paragraph !!}

@endforeach
@if (!empty($code))
    {!! $code !!}

@if (!empty($codeNote))
{!! $codeNote !!}

@endif
@endif
@if (!empty($actionUrl))
{!! $actionLabel !!}:
{!! $actionUrl !!}

@endif
@foreach ($outro ?? [] as $paragraph)
{!! $paragraph !!}

@endforeach
@if (!empty($warning))
{!! $warning !!}
@if (!empty($warningLinkUrl))
{!! $warningLinkLabel !!}: {!! $warningLinkUrl !!}
@endif
@endif
@endsection

@if (!empty($footerNote))
@section('footer_note'){!! $footerNote !!}@endsection
@endif
