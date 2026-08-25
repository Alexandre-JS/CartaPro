@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'medium',
    'type' => 'button',
    'loadingLabel' => null,
])
@php
    $classes = collect([
        'btn',
        $variant !== 'primary' ? match($variant) {
            'secondary' => 'light',
            'danger' => 'danger',
            'warning' => 'warning',
            'ghost' => 'ghost',
            default => null,
        } : null,
        $size === 'small' ? 'small' : null,
    ])->filter()->implode(' ');
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @if($loadingLabel)data-loading-label="{{ $loadingLabel }}"@endif {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
