<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDesignSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_heavy_pages_share_headers_toolbars_tables_and_dialogs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.questions.index'))
            ->assertOk()
            ->assertSee('class="page-header"', false)
            ->assertSee('class="data-toolbar question-bank-filters"', false)
            ->assertSee('data-surface', false)
            ->assertSee('aria-label="Pesquisar e filtrar perguntas"', false);

        $this->actingAs($admin)->get(route('admin.schools.index'))
            ->assertOk()
            ->assertSee('class="page-header"', false)
            ->assertSee('class="data-toolbar"', false)
            ->assertSee('data-surface', false)
            ->assertDontSee('onsubmit="return confirm', false);
    }

    public function test_shell_exposes_accessible_scalable_navigation_hooks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Saltar para o conteúdo')
            ->assertSee('enhanceAdminNavigation', false)
            ->assertSee('aria-current', false)
            ->assertSee('data-admin-context="platform"', false);
    }

    public function test_design_tokens_keep_brand_color_separate_from_semantic_states(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertStringContainsString('--pv-indigo:#1a1f5c', $css);
        $this->assertStringContainsString('--pv-cyan:#00b8f0', $css);
        $this->assertStringContainsString('--pv-orange:#ff8a00', $css);
        $this->assertStringContainsString('--pv-success:#18723a', $css);
        $this->assertStringContainsString('.data-surface', $css);
        $this->assertStringContainsString('.page-header', $css);
    }
}
