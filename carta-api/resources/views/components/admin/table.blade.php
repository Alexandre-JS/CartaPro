@props(['caption' => null, 'labelledby' => null, 'compact' => false])

<section {{ $attributes->class(['card', 'table-card', 'table-card--compact' => $compact]) }}>
    <table class="data-table" @if($labelledby)aria-labelledby="{{ $labelledby }}"@endif>
        @if($caption)<caption class="sr-only">{{ $caption }}</caption>@endif
        @isset($head)<thead>{{ $head }}</thead>@endisset
        <tbody>{{ $slot }}</tbody>
    </table>
</section>
