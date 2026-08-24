@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'as' => 'input',
    'hint' => null,
    'error' => null,
    'required' => false,
    'full' => false,
])
@php
    $id = $attributes->get('id', 'field-'.str($name)->replace(['[', ']', '.'], '-')->trim('-'));
    $message = $error ?? $errors->first($name);
    $hintId = $hint ? $id.'-hint' : null;
    $errorId = $message ? $id.'-error' : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
    $control = $attributes->except(['class'])->merge([
        'id' => $id,
        'name' => $name,
        'required' => $required ?: null,
        'aria-invalid' => $message ? 'true' : null,
        'aria-describedby' => $describedBy ?: null,
    ]);
@endphp

<div {{ $attributes->only('class')->class(['field', 'full' => $full, 'field-has-error' => $message]) }}>
    <label for="{{ $id }}">{{ $label }} @if($required)<span class="field-required" aria-hidden="true">*</span>@endif</label>
    @if($as === 'select')
        <select {{ $control }}>{{ $slot }}</select>
    @elseif($as === 'textarea')
        <textarea {{ $control }}>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}" value="{{ old($name, $value) }}" {{ $control }}>
    @endif
    @if($hint)<small id="{{ $hintId }}" class="field-hint">{{ $hint }}</small>@endif
    @if($message)<small id="{{ $errorId }}" class="field-error">{{ $message }}</small>@endif
</div>
