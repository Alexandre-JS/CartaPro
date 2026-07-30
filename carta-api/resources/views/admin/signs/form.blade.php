@extends('layouts.admin')
@section('title',$sign->exists ? 'Editar sinal' : 'Novo sinal')
@section('page-title',$sign->exists ? 'Editar sinal' : 'Novo sinal')
@section('page-subtitle','O significado é a frase curta que o aluno lê primeiro; a descrição é o texto de estudo.')
@section('content')
<form class="card form-card" enctype="multipart/form-data" method="POST" action="{{ $sign->exists ? route('admin.signs.update', $sign) : route('admin.signs.store') }}">
@csrf
@if ($sign->exists)
    @method('PUT')
@endif
<div class="form-grid">
    <div class="field"><label>Nome</label><input name="name" value="{{ old('name',$sign->name) }}" required></div>
    <div class="field"><label>Identificador</label><input name="slug" value="{{ old('slug',$sign->slug) }}" required><small>Usado nas fichas de estudo para referenciar este sinal.</small></div>

    <div class="field"><label>Categoria</label>
        <select name="category">
            @foreach($categorias as $slug => $dados)
                <option value="{{ $slug }}" @selected(old('category',$sign->category)===$slug)>{{ $dados['nome'] }}</option>
            @endforeach
        </select>
        <small>Inclui marcas rodoviárias, semáforos e sinais dos agentes.</small>
    </div>

    <div class="field"><label>Tema associado</label>
        <select name="topic_id">
            <option value="">Sem tema</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" @selected(old('topic_id',$sign->topic_id) == $topic->id)>{{ $topic->name }}</option>
            @endforeach
        </select>
        <small>Liga o sinal ao tema, para o treino contar no progresso certo.</small>
    </div>

    <div class="field"><label>Ficheiro SVG</label><input type="file" name="svg" accept=".svg,image/svg+xml" @required(!$sign->exists)>
        @if ($sign->exists && $sign->file_path)
            <small>Atual: {{ $sign->file_path }}</small>
        @endif
    </div>
    <div class="field"><label>Artigo de referência</label><input type="number" name="article_ref" min="1" value="{{ old('article_ref',$sign->article_ref) }}"><small>Opcional. Permite abrir o artigo a partir do sinal.</small></div>
    <div class="field"><label>Ordem</label><input type="number" name="sort_order" min="0" value="{{ old('sort_order',$sign->sort_order ?? 0) }}"></div>

    <div class="field full"><label>Significado <small>Frase curta — é a resposta certa no treino de sinais.</small></label><textarea name="meaning" maxlength="500" required>{{ old('meaning',$sign->meaning) }}</textarea></div>
    <div class="field full"><label>Descrição de estudo <small>Explicação mais longa: o que fazer ao encontrar este sinal.</small></label><textarea name="description" rows="5">{{ old('description',$sign->description) }}</textarea></div>

    <div class="field full"><div class="checks">
        <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$sign->exists ? $sign->is_active : true))> Sinal ativo</label>
        <label><input type="checkbox" name="is_locked" value="1" @checked(old('is_locked',$sign->is_locked ?? false))> Só para o plano completo</label>
    </div></div>
</div>
<div class="form-actions"><a class="btn light" href="{{ route('admin.signs.index') }}">Cancelar</a><button class="btn">Guardar sinal</button></div>
</form>
@endsection
