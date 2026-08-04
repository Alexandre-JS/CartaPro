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
    <div class="field">
        <label>Nome <x-ajuda texto="Como o sinal é conhecido — é o que o aluno vê na biblioteca e nos exames. Ex.: Proibido ultrapassar." /></label>
        <input name="name" value="{{ old('name',$sign->name) }}" required>
    </div>

    {{-- Deixado em branco, o controlador deriva-o do nome. Obrigar a inventar
         um código único à mão era trabalho sem valor e colidia em silêncio. --}}
    <div class="field">
        <label>Identificador <x-ajuda texto="Deixa vazio e é gerado a partir do nome. Só preenche se precisares de um código específico para referenciar o sinal nas fichas de estudo." /></label>
        <input name="slug" value="{{ old('slug',$sign->slug) }}" placeholder="{{ $sign->exists ? '' : 'gerado a partir do nome' }}">
    </div>

    <div class="field">
        <label>Categoria <x-ajuda texto="Agrupa o sinal na biblioteca e no treino. Inclui marcas rodoviárias, semáforos e sinais dos agentes." /></label>
        <select name="category">
            @foreach($categorias as $slug => $dados)
                <option value="{{ $slug }}" @selected(old('category',$sign->category)===$slug)>{{ $dados['nome'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label>Tema associado <x-ajuda texto="Liga o sinal a um tema de estudo, para o treino contar no progresso certo do aluno. Opcional." /></label>
        <select name="topic_id">
            <option value="">Sem tema</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" @selected(old('topic_id',$sign->topic_id) == $topic->id)>{{ $topic->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label>Imagem do sinal <x-ajuda texto="SVG, PNG, JPG ou WebP, até 2 MB. Prefere SVG: mantém-se nítido em qualquer ecrã, ao contrário das imagens de pixels que ficam desfocadas quando ampliadas." /></label>
        <input type="file" name="svg" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp" @required(!$sign->exists)>
        @if ($sign->exists && $sign->file_path)
            <small>Atual: {{ $sign->file_path }}</small>
        @endif
    </div>

    <div class="field">
        <label>Artigo de referência <x-ajuda texto="Número do artigo do Código da Estrada. Preenchido, o aluno abre o artigo directamente a partir do sinal. Opcional." /></label>
        <input type="number" name="article_ref" min="1" value="{{ old('article_ref',$sign->article_ref) }}">
    </div>

    <div class="field">
        <label>Ordem <x-ajuda texto="Posição dentro da categoria. Números menores aparecem primeiro; iguais ordenam-se por nome." /></label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order',$sign->sort_order ?? 0) }}">
    </div>

    <div class="field full">
        <label>Significado <x-ajuda texto="Frase curta e directa — é a resposta considerada certa no treino de sinais. Máximo 500 caracteres." /></label>
        <textarea name="meaning" maxlength="500" required>{{ old('meaning',$sign->meaning) }}</textarea>
    </div>

    <div class="field full">
        <label>Descrição de estudo <x-ajuda texto="Explicação mais longa: o que o condutor deve fazer ao encontrar este sinal. É o texto que o aluno lê na ficha de estudo." /></label>
        <textarea name="description" rows="5">{{ old('description',$sign->description) }}</textarea>
    </div>

    <div class="field full"><div class="checks">
        <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$sign->exists ? $sign->is_active : true))> Sinal ativo
            <x-ajuda texto="Desactivado, o sinal deixa de aparecer no app e nos exames, mas não se perde nem é apagado." /></label>
        <label><input type="checkbox" name="is_locked" value="1" @checked(old('is_locked',$sign->is_locked ?? false))> Só para o plano completo
            <x-ajuda texto="Marcado, só alunos com o plano pago vêem este sinal. Os outros vêem-no com cadeado." /></label>
    </div></div>
</div>
<div class="form-actions"><a class="btn light" href="{{ route('admin.signs.index') }}">Cancelar</a><button class="btn">Guardar sinal</button></div>
</form>
@endsection
