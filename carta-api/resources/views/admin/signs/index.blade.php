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
        @foreach ($categorias as $categoria)
            <option value="{{ $categoria->id }}" @selected((int) request('category') === $categoria->id)>{{ $categoria->name }} ({{ $porCategoria[$categoria->id] ?? 0 }})</option>
        @endforeach
    </select>
    <button class="btn light">Pesquisar</button>
</form>

<section class="library-grid">
    @forelse ($signs as $sign)
        @php($imagemDisponivel = $sign->file_path && is_file(public_path(ltrim($sign->file_path, '/'))))
        <article class="card library-item">
            <div class="library-preview" data-sign-name="{{ $sign->name }}" data-stored-image="{{ $sign->file_path }}">@if($imagemDisponivel)<img src="{{ asset(ltrim($sign->file_path, '/')) }}" alt="{{ $sign->name }}">@else<span class="missing-image">Imagem em falta</span>@endif</div>
            <span class="status {{ $sign->is_active ? 'active' : 'inactive' }}">{{ $sign->categoriaNome() }}</span>@if ($sign->subcategory)<small style="display:block;color:var(--muted)">{{ $sign->subcategory->name }}</small>@endif
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
<script>
document.querySelectorAll('.library-preview').forEach(function (preview) {
    const image = preview.querySelector('img');
    if (! image) {
        console.warn('[CartaPro:sinais] Ficheiro indisponível', {
            nome: preview.dataset.signName,
            caminhoGuardado: preview.dataset.storedImage || null,
            pagina: location.href,
        });
        return;
    }
    const loaded = function () {
        console.info('[CartaPro:sinais] Imagem carregada', {
            nome: image.alt,
            src: image.currentSrc || image.src,
            largura: image.naturalWidth,
            altura: image.naturalHeight,
        });
    };
    const failed = function (event) {
        console.error('[CartaPro:sinais] Falha ao carregar imagem', {
            nome: image.alt,
            src: image.currentSrc || image.src,
            baseUrl: document.baseURI,
            pagina: location.href,
            eventType: event?.type || 'estado inicial',
        });
    };
    image.addEventListener('load', loaded, { once: true });
    image.addEventListener('error', failed, { once: true });
    if (image.complete) image.naturalWidth > 0 ? loaded() : failed();
});
console.info('[CartaPro:sinais] Biblioteca iniciada', {
    imagensRenderizadas: document.querySelectorAll('.library-preview img').length,
    imagensEmFalta: document.querySelectorAll('.library-preview .missing-image').length,
});
</script>
@endsection
