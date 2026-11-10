{{-- The footer image is drawn edge to edge on the page layer; this part sits above it. --}}
@if ($content !== '' || $pageNumber !== '')
<table class="plain footline">
    <tr>
        <td>{!! $content !!}</td>
        <td style="width: 28mm; text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};">{{ $pageNumber }}</td>
    </tr>
</table>
@endif
