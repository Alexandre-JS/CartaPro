@extends('layouts.admin')
@section('title', 'Glossário')
@section('page-title', 'Glossário de termos')
@section('page-subtitle', 'Definições curtas e pesquisáveis. O app permite consultá-las a partir das explicações das perguntas.')
@section('content')

<section class="split-grid">

    @if(auth()->user()->isAdmin())
        <form class="card inline-form" method="POST" action="{{ route('admin.glossary.store') }}">@csrf
            <div class="field"><label>Termo</label><input name="term" placeholder="Ex.: berma" required></div>
            <div class="field"><label>Artigo de referência</label><input type="number" name="article_ref" min="1"></div>
            <div class="field full"><label>Definição</label><textarea name="definition" maxlength="2000" rows="4" required></textarea></div>
            <div class="field"><label>Ordem</label><input type="number" name="sort_order" min="0" value="0"></div>
            <div class="field full"><div class="checks"><label><input type="checkbox" name="is_active" value="1" checked> Ativo</label></div></div>
            <div class="form-actions full"><button class="btn">Adicionar termo</button></div>
        </form>
    @endif

    <div>
        <form class="filters"><input name="q" value="{{ request('q') }}" placeholder="Termo ou definição"><button class="btn light">Pesquisar</button></form>

        <section class="card table-card">
            <table class="data-table">
                <thead><tr><th>Termo</th><th>Definição</th><th>Artigo</th><th>Estado</th><th>Ações</th></tr></thead>
                <tbody>
                @forelse($terms as $term)
                    <tr>
                        <td><strong>{{ $term->term }}</strong><br><small>{{ $term->slug }}</small></td>
                        <td>{{ str($term->definition)->limit(160) }}</td>
                        <td>{{ $term->article_ref ? 'Art. '.$term->article_ref : '—' }}</td>
                        <td><span class="status {{ $term->is_active ? 'active' : 'inactive' }}">{{ $term->is_active ? 'Ativo' : 'Inativo' }}</span></td>
                        <td class="actions">
                            <a class="btn light small" href="{{ route('admin.glossary.show', $term) }}">Ver</a>
                            @if(auth()->user()->isAdmin())
                                <form method="POST" action="{{ route('admin.glossary.destroy', $term) }}" onsubmit="return confirm('Remover este termo?')">@csrf @method('DELETE')<button class="btn danger small">Remover</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="5">Glossário vazio. Comece pelos termos que confundem mais os alunos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
        <div class="pagination">{{ $terms->links() }}</div>
    </div>

</section>

@endsection
