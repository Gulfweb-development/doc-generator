@if ($banner)
    <div style="margin-bottom: 1.5mm;"><img src="var:footer_image" style="width: 100%;"></div>
@endif
<table class="plain footline">
    <tr>
        <td>{!! $content !!}</td>
        <td style="width: 28mm; text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};">{{ $pageNumber }}</td>
    </tr>
</table>
