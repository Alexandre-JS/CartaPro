@php
    $pageTitle = trim($__env->yieldContent('title', 'ProntoVia — Prepare-se para o exame de condução'));
    $pageDescription = trim($__env->yieldContent('description', 'Aprenda, pratique, faça simulados e acompanhe a sua preparação para o exame de condução com o ProntoVia.'));
    $pageUrl = url()->current();
    $socialImage = config('prontovia.social_image') ? asset(config('prontovia.social_image')) : null;
    $organizationId = url('/').'#organization';
    $websiteId = url('/').'#website';
    $schema = [
        [
            '@type' => 'Organization',
            '@id' => $organizationId,
            'name' => 'ProntoVia',
            'url' => url('/'),
            'description' => 'Plataforma educativa de preparação teórica para candidatos à condução e de acompanhamento para escolas.',
            'email' => config('prontovia.support_email') ?: null,
            'telephone' => config('prontovia.contact_phone') ?: null,
            'logo' => asset('images/prontovia/favicon.svg'),
            'sameAs' => array_values(array_filter(config('prontovia.social', []))),
        ],
        [
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'url' => url('/'),
            'name' => 'ProntoVia',
            'inLanguage' => 'pt-MZ',
            'publisher' => ['@id' => $organizationId],
        ],
        [
            '@type' => 'WebPage',
            '@id' => $pageUrl.'#webpage',
            'url' => $pageUrl,
            'name' => $pageTitle,
            'description' => $pageDescription,
            'inLanguage' => 'pt-MZ',
            'isPartOf' => ['@id' => $websiteId],
            'about' => ['@id' => $organizationId],
        ],
    ];

    if (! request()->routeIs('website.home')) {
        $schema[] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => route('website.home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => request()->routeIs('website.candidates') ? 'Para candidatos' : 'Para escolas', 'item' => $pageUrl],
            ],
        ];
    }

    $schema = array_map(fn ($item) => array_filter($item, fn ($value) => $value !== null && $value !== []), $schema);
@endphp
<!doctype html>
<html lang="pt-MZ">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1A1F5C">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="{{ $pageUrl }}">
    <link rel="alternate" hreflang="pt-MZ" href="{{ $pageUrl }}">
    <link rel="sitemap" type="application/xml" href="{{ route('website.sitemap') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/prontovia/favicon.svg') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="ProntoVia">
    <meta property="og:locale" content="pt_MZ">
    <meta property="og:title" content="@yield('og_title', $pageTitle)">
    <meta property="og:description" content="@yield('og_description', $pageDescription)">
    <meta property="og:url" content="{{ $pageUrl }}">
    @if($socialImage)<meta property="og:image" content="{{ $socialImage }}">@endif
    <meta name="twitter:card" content="{{ $socialImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="@yield('og_title', $pageTitle)">
    <meta name="twitter:description" content="@yield('og_description', $pageDescription)">
    @if($socialImage)<meta name="twitter:image" content="{{ $socialImage }}">@endif
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $schema], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @vite(['resources/css/website.css', 'resources/js/website.js'])
</head>
<body>
<a class="pv-skip-link" href="#conteudo">Saltar para o conteúdo</a>
<div class="pv-page-loader" data-pv-loader role="status" aria-live="polite" aria-label="A carregar ProntoVia">
    <div class="pv-loader-road" aria-hidden="true"><span></span><span></span><span></span></div>
    <strong>Pronto<span>Via</span></strong>
    <small>A preparar o seu percurso…</small>
