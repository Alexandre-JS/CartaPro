<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Painel') · CartaPro</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo/icon CartaPro.png') }}">
    {{--
        A data de modificação no endereço obriga o browser — e o CDN à frente
        dele — a buscar a folha de estilos outra vez quando ela muda. Sem isto
        o ficheiro é servido de cache indefinidamente: uma correcção de estilo
        publicada continuava invisível para quem já tinha visitado o painel, e
        parecia que o deploy não tinha subido.
    --}}
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) ?: '1' }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('images/logo/icon CartaPro.png') }}" alt=""><span><strong>Carta<b>Pro</b></strong><small>Painel de gestão</small></span></a>
        <span class="nav-label">Menu principal</span>
        <nav class="nav">
            <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><svg viewBox="0 0 24 24"><path d="M3 11 12 3l9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>Dashboard</a>
            <a class="{{ request()->routeIs('admin.questions.*') ? 'active' : '' }}" href="{{ route('admin.questions.index') }}"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM15 3v5h5M9 12h7M9 16h7"/></svg>Perguntas</a>
            @if(auth()->user()->isAdmin())<a class="{{ request()->routeIs('admin.approvals.*') ? 'active' : '' }}" href="{{ route('admin.approvals.index') }}"><svg viewBox="0 0 24 24"><path d="M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10zM8.5 12l2.2 2.2 4.8-5"/></svg>Aprovação @if(($sidebarReviewCount ?? 0)>0)<span class="badge">{{ $sidebarReviewCount }}</span>@endif</a>@endif
        </nav>
        <span class="nav-label">Conteúdo</span>
        <nav class="nav"><a class="{{ request()->routeIs('admin.signs.*') ? 'active' : '' }}" href="{{ route('admin.signs.index') }}"><svg viewBox="0 0 24 24"><path d="M12 3 2 21h20zM12 9v5M12 18h.01"/></svg>Sinais</a><a class="{{ request()->routeIs('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}"><svg viewBox="0 0 24 24"><path d="M5 3h14v18H5zM8 7h8M8 11h8M8 15h6"/></svg>Artigos</a><a class="{{ request()->routeIs('admin.lessons.*') ? 'active' : '' }}" href="{{ route('admin.lessons.index') }}"><svg viewBox="0 0 24 24"><path d="M4 19.5V6a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2zM8 8h8M8 12h6"/></svg>Fichas de estudo</a><a class="{{ request()->routeIs('admin.glossary.*') ? 'active' : '' }}" href="{{ route('admin.glossary.index') }}"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM8 9h8M8 13h4M16 13h.01"/></svg>Glossário</a><a class="{{ request()->routeIs('admin.topics.*') ? 'active' : '' }}" href="{{ route('admin.topics.index') }}"><svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>Temas</a>@if(auth()->user()->isAdmin())<a class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>Categorias</a>@endif</nav>
        @if(auth()->user()->isAdmin())
            <nav class="nav"><a class="{{ request()->routeIs('admin.publications.*') ? 'active' : '' }}" href="{{ route('admin.publications.index') }}"><svg viewBox="0 0 24 24"><path d="M12 3v12M7 8l5-5 5 5M5 14v6h14v-6"/></svg>Publicação</a></nav>
            <span class="nav-label">Gestão</span>
            <nav class="nav">
                <a class="{{ request()->routeIs('admin.schools.*') ? 'active' : '' }}" href="{{ route('admin.schools.index') }}"><svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V8l7-5 7 5v13M9 12h6M9 16h6"/></svg>Escolas</a>
                <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M22 21v-2a4 4 0 0 0-3-3.8M16 3.2a4 4 0 0 1 0 7.6"/></svg>Utilizadores</a>
                <a class="{{ request()->routeIs('admin.mobile-users.*') ? 'active' : '' }}" href="{{ route('admin.mobile-users.index') }}"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8M19 8v6M16 11h6"/></svg>Utilizadores do app</a>
                <a class="{{ request()->routeIs('admin.unlocks.*') ? 'active' : '' }}" href="{{ route('admin.unlocks.index') }}"><svg viewBox="0 0 24 24"><path d="M7 11V7a5 5 0 0 1 10 0M5 11h14v10H5zM12 15v3"/></svg>Desbloqueios</a>
            </nav>
        @endif
        <span class="nav-label">Escola</span><nav class="nav"><a class="{{ request()->routeIs('admin.classrooms.*') ? 'active' : '' }}" href="{{ route('admin.classrooms.index') }}"><svg viewBox="0 0 24 24"><path d="M3 7h18M5 7v14h14V7M9 11h6M9 15h6"/></svg>Turmas</a><a class="{{ request()->routeIs('admin.exams.*') ? 'active' : '' }}" href="{{ route('admin.exams.index') }}"><svg viewBox="0 0 24 24"><path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h5"/></svg>Provas</a><a class="{{ request()->routeIs('admin.sessions.*') ? 'active' : '' }}" href="{{ route('admin.sessions.index') }}"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM8 9h8M8 13h5"/></svg>Sessões</a><a class="{{ request()->routeIs('admin.results.*') ? 'active' : '' }}" href="{{ route('admin.results.index') }}"><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>Resultados</a></nav>
        <div class="sidebar-footer"><form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="logout" type="submit">Terminar sessão</button></form></div>
    </aside>
    <section class="workspace">
        <header class="topbar">
            <div class="page-heading"><button class="menu-button" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><div><h1>@yield('page-title', 'Dashboard')</h1><p>@yield('page-subtitle', 'Gestão de conteúdo CartaPro')</p></div></div>
            <form class="search" action="{{ route('admin.questions.index') }}"><input name="q" value="{{ request('q') }}" placeholder="Pesquisar perguntas"><button aria-label="Pesquisar">⌕</button></form>
            <div class="admin-user"><span class="avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrador' : auth()->user()->school?->name }}</small></div></div>
        </header>
        <main class="content">
            @if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="errors"><strong>Verifique os dados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </section>
</div>
<script>
document.querySelectorAll('form[action*="/admin/"]').forEach(function (form) {
    if (/\/admin\/(exams|sessions|classrooms|unlocks)\/\d+$/.test(form.action) && form.parentElement.tagName === 'TD') form.parentElement.classList.add('actions');
});
document.querySelectorAll('.actions, .library-actions, .approval-head').forEach(function (container) {
    if ([].some.call(container.querySelectorAll('a'), function (link) { return link.textContent.trim() === 'Ver'; })) return;

    var edit = [].find.call(container.querySelectorAll('a[href]'), function (link) { return /\/edit(?:\?|$)/.test(link.href); });
    var destructive = container.querySelector('form[action*="/admin/"]');
    var download = container.querySelector('a[href*="/publications/"][href$="/download"]');
    var showUrl = edit ? edit.href.replace(/\/edit(?:\?.*)?$/, '') : (download ? download.href.replace(/\/download$/, '') : null);

    if (!showUrl && destructive) {
        var candidate = destructive.action;
        if (/\/admin\/(exams|sessions|classrooms|unlocks)\/\d+$/.test(candidate)) showUrl = candidate;
    }
    if (!showUrl) return;

    var view = document.createElement('a');
    view.className = 'btn light small';
    view.href = showUrl;
    view.textContent = 'Ver';
    container.insertBefore(view, container.firstElementChild);
});
</script>
</body>
</html>
