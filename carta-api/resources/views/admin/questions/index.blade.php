@extends('layouts.admin')
@section('title','Perguntas')
@section('page-title','Perguntas')
@section('page-subtitle','Crie, organize e acompanhe o conteúdo de avaliação.')
@section('content')
@php($hasAdvancedFilters = request()->filled('topic') || request()->filled('type') || request()->filled('category') || request()->filled('status') || request('sort', 'latest') !== 'latest' || request('per_page', 15) != 15)
@php($activeFilterCount = collect([request('topic'), request('type'), request('category'), request('status')])->filter(fn($value) => filled($value))->count())
<div class="toolbar"><div><h2 id="question-bank-title">Banco de perguntas</h2><p>{{ $questions->total() }} registos encontrados</p></div><x-admin.button :href="route('admin.questions.create')">＋ Nova pergunta</x-admin.button></div>
<form class="card question-bank-filters" method="GET">
    <div class="question-bank-search"><input name="q" value="{{ request('q') }}" aria-label="Pesquisar perguntas" placeholder="Pesquisar por enunciado ou identificador"><x-admin.button type="submit">Pesquisar</x-admin.button><x-admin.button variant="secondary" id="toggle-bank-filters" type="button" aria-expanded="{{ $hasAdvancedFilters ? 'true' : 'false' }}">Filtros @if($activeFilterCount)<span class="filter-count">{{ $activeFilterCount }}</span>@endif</x-admin.button>@if(request()->query())<x-admin.button variant="secondary" :href="route('admin.questions.index')">Limpar</x-admin.button>@endif</div>
    <div class="question-bank-advanced" id="question-bank-advanced" @hidden(!$hasAdvancedFilters)>
        <x-admin.field name="topic" label="Tema" :value="request('topic')" placeholder="Pesquisar pelo nome" />
        <x-admin.field as="select" name="type" label="Tipo"><option value="">Todos</option><option value="teorico" @selected(request('type')==='teorico')>Teórico</option><option value="pratico" @selected(request('type')==='pratico')>Prático</option></x-admin.field>
        <x-admin.field as="select" name="category" label="Categoria"><option value="">Todas</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(request('category')===$category->slug)>{{ $category->name }}</option>@endforeach</x-admin.field>
        <x-admin.field as="select" name="status" label="Estado"><option value="">Todos</option>@foreach(['draft'=>'Rascunho','review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</x-admin.field>
        <x-admin.field as="select" name="sort" label="Ordenar"><option value="latest" @selected(request('sort','latest')==='latest')>Mais recentes</option><option value="updated" @selected(request('sort')==='updated')>Alteradas recentemente</option><option value="oldest" @selected(request('sort')==='oldest')>Mais antigas</option><option value="topic" @selected(request('sort')==='topic')>Tema e ordem</option></x-admin.field>
        <x-admin.field as="select" name="per_page" label="Por página"><option value="15" @selected(request('per_page',15)==15)>15</option><option value="30" @selected(request('per_page')==30)>30</option><option value="50" @selected(request('per_page')==50)>50</option></x-admin.field>
        <x-admin.button variant="secondary" type="submit" class="question-filter-apply">Aplicar filtros</x-admin.button>
    </div>
</form>
<x-admin.table class="question-bank-table" labelledby="question-bank-title">
<x-slot:head><tr><th scope="col">Pergunta</th><th scope="col">Classificação</th><th scope="col">Origem</th><th scope="col">Estado</th><th scope="col">Atualização</th><th scope="col">Ações</th></tr></x-slot:head>
@forelse($questions as $question)
<tr>
    <td class="question-main"><small class="question-code">{{ $question->external_id }}</small><strong>{{ str($question->statement)->limit(105) }}</strong><small>{{ count($question->options) }} opções @if($question->article_ref)· Artigo {{ $question->article_ref }}@endif</small></td>
    <td><strong>{{ $question->topic->name }}</strong><small class="question-meta">{{ ucfirst($question->type) }} · {{ implode(', ', $question->categories) }}</small></td>
    <td>{{ $question->author?->name ?? 'Sistema' }} @if($question->school)<small class="question-meta">{{ $question->school->name }}</small>@endif</td>
    <td><x-admin.state :type="$question->status">{{ ['draft'=>'Rascunho','review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'][$question->status] }}</x-admin.state></td>
    <td><small>{{ $question->updated_at->format('d/m/Y') }}</small></td>
    <td class="actions"><x-admin.button variant="secondary" size="small" :href="route('admin.questions.show',$question)">Ver</x-admin.button><x-admin.button variant="secondary" size="small" :href="route('admin.questions.edit',$question)">Editar</x-admin.button><x-admin.button variant="danger" size="small" data-dialog-open="delete-question-{{ $question->id }}">Remover</x-admin.button></td>
</tr>
@empty
<x-admin.empty-state table :colspan="6" icon="search" title="Nenhuma pergunta encontrada" description="Experimente alterar ou limpar os filtros aplicados." />
@endforelse
</x-admin.table>
<x-admin.pagination :paginator="$questions" />
@foreach($questions as $question)
    <x-admin.dialog id="delete-question-{{ $question->id }}" title="Remover pergunta?" description="Esta ação não pode ser anulada." size="small">
        <p>Vai remover permanentemente a pergunta <strong>{{ $question->external_id }}</strong>.</p>
        <x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.questions.destroy',$question) }}">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit" loading-label="A remover…">Remover pergunta</x-admin.button></form></x-slot:footer>
    </x-admin.dialog>
@endforeach
<script>document.getElementById('toggle-bank-filters').addEventListener('click',function(){var panel=document.getElementById('question-bank-advanced'),open=panel.hidden;panel.hidden=!open;this.setAttribute('aria-expanded',open?'true':'false')});</script>
@endsection
