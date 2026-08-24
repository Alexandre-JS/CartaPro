<!doctype html>
<html lang="pt-MZ">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111544">
    <title>@yield('title', 'Painel') · ProntoVia</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/prontovia/iconProntovia.webp') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/prontovia/iconProntovia.png') }}">
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
<a class="admin-skip-link" href="#admin-content">Saltar para o conteúdo</a>
<div class="admin-loading-bar" data-admin-loading-bar hidden aria-hidden="true"><span></span></div>
<div class="admin-loading-overlay" data-admin-loading-overlay hidden role="status" aria-live="polite" aria-atomic="true">
    <div class="admin-loading-indicator" aria-hidden="true"><span></span><span></span><span></span></div>
    <strong data-admin-loading-message>A processar…</strong>
    <small>Aguarde sem fechar esta página.</small>
</div>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('admin.dashboard') }}"><img class="admin-brand-logo" src="{{ asset('images/prontovia/Prontovia-white.png') }}" width="1640" height="664" alt="ProntoVia"><small>Painel de gestão</small></a>
        <span class="nav-label">Menu principal</span>
        <nav class="nav">
            <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><svg viewBox="0 0 24 24"><path d="M3 11 12 3l9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>Dashboard</a>
            <a class="{{ request()->routeIs('admin.questions.*') ? 'active' : '' }}" href="{{ route('admin.questions.index') }}"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6zM15 3v5h5M9 12h7M9 16h7"/></svg>Perguntas</a>
            @if(auth()->user()->isAdmin())<a class="{{ request()->routeIs('admin.approvals.*') ? 'active' : '' }}" href="{{ route('admin.approvals.index') }}"><svg viewBox="0 0 24 24"><path d="M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10zM8.5 12l2.2 2.2 4.8-5"/></svg>Aprovação @if(($sidebarReviewCount ?? 0)>0)<span class="badge">{{ $sidebarReviewCount }}</span>@endif</a>@endif
        </nav>
        <span class="nav-label">Conteúdo</span>
        <nav class="nav"><a class="{{ request()->routeIs('admin.signs.*') ? 'active' : '' }}" href="{{ route('admin.signs.index') }}"><svg viewBox="0 0 24 24"><path d="M12 3 2 21h20zM12 9v5M12 18h.01"/></svg>Sinais</a>@if(auth()->user()->isAdmin())<a class="{{ request()->routeIs('admin.sign-categories.*') ? 'active' : '' }}" href="{{ route('admin.sign-categories.index') }}"><svg viewBox="0 0 24 24"><path d="M3 5h7v6H3zM14 5h7v6h-7zM3 15h7v4H3zM14 15h7v4h-7z"/></svg>Categorias de sinais</a>@endif<a class="{{ request()->routeIs('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}"><svg viewBox="0 0 24 24"><path d="M5 3h14v18H5zM8 7h8M8 11h8M8 15h6"/></svg>Artigos</a><a class="{{ request()->routeIs('admin.lessons.*') ? 'active' : '' }}" href="{{ route('admin.lessons.index') }}"><svg viewBox="0 0 24 24"><path d="M4 19.5V6a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2zM8 8h8M8 12h6"/></svg>Fichas de estudo</a><a class="{{ request()->routeIs('admin.glossary.*') ? 'active' : '' }}" href="{{ route('admin.glossary.index') }}"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM8 9h8M8 13h4M16 13h.01"/></svg>Glossário</a><a class="{{ request()->routeIs('admin.topics.*') ? 'active' : '' }}" href="{{ route('admin.topics.index') }}"><svg viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>Temas</a>@if(auth()->user()->isAdmin())<a class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>Categorias</a>@endif</nav>
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
            <div class="page-heading"><button class="menu-button" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><div><h1>@yield('page-title', 'Dashboard')</h1><p>@yield('page-subtitle', 'Gestão ProntoVia')</p></div></div>
            <form class="search" action="{{ route('admin.questions.index') }}"><input name="q" value="{{ request('q') }}" placeholder="Pesquisar perguntas"><button aria-label="Pesquisar">⌕</button></form>
            <div class="admin-user"><span class="avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrador' : auth()->user()->school?->name }}</small></div></div>
        </header>
        <main class="content" id="admin-content">
            @if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="errors"><strong>Verifique os dados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </section>
</div>
<script>
var adminLoadingBar = document.querySelector('[data-admin-loading-bar]');
var adminLoadingOverlay = document.querySelector('[data-admin-loading-overlay]');
var adminLoadingMessage = document.querySelector('[data-admin-loading-message]');
var adminLoadingTimer;

function beginAdminLoading(message, delayedOverlay) {
    if (adminLoadingBar) adminLoadingBar.hidden = false;
    document.body.setAttribute('aria-busy', 'true');
    if (adminLoadingMessage && message) adminLoadingMessage.textContent = message;
    window.clearTimeout(adminLoadingTimer);
    adminLoadingTimer = window.setTimeout(function () {
        if (adminLoadingOverlay) adminLoadingOverlay.hidden = false;
    }, delayedOverlay === false ? 0 : 450);
}

function resetAdminLoading() {
    window.clearTimeout(adminLoadingTimer);
    if (adminLoadingBar) adminLoadingBar.hidden = true;
    if (adminLoadingOverlay) adminLoadingOverlay.hidden = true;
    document.body.removeAttribute('aria-busy');
    document.querySelectorAll('[data-loading-original]').forEach(function (button) {
        button.innerHTML = button.dataset.loadingOriginal;
        button.disabled = false;
        button.removeAttribute('data-loading-original');
    });
}

window.addEventListener('pageshow', resetAdminLoading);
document.addEventListener('submit', function (event) {
    window.setTimeout(function () {
        if (event.defaultPrevented) return;
        var button = event.submitter || event.target.querySelector('button[type="submit"],input[type="submit"]');
        if (button && button.tagName === 'BUTTON' && !button.dataset.loadingOriginal) {
            button.dataset.loadingOriginal = button.innerHTML;
            button.textContent = 'A processar…';
            button.disabled = true;
        }
        beginAdminLoading('A guardar alterações…');
    }, 0);
});
document.addEventListener('click', function (event) {
    var link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
    var destination = new URL(link.href, window.location.href);
    if (destination.origin !== window.location.origin || (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash)) return;
    beginAdminLoading('A abrir a página…');
});

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
