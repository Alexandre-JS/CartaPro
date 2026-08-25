@props(['title', 'description' => null, 'count' => null, 'countLabel' => 'registos'])

<header {{ $attributes->class('page-header') }}>
    <div class="page-header-copy">
        <h2>{{ $title }}</h2>
        @if($description)<p>{{ $description }}</p>@endif
        @if($count !== null)<span class="page-header-count">{{ number_format($count, 0, ',', ' ') }} {{ $countLabel }}</span>@endif
    </div>
    @if(trim((string) $slot) !== '')<div class="page-header-actions">{{ $slot }}</div>@endif
</header>
