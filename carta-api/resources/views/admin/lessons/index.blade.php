@extends('layouts.admin')
@section('title', 'Fichas de estudo')
@section('page-title', 'Fichas de estudo')
@section('page-subtitle', 'Material de estudo organizado por área, tema e referências.')
@section('content')
<div class="study-sheets-page">
    <x-admin.page-header id="study-sheets-title" title="Fichas de estudo" description="Ensine com linguagem clara e mantenha as referências acessíveis ao aluno." :count="$lessons->total()" count-label="fichas">
        @if(auth()->user()->isAdmin())<x-admin.button :href="route('admin.lessons.create')"><i class="bi bi-plus-lg" aria-hidden="true"></i>Nova ficha</x-admin.button>@endif
    </x-admin.page-header>
    <section class="study-group-summary" aria-label="Fichas por área">
        @foreach($grupos as $slug => $dados)<div><span>{{ $dados['nome'] }}</span><strong>{{ $porGrupo[$slug] ?? 0 }}</strong><small>{{ $dados['descricao'] }}</small></div>@endforeach
    </section>
    <form method="get" class="data-toolbar study-sheets-filters" aria-label="Pesquisar fichas de estudo">
        <label class="pv-table-search"><i class="bi bi-search" aria-hidden="true"></i><input name="q" value="{{ request('q') }}" placeholder="Pesquisar por título ou resumo" aria-label="Título ou resumo"></label>
        <label class="field"><span>Área</span><select name="group"><option value="">Todas as áreas</option>@foreach($grupos as $slug=>$dados)<option value="{{ $slug }}" @selected(request('group') === $slug)>{{ $dados['nome'] }}</option>@endforeach</select></label>
        <x-admin.button type="submit" variant="secondary"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</x-admin.button>
        @if(request()->hasAny(['q','group']))<x-admin.button variant="secondary" :href="route('admin.lessons.index')">Limpar</x-admin.button>@endif
    </form>
    <x-admin.table class="study-sheets-table" labelledby="study-sheets-title" caption="Fichas de estudo">
        <x-slot:head><tr><th scope="col">Ficha</th><th scope="col">Área e tema</th><th scope="col">Referências</th><th scope="col">Leitura</th><th scope="col">Estado</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
        @forelse($lessons as $lesson)
            <tr><td class="study-sheet-main"><strong>{{ $lesson->title }}</strong><small>{{ str($lesson->summary)->limit(125) }}</small></td><td><x-admin.state type="active">{{ $lesson->grupoNome() }}</x-admin.state><small class="study-sheet-meta">{{ $lesson->topic?->name ?? 'Sem tema' }}</small></td><td class="study-sheet-links"><strong>{{ count($lesson->sign_slugs ?? []) }} sinais</strong><small>{{ count($lesson->article_numbers ?? []) }} artigos</small></td><td>{{ $lesson->reading_minutes }} min</td><td><x-admin.state :type="$lesson->is_active ? 'approved' : 'neutral'">{{ $lesson->is_active ? 'Ativa' : 'Inativa' }}</x-admin.state>@if($lesson->is_locked)<small class="study-plan-badge">Plano completo</small>@endif</td><td class="actions"><x-admin.row-actions :view-href="route('admin.lessons.show', $lesson)" label="Ações da ficha"><a href="{{ route('admin.lessons.edit', $lesson) }}" role="menuitem"><i class="bi bi-pencil" aria-hidden="true"></i>Editar</a>@if(auth()->user()->isAdmin())<form method="POST" action="{{ route('admin.lessons.destroy', $lesson) }}" onsubmit="return confirm('Remover esta ficha?')" role="menuitem">@csrf @method('DELETE')<button type="submit" class="is-danger"><i class="bi bi-trash3" aria-hidden="true"></i>Remover</button></form>@endif</x-admin.row-actions></td></tr>
        @empty
            <x-admin.empty-state table :colspan="6" icon="book" title="Ainda não existem fichas de estudo" description="Crie a primeira ficha para começar o material que o aluno lê antes de praticar." />
        @endforelse
    </x-admin.table>
    <x-admin.pagination :paginator="$lessons" />
</div>
@endsection
