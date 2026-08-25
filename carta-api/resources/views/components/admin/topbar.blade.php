@props(['search' => null, 'contextLabel', 'pageTitle', 'pageSubtitle', 'reviewCount' => 0])
@php($user = auth()->user())

<header class="pv-topbar topbar" aria-label="Barra superior">
    <span class="sr-only">{{ $pageTitle }} — {{ $pageSubtitle }}</span>
    <div class="pv-topbar-navigation">
        <button class="pv-topbar-control pv-topbar-menu" type="button" data-sidebar-open aria-expanded="false" aria-controls="sidebar" aria-label="Abrir menu">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <button class="pv-topbar-control pv-topbar-collapse" type="button" data-sidebar-collapse aria-expanded="true" aria-controls="sidebar" aria-label="Recolher menu">
            <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
        </button>
        <span class="pv-topbar-context">{{ $contextLabel }}</span>
    </div>

    @if($search)
        <form class="pv-topbar-search search" action="{{ $search[0] }}" role="search" aria-label="{{ $search[1] }}">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label class="sr-only" for="admin-context-search">{{ $search[1] }}</label>
            <input id="admin-context-search" name="q" value="{{ request('q') }}" placeholder="{{ $search[2] }}" autocomplete="off">
            @if(request()->filled('q'))<a href="{{ $search[0] }}" aria-label="Limpar pesquisa"><i class="bi bi-x-lg" aria-hidden="true"></i></a>@endif
            <button type="submit" aria-label="Pesquisar"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        </form>
    @else
        <div class="pv-topbar-spacer" aria-hidden="true"></div>
    @endif

    <div class="pv-topbar-actions">
        @if($user->hasPermission('question.review'))
            <a class="pv-topbar-control pv-topbar-notifications" href="{{ route('admin.approvals.index') }}" aria-label="{{ $reviewCount ? $reviewCount.' perguntas aguardam aprovação' : 'Sem perguntas aguardando aprovação' }}" title="Aprovações">
                <i class="bi bi-bell" aria-hidden="true"></i>
                @if($reviewCount)<span>{{ $reviewCount > 99 ? '99+' : $reviewCount }}</span>@endif
            </a>
        @endif

        <details class="pv-user-menu">
            <summary aria-label="Abrir menu do utilizador">
                <span class="pv-user-avatar" aria-hidden="true">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                <span class="pv-user-copy"><strong>{{ $user->name }}</strong><small>{{ $user->roleLabel() }}</small></span>
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </summary>
            <div class="pv-user-popover">
                <div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small>@if($user->school)<small>{{ $user->school->name }}</small>@endif</div>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button type="submit"><i class="bi bi-box-arrow-left" aria-hidden="true"></i>Terminar sessão</button></form>
            </div>
        </details>
    </div>
</header>
