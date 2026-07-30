@extends('layouts.admin')
@section('title', $lesson->exists ? 'Editar ficha' : 'Nova ficha de estudo')
@section('page-title', $lesson->exists ? 'Editar ficha de estudo' : 'Nova ficha de estudo')
@section('page-subtitle', 'Escreva em linguagem simples. Ligue os sinais e os artigos para o aluno poder consultar a fonte.')
@section('content')

<form class="card form-card" method="POST" action="{{ $lesson->exists ? route('admin.lessons.update', $lesson) : route('admin.lessons.store') }}">
@csrf @if($lesson->exists)@method('PUT')@endif
<div class="form-grid">

    <div class="field full"><label>Título</label><input name="title" value="{{ old('title', $lesson->title) }}" required></div>

    <div class="field"><label>Área de estudo</label>
        <select name="group">
            @foreach($grupos as $slug => $dados)
                <option value="{{ $slug }}" @selected(old('group', $lesson->group) === $slug)>{{ $dados['nome'] }}</option>
            @endforeach
        </select>
        <small>Define onde a ficha aparece no ecrã de estudos do app.</small>
    </div>

    <div class="field"><label>Tema associado</label>
        <select name="topic_id">
            <option value="">Sem tema</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" @selected(old('topic_id', $lesson->topic_id) == $topic->id)>{{ $topic->name }}</option>
            @endforeach
        </select>
        <small>Permite sugerir a ficha quando o aluno está fraco nesse tema.</small>
    </div>

    <div class="field"><label>Identificador</label><input name="slug" value="{{ old('slug', $lesson->slug) }}" placeholder="gerado a partir do título"></div>
    <div class="field"><label>Minutos de leitura</label><input type="number" name="reading_minutes" min="1" max="60" value="{{ old('reading_minutes', $lesson->reading_minutes ?? 3) }}" required></div>
    <div class="field"><label>Ordem</label><input type="number" name="sort_order" min="0" value="{{ old('sort_order', $lesson->sort_order ?? 0) }}"></div>

    <div class="field full"><label>Resumo <small>Uma ou duas frases. É o que aparece na lista.</small></label>
        <textarea name="summary" maxlength="500" rows="2">{{ old('summary', $lesson->summary) }}</textarea>
    </div>

    <div class="field full"><label>Conteúdo <small>Uma ideia por parágrafo. Linhas em branco separam parágrafos; comece a linha com "- " para uma lista.</small></label>
        <textarea name="body" style="min-height:320px" required>{{ old('body', $lesson->body) }}</textarea>
    </div>

    <div class="field"><label>Categorias de carta <small>Nenhuma selecionada = todas.</small></label>
        <div class="checks">
            @foreach($categorias as $slug => $nome)
                <label><input type="checkbox" name="license_categories[]" value="{{ $slug }}" @checked(in_array($slug, old('license_categories', $lesson->license_categories ?? [])))> {{ $nome }}</label>
            @endforeach
        </div>
    </div>

    <div class="field"><label>Sinais referenciados</label>
        <select name="sign_slugs[]" multiple size="8">
            @foreach($signs as $sign)
                <option value="{{ $sign->slug }}" @selected(in_array($sign->slug, old('sign_slugs', $lesson->sign_slugs ?? [])))>{{ $sign->name }}</option>
            @endforeach
        </select>
        <small>Aparecem no fim da ficha, com imagem, para o aluno reconhecer.</small>
    </div>

    <div class="field"><label>Artigos referenciados</label>
        <select name="article_numbers[]" multiple size="8">
            @foreach($articles as $article)
                <option value="{{ $article->number }}" @selected(in_array($article->number, old('article_numbers', $lesson->article_numbers ?? [])))>Art. {{ $article->number }} — {{ str($article->title)->limit(45) }}</option>
            @endforeach
        </select>
        <small>Dá ao aluno a base legal do que acabou de ler.</small>
    </div>

    <div class="field full"><div class="checks">
        <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $lesson->exists ? $lesson->is_active : true))> Ficha ativa</label>
        <label><input type="checkbox" name="is_locked" value="1" @checked(old('is_locked', $lesson->is_locked ?? false))> Só para o plano completo</label>
    </div></div>

</div>
<div class="form-actions">
    <a class="btn light" href="{{ route('admin.lessons.index') }}">Cancelar</a>
    <button class="btn">Guardar ficha</button>
</div>
</form>

@endsection
