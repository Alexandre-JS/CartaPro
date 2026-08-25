@extends('layouts.admin')
@section('title','Perguntas')
@section('page-title','Perguntas')
@section('page-subtitle','Crie, organize e acompanhe o conteúdo de avaliação.')
@section('content')
@php($hasAdvancedFilters = request()->filled('topic') || request()->filled('type') || request()->filled('category') || request()->filled('status') || request('sort', 'latest') !== 'latest' || request('per_page', 10) != 10)
@php($activeFilterCount = collect([request('topic'), request('type'), request('category'), request('status')])->filter(fn($value) => filled($value))->count())
<x-admin.page-header id="question-bank-title" title="Banco de perguntas" description="Pesquise, filtre e mantenha o conteúdo de avaliação." :count="$questions->total()">
    <x-admin.button :href="route('admin.questions.create')"><i class="bi bi-plus-lg" aria-hidden="true"></i>Nova pergunta</x-admin.button>
</x-admin.page-header>
<x-admin.data-toolbar class="question-bank-filters" label="Pesquisar e filtrar perguntas">
    <div class="question-bank-search"><label class="pv-table-search"><i class="bi bi-search" aria-hidden="true"></i><span class="sr-only">Pesquisar perguntas</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Pesquisar por enunciado ou identificador"></label><x-admin.button type="submit">Pesquisar</x-admin.button><x-admin.button variant="secondary" id="toggle-bank-filters" type="button" aria-expanded="{{ $hasAdvancedFilters ? 'true' : 'false' }}"><i class="bi bi-sliders" aria-hidden="true"></i>Filtros @if($activeFilterCount)<span class="filter-count">{{ $activeFilterCount }}</span>@endif</x-admin.button>@if(request()->query())<x-admin.button variant="secondary" :href="route('admin.questions.index')">Limpar</x-admin.button>@endif</div>
    <div class="question-bank-advanced" id="question-bank-advanced" @if(!$hasAdvancedFilters) hidden @endif>
        <x-admin.field name="topic" label="Tema" :value="request('topic')" placeholder="Pesquisar pelo nome" />
        <x-admin.field as="select" name="type" label="Tipo"><option value="">Todos</option><option value="teorico" @selected(request('type')==='teorico')>Teórico</option><option value="pratico" @selected(request('type')==='pratico')>Prático</option></x-admin.field>
        <x-admin.field as="select" name="category" label="Categoria"><option value="">Todas</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(request('category')===$category->slug)>{{ $category->name }}</option>@endforeach</x-admin.field>
        <x-admin.field as="select" name="status" label="Estado"><option value="">Todos</option>@foreach(['draft'=>'Rascunho','review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</x-admin.field>
        <x-admin.field as="select" name="sort" label="Ordenar"><option value="latest" @selected(request('sort','latest')==='latest')>Mais recentes</option><option value="updated" @selected(request('sort')==='updated')>Alteradas recentemente</option><option value="oldest" @selected(request('sort')==='oldest')>Mais antigas</option><option value="topic" @selected(request('sort')==='topic')>Tema e ordem</option></x-admin.field>
        <x-admin.field as="select" name="per_page" label="Por página"><option value="10" @selected(request('per_page',10)==10)>10</option><option value="30" @selected(request('per_page')==30)>30</option><option value="50" @selected(request('per_page')==50)>50</option></x-admin.field>
        <x-admin.button variant="secondary" type="submit" class="question-filter-apply">Aplicar filtros</x-admin.button>
    </div>
</x-admin.data-toolbar>
<x-admin.table class="question-bank-table" labelledby="question-bank-title" caption="Banco de perguntas">
<x-slot:head><tr><th scope="col">Pergunta</th><th scope="col">Tema</th><th scope="col">Estado</th><th scope="col">Atualização</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
@forelse($questions as $question)
<tr>
    <td class="question-main"><strong title="{{ $question->statement }}">{{ str($question->statement)->limit(105) }}</strong></td>
    <td><strong>{{ $question->topic->name }}</strong></td>
    <td><x-admin.state :type="$question->status">{{ ['draft'=>'Rascunho','review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'][$question->status] }}</x-admin.state></td>
    <td><time datetime="{{ $question->updated_at->toDateString() }}">{{ $question->updated_at->format('d/m/Y') }}</time></td>
    <td class="actions"><x-admin.row-actions :view-href="route('admin.questions.show',$question)" label="Ações da pergunta"><a href="{{ route('admin.questions.edit',$question) }}" role="menuitem"><i class="bi bi-pencil" aria-hidden="true"></i>Editar</a><button type="button" role="menuitem" class="is-danger" data-dialog-open="delete-question-{{ $question->id }}"><i class="bi bi-trash3" aria-hidden="true"></i>Remover</button></x-admin.row-actions></td>
</tr>
@empty
<x-admin.empty-state table :colspan="5" icon="search" title="Nenhuma pergunta encontrada" description="Experimente alterar ou limpar os filtros aplicados." />
@endforelse
</x-admin.table>
<x-admin.pagination :paginator="$questions" />
@foreach($questions as $question)
    <x-admin.dialog id="delete-question-{{ $question->id }}" title="Remover pergunta?" description="Esta ação não pode ser anulada." size="small">
        <p>Vai remover permanentemente a pergunta “<strong>{{ str($question->statement)->limit(90) }}</strong>”.</p>
        <x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><form method="POST" action="{{ route('admin.questions.destroy',$question) }}">@csrf @method('DELETE')<x-admin.button variant="danger" type="submit" loading-label="A remover…">Remover pergunta</x-admin.button></form></x-slot:footer>
    </x-admin.dialog>
@endforeach
<script>document.getElementById('toggle-bank-filters').addEventListener('click',function(){var panel=document.getElementById('question-bank-advanced'),open=panel.hidden;panel.hidden=!open;this.setAttribute('aria-expanded',open?'true':'false')});</script>
@endsection
