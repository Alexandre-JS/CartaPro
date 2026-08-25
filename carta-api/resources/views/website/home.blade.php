@extends('layouts.website')

@section('title', 'ProntoVia — Prepare-se para o exame de condução')
@section('description', 'Prepare-se para o exame de condução em Moçambique: aprenda o Código da Estrada, pratique por tema, faça simulados e acompanhe o seu progresso.')

@section('content')
<section class="pv-hero {{ config('prontovia.images.home_hero') ? 'pv-has-background-image' : '' }}" @if(config('prontovia.images.home_hero')) style="--pv-background-image: url('{{ asset(config('prontovia.images.home_hero')) }}')" @endif>
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6">
                <span class="pv-eyebrow"><i class="bi bi-sign-turn-right" aria-hidden="true"></i> O seu percurso começa aqui</span>
                <h1>Prepare-se para conduzir com confiança.</h1>
                <p class="pv-lead">Troque a dúvida sobre o que estudar por um percurso claro: aprenda o Código da Estrada, pratique os temas mais difíceis e acompanhe a sua evolução.</p>
                <div class="pv-hero-actions">
                    @if(config('prontovia.android_url'))
                        <a class="pv-btn pv-btn-accent" href="{{ config('prontovia.android_url') }}"><i class="bi bi-google-play" aria-hidden="true"></i> Baixar a aplicação</a>
                    @elseif(config('prontovia.web_app_url'))
                        <a class="pv-btn pv-btn-accent" href="{{ config('prontovia.web_app_url') }}">Começar agora <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    @else
                        <a class="pv-btn pv-btn-accent" href="{{ route('website.candidates') }}">Conhecer a aplicação <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    @endif
                    <a class="pv-btn pv-btn-secondary" href="#como-funciona">Ver como funciona</a>
                </div>
                <p class="pv-brand-promise"><span></span> Aprenda. Pratique. Esteja pronto.</p>
            </div>
            <div class="col-lg-6">
                <div class="pv-product-visual" aria-label="Demonstração visual do acompanhamento de preparação no ProntoVia">
                    <div class="pv-route-line" aria-hidden="true"></div>
                    <div class="pv-phone">
                        <div class="pv-phone-top"><span></span></div>
                        <div class="pv-phone-screen">
                            <div class="pv-demo-header"><span class="pv-demo-logo">PV</span><i class="bi bi-bell" aria-hidden="true"></i></div>
                            <p class="pv-demo-greeting">Olá! Continue a avançar.</p>
                            <div class="pv-readiness-ring" style="--value: 78">
                                <div><strong>78%</strong><span>Prontidão</span></div>
                            </div>
                            <p class="pv-demo-note">Indicador de demonstração</p>
                            <div class="pv-demo-priority"><span><i class="bi bi-exclamation-diamond" aria-hidden="true"></i></span><div><small>Recomendação</small><strong>Reforce prioridade</strong></div></div>
                            <div class="pv-demo-grid"><span><i class="bi bi-book"></i> Aprender</span><span><i class="bi bi-ui-checks-grid"></i> Simular</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pv-section pv-story-section" aria-labelledby="story-title">
    <div class="container">
        <div class="row align-items-end gy-4 gx-lg-5">
            <div class="col-lg-7">
                <span class="pv-kicker">O desafio não é apenas estudar</span>
                <h2 id="story-title">É saber o que fazer a seguir.</h2>
            </div>
            <div class="col-lg-5">
                <p class="pv-section-copy">Quando todos os temas parecem igualmente urgentes, é fácil repetir testes sem compreender os erros. O ProntoVia transforma cada resultado numa orientação simples para o próximo estudo.</p>
            </div>
        </div>
        <div class="pv-story-flow" aria-label="Da dúvida à preparação">
            <div><span>01</span><strong>Compreenda</strong><small>Aprenda as regras no seu contexto.</small></div>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
            <div><span>02</span><strong>Pratique</strong><small>Concentre-se onde precisa melhorar.</small></div>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
            <div><span>03</span><strong>Avance</strong><small>Use o progresso para decidir o próximo passo.</small></div>
        </div>
    </div>
