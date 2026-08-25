<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Exam;
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
            ->assertSee('question-bank-filters', false)
            ->assertSee('data-surface', false)
            ->assertSee('aria-label="Pesquisar e filtrar perguntas"', false)
            ->assertSee('data-table-density="comfortable"', false)
            ->assertSee('pv-table-pagination', false);

        $this->actingAs($admin)->get(route('admin.schools.index'))
            ->assertOk()
            ->assertSee('class="page-header"', false)
            ->assertSee('class="data-toolbar"', false)
            ->assertSee('data-surface', false)
            ->assertSee('data-dialog-open="create-school"', false)
            ->assertSee('id="create-school-form"', false)
            ->assertDontSee('onsubmit="return confirm', false);
    }

    public function test_school_and_user_creation_use_reusable_dialogs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('data-dialog-open="create-user"', false)
            ->assertSee('id="create-user-form"', false)
            ->assertSee('users-table', false);
    }

    public function test_payments_page_uses_access_summary_search_and_registration_dialog(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.unlocks.index'))
            ->assertOk()
            ->assertSee('id="payments-page-title"', false)
            ->assertSee('payments-summary', false)
            ->assertSee('payments-filters', false)
            ->assertSee('data-dialog-open="create-unlock"', false)
            ->assertSee('payments-table', false)
            ->assertSee('Nenhum pagamento registado');
    }

    public function test_publications_page_separates_readiness_history_and_publish_dialog(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.publications.index'))
            ->assertOk()
            ->assertSee('id="publications-page-title"', false)
            ->assertSee('publication-readiness', false)
            ->assertSee('publications-table', false)
            ->assertSee('data-dialog-open="create-publication"', false)
            ->assertSee('Nenhum pacote publicado');
    }

    public function test_taxonomy_pages_use_consistent_tables_and_creation_dialogs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.topics.index'))
            ->assertOk()->assertSee('id="topics-page-title"', false)->assertSee('data-dialog-open="create-topic"', false)->assertSee('topics-table', false);
        $this->actingAs($admin)->get(route('admin.sign-categories.index'))
            ->assertOk()->assertSee('id="sign-categories-title"', false)->assertSee('data-dialog-open="create-sign-category"', false)->assertSee('sign-categories-table', false);
        $this->actingAs($admin)->get(route('admin.categories.index'))
            ->assertOk()->assertSee('id="license-categories-title"', false)->assertSee('data-dialog-open="create-license-category"', false)->assertSee('license-categories-table', false);
    }

    public function test_admin_layout_exposes_reusable_system_message_region(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->withSession(['status' => 'Guardado com sucesso.'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('admin-messages', false)
            ->assertSee('data-message-toast', false)
            ->assertSee('Guardado com sucesso.')
            ->assertSee('data-message-dismiss', false);
    }

    public function test_shell_exposes_accessible_scalable_navigation_hooks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Saltar para o conteúdo')
            ->assertSee('class="pv-sidebar sidebar"', false)
            ->assertSee('data-sidebar-collapse', false)
            ->assertSee('class="bi bi-speedometer2"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-admin-context="platform"', false);
    }

    public function test_topbar_keeps_global_actions_compact_and_contextual(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.questions.index'))
            ->assertOk()
            ->assertSee('class="pv-topbar topbar"', false)
            ->assertSee('data-sidebar-collapse', false)
            ->assertSee('class="pv-topbar-search search"', false)
            ->assertSee('class="bi bi-search"', false)
            ->assertSee('pv-topbar-notifications', false)
            ->assertSee('class="pv-user-menu"', false)
            ->assertSee('Abrir menu do utilizador');
    }

    public function test_dashboard_prioritizes_four_kpis_attention_and_a_single_primary_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="pv-dashboard"', false)
            ->assertSee('class="dashboard-kpis"', false)
            ->assertSee('dashboard-kpi', false)
            ->assertSee('Crescimento e utilização')
            ->assertSee('Atenção necessária')
            ->assertSee('Atividade das escolas')
            ->assertSee('bi bi-person-plus', false);
    }

    public function test_mobile_users_page_uses_the_operational_table_pattern(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.mobile-users.index'))
            ->assertOk()
            ->assertSee('id="mobile-users-title"', false)
            ->assertSee('mobile-users-filters', false)
            ->assertSee('data-table-density="comfortable"', false)
            ->assertSee('Contas mobile')
            ->assertSee('Nenhum utilizador encontrado');
    }

    public function test_classrooms_page_uses_a_table_and_keeps_student_management_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertSee('id="classrooms-title"', false)
            ->assertSee('classrooms-table', false)
            ->assertSee('Nova turma')
            ->assertSee('Ainda não existem turmas');
    }

    public function test_exams_page_uses_compact_actions_and_operational_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.exams.index'))
            ->assertOk()
            ->assertSee('id="exam-list-title"', false)
            ->assertSee('exams-table', false)
            ->assertSee('Nova prova')
            ->assertSee('Ainda não existem provas');
    }

    public function test_exam_detail_uses_a_structured_summary_and_question_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $exam = Exam::create(['created_by' => $admin->id, 'name' => 'Prova de detalhe', 'type' => 'teorico', 'selection_mode' => 'manual', 'license_category' => 'ligeiro', 'license_categories' => ['ligeiro'], 'question_count' => 2, 'pass_score' => 2, 'duration_minutes' => 30, 'is_active' => true, 'is_public' => false, 'is_locked' => false, 'publication_status' => 'draft']);

        $this->actingAs($admin)->get(route('admin.exams.show', $exam))
            ->assertOk()
            ->assertSee('id="exam-detail-title"', false)
            ->assertSee('class="exam-summary"', false)
            ->assertSee('Configuração')
            ->assertSee('Perguntas selecionadas');
    }

    public function test_exam_edit_page_exposes_structured_form_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $exam = Exam::create(['created_by' => $admin->id, 'name' => 'Prova para editar', 'type' => 'teorico', 'selection_mode' => 'manual', 'license_category' => 'ligeiro', 'license_categories' => ['ligeiro'], 'question_count' => 2, 'pass_score' => 2, 'duration_minutes' => 30, 'is_active' => true, 'is_public' => false, 'is_locked' => false, 'publication_status' => 'draft']);

        $this->actingAs($admin)->get(route('admin.exams.edit', $exam))
            ->assertOk()
            ->assertSee('id="exam-edit-title"', false)
            ->assertSee('class="exam-form-section"', false)
            ->assertSee('Informação da prova')
            ->assertSee('Seleção de perguntas')
            ->assertSee('Guardar alterações');
    }

    public function test_sessions_page_uses_operational_table_and_session_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('id="session-list-title"', false)
            ->assertSee('sessions-table', false)
            ->assertSee('Nova sessão')
            ->assertSee('Nenhuma sessão criada');
    }

    public function test_results_page_uses_summary_filters_and_a_readable_results_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.results.index'))
            ->assertOk()
            ->assertSee('id="results-page-title"', false)
            ->assertSee('results-kpis', false)
            ->assertSee('results-filters', false)
            ->assertSee('results-table', false)
            ->assertSee('Ainda não existem resultados');
    }

    public function test_approvals_page_uses_review_queue_tabs_and_clear_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.approvals.index'))
            ->assertOk()
            ->assertSee('id="approval-page-title"', false)
            ->assertSee('approval-tabs', false)
            ->assertSee('approval-filters', false)
            ->assertSee('Não existem perguntas neste estado');
    }

    public function test_sign_library_uses_search_filters_and_a_compact_visual_grid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.signs.index'))
            ->assertOk()
            ->assertSee('id="sign-library-title"', false)
            ->assertSee('sign-library-filters', false)
            ->assertSee('sign-library-grid', false)
            ->assertSee('Ainda não existem sinais cadastrados');
    }

    public function test_study_sheets_page_uses_area_summary_filters_and_operational_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.lessons.index'))
            ->assertOk()
            ->assertSee('id="study-sheets-title"', false)
            ->assertSee('study-group-summary', false)
            ->assertSee('study-sheets-filters', false)
            ->assertSee('study-sheets-table', false)
            ->assertSee('Ainda não existem fichas de estudo');
    }

    public function test_legal_library_uses_search_filters_and_a_readable_article_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSee('id="legal-library-title"', false)
            ->assertSee('legal-library-filters', false)
            ->assertSee('legal-library-table', false)
            ->assertSee('Ainda não existem artigos');
    }

    public function test_glossary_page_uses_compact_creation_search_and_term_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.glossary.index'))
            ->assertOk()
            ->assertSee('id="glossary-page-title"', false)
            ->assertSee('glossary-create-form', false)
            ->assertSee('glossary-filters', false)
            ->assertSee('glossary-table', false)
            ->assertSee('Glossário vazio');
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
        $this->assertStringContainsString('--sidebar-width:244px', $css);
        $this->assertStringContainsString('.pv-sidebar-section a.is-active:before', $css);
        $this->assertStringContainsString('.pv-topbar.topbar', $css);
        $this->assertStringContainsString('min-height:64px', $css);
        $this->assertStringContainsString('.pv-user-popover', $css);
        $this->assertStringContainsString('.dashboard-kpis', $css);
        $this->assertStringContainsString('grid-template-columns:repeat(4,minmax(0,1fr))', $css);
        $this->assertStringContainsString('.dashboard-action-strip', $css);
        $this->assertStringContainsString('.data-table--compact', $css);
        $this->assertStringContainsString('.data-table--hover tbody tr:hover', $css);
        $this->assertStringContainsString('.pv-row-menu', $css);
        $this->assertStringContainsString('.pv-table-pagination', $css);
    }
}
