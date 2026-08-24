@props([
    'title',
    'description' => null,
    'icon' => 'inbox',
    'table' => false,
    'colspan' => 1,
])
@php
    // Traçados do Bootstrap Icons, incorporados para o painel continuar funcional offline.
    $iconPath = match($icon) {
        'search' => '<path d="M11 6a5 5 0 1 1-10 0 5 5 0 0 1 10 0m-1.293 4.707 3 3a1 1 0 0 0 1.414-1.414l-3-3a6.5 6.5 0 1 0-1.414 1.414"/>',
        'people' => '<path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.998c-.001-.264-.167-1.03-.76-1.72C5.656 10.596 4.742 10 3.5 10c-1.241 0-2.155.596-2.74 1.278C.166 11.97 0 12.735 0 13c0 1 1 1 1 1h6c0-.368.122-.704.341-.993zM3.5 9a2 2 0 1 0 0-4 2 2 0 0 0 0 4m7.5-1a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5m4.978 5H16c0-.265-.166-1.03-.76-1.722C14.656 10.596 13.742 10 12.5 10c-.47 0-.891.085-1.26.228.85.632 1.36 1.42 1.574 2.072.06.183.096.35.111.493.292.195.52.207.553.207z"/>',
        default => '<path d="M4.98 4a.5.5 0 0 0-.39.188L1.54 8H6.5l.5 1h2l.5-1h4.96l-3.05-3.812A.5.5 0 0 0 11.02 4zM1.106 7.553 3.81 4.17A1.5 1.5 0 0 1 4.98 3h6.04a1.5 1.5 0 0 1 1.17.563l2.704 3.383A.5.5 0 0 1 15 7.5V12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V8a.5.5 0 0 1 .106-.447M2 9v3h12V9h-3.882l-.5 1h-3.236l-.5-1z"/>',
    };
@endphp
@if($table)<tr><td colspan="{{ $colspan }}" class="empty"><div class="empty-state">@else<div {{ $attributes->class('empty-state') }}>@endif
    <svg class="empty-state-icon" viewBox="0 0 16 16" aria-hidden="true">{!! $iconPath !!}</svg>
    <strong>{{ $title }}</strong>
    @if($description)<p>{{ $description }}</p>@endif
    @if(trim((string) $slot) !== '')<div class="empty-state-action">{{ $slot }}</div>@endif
@if($table)</div></td></tr>@else</div>@endif