</section>

<section class="pv-section" id="funcionalidades">
    <div class="container">
        <div class="pv-section-heading text-center mx-auto">
            <span class="pv-kicker">Um método, não uma lista de testes</span>
            <h2>Cada etapa responde a uma pergunta.</h2>
            <p>O percurso mantém a preparação focada, do primeiro conteúdo à decisão sobre o que rever.</p>
        </div>
        <div class="row g-0 pv-value-grid">
            @foreach([
                ['book', 'O que preciso compreender?', 'Conteúdos organizados explicam as regras e os conceitos essenciais.'],
                ['bullseye', 'Onde estou a falhar?', 'A prática por tema concentra o esforço nas dificuldades reais.'],
                ['stopwatch', 'Consigo aplicar o que aprendi?', 'Os simulados juntam perguntas, tempo e resultado numa experiência completa.'],
                ['graph-up-arrow', 'Qual é o próximo passo?', 'O progresso transforma erros e resultados numa prioridade de estudo.'],
            ] as [$icon, $title, $text])
                <div class="col-sm-6 col-lg-3">
                    <article class="pv-value-card pv-reveal">
                        <span class="pv-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                        <h3>{{ $title }}</h3><p>{{ $text }}</p>
                    </article>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="pv-section pv-readiness-section pv-has-background-image pv-readiness-photo" style="--pv-background-image: url('{{ asset('images/prontovia/EXAMES.jpg') }}')">
    <div class="container">
        <div class="row align-items-center gy-5 gx-lg-5">
            <div class="col-lg-5">
                <span class="pv-kicker">Progresso que faz sentido</span>
                <h2>Saiba o quanto está preparado.</h2>
                <p class="pv-section-copy">O ProntoVia analisa a sua evolução por tema para ajudá-lo a identificar o que domina e onde ainda precisa reforçar.</p>
                <div class="pv-insight"><i class="bi bi-lightbulb" aria-hidden="true"></i><p><strong>Próximo passo sugerido</strong>Reforce prioridade e manobras antes do próximo simulado.</p></div>
                <small class="pv-disclaimer">Indicador baseado no seu desempenho dentro da plataforma. Não representa uma probabilidade de aprovação.</small>
                @if(config('prontovia.android_url'))
                    <a class="pv-btn pv-btn-primary pv-context-cta" href="{{ config('prontovia.android_url') }}"><i class="bi bi-google-play" aria-hidden="true"></i> Baixar e começar a praticar</a>
                @else
                    <a class="pv-btn pv-btn-primary pv-context-cta" href="{{ route('website.candidates') }}">Ver a preparação para candidatos <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                @endif
            </div>
            <div class="col-lg-7">
                <div class="pv-readiness-dashboard pv-reveal" aria-label="Exemplo da visão de preparação no ProntoVia">
                    <div class="pv-readiness-dashboard-head"><span><i class="bi bi-speedometer2" aria-hidden="true"></i></span><div><small>Visão de preparação</small><strong>O seu progresso num só lugar</strong></div></div>
                    <div class="pv-readiness-dashboard-main">
                        <div class="pv-readiness-gauge">
                            <svg viewBox="0 0 220 220" role="img" aria-label="Prontidão: 78 por cento">
                                <circle class="pv-gauge-track" cx="110" cy="110" r="88"></circle>
                                <circle class="pv-gauge-value" cx="110" cy="110" r="88" pathLength="100"></circle>
                            </svg>
                            <div><small>Você está</small><strong>78<sup>%</sup></strong><span>Preparado</span><em><i class="bi bi-check-circle-fill" aria-hidden="true"></i> No caminho certo</em></div>
                        </div>
                        <div class="pv-performance-chart">
                            <div class="pv-chart-title"><span><i class="bi bi-bar-chart-fill" aria-hidden="true"></i> Evolução do desempenho</span><strong>78%</strong></div>
                            <svg viewBox="0 0 360 170" role="img" aria-label="Desempenho crescente de 18 para 78 por cento em cinco momentos">
                                <g class="pv-chart-grid"><line x1="35" y1="20" x2="340" y2="20"/><line x1="35" y1="55" x2="340" y2="55"/><line x1="35" y1="90" x2="340" y2="90"/><line x1="35" y1="125" x2="340" y2="125"/></g>
                                <polyline class="pv-chart-line" points="38,118 108,96 178,76 248,53 330,26"/>
                                <g class="pv-chart-points"><circle cx="38" cy="118" r="5"/><circle cx="108" cy="96" r="5"/><circle cx="178" cy="76" r="5"/><circle cx="248" cy="53" r="5"/><circle cx="330" cy="26" r="5"/></g>
                                <g class="pv-chart-labels"><text x="30" y="151">1.º</text><text x="100" y="151">2.º</text><text x="170" y="151">3.º</text><text x="240" y="151">4.º</text><text x="316" y="151">Atual</text></g>
                            </svg>
                        </div>
                    </div>
                    <div class="pv-readiness-dashboard-bottom">
                        <div class="pv-readiness-topics">
                            @foreach([['traffic-light','Sinais',91,'blue'],['sign-yield','Prioridade',64,'orange'],['speedometer','Velocidade',84,'cyan'],['car-front','Manobras',59,'violet']] as [$icon,$topic,$score,$tone])
                                <div class="pv-readiness-topic pv-topic-{{ $tone }}"><span><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><div><small>{{ $topic }}</small><strong>{{ $score }}%</strong><div class="pv-topic-meter"><i style="width:{{ $score }}%"></i></div></div></div>
                            @endforeach
                        </div>
                        <div class="pv-learning-path"><strong>O seu caminho</strong><ol><li class="is-complete"><i class="bi bi-check2"></i><span>Fundamentos<small>Concluído</small></span></li><li class="is-complete"><i class="bi bi-check2"></i><span>Conteúdos<small>Concluído</small></span></li><li class="is-current"><i class="bi bi-circle"></i><span>Simulados<small>Em andamento</small></span></li><li><i class="bi bi-circle"></i><span>Preparação final<small>Pendente</small></span></li></ol></div>
                    </div>
                    <small class="pv-dashboard-disclaimer">Dados ilustrativos. A prontidão organiza o estudo e não garante aprovação oficial.</small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pv-section pv-audience-section">
    <div class="container">
        <div class="pv-section-heading text-start"><span class="pv-kicker">Duas experiências, uma preparação</span><h2>Use o ProntoVia à sua maneira.</h2></div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-6">
                <article class="pv-audience-card pv-audience-primary pv-reveal">
                    <span class="pv-audience-label">Para si</span><span class="pv-audience-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                    <h3>Quero preparar-me.</h3><p>Leve no telemóvel um percurso de aprendizagem, prática e acompanhamento da sua evolução.</p>
                    <a href="{{ config('prontovia.android_url') ?: route('website.candidates') }}">{{ config('prontovia.android_url') ? 'Baixar a aplicação' : 'Conhecer a aplicação' }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </article>
            </div>
            <div class="col-lg-5">
                <article class="pv-audience-card pv-reveal">
                    <span class="pv-audience-label">Para a sua escola</span><span class="pv-audience-icon"><i class="bi bi-buildings" aria-hidden="true"></i></span>
                    <h3>Quero fortalecer a minha escola.</h3><p>Acompanhe melhor os alunos, demonstre o valor do seu ensino e diferencie a escola.</p>
                    <a href="{{ route('website.schools') }}">Conhecer ProntoVia Escolas <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="pv-section pv-schools-preview {{ config('prontovia.images.schools_section') ? 'pv-has-background-image' : '' }}" @if(config('prontovia.images.schools_section')) style="--pv-background-image: url('{{ asset(config('prontovia.images.schools_section')) }}')" @endif>
    <div class="container">
        <div class="row align-items-center gy-5 gx-lg-5">
            <div class="col-lg-5">
                <span class="pv-kicker pv-kicker-light">ProntoVia Escolas</span>
                <h2>Melhores decisões para os alunos. Mais valor para a escola.</h2>
                <p>Acompanhe dificuldades, organize reforços e torne visível a qualidade do apoio que a sua escola oferece.</p>
                <p class="pv-school-principle"><i class="bi bi-link-45deg" aria-hidden="true"></i><span><strong>A escola acompanha.</strong> O aluno continua a aprender na sua própria conta ProntoVia.</span></p>
                <a class="pv-btn pv-btn-light" href="{{ route('website.schools') }}">Ver como a escola pode crescer</a>
            </div>
            <div class="col-lg-7">
                {{-- Imagem experimental: substituir pelo dashboard definitivo mantendo o mesmo nome/caminho. --}}
                <figure class="pv-school-dashboard-image pv-reveal">
                    <img src="{{ asset('images/prontovia/dashboard-grafic.png') }}" width="1014" height="636" loading="lazy" alt="Exemplo visual temporário de um painel de acompanhamento">
                    <figcaption>Imagem experimental para avaliação do enquadramento visual.</figcaption>
                </figure>
            </div>
        </div>
    </div>
</section>

<section class="pv-section pv-partners" aria-labelledby="partners-title">
    <div class="container">
        <div class="pv-partners-heading"><div><span class="pv-kicker">Escolas parceiras</span><h2 id="partners-title">Encontre uma escola que investe no acompanhamento.</h2><p>Conheça instituições que apresentam os seus serviços no ProntoVia.</p></div><span class="pv-sponsored-label"><i class="bi bi-megaphone" aria-hidden="true"></i> Conteúdo publicitário</span></div>
        @if(count(config('prontovia.partners', [])))
            <div class="pv-partner-list">
                @foreach(config('prontovia.partners') as $partner)
                    <a class="pv-partner-item" href="{{ $partner['url'] }}" target="_blank" rel="sponsored noopener noreferrer">
                        @if($partner['logo'])<img src="{{ asset($partner['logo']) }}" alt="Logótipo da {{ $partner['name'] }}" loading="lazy">@else<span class="pv-partner-monogram" aria-hidden="true">{{ mb_substr($partner['name'], 0, 1) }}</span>@endif
                        <span><strong>{{ $partner['name'] }}</strong>@if($partner['location'])<small><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $partner['location'] }}</small>@endif</span>
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        @else
            <div class="pv-partner-empty"><div><strong>A sua escola pode aparecer aqui.</strong><p>Apresente a sua instituição a candidatos que procuram preparar-se melhor.</p></div><a class="pv-btn pv-btn-primary" href="{{ route('website.schools') }}#contacto">Tornar-se escola parceira</a></div>
        @endif
    </div>
