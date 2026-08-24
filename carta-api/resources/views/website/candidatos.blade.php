@extends('layouts.website')

@section('title', 'ProntoVia para candidatos — Prepare-se ao seu ritmo')
@section('description', 'Prepare-se para o exame de condução em Moçambique: estude o Código da Estrada, pratique por tema, faça simulados e acompanhe a sua evolução.')

@section('content')
<section class="pv-page-hero pv-candidate-hero {{ config('prontovia.images.candidate_hero') ? 'pv-has-background-image' : '' }}" @if(config('prontovia.images.candidate_hero')) style="--pv-background-image: url('{{ asset(config('prontovia.images.candidate_hero')) }}')" @endif>
    <div class="container"><div class="row align-items-center gy-5">
        <div class="col-lg-7"><span class="pv-eyebrow"><i class="bi bi-person" aria-hidden="true"></i> ProntoVia para candidatos</span><h1>A sua preparação, ao seu ritmo.</h1><p class="pv-lead">Aprenda, pratique e compreenda onde precisa melhorar — com uma conta que é sua e não depende de uma escola.</p><div class="pv-hero-actions">
            @if(config('prontovia.android_url') || config('prontovia.web_app_url'))
                <a class="pv-btn pv-btn-accent" href="{{ config('prontovia.web_app_url') ?: config('prontovia.android_url') }}">Começar agora <i class="bi bi-arrow-right"></i></a>
            @else
                <a class="pv-btn pv-btn-accent" href="#experiencia">Descobrir a experiência <i class="bi bi-arrow-down"></i></a>
            @endif
            <a class="pv-btn pv-btn-secondary" href="#recursos">Ver recursos</a>
        </div><p class="pv-brand-promise"><span></span> Aprenda. Pratique. Esteja pronto.</p></div>
        <div class="col-lg-5"><div class="pv-candidate-orbit" aria-hidden="true"><div class="pv-orbit-center"><i class="bi bi-person-check"></i><strong>O seu percurso</strong></div><span class="pv-orbit-item one"><i class="bi bi-book"></i> Aprender</span><span class="pv-orbit-item two"><i class="bi bi-bullseye"></i> Praticar</span><span class="pv-orbit-item three"><i class="bi bi-graph-up"></i> Evoluir</span></div></div>
    </div></div>
</section>

<section class="pv-section" id="experiencia"><div class="container"><div class="pv-section-heading text-center mx-auto"><span class="pv-kicker">Independente por princípio</span><h2>Uma experiência completa, mesmo sem escola.</h2><p>O ProntoVia foi pensado primeiro para si. A ligação a uma escola é uma extensão opcional, não uma condição para aprender.</p></div>
    <div class="row g-4">
        @foreach([['person-plus','Crie a sua conta','O seu histórico e progresso permanecem ligados à sua conta individual.'],['sliders','Escolha como estudar','Pratique por tema, reveja erros ou avance para um simulado.'],['activity','Receba orientação','Veja o que domina e quais temas merecem a sua atenção agora.']] as [$icon,$title,$text])
            <div class="col-md-4"><article class="pv-detail-card"><span class="pv-icon"><i class="bi bi-{{ $icon }}"></i></span><h3>{{ $title }}</h3><p>{{ $text }}</p></article></div>
        @endforeach
    </div>
</div></section>

<section class="pv-section pv-light-section" id="recursos"><div class="container"><div class="row align-items-center gy-5 gx-lg-5">
    <div class="col-lg-5"><span class="pv-kicker">Tudo o que precisa para praticar</span><h2>Da aprendizagem à revisão.</h2><p class="pv-section-copy">Alterne entre conteúdos, treino e avaliação sem perder de vista o seu objetivo.</p></div>
    <div class="col-lg-7"><div class="pv-resource-list">
        @foreach([['journal-text','Aprender','Lições, Código da Estrada, glossário e conteúdos organizados.'],['signpost-split','Sinais','Consulte a biblioteca e pratique o reconhecimento de sinais.'],['crosshair','Praticar por tema','Concentre a sessão num tema específico.'],['clipboard-check','Simulados','Treine com perguntas, tempo e resultado.'],['arrow-repeat','Revisão inteligente','Volte às perguntas no momento certo para consolidar.'],['bar-chart-line','Progresso e prontidão','Acompanhe resultados recentes e prioridades de estudo.']] as [$icon,$title,$text])
            <article><i class="bi bi-{{ $icon }}"></i><div><h3>{{ $title }}</h3><p>{{ $text }}</p></div></article>
        @endforeach
    </div></div>
