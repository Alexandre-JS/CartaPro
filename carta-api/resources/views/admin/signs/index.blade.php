@extends('layouts.admin')
@section('title', 'Sinais')
@section('page-title', 'Biblioteca de sinais')
@section('page-subtitle', 'Sinalização vertical, marcas rodoviárias, semáforos e sinais dos agentes.')
@section('content')
<div class="sign-library-page">
    <x-admin.page-header id="sign-library-title" title="Biblioteca de sinais" description="Consulte, organize e mantenha o conteúdo visual usado no estudo e nas perguntas." :count="$signs->total()" count-label="sinais">
        @if(auth()->user()->isAdmin())<x-admin.button :href="route('admin.signs.create')"><i class="bi bi-plus-lg" aria-hidden="true"></i>Novo sinal</x-admin.button>@endif
    </x-admin.page-header>
    <form method="get" class="data-toolbar sign-library-filters" aria-label="Pesquisar biblioteca de sinais">
        <label class="pv-table-search"><i class="bi bi-search" aria-hidden="true"></i><input name="q" value="{{ request('q') }}" placeholder="Pesquisar por nome ou significado" aria-label="Nome ou significado"></label>
        <label class="field"><span>Categoria</span><select name="category"><option value="">Todas as categorias</option>@foreach($categorias as $categoria)<option value="{{ $categoria->id }}" @selected((int) request('category') === $categoria->id)>{{ $categoria->name }} ({{ $porCategoria[$categoria->id] ?? 0 }})</option>@endforeach</select></label>
        <x-admin.button type="submit" variant="secondary"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</x-admin.button>
        @if(request()->hasAny(['q','category']))<x-admin.button variant="secondary" :href="route('admin.signs.index')">Limpar</x-admin.button>@endif
    </form>
    <section class="sign-library-grid" aria-labelledby="sign-library-title">
        @forelse($signs as $sign)
            @php($imagemDisponivel = $sign->file_path && is_file(public_path(ltrim($sign->file_path, '/'))))
            <article class="sign-library-item">
                <div class="sign-library-preview">@if($imagemDisponivel)<img src="{{ asset(ltrim($sign->file_path, '/')) }}" alt="{{ $sign->name }}">@else<span><i class="bi bi-image" aria-hidden="true"></i>Imagem em falta</span>@endif</div>
                <div class="sign-library-copy"><div class="sign-library-meta"><x-admin.state :type="$sign->is_active ? 'approved' : 'neutral'">{{ $sign->categoriaNome() }}</x-admin.state>@if($sign->is_locked)<span class="sign-plan-badge">Plano completo</span>@endif</div><h3>{{ $sign->name }}</h3><p>{{ str($sign->meaning)->limit(110) }}</p>@if(!$sign->description)<small class="sign-missing-copy">Sem texto de estudo</small>@endif</div>
                <x-admin.row-actions :view-href="route('admin.signs.show', $sign)" label="Ações do sinal"><a href="{{ route('admin.signs.edit', $sign) }}" role="menuitem"><i class="bi bi-pencil" aria-hidden="true"></i>Editar</a>@if(auth()->user()->isAdmin())<form method="POST" action="{{ route('admin.signs.destroy', $sign) }}" onsubmit="return confirm('Remover este sinal?')" role="menuitem">@csrf @method('DELETE')<button type="submit" class="is-danger"><i class="bi bi-trash3" aria-hidden="true"></i>Remover</button></form>@endif</x-admin.row-actions>
            </article>
        @empty
            <x-admin.empty-state icon="image" title="Ainda não existem sinais cadastrados" description="Adicione o primeiro sinal para começar a biblioteca visual." />
        @endforelse
    </section>
    <x-admin.pagination :paginator="$signs" />
</div>
@endsection
