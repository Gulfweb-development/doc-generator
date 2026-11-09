{!! $body !!}
@if ($signature || $stamp)
    <table class="plain signoff" style="width: 100%;">
        <tr>
            <td style="width: 62mm; vertical-align: bottom;">
                @if ($signature)
                    <img src="var:signature" style="max-height: 18mm; max-width: 58mm;">
                @endif
            </td>
            <td>&nbsp;</td>
            <td style="width: 30mm; text-align: center; vertical-align: bottom;">
                @if ($stamp)
                    <img src="var:stamp" style="max-height: 28mm; max-width: 28mm;">
                @endif
            </td>
        </tr>
        @if ($signature)
            <tr>
                <td class="signature-line">{{ $signatureLabel }}</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        @endif
    </table>
@endif
