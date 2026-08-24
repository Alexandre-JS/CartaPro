<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_login_uses_the_prontovia_identity_without_changing_the_authentication_form(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Entrar na sua conta')
            ->assertSee('Acompanhe cada percurso com mais clareza.')
            ->assertSee('images/prontovia/pessoa-que.avif', false)
            ->assertSee('images/prontovia/Prontovia-white.png', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee(route('login.store'), false)
            ->assertSee('noindex,nofollow', false);
    }

    public function test_authenticated_user_can_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Perguntas aprovadas')
            ->assertSee('ProntoVia')
            ->assertSee('images/prontovia/Prontovia-white.png', false)
            ->assertSee('content="#111544"', false)
            ->assertSee('data-admin-loading-overlay', false)
            ->assertSee('data-admin-loading-bar', false)
            ->assertSee('id="admin-content"', false);
    }

    public function test_admin_can_open_read_only_detail_page(): void
    {
        $admin = User::factory()->create();
        $topic = Topic::create(['name' => 'Prioridade', 'slug' => 'prioridade', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.topics.show', $topic))
            ->assertOk()
            ->assertSee('Detalhes do tema')
            ->assertSee('Prioridade')
            ->assertSee('Voltar')
            ->assertSee('Editar');
    }
}
