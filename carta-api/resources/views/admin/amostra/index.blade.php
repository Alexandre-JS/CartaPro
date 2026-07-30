@extends('layouts.admin')
@section('title','Amostra gratuita')
@section('page-title','Amostra gratuita')
@section('page-subtitle','O que um aluno vê antes de desbloquear. As primeiras N de cada grupo ficam livres; o resto fecha.')
@section('content')

<p class="alert">A regra é <strong>posicional</strong>: as primeiras de cada tema, categoria ou capítulo. Igual para toda a gente e previsível — o aluno prova todos os temas e encontra o cadeado onde está fraco, que é onde a compra faz sentido.</p>

<section class="split-grid">
    <form class="card inline-form" method="GET" action="{{ route('admin.amostra.index') }}">
        <div class="field"><label>Provas livres <small>Sempre as mesmas, para todos.</small></label><input type="number" name="exames" min="0" value="{{ $limites['exames'] }}"></div>
        <div class="field"><label>Perguntas por tema</label><input type="number" name="perguntas" min="0" value="{{ $limites['perguntas'] }}"></div>
        <div class="field"><label>Sinais por categoria</label><input type="number" name="sinais" min="0" value="{{ $limites['sinais'] }}"></div>
        <div class="field"><label>Fichas por grupo</label><input type="number" name="fichas" min="0" value="{{ $limites['fichas'] }}"></div>
        <div class="field"><label>Artigos por capítulo</label><input type="number" name="artigos" min="0" value="{{ $limites['artigos'] }}"></div>
        <div class="field"><label>Termos do glossário</label><input type="number" name="glossario" min="0" value="{{ $limites['glossario'] }}"></div>
        <div class="form-actions full"><button class="btn light">Pré-visualizar</button></div>
    </form>

    <div>
        <section class="card table-card">
            <table class="data-table">
                <thead><tr><th>Conteúdo</th><th>À vista</th><th>Bloqueado</th><th>Grupos</th></tr></thead>
                <tbody>
                    @foreach($plano as $frente => $dados)
                    <tr>
                        <td><strong>{{ ucfirst($frente) }}</strong></td>
                        <td><span class="status approved">{{ $dados['livres'] }}</span></td>
                        <td>@if($dados['bloqueados'])<span class="status review">{{ $dados['bloqueados'] }}</span>@else<span class="status inactive">0</span>@endif</td>
                        <td>{{ $dados['total'] }} no total, {{ $dados['grupos'] }} {{ $dados['grupos'] === 1 ? 'grupo' : 'grupos' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        {{-- Só depois de ver a simulação: a alteração toca em todo o catálogo. --}}
        <form class="card" method="POST" action="{{ route('admin.amostra.store') }}">
            @csrf
            @foreach($limites as $chave => $valor)<input type="hidden" name="{{ $chave }}" value="{{ $valor }}">@endforeach
            <p>Aplicar reescreve o cadeado de <strong>todos</strong> os conteúdos acima — o que estiver marcado à mão será substituído por esta regra.</p>
            <div class="form-actions"><button class="btn" onclick="return confirm('Aplicar esta amostra a todo o catálogo?')">Aplicar amostra</button></div>
        </form>

        <p class="alert warning">Depois de aplicar é preciso <a href="{{ route('admin.publications.index') }}">publicar o pacote</a>: o app consome o pacote publicado, não a base de dados.</p>
    </div>
</section>

@endsection
