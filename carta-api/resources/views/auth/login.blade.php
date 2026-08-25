<!doctype html>
<html lang="pt-MZ">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#1A1F5C">
    <title>Entrar no ProntoVia</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/prontovia/iconProntovia.webp') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/prontovia/iconProntovia.png') }}">
    @vite(['resources/css/website.css'])
</head>
<body class="pv-auth-page" style="--pv-auth-background: url('{{ asset(config('prontovia.images.home_hero') ?: 'images/prontovia/pessoa-que.avif') }}')">
    <a class="pv-skip-link" href="#login-form">Saltar para o formulário</a>
    <main class="pv-auth-shell">
        <section class="pv-auth-story" aria-labelledby="auth-story-title">
            <a href="{{ route('website.home') }}" aria-label="ProntoVia — voltar ao início"><img src="{{ asset('images/prontovia/Prontovia-white.png') }}" width="1640" height="664" alt="ProntoVia"></a>
            <div>
                <span class="pv-auth-eyebrow"><i class="bi bi-shield-check" aria-hidden="true"></i> Área segura</span>
                <h1 id="auth-story-title">Acompanhe cada percurso com mais clareza.</h1>
                <p>Entre para gerir conteúdos, turmas, testes e resultados no ambiente ProntoVia.</p>
            </div>
            <small>Aprenda. Pratique. Esteja pronto.</small>
        </section>
        <section class="pv-auth-form-panel" id="login-form" aria-labelledby="login-title">
            <div class="pv-auth-form-inner">
                <a class="pv-auth-mobile-logo" href="{{ route('website.home') }}"><img src="{{ asset('images/prontovia/prontovia.png') }}" width="710" height="141" alt="ProntoVia"></a>
                <span class="pv-kicker">Bem-vindo de volta</span>
                <h2 id="login-title">Entrar na sua conta</h2>
                <p class="pv-auth-intro">Utilize os dados associados ao seu acesso administrativo ou escolar.</p>
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="pv-auth-field">
                        <label for="email">Endereço de email</label>
                        <div class="pv-auth-control"><i class="bi bi-envelope" aria-hidden="true"></i><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" required autofocus @error('email') aria-invalid="true" aria-describedby="email-error" @enderror></div>
                        @error('email')<span class="pv-auth-error" id="email-error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i>{{ $message }}</span>@enderror
                    </div>
                    <div class="pv-auth-field">
                        <label for="password">Palavra-passe</label>
                        <div class="pv-auth-control"><i class="bi bi-lock" aria-hidden="true"></i><input id="password" type="password" name="password" autocomplete="current-password" required></div>
                    </div>
                    <label class="pv-auth-remember"><input type="checkbox" name="remember" value="1"><span>Manter sessão iniciada neste dispositivo</span></label>
                    <button class="pv-btn pv-btn-primary pv-auth-submit" type="submit">Entrar <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
                </form>
                <div class="pv-auth-help"><i class="bi bi-info-circle" aria-hidden="true"></i><p><strong>É candidato?</strong> A preparação individual acontece na aplicação ProntoVia.</p></div>
                <a class="pv-auth-back" href="{{ route('website.home') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar ao website</a>
            </div>
        </section>
    </main>
</body>
</html>
