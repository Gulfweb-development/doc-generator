{!! $body !!}
@if (($stackedSignoff ?? false) && ($signature || $stamp))
    {{-- Signature, then stamp, centred in one column at the end side (left in Arabic, right in English). --}}
    <table class="plain signoff" style="width: 100%;">
        <tr>
            <td>&nbsp;</td>
            <td style="width: 72mm; text-align: center; vertical-align: top;">
                @if ($signature)
                    <div><img src="var:signature" style="max-height: 22mm; max-width: 70mm;"></div>
                @endif
                @if ($stamp)
                    {{-- Small and centred under the name, like an embossed seal. --}}
                    <div style="margin-top: 3mm;"><img src="var:stamp" style="max-height: 20mm; max-width: 20mm;"></div>
                @endif
            </td>
        </tr>
    </table>
@elseif ($signature || $stamp)
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
