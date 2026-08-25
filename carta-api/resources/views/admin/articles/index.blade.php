@extends('layouts.admin')
@section('title', 'Artigos')
@section('page-title', 'Artigos do Código')
@section('page-subtitle', 'Consulte e ligue referências legais às perguntas e fichas de estudo.')
@section('content')
<div class="legal-library-page">
    <x-admin.page-header id="legal-library-title" title="Biblioteca legal" description="Mantenha os artigos pesquisáveis, organizados por capítulo e prontos para consulta." :count="$articles->total()" count-label="artigos">
        @if(auth()->user()->isAdmin())<x-admin.button :href="route('admin.articles.create')"><i class="bi bi-plus-lg" aria-hidden="true"></i>Novo artigo</x-admin.button><x-admin.button variant="secondary" data-dialog-open="import-articles"><i class="bi bi-upload" aria-hidden="true"></i>Importar JSON</x-admin.button>@endif
    </x-admin.page-header>
    @if($semCapitulo)<div class="legal-library-alert"><i class="bi bi-info-circle" aria-hidden="true"></i><span><strong>{{ $semCapitulo }} artigo(s) sem capítulo.</strong> Atribua o capítulo para a leitura no app ficar organizada.</span></div>@endif
    <form method="get" class="data-toolbar legal-library-filters" aria-label="Pesquisar artigos legais">
        <label class="pv-table-search"><i class="bi bi-search" aria-hidden="true"></i><input name="q" value="{{ request('q') }}" placeholder="Pesquisar por número, título ou texto" aria-label="Número, título ou texto"></label>
        <label class="field"><span>Capítulo</span><select name="chapter"><option value="">Todos os capítulos</option>@foreach($capitulos as $capitulo)<option value="{{ $capitulo->chapter_number }}" @selected(request('chapter') == $capitulo->chapter_number)>Cap. {{ $capitulo->chapter_number }} — {{ $capitulo->chapter_title ?: 'Sem título' }} ({{ $capitulo->total }})</option>@endforeach</select></label>
        <x-admin.button type="submit" variant="secondary"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</x-admin.button>
        @if(request()->hasAny(['q','chapter']))<x-admin.button variant="secondary" :href="route('admin.articles.index')">Limpar</x-admin.button>@endif
    </form>
    <x-admin.table class="legal-library-table" labelledby="legal-library-title" caption="Artigos do Código">
        <x-slot:head><tr><th scope="col">Artigo</th><th scope="col">Capítulo</th><th scope="col">Título e conteúdo</th><th scope="col">Acesso</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
        @forelse($articles as $article)
            <tr><td class="legal-number"><strong>{{ $article->number }}</strong><small>Artigo</small></td><td>@if($article->chapter_number)<x-admin.state type="active">Cap. {{ $article->chapter_number }}</x-admin.state><small class="legal-meta">{{ $article->chapter_title ?: 'Sem título' }}</small>@else<x-admin.state type="review">Sem capítulo</x-admin.state>@endif</td><td class="legal-content"><strong>{{ $article->title }}</strong><small>{{ str($article->text)->limit(145) }}</small></td><td><x-admin.state :type="$article->is_active ? 'approved' : 'neutral'">{{ $article->is_active ? 'Ativo' : 'Inativo' }}</x-admin.state><small class="legal-plan">{{ $article->is_locked ? 'Plano completo' : 'Gratuito' }}</small></td><td class="actions"><x-admin.row-actions :view-href="route('admin.articles.show', $article)" label="Ações do artigo"><a href="{{ route('admin.articles.edit', $article) }}" role="menuitem"><i class="bi bi-pencil" aria-hidden="true"></i>Editar</a>@if(auth()->user()->isAdmin())<form method="POST" action="{{ route('admin.articles.destroy', $article) }}" onsubmit="return confirm('Remover este artigo?')" role="menuitem">@csrf @method('DELETE')<button type="submit" class="is-danger"><i class="bi bi-trash3" aria-hidden="true"></i>Remover</button></form>@endif</x-admin.row-actions></td></tr>
        @empty
            <x-admin.empty-state table :colspan="5" icon="book" title="Ainda não existem artigos" description="Adicione ou importe artigos para construir a biblioteca legal." />
        @endforelse
    </x-admin.table>
    <x-admin.pagination :paginator="$articles" />
</div>
@if(auth()->user()->isAdmin())
<x-admin.dialog id="import-articles" title="Importar artigos" description="Carregue um ficheiro JSON com os campos numero, titulo, texto, capitulo e capituloTitulo.">
    <form id="import-articles-form" method="POST" action="{{ route('admin.articles.import') }}" enctype="multipart/form-data" class="modal-form-grid">
        @csrf
        <x-admin.field name="file" label="Ficheiro JSON" type="file" accept="application/json,.json" required class="modal-form-full" />
        <p class="field-help modal-form-full">Os artigos existentes com o mesmo número serão atualizados. O limite é 10 MB.</p>
    </form>
    <x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><x-admin.button type="submit" form="import-articles-form"><i class="bi bi-upload" aria-hidden="true"></i>Importar artigos</x-admin.button></x-slot:footer>
</x-admin.dialog>
@endif
@endsection
