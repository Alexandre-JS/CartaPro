@extends('layouts.admin')
@section('title',$categoria->exists ? 'Editar categoria' : 'Nova categoria')
@section('page-title',$categoria->exists ? 'Editar categoria' : 'Nova categoria')
@section('page-subtitle','Uma categoria com pai é uma subcategoria. Sem pai, é uma categoria de topo.')
@section('content')
<form class="card form-card" method="POST" action="{{ $categoria->exists ? route('admin.sign-categories.update', $categoria) : route('admin.sign-categories.store') }}">
@csrf
@if ($categoria->exists)
    @method('PUT')
@endif
<div class="form-grid">
    <div class="field">
        <label>Nome <x-ajuda texto="Como a categoria aparece no painel e no app. Ex.: Sinais de perigo." /></label>
        <input name="name" value="{{ old('name',$categoria->name) }}" required>
    </div>

    <div class="field">
        <label>Identificador <x-ajuda texto="Deixa vazio e é gerado a partir do nome. É a chave que o app usa — mudá-lo depois de haver sinais obriga o app a descarregar o conteúdo de novo." /></label>
        <input name="slug" value="{{ old('slug',$categoria->slug) }}" placeholder="{{ $categoria->exists ? '' : 'gerado a partir do nome' }}">
    </div>

    <div class="field">
        <label>Categoria-mãe <x-ajuda texto="Deixa em branco para criar uma categoria de topo. Escolhendo uma, esta passa a ser subcategoria dela. Só se permite um nível de subcategorias." /></label>
        <select name="parent_id">
            <option value="">Nenhuma — é categoria de topo</option>
            @foreach($paisPossiveis as $pai)
                <option value="{{ $pai->id }}" @selected(old('parent_id',$categoria->parent_id) == $pai->id)>{{ $pai->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label>Ordem <x-ajuda texto="Posição na lista. Números menores aparecem primeiro; iguais ordenam-se por nome." /></label>
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order',$categoria->sort_order ?? 0) }}">
    </div>

    <div class="field full">
        <label>Descrição <x-ajuda texto="Frase curta que explica o que a categoria agrupa. Aparece no app, por baixo do nome." /></label>
        <input name="description" maxlength="255" value="{{ old('description',$categoria->description) }}">
    </div>

    <div class="field">
        <label>Ícone <x-ajuda texto="Nome de um ícone Ionicons, como warning-outline ou ban-outline. Opcional — vazio, o app mostra a categoria sem ícone." /></label>
        <input name="icon" value="{{ old('icon',$categoria->icon) }}" placeholder="warning-outline">
    </div>

    <div class="field full"><div class="checks">
        <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$categoria->exists ? $categoria->is_active : true))> Categoria ativa
            <x-ajuda texto="Desactivada, deixa de ser oferecida ao catalogar sinais e some do app. Os sinais que já lhe pertencem não se perdem." /></label>
    </div></div>
</div>
<div class="form-actions">
    <a class="btn light" href="{{ route('admin.sign-categories.index') }}">Cancelar</a>
    <button class="btn">Guardar categoria</button>
</div>
</form>
@endsection
