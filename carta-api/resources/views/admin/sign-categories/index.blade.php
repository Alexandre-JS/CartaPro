@extends('layouts.admin')
@section('title','Categorias de sinais')
@section('page-title','Categorias de sinais')
@section('page-subtitle','Cada sinal pertence a uma categoria. As subcategorias são opcionais e servem para refinar dentro dela.')
@section('content')

<div class="toolbar">
    <div>
        <h2>Categorias</h2>
        <p>{{ $categorias->count() }} categoria(s) de topo · {{ $categorias->sum(fn ($c) => $c->children->count()) }} subcategoria(s)</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('admin.sign-categories.create') }}">Nova categoria</a>
    </div>
</div>

<div class="card table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Identificador</th>
                <th>Sinais</th>
                <th>Ordem</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($categorias as $categoria)
            <tr>
                <td>
                    <strong>{{ $categoria->name }}</strong>
                    @if ($categoria->description)
                        <small style="display:block;color:var(--muted)">{{ $categoria->description }}</small>
                    @endif
                </td>
                <td><code>{{ $categoria->slug }}</code></td>
                <td>{{ $categoria->signs_count }}</td>
                <td>{{ $categoria->sort_order }}</td>
                <td><span class="status {{ $categoria->is_active ? 'active' : 'inactive' }}">{{ $categoria->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                <td class="actions">
                    <a class="btn light small" href="{{ route('admin.sign-categories.create', ['parent' => $categoria->id]) }}">+ Subcategoria</a>
                    <a class="btn light small" href="{{ route('admin.sign-categories.edit', $categoria) }}">Editar</a>
                    <form method="POST" action="{{ route('admin.sign-categories.destroy', $categoria) }}" onsubmit="return confirm('Apagar {{ $categoria->name }}?')">
                        @csrf @method('DELETE')
                        <button class="btn danger small">Apagar</button>
                    </form>
                </td>
            </tr>

            {{-- As subcategorias aparecem debaixo do pai, indentadas: a relação
                 entre as duas é a informação que interessa nesta listagem. --}}
            @foreach($categoria->children as $sub)
                <tr>
                    <td style="padding-left:34px;color:var(--muted)">
                        ↳ {{ $sub->name }}
                        @if ($sub->description)
                            <small style="display:block">{{ $sub->description }}</small>
                        @endif
                    </td>
                    <td><code>{{ $sub->slug }}</code></td>
                    <td>{{ $sub->signs()->count() }}</td>
                    <td>{{ $sub->sort_order }}</td>
                    <td><span class="status {{ $sub->is_active ? 'active' : 'inactive' }}">{{ $sub->is_active ? 'Ativa' : 'Inativa' }}</span></td>
                    <td class="actions">
                        <a class="btn light small" href="{{ route('admin.sign-categories.edit', $sub) }}">Editar</a>
                        <form method="POST" action="{{ route('admin.sign-categories.destroy', $sub) }}" onsubmit="return confirm('Apagar {{ $sub->name }}?')">
                            @csrf @method('DELETE')
                            <button class="btn danger small">Apagar</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="6" class="empty">Ainda não há categorias. Cria a primeira para poderes catalogar sinais.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
