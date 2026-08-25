@extends('layouts.admin')
@section('title',$topic->exists ? 'Editar tema' : 'Novo tema')
@section('page-title',$topic->exists ? 'Editar tema' : 'Novo tema')
@section('page-subtitle','Use nomes curtos e identificadores estáveis para o aplicativo.')
@section('content')
<form class="card form-card" method="POST" action="{{ $topic->exists ? route('admin.topics.update',$topic) : route('admin.topics.store') }}">@csrf @if($topic->exists)@method('PUT')@endif
<div class="form-grid"><div class="field"><label>Nome</label><input name="name" value="{{ old('name',$topic->name) }}" required></div><div class="field"><label>Identificador</label><input name="slug" value="{{ old('slug',$topic->slug) }}" placeholder="ex.: sinais_perigo" required></div><div class="field full"><label>Descrição</label><textarea name="description">{{ old('description',$topic->description) }}</textarea></div><div class="field"><label>Ordem</label><input type="number" min="0" name="sort_order" value="{{ old('sort_order',$topic->sort_order ?? 0) }}" required></div><div class="field"><label>Estado</label><div class="checks"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$topic->exists ? $topic->is_active : true))> Tema ativo</label></div></div></div>
<div class="form-actions"><a class="btn light" href="{{ route('admin.topics.index') }}">Cancelar</a><button class="btn">Guardar tema</button></div></form>
@endsection
