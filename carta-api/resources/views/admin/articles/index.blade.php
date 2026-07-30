@extends('layouts.admin')
@section('title', 'Artigos')
@section('page-title', 'Artigos do Código')
@section('page-subtitle', 'Consulte e ligue referências legais às perguntas.')
@section('content')
<div class="toolbar"><div><h2>Biblioteca legal</h2><p>{{ $articles->total() }} artigos</p></div>@if(auth()->user()->isAdmin())<a class="btn" href="{{ route('admin.articles.create') }}">＋ Novo artigo</a>@endif</div>
@if($semCapitulo)<p class="alert warning">{{ $semCapitulo }} artigo(s) sem capítulo atribuído. No app aparecem agrupados em "Outras disposições" — atribua o capítulo para a leitura ficar organizada.</p>@endif
<form class="filters"><input name="q" value="{{ request('q') }}" placeholder="Número, título ou texto"><select name="chapter"><option value="">Todos os capítulos</option>@foreach($capitulos as $capitulo)<option value="{{ $capitulo->chapter_number }}" @selected(request('chapter') == $capitulo->chapter_number)>Cap. {{ $capitulo->chapter_number }} — {{ $capitulo->chapter_title ?: 'sem título' }} ({{ $capitulo->total }})</option>@endforeach</select><button class="btn light">Pesquisar</button></form>
<section class="card table-card"><table class="data-table"><thead><tr><th>Artigo</th><th>Capítulo</th><th>Título e conteúdo</th><th>Estado</th><th>Ações</th></tr></thead><tbody>
@forelse($articles as $article)
<tr><td><strong>{{ $article->number }}</strong></td><td>@if($article->chapter_number)<span class="status active">Cap. {{ $article->chapter_number }}</span><br><small>{{ $article->chapter_title }}</small>@else<span class="status review">Sem capítulo</span>@endif</td><td><strong>{{ $article->title }}</strong><br><small>{{ str($article->text)->limit(130) }}</small></td><td><span class="status {{ $article->is_active ? 'active' : 'inactive' }}">{{ $article->is_active ? 'Ativo' : 'Inativo' }}</span></td><td class="actions"><a class="btn light small" href="{{ route('admin.articles.show', $article) }}">Ver</a>@if(auth()->user()->isAdmin())<a class="btn light small" href="{{ route('admin.articles.edit', $article) }}">Editar</a><form method="POST" action="{{ route('admin.articles.destroy', $article) }}" onsubmit="return confirm('Remover este artigo?')">@csrf @method('DELETE')<button class="btn danger small">Remover</button></form>@endif</td></tr>
@empty<tr><td class="empty" colspan="5">Ainda não existem artigos.</td></tr>@endforelse
</tbody></table></section><div class="pagination">{{ $articles->links() }}</div>
@endsection