</div></div></section>

<section class="pv-section"><div class="container"><div class="row align-items-center gy-5 gx-lg-5">
    <div class="col-lg-6"><div class="pv-readiness-panel"><div class="pv-panel-top"><div><span>O seu desenvolvimento</span><strong>Prontidão pedagógica</strong></div><div class="pv-score">78<small>%</small></div></div><div class="pv-progress-track"><span style="width:78%"></span></div><div class="pv-topic-list"><div class="pv-topic-row"><span>Sinais</span><div class="pv-progress-track"><span style="width:91%"></span></div><strong>91%</strong></div><div class="pv-topic-row"><span>Prioridade</span><div class="pv-progress-track"><span style="width:64%"></span></div><strong>64%</strong></div><div class="pv-topic-row"><span>Manobras</span><div class="pv-progress-track"><span style="width:59%"></span></div><strong>59%</strong></div></div><small>Exemplo visual. O indicador real depende do desempenho dentro da plataforma.</small></div></div>
    <div class="col-lg-6"><span class="pv-kicker">Mais clareza, menos adivinhação</span><h2>Saiba onde concentrar o próximo estudo.</h2><p class="pv-section-copy">O progresso não se resume à nota do último teste. O ProntoVia considera a prática por tema, os erros e as revisões para apresentar uma orientação pedagógica.</p><div class="pv-insight"><i class="bi bi-compass"></i><p><strong>Orientação, não promessa</strong>A prontidão ajuda a organizar a preparação. Não prevê nem garante aprovação num exame oficial.</p></div>@if(config('prontovia.android_url') || config('prontovia.web_app_url'))<a class="pv-btn pv-btn-primary pv-context-cta" href="{{ config('prontovia.web_app_url') ?: config('prontovia.android_url') }}">Começar a minha preparação <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endif</div>
</div></div></section>

<section class="pv-section pv-offline-band"><div class="container"><div class="row align-items-center gy-4"><div class="col-lg-8"><span class="pv-kicker pv-kicker-light">Preparado para conectividade limitada</span><h2>Continue com o conteúdo já descarregado.</h2><p>Depois do primeiro acesso e download, vários recursos permanecem disponíveis localmente. Quando recuperar a ligação, o progresso pendente pode ser sincronizado.</p></div><div class="col-lg-4 text-lg-end"><span class="pv-offline-icon"><i class="bi bi-cloud-check"></i></span></div></div></div></section>

<section class="pv-section"><div class="container"><div class="pv-link-school"><div><span class="pv-kicker">Uma extensão opcional</span><h2>Se a sua escola usar ProntoVia.</h2><p>A integração futura poderá permitir acompanhamento adicional pela escola. A sua aprendizagem individual continua a ser o centro da experiência.</p></div><a class="pv-btn pv-btn-secondary" href="{{ route('website.schools') }}">Conhecer ProntoVia Escolas</a></div></div></section>

<section class="pv-final-cta"><div class="container"><div class="pv-final-inner"><div><span class="pv-kicker pv-kicker-light">A aplicação ProntoVia</span><h2>A preparação cabe no seu dia.</h2><p>Abra a aplicação quando tiver alguns minutos e continue do ponto onde parou.</p></div><div class="pv-final-actions">
    @if(config('prontovia.android_url'))<a class="pv-download-button" href="{{ config('prontovia.android_url') }}"><i class="bi bi-google-play" aria-hidden="true"></i><span><small>Disponível no</small><strong>Google Play</strong></span></a>@else<button class="pv-download-button" type="button" disabled><i class="bi bi-phone" aria-hidden="true"></i><span><small>Aplicação Android</small><strong>Disponível em breve</strong></span></button>@endif
</div></div></div></section>
@endsection