</section>

<section class="pv-section pv-how" id="como-funciona">
    <div class="container">
        <div class="pv-section-heading text-center mx-auto"><span class="pv-kicker">Como funciona</span><h2>Um passo de cada vez, sempre a avançar.</h2></div>
        <ol class="pv-steps">
            @foreach([['person-plus','Crie a sua conta'],['book','Escolha o que estudar'],['ui-checks-grid','Pratique e faça simulados'],['search','Descubra onde precisa melhorar'],['speedometer2','Acompanhe a sua prontidão']] as $index => [$icon,$text])
                <li><span class="pv-step-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><small>Passo {{ $index + 1 }}</small><strong>{{ $text }}</strong></li>
            @endforeach
        </ol>
    </div>
</section>

<section class="pv-section pv-trust">
    <div class="container"><div class="pv-trust-inner"><span class="pv-trust-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span><div><span class="pv-kicker">Limites claros</span><h2>Preparação séria, sem promessas fáceis.</h2><p>O ProntoVia apoia a aprendizagem e a prática. É uma plataforma educativa independente e não representa nem substitui entidades reguladoras ou processos oficiais de exame.</p></div></div></div>
</section>

<section class="pv-section" id="faq">
    <div class="container">
        <div class="row gx-lg-5 gy-4"><div class="col-lg-4"><span class="pv-kicker">Perguntas frequentes</span><h2>Informação clara antes de começar.</h2><p>Sem promessas de aprovação e sem dependência obrigatória de uma escola.</p></div>
        <div class="col-lg-8"><div class="accordion pv-accordion" id="pvFaq">
            @foreach([
                ['O ProntoVia substitui uma escola de condução?', 'Não. O ProntoVia complementa a preparação teórica e ajuda a acompanhar a evolução. A formação prática e os procedimentos formais continuam a seguir as entidades competentes.'],
                ['Preciso estar matriculado numa escola para utilizar?', 'Não. Pode criar uma conta individual e utilizar o ProntoVia de forma independente.'],
                ['Posso ligar a minha conta a uma escola depois?', 'A ligação opcional entre a conta individual e a escola faz parte da evolução prevista do produto. Neste momento, as experiências individual e escolar funcionam separadamente.'],
                ['Posso continuar a utilizar se mudar de escola?', 'A sua conta individual não depende de uma escola. A continuidade de uma futura ligação escolar dependerá das opções disponibilizadas nessa integração.'],
                ['O ProntoVia realiza exames oficiais?', 'Não. Os simulados e testes são recursos educativos de preparação e não são exames oficiais.'],
                ['Preciso sempre de Internet?', 'É necessária ligação para criar a conta e descarregar o conteúdo. Depois, vários recursos já descarregados podem ser usados com conectividade limitada; a sincronização acontece quando houver ligação.'],
                ['Existe uma versão gratuita?', 'Sim. O sistema possui acesso gratuito e conteúdo adicional que pode ser desbloqueado. As condições comerciais são apresentadas no produto.'],
                ['Como funciona o ProntoVia Escolas?', 'A escola gere turmas, alunos, testes, sessões e resultados no seu painel. A futura ligação à conta individual será opcional.'],
            ] as $index => [$question,$answer])
                <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button {{ $index ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $index }}" aria-expanded="{{ $index ? 'false' : 'true' }}" aria-controls="faq{{ $index }}">{{ $question }}</button></h3><div id="faq{{ $index }}" class="accordion-collapse collapse {{ $index ? '' : 'show' }}" data-bs-parent="#pvFaq"><div class="accordion-body">{{ $answer }}</div></div></div>
            @endforeach
        </div></div></div>
    </div>
</section>

<section class="pv-final-cta">
    <div class="container"><div class="pv-final-inner"><div><span class="pv-kicker pv-kicker-light">ProntoVia no seu telemóvel</span><h2>Leve a preparação consigo.</h2><p>Estude, pratique e acompanhe a sua evolução onde estiver.</p></div><div class="pv-final-actions">@if(config('prontovia.android_url'))<a class="pv-download-button" href="{{ config('prontovia.android_url') }}"><i class="bi bi-google-play" aria-hidden="true"></i><span><small>Disponível no</small><strong>Google Play</strong></span></a>@else<button class="pv-download-button" type="button" disabled><i class="bi bi-phone" aria-hidden="true"></i><span><small>Aplicação Android</small><strong>Disponível em breve</strong></span></button>@endif<a class="pv-school-text-link" href="{{ route('website.schools') }}">Representa uma escola? Conheça a solução <i class="bi bi-arrow-right"></i></a></div></div></div>
</section>
@endsection
