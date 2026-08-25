@props(['platform' => true, 'contextLabel', 'reviewCount' => 0])
@php
    $user = auth()->user();
    $item = fn (string $label, string $icon, string $route, string|array $active, bool $visible = true, ?int $badge = null) => compact('label', 'icon', 'route', 'active', 'visible', 'badge');

    $overview = [
        $item('Resumo', 'speedometer2', 'admin.dashboard', 'admin.dashboard'),
    ];
    $learning = [
        $item('Candidatos', 'people', 'admin.mobile-users.index', 'admin.mobile-users.*', $platform && $user->isAdmin()),
        $item('Turmas', 'collection', 'admin.classrooms.index', 'admin.classrooms.*', $platform || $user->hasPermission('classroom.manage')),
        $item('Operações escolares', 'person-workspace', 'admin.school-operations.index', 'admin.school-operations.*', $user->hasPermission('classroom.manage')),
        $item('Provas', 'clipboard-check', 'admin.exams.index', 'admin.exams.*', $platform || $user->hasPermission('exam.create')),
        $item('Sessões', 'calendar-check', 'admin.sessions.index', 'admin.sessions.*', $platform || $user->hasPermission('exam.publish')),
        $item('Resultados', 'bar-chart-line', 'admin.results.index', 'admin.results.*', $platform || $user->hasPermission('analytics.view')),
    ];
    $content = [
        $item('Perguntas', 'question-square', 'admin.questions.index', 'admin.questions.*', $user->hasPermission('question.create')),
        $item('Aprovação', 'check2-square', 'admin.approvals.index', 'admin.approvals.*', $user->hasPermission('question.review'), $reviewCount),
        $item('Sinais', 'signpost-2', 'admin.signs.index', 'admin.signs.*'),
        $item('Fichas de estudo', 'book', 'admin.lessons.index', 'admin.lessons.*'),
        $item('Código da Estrada', 'journal-text', 'admin.articles.index', 'admin.articles.*'),
        $item('Glossário', 'bookmark', 'admin.glossary.index', 'admin.glossary.*'),
    ];
    $management = [
        $item('Escolas', 'building', 'admin.schools.index', 'admin.schools.*', $platform && $user->isAdmin()),
        $item('Utilizadores', 'person-badge', 'admin.users.index', 'admin.users.*', $platform && $user->isAdmin()),
        $item('Pagamentos', 'credit-card', 'admin.unlocks.index', ['admin.unlocks.*', 'admin.pagamentos.*'], $platform && $user->isAdmin()),
        $item('Planos', 'layers', 'admin.plans.index', 'admin.plans.*', $platform && $user->isAdmin()),
        $item('Publicações', 'cloud-arrow-up', 'admin.publications.index', 'admin.publications.*', $platform && $user->isAdmin()),
    ];
    $system = [
        $item('Temas', 'grid', 'admin.topics.index', 'admin.topics.*'),
        $item('Categorias de sinais', 'tags', 'admin.sign-categories.index', 'admin.sign-categories.*', $platform && $user->isAdmin()),
        $item('Categorias de carta', 'card-list', 'admin.categories.index', 'admin.categories.*', $platform && $user->isAdmin()),
    ];
    $sections = [
        ['label' => 'Visão geral', 'items' => $overview],
        ['label' => 'Aprendizagem', 'items' => $learning],
        ['label' => 'Conteúdo', 'items' => $content],
        ['label' => 'Gestão', 'items' => $management],
        ['label' => 'Sistema', 'items' => $system],
    ];
@endphp

<aside class="pv-sidebar sidebar" id="sidebar" aria-label="Navegação principal">
    <div class="pv-sidebar-brand">
        <a class="pv-sidebar-logo" href="{{ route('admin.dashboard') }}" aria-label="ProntoVia — Resumo">
            <img class="pv-sidebar-logo-full" src="{{ asset('images/prontovia/Prontovia-white.png') }}" width="1640" height="664" alt="ProntoVia">
            <img class="pv-sidebar-logo-mark" src="{{ asset('images/prontovia/iconProntovia.png') }}" width="258" height="245" alt="">
        </a>
        <button class="sidebar-close" type="button" data-sidebar-close aria-label="Fechar menu"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div class="pv-sidebar-context"><span></span><strong>{{ $contextLabel }}</strong></div>
    <span class="sr-only">{{ $platform ? 'Governação do conteúdo, utilizadores e operação global.' : 'Turmas, alunos, provas e evolução da sua escola.' }}</span>

    <nav class="pv-sidebar-nav" aria-label="Módulos">
        @foreach($sections as $section)
            @php($visibleItems = collect($section['items'])->where('visible', true))
            @if($visibleItems->isNotEmpty())
                <section class="pv-sidebar-section" aria-labelledby="pv-nav-section-{{ $loop->index }}">
                    <h2 id="pv-nav-section-{{ $loop->index }}">{{ $section['label'] }}</h2>
                    <ul>
                        @foreach($visibleItems as $navItem)
                            @php($active = request()->routeIs(...(array) $navItem['active']))
                            <li><a href="{{ route($navItem['route']) }}" @class(['is-active' => $active]) @if($active)aria-current="page"@endif title="{{ $navItem['label'] }}">
                                <i class="bi bi-{{ $navItem['icon'] }}" aria-hidden="true"></i><span>{{ $navItem['label'] }}</span>
                                @if($navItem['badge'])<em aria-label="{{ $navItem['badge'] }} pendentes">{{ $navItem['badge'] }}</em>@endif
                            </a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach
    </nav>

    <div class="pv-sidebar-footer">
        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit" title="Terminar sessão"><i class="bi bi-box-arrow-left" aria-hidden="true"></i><span>Terminar sessão</span></button></form>
    </div>
</aside>
