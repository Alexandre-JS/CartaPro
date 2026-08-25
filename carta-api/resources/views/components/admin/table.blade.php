@props(['caption' => null, 'labelledby' => null, 'density' => 'comfortable', 'hover' => true])
@php($compact = $density === 'compact')

<section {{ $attributes->class(['data-surface', 'table-card', 'table-card--compact' => $compact]) }} data-table-density="{{ $compact ? 'compact' : 'comfortable' }}">
    <table @class(['data-table', 'data-table--hover' => $hover, 'data-table--compact' => $compact]) @if($labelledby)aria-labelledby="{{ $labelledby }}"@endif>
        @if($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
        @isset($head)<thead>{{ $head }}</thead>@endisset
        <tbody>{{ $slot }}</tbody>
    </table>
</section>
