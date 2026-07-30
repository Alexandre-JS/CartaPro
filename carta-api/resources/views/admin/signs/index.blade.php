@extends('layouts.admin')

@section('title', 'Sinais')
@section('page-title', 'Biblioteca de sinais')
@section('page-subtitle', 'Sinalização vertical, marcas rodoviárias, semáforos e sinais dos agentes. Alimenta o ecrã de sinais e o treino no app.')

@section('content')
<div class="toolbar">
    <div>
        <h2>Sinais cadastrados</h2>
        <p>{{ $signs->total() }} sinais</p>
    </div>
    @if (auth()->user()->isAdmin())
        <a class="btn" href="{{ route('admin.signs.create') }}">＋ Novo sinal</a>
    @endif
</div>

<form class="filters">
    <input name="q" value="{{ request('q') }}" placeholder="Nome ou significado">
    <select name="category">
        <option value="">Todas as categorias</option>
        @foreach ($categorias as $slug => $dados)
            <option value="{{ $slug }}" @selected(request('category') === $slug)>{{ $dados['nome'] }} ({{ $porCategoria[$slug] ?? 0 }})</option>
        @endforeach
    </select>
    <button class="btn light">Pesquisar</button>
</form>

<section class="library-grid">
    @forelse ($signs as $sign)
        <article class="card library-item">
            <div class="library-preview"><img src="{{ $sign->file_path }}" alt="{{ $sign->name }}"></div>
            <span class="status {{ $sign->is_active ? 'active' : 'inactive' }}">{{ $sign->categoriaNome() }}</span>
            <h3>{{ $sign->name }}</h3>
            <p>{{ str($sign->meaning)->limit(90) }}</p>

            @if ($sign->is_locked)
                <span class="status review">Plano completo</span>
            @endif

            @if (! $sign->description)
                <span class="status inactive">Sem texto de estudo</span>
            @endif

            <div class="library-actions">
                <a class="btn light small" href="{{ route('admin.signs.show', $sign) }}">Ver</a>
                @if (auth()->user()->isAdmin())
                    <a class="btn light small" href="{{ route('admin.signs.edit', $sign) }}">Editar</a>
                    <form method="POST" action="{{ route('admin.signs.destroy', $sign) }}" onsubmit="return confirm('Remover este sinal?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn danger small">Remover</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="card empty">Ainda não existem sinais cadastrados.</div>
    @endforelse
</section>

<div class="pagination">{{ $signs->links() }}</div>
@endsection
