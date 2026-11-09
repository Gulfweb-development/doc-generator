@php($logoHtml = $logo ? '<img src="var:logo" style="max-height: 16mm; max-width: 45mm;">' : '')
<div class="letterhead">
    @if ($position === 'center')
        <div style="text-align: center; margin-bottom: 2mm;">{!! $logoHtml !!}</div>
        <div>{!! $content !!}</div>
    @else
        {{-- dir="ltr" keeps the chosen left or right position in Arabic documents too. --}}
        <table class="plain" dir="ltr">
            <tr>
                @if ($position === 'left' && $logo)
                    <td style="width: 22mm; vertical-align: middle;">{!! $logoHtml !!}</td>
                @endif
                <td style="vertical-align: middle; text-align: {{ $direction === 'rtl' ? 'right' : 'left' }};">{!! $content !!}</td>
                @if ($position === 'right' && $logo)
                    <td style="width: 22mm; vertical-align: middle; text-align: right;">{!! $logoHtml !!}</td>
                @endif
            </tr>
        </table>
    @endif
</div>
<div class="letterhead-rule"></div>
