@extends('layouts.admin')

@section('title', $title)
@section('page-title', $title)
@section('page-subtitle', $subtitle)

@section('content')
<div class="toolbar">
    <a class="btn light" href="{{ $backUrl }}">← Voltar</a>
    @if ($editUrl)<a class="btn" href="{{ $editUrl }}">Editar</a>@endif
</div>
<section class="card detail-card">
    @if ($image)<div class="detail-image"><img src="{{ $image }}" alt="{{ $title }}"></div>@endif
    <dl class="detail-grid">
        @foreach ($fields as $label => $value)
            <div class="detail-field {{ mb_strlen((string) $value) > 100 ? 'full' : '' }}"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
    </dl>
    @if ($options)
        <div class="detail-section"><h3>Opções</h3><div class="question-options">
            @foreach ($options as $index => $option)
                <div class="question-option {{ $index === $correctIndex ? 'correct' : '' }}"><span class="option-letter">{{ chr(65 + $index) }}</span><span>{{ $option }}</span>@if ($index === $correctIndex)<strong class="correct-mark">✓ Correta</strong>@endif</div>
            @endforeach
        </div></div>
    @endif
</section>
@endsection
