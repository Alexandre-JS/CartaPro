@props(['viewHref' => null, 'label' => 'Ações do registo'])

<div {{ $attributes->class('pv-row-actions') }}>
    @if($viewHref)<a class="pv-row-primary" href="{{ $viewHref }}">Ver</a>@endif
    @if(trim((string) $slot) !== '')
        <details>
            <summary aria-label="{{ $label }}" title="{{ $label }}"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></summary>
            <div class="pv-row-menu" role="menu">{{ $slot }}</div>
        </details>
    @endif
</div>
