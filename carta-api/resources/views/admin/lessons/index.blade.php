@extends('layouts.admin')
@section('title', 'Fichas de estudo')
@section('page-title', 'Fichas de estudo')
@section('page-subtitle', 'O material que ensina. Os artigos do Código, em linguagem legal, não bastam para o aluno estudar.')
@section('content')

<div class="toolbar">
    <div><h2>{{ $lessons->total() }} fichas</h2><p>Aparecem no app agrupadas por área de estudo.</p></div>
    @if(auth()->user()->isAdmin())<a class="btn" href="{{ route('admin.lessons.create') }}">＋ Nova ficha</a>@endif
</div>

<section class="metric-grid">
    @foreach($grupos as $slug => $dados)
        <article class="card metric-card">
            <span class="metric-icon blue">{{ $loop->iteration }}</span>
            <div><span>{{ $dados['nome'] }}</span><strong>{{ $porGrupo[$slug] ?? 0 }}</strong><small>{{ $dados['descricao'] }}</small></div>
        </article>
    @endforeach
</section>

<form class="filters">
    <input name="q" value="{{ request('q') }}" placeholder="Título ou resumo">
    <select name="group">
        <option value="">Todas as áreas</option>
        @foreach($grupos as $slug => $dados)
            <option value="{{ $slug }}" @selected(request('group')===$slug)>{{ $dados['nome'] }}</option>
        @endforeach
    </select>
    <button class="btn light">Pesquisar</button>
</form>

<section class="card table-card">
    <table class="data-table">
        <thead><tr><th>Ficha</th><th>Área</th><th>Tema</th><th>Ligações</th><th>Leitura</th><th>Estado</th><th>Ações</th></tr></thead>
        <tbody>
        @forelse($lessons as $lesson)
            <tr>
                <td><strong>{{ $lesson->title }}</strong><br><small>{{ str($lesson->summary)->limit(110) }}</small></td>
                <td><span class="status active">{{ $lesson->grupoNome() }}</span></td>
                <td>{{ $lesson->topic?->name ?? '—' }}</td>
                <td>
                    <small>
                        {{ count($lesson->sign_slugs ?? []) }} sinais ·
                        {{ count($lesson->article_numbers ?? []) }} artigos
                    </small>
                </td>
                <td>{{ $lesson->reading_minutes }} min</td>
                <td>
                    <span class="status {{ $lesson->is_active ? 'active' : 'inactive' }}">{{ $lesson->is_active ? 'Ativa' : 'Inativa' }}</span>
                    @if($lesson->is_locked)<br><span class="status review">Plano completo</span>@endif
                </td>
                <td class="actions">
                    @if(auth()->user()->isAdmin())
                        <a class="btn light small" href="{{ route('admin.lessons.edit', $lesson) }}">Editar</a>
                        <form method="POST" action="{{ route('admin.lessons.destroy', $lesson) }}" onsubmit="return confirm('Remover esta ficha?')">@csrf @method('DELETE')<button class="btn danger small">Remover</button></form>
                    @else
                        <small>Leitura</small>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td class="empty" colspan="7">Ainda não existem fichas de estudo. É o conteúdo que o aluno lê antes de praticar.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
<div class="pagination">{{ $lessons->links() }}</div>

@endsection
