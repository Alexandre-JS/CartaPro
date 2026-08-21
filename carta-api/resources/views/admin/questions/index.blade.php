@extends('layouts.admin')
@section('title','Perguntas')
@section('page-title','Perguntas')
@section('page-subtitle','Crie, organize e acompanhe o conteúdo de avaliação.')
@section('content')
@php($hasAdvancedFilters = request()->filled('topic') || request()->filled('type') || request()->filled('category') || request()->filled('status') || request('sort', 'latest') !== 'latest' || request('per_page', 15) != 15)
@php($activeFilterCount = collect([request('topic'), request('type'), request('category'), request('status')])->filter(fn($value) => filled($value))->count())
<div class="toolbar"><div><h2>Banco de perguntas</h2><p>{{ $questions->total() }} registos encontrados</p></div><a class="btn" href="{{ route('admin.questions.create') }}">＋ Nova pergunta</a></div>
<form class="card question-bank-filters" method="GET">
    <div class="question-bank-search"><input name="q" value="{{ request('q') }}" placeholder="Pesquisar por enunciado ou identificador"><button class="btn">Pesquisar</button><button class="btn light" id="toggle-bank-filters" type="button" aria-expanded="{{ $hasAdvancedFilters ? 'true' : 'false' }}">Filtros @if($activeFilterCount)<span class="filter-count">{{ $activeFilterCount }}</span>@endif</button>@if(request()->query())<a class="btn light" href="{{ route('admin.questions.index') }}">Limpar</a>@endif</div>
    <div class="question-bank-advanced" id="question-bank-advanced" @hidden(!$hasAdvancedFilters)>
        <div class="field"><label>Tema</label><input name="topic" value="{{ request('topic') }}" placeholder="Pesquisar pelo nome"></div>
        <div class="field"><label>Tipo</label><select name="type"><option value="">Todos</option><option value="teorico" @selected(request('type')==='teorico')>Teórico</option><option value="pratico" @selected(request('type')==='pratico')>Prático</option></select></div>
        <div class="field"><label>Categoria</label><select name="category"><option value="">Todas</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(request('category')===$category->slug)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="field"><label>Estado</label><select name="status"><option value="">Todos</option>@foreach(['draft'=>'Rascunho','review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label>Ordenar</label><select name="sort"><option value="latest" @selected(request('sort','latest')==='latest')>Mais recentes</option><option value="updated" @selected(request('sort')==='updated')>Alteradas recentemente</option><option value="oldest" @selected(request('sort')==='oldest')>Mais antigas</option><option value="topic" @selected(request('sort')==='topic')>Tema e ordem</option></select></div>
        <div class="field"><label>Por página</label><select name="per_page"><option value="15" @selected(request('per_page',15)==15)>15</option><option value="30" @selected(request('per_page')==30)>30</option><option value="50" @selected(request('per_page')==50)>50</option></select></div>
        <button class="btn light question-filter-apply">Aplicar filtros</button>
    </div>
</form>
<section class="card table-card question-bank-table"><table class="data-table"><thead><tr><th>Pergunta</th><th>Classificação</th><th>Origem</th><th>Estado</th><th>Atualização</th><th>Ações</th></tr></thead><tbody>
@forelse($questions as $question)
<tr>
    <td class="question-main"><small class="question-code">{{ $question->external_id }}</small><strong>{{ str($question->statement)->limit(105) }}</strong><small>{{ count($question->options) }} opções @if($question->article_ref)· Artigo {{ $question->article_ref }}@endif</small></td>
    <td><strong>{{ $question->topic->name }}</strong><small class="question-meta">{{ ucfirst($question->type) }} · {{ implode(', ', $question->categories) }}</small></td>
    <td>{{ $question->author?->name ?? 'Sistema' }} @if($question->school)<small class="question-meta">{{ $question->school->name }}</small>@endif</td>
    <td><span class="status {{ $question->status }}">{{ ['draft'=>'Rascunho','review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'][$question->status] }}</span></td>
    <td><small>{{ $question->updated_at->format('d/m/Y') }}</small></td>
    <td class="actions"><a class="btn light small" href="{{ route('admin.questions.show',$question) }}">Ver</a><a class="btn light small" href="{{ route('admin.questions.edit',$question) }}">Editar</a><form method="POST" action="{{ route('admin.questions.destroy',$question) }}" onsubmit="return confirm('Remover esta pergunta?')">@csrf @method('DELETE')<button class="btn danger small">Remover</button></form></td>
</tr>
@empty
<tr><td colspan="6" class="empty">Nenhuma pergunta corresponde aos filtros.</td></tr>
@endforelse
</tbody></table></section><div class="pagination">{{ $questions->links() }}</div>
<script>document.getElementById('toggle-bank-filters').addEventListener('click',function(){var panel=document.getElementById('question-bank-advanced'),open=panel.hidden;panel.hidden=!open;this.setAttribute('aria-expanded',open?'true':'false')});</script>
@endsection