</div>
<aside class="pv-utility-bar" aria-label="Informações de contacto">
    <div class="container">
        <span class="pv-utility-promise">Aprenda. Pratique. Esteja pronto.</span>
        <div class="pv-utility-details">
            @if(config('prontovia.business_hours'))<span><i class="bi bi-clock" aria-hidden="true"></i>{{ config('prontovia.business_hours') }}</span>@endif
            @if(config('prontovia.support_email'))<a href="mailto:{{ config('prontovia.support_email') }}"><i class="bi bi-envelope" aria-hidden="true"></i>{{ config('prontovia.support_email') }}</a>@endif
            @if(config('prontovia.contact_phone'))<a href="{{ config('prontovia.contact_phone_url') ?: 'tel:'.preg_replace('/\s+/', '', config('prontovia.contact_phone')) }}"><i class="bi bi-telephone" aria-hidden="true"></i>{{ config('prontovia.contact_phone') }}</a>@endif
            <nav class="pv-social-links" aria-label="Redes sociais">
                @foreach(config('prontovia.social', []) as $network => $url)
                    @if($url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="ProntoVia no {{ ucfirst($network) }}"><i class="bi bi-{{ $network }}" aria-hidden="true"></i></a>@endif
                @endforeach
            </nav>
        </div>
    </div>
</aside>
<header class="pv-header" data-pv-header>
    <nav class="navbar navbar-expand-lg pv-navbar" aria-label="Navegação principal">
        <div class="container">
            <a class="pv-brand" href="{{ route('website.home') }}" aria-label="ProntoVia — Início">
                <span class="pv-brand-mark" aria-hidden="true"><span></span></span>
                <span>Pronto<span>Via</span></span>
            </a>
            <button class="navbar-toggler pv-menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#pvMenu" aria-controls="pvMenu" aria-label="Abrir menu">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <div class="offcanvas offcanvas-end pv-offcanvas" tabindex="-1" id="pvMenu" aria-labelledby="pvMenuLabel">
                <div class="offcanvas-header">
                    <span class="pv-brand" id="pvMenuLabel"><span class="pv-brand-mark" aria-hidden="true"><span></span></span><span>Pronto<span>Via</span></span></span>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar menu"></button>
                </div>
                <div class="offcanvas-body align-items-lg-center">
                    <ul class="navbar-nav mx-lg-auto pv-nav-links">
                        <li class="nav-item"><a class="nav-link" href="{{ route('website.home') }}">Início</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('website.home') }}#como-funciona">Como funciona</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('website.candidates') }}">Para candidatos</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('website.schools') }}">Para escolas</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('website.home') }}#funcionalidades">Funcionalidades</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('website.home') }}#faq">FAQ</a></li>
                    </ul>
                    <div class="pv-nav-actions">
                        <a class="pv-login-link" href="{{ route('login') }}">Entrar</a>
                        @if(config('prontovia.android_url'))
                            <a class="pv-btn pv-btn-primary pv-btn-small" href="{{ config('prontovia.android_url') }}"><i class="bi bi-google-play" aria-hidden="true"></i> Baixar app</a>
                        @else
                            <a class="pv-btn pv-btn-primary pv-btn-small" href="{{ route('website.candidates') }}">Conhecer a aplicação</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="conteudo">
    @yield('content')
</main>

<footer class="pv-footer">
    <div class="container">
        <div class="row gy-5">
            <div class="col-lg-4">
                <a class="pv-brand pv-brand-footer" href="{{ route('website.home') }}"><span class="pv-brand-mark" aria-hidden="true"><span></span></span><span>Pronto<span>Via</span></span></a>
                <p class="pv-footer-tagline">Aprenda. Pratique. Esteja pronto.</p>
                <p class="pv-footer-about">Uma plataforma educativa para acompanhar cada etapa da sua preparação para conduzir.</p>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h2>Produto</h2>
                <a href="{{ route('website.home') }}#como-funciona">Como funciona</a>
                <a href="{{ route('website.home') }}#funcionalidades">Funcionalidades</a>
                <a href="{{ route('website.candidates') }}">Para candidatos</a>
                <a href="{{ route('website.schools') }}">ProntoVia Escolas</a>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h2>Aprender</h2>
                <a href="{{ route('website.candidates') }}#recursos">Sinais</a>
                <a href="{{ route('website.candidates') }}#recursos">Código da Estrada</a>
                <a href="{{ route('website.candidates') }}#experiencia">Conteúdos</a>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h2>Suporte</h2>
                <a href="{{ route('website.home') }}#faq">FAQ</a>
                @if(config('prontovia.support_email'))
                    <a href="mailto:{{ config('prontovia.support_email') }}">Contacto</a>
                @else
                    <span>Contacto</span>
                @endif
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <h2>Legal</h2>
                <span>Termos</span>
                <span>Privacidade</span>
            </div>
        </div>
        <div class="pv-footer-bottom">
            <p>© {{ date('Y') }} ProntoVia.</p>
            <p>O ProntoVia é uma plataforma educativa independente e não representa nem substitui entidades reguladoras ou processos oficiais de exame.</p>
        </div>
    </div>
</footer>
</body>
</html>
