@extends('emails.layouts.brand')

@section('title', $heading)
@section('eyebrow', $eyebrow)
@section('heading', $heading)

@section('body')
    <p style="margin:0 0 14px;">{!! nl2br(e($intro)) !!}</p>
    @if(count(array_filter($rows, fn ($v) => $v !== null && $v !== '')))
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EAF3EA;border-radius:12px;margin:0 0 16px;">
        <tr><td style="padding:12px 18px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#0A3B22;">
                @foreach($rows as $k => $v)
                    @continue($v === null || $v === '')
                    <tr>
                        <td style="padding:4px 12px 4px 0;color:#4A6656;white-space:nowrap;vertical-align:top;">{{ $k }}</td>
                        <td style="padding:4px 0;font-weight:600;">{{ $v }}</td>
                    </tr>
                @endforeach
            </table>
        </td></tr>
    </table>
    @endif
    @if($url)
        @include('emails.partials.button', ['url' => $url, 'label' => $label ?? 'Open ZamPayroll'])
    @endif
    @if($attachmentName)
        <p style="margin:14px 0 0;font-size:13px;color:#4A6656;">A copy is attached as {{ $attachmentName }}.</p>
    @endif
    <p style="margin:14px 0 0;font-size:12px;color:#4A6656;">For your security, sign in to ZamPayroll to see the document. We never put confidential documents in an open link.</p>
@endsection
