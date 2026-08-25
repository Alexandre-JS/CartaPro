<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WebsiteTest extends TestCase
{
    public function test_public_website_routes_are_named_and_do_not_require_authentication(): void
    {
        $routes = [
            'website.home' => ['/', 'Prepare-se para conduzir com confiança.'],
            'website.candidates' => ['/candidatos', 'A sua preparação, ao seu ritmo.'],
            'website.schools' => ['/escolas', 'Prepare melhor os alunos. Fortaleça a sua escola.'],
        ];

        foreach ($routes as $name => [$uri, $copy]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertSame(['web'], $route->gatherMiddleware());
            $this->assertNotContains('auth', $route->gatherMiddleware());
            $this->get($uri)
                ->assertOk()
                ->assertSee($copy)
                ->assertSee('Aprenda. Pratique. Esteja pronto.')
                ->assertSee('data-pv-loader', false)
                ->assertSee(route('login'));
        }
    }

    public function test_website_navigation_links_the_three_public_pages(): void
    {
        $this->get('/')
            ->assertSee(route('website.home'))
            ->assertSee(route('website.candidates'))
            ->assertSee(route('website.schools'));
    }

    public function test_unconfigured_product_urls_are_not_rendered_as_empty_links(): void
    {
        config()->set('prontovia.android_url');
        config()->set('prontovia.web_app_url');

        $this->get('/candidatos')
            ->assertOk()
            ->assertDontSee('href=""', false);
    }

    public function test_configured_hero_image_is_rendered_as_a_background_asset(): void
    {
        config()->set('prontovia.images.home_hero', 'images/prontovia/home-hero.webp');

        $this->get('/')
            ->assertOk()
            ->assertSee('pv-has-background-image', false)
            ->assertSee(asset('images/prontovia/home-hero.webp'), false);
    }

    public function test_school_value_section_uses_the_selected_background_image(): void
    {
        config()->set('prontovia.images.schools_section', 'images/prontovia/backgrouseccao.png');

        $this->get('/')
            ->assertOk()
            ->assertSee('Melhores decisões para os alunos. Mais valor para a escola.')
            ->assertSee(asset('images/prontovia/backgrouseccao.png'), false);
    }

    public function test_default_home_hero_uses_the_person_background_image(): void
    {
        config()->set('prontovia.images.home_hero', 'images/prontovia/pessoa-que.avif');

        $this->get('/')
            ->assertOk()
            ->assertSee('pv-has-background-image', false)
            ->assertSee(asset('images/prontovia/pessoa-que.avif'), false);
    }

    public function test_audience_pages_use_only_their_featured_hero_backgrounds(): void
    {
        config()->set('prontovia.images.candidate_hero', 'images/prontovia/pessoa-que.avif');
        config()->set('prontovia.images.school_hero', 'images/prontovia/backgrouseccao.png');

        $this->get('/candidatos')
            ->assertOk()
            ->assertSee('pv-candidate-hero', false)
            ->assertSee(asset('images/prontovia/pessoa-que.avif'), false);

        $this->get('/escolas')
            ->assertOk()
            ->assertSee('pv-school-hero', false)
            ->assertSee(asset('images/prontovia/backgrouseccao.png'), false);
    }

    public function test_configured_contact_details_and_android_download_are_rendered(): void
    {
        config()->set('prontovia.support_email', 'equipa@example.test');
        config()->set('prontovia.contact_phone', '+258 84 000 0000');
        config()->set('prontovia.business_hours', 'Segunda a sexta');
        config()->set('prontovia.android_url', 'https://play.google.com/store/apps/details?id=test');

        $this->get('/')
            ->assertOk()
            ->assertSee('equipa@example.test')
            ->assertSee('+258 84 000 0000')
            ->assertSee('Segunda a sexta')
            ->assertSee('Baixar app')
            ->assertSee('Google Play');
    }

    public function test_public_pages_expose_search_and_social_metadata(): void
    {
        foreach (['website.home', 'website.candidates', 'website.schools'] as $routeName) {
            $response = $this->get(route($routeName));

            $response->assertOk()
                ->assertSee('<link rel="canonical"', false)
                ->assertSee('<meta name="description"', false)
                ->assertSee('<meta property="og:title"', false)
                ->assertSee('<script type="application/ld+json">', false)
                ->assertSee('"@type":"WebPage"', false)
                ->assertSee(asset('images/prontovia/prontovia.png'), false);
        }
    }

    public function test_sitemap_and_robots_list_the_public_website(): void
    {
        $this->get(route('website.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('website.home'))
            ->assertSee(route('website.candidates'))
            ->assertSee(route('website.schools'));

        $this->get(route('website.robots'))
            ->assertOk()
            ->assertSee('Disallow: /admin/')
            ->assertSee(route('website.sitemap'));
    }

    public function test_homepage_has_a_clear_story_and_avoids_the_repeated_feature_catalogue(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('É saber o que fazer a seguir.')
            ->assertSee('Ver como funciona')
            ->assertSee('Qual é o próximo passo?')
            ->assertDontSee('Tudo num só percurso');
    }

    public function test_customer_and_school_pages_present_distinct_conversion_goals(): void
    {
        config()->set('prontovia.android_url', 'https://play.google.com/store/apps/details?id=prontovia');

        $this->get('/')
            ->assertOk()
            ->assertSee('Baixar a aplicação')
            ->assertSee('https://play.google.com/store/apps/details?id=prontovia');

        $this->get('/escolas')
            ->assertOk()
            ->assertSee('Acompanhar o progresso também é uma forma de crescer.')
            ->assertSee('Demonstre acompanhamento')
            ->assertSee('Diferencie a escola')
            ->assertSee('não promete matrículas');
    }

    public function test_privacy_controls_and_local_assistant_are_available_on_public_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-pv-cookie-banner', false)
            ->assertSee('Apenas necessários')
            ->assertSee('Aceitar todos')
            ->assertSee('Definições de cookies')
            ->assertSee('data-pv-assistant', false)
            ->assertSee('Este assistente apresenta informação do site e não recolhe a conversa.');
    }

    public function test_configured_school_partner_is_identified_as_sponsored(): void
    {
        config()->set('prontovia.partners', [[
            'name' => 'Escola Exemplo',
            'url' => 'https://escola.example.test',
            'logo' => null,
            'location' => 'Maputo',
        ]]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Escola Exemplo')
            ->assertSee('Maputo')
            ->assertSee('Conteúdo publicitário')
            ->assertSee('rel="sponsored noopener noreferrer"', false);
    }

    public function test_logo_variants_are_used_on_their_intended_backgrounds(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('images/prontovia/prontovia.png', false)
            ->assertSee('images/prontovia/Prontovia-white.png', false)
            ->assertSee('pv-brand-logo-dark', false);
    }

    public function test_prontovia_icon_is_exposed_as_webp_favicon_with_png_fallback(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('type="image/webp"', false)
            ->assertSee('images/prontovia/iconProntovia.webp', false)
            ->assertSee('images/prontovia/iconProntovia.png', false)
            ->assertSee('rel="apple-touch-icon"', false);
    }

    public function test_experimental_school_dashboard_image_is_rendered_responsively(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('images/prontovia/dashboard-grafic.png', false)
            ->assertSee('pv-school-dashboard-image', false)
            ->assertSee('Imagem experimental para avaliação');
    }

    public function test_readiness_section_uses_the_exam_photo_as_a_protected_background(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('pv-readiness-photo', false)
            ->assertSee('images/prontovia/EXAMES.jpg', false);
    }

    public function test_readiness_dashboard_exposes_progress_topics_and_learning_path(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('pv-readiness-dashboard', false)
            ->assertSee('Prontidão: 78 por cento')
            ->assertSee('Evolução do desempenho')
            ->assertSee('Sinais')
            ->assertSee('Prioridade')
            ->assertSee('Simulados')
            ->assertSee('Em andamento');
    }
}
