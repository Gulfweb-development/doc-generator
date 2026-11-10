@php($logoHtml = $logo ? '<img src="var:logo" style="max-height: 16mm; max-width: 45mm;">' : '')
@if ($banner ?? false)
<div style="margin-bottom: 2mm;"><img src="var:header_image" style="width: 100%;"></div>
@endif
{{-- Each part prints only when filled in, so a banner can stand alone or sit above logo and text. --}}
@if ($logo || $content !== '' || $rightContent !== '')
<div class="letterhead">
    @if ($position === 'center' && $rightContent === '')
        <div style="text-align: center; margin-bottom: 2mm;">{!! $logoHtml !!}</div>
        <div>{!! $content !!}</div>
    @else
        {{-- dir="ltr" keeps left and right where they were placed, in Arabic documents too. --}}
        <table class="plain" dir="ltr">
            <tr>
                @if ($position === 'left' && $logo)
                    <td style="width: 22mm; vertical-align: middle;">{!! $logoHtml !!}</td>
                @endif
                <td style="vertical-align: middle; text-align: {{ $direction === 'rtl' && $rightContent === '' ? 'right' : 'left' }};">{!! $content !!}</td>
                @if ($position === 'center' && $logo)
                    <td style="width: 22mm; vertical-align: middle; text-align: center;">{!! $logoHtml !!}</td>
                @endif
                @if ($rightContent !== '')
                    <td style="vertical-align: middle; text-align: right;">{!! $rightContent !!}</td>
                @endif
                @if ($position === 'right' && $logo)
                    <td style="width: 22mm; vertical-align: middle; text-align: right;">{!! $logoHtml !!}</td>
                @endif
            </tr>
        </table>
    @endif
</div>
<div class="letterhead-rule"></div>
@endif
