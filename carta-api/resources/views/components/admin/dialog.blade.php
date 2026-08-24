@props(['id', 'title', 'description' => null, 'size' => 'medium'])

<dialog id="{{ $id }}" {{ $attributes->class(['admin-dialog', 'admin-dialog--small' => $size === 'small']) }} aria-labelledby="{{ $id }}-title" @if($description)aria-describedby="{{ $id }}-description"@endif>
    <div class="admin-dialog-head">
        <div><h2 id="{{ $id }}-title">{{ $title }}</h2>@if($description)<p id="{{ $id }}-description">{{ $description }}</p>@endif</div>
        <button class="admin-dialog-close" type="button" data-dialog-close aria-label="Fechar diálogo">×</button>
    </div>
    <div class="admin-dialog-body">{{ $slot }}</div>
    @isset($footer)<div class="admin-dialog-footer">{{ $footer }}</div>@endisset
</dialog>
