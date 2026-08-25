@props(['action' => null, 'method' => 'GET', 'label' => 'Ferramentas dos dados'])

<form action="{{ $action ?: url()->current() }}" method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}" {{ $attributes->class('data-toolbar') }} aria-label="{{ $label }}">
    @if(strtoupper($method) !== 'GET')@csrf @method($method)@endif
    {{ $slot }}
</form>
