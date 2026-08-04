<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\GlossaryTerm;
use App\Models\Lesson;
use App\Models\School;
use App\Models\Sign;
use App\Models\SignCategory;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Painel do material de estudo.
 *
 * As fichas e o glossário eram os únicos recursos sem página de detalhe: o URL
 * `admin/lessons/{id}` — a forma usada por todos os outros — respondia 405, e as
 * escolas viam a lista de fichas sem nunca poder ler o corpo de nenhuma.
 */
class StudyPanelTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(): Lesson
    {
        $topic = Topic::create(['name' => 'Sinalização', 'slug' => 'sinalizacao', 'is_active' => true]);

        Sign::create([
            'name' => 'Curva à direita',
            'sign_category_id' => SignCategory::where('slug', 'perigo')->value('id'),
            'meaning' => 'Curva perigosa à direita',
            'file_path' => 'images/signs/curva-direita.svg',
            'is_active' => true,
        ]);

        Article::create(['number' => 12, 'title' => 'Cedência de passagem', 'text' => 'Texto do artigo 12.']);

        return Lesson::create([
            'topic_id' => $topic->id,
            'slug' => 'ler-os-sinais-pela-forma',
            'title' => 'Ler os sinais pela forma',
            'summary' => 'A forma e a cor dizem o tipo de sinal.',
            'body' => "Triângulo com bordo vermelho avisa de perigo.\n\n- Círculo vermelho proíbe",
            'group' => 'sinalizacao',
            'sign_slugs' => ['curva-a-direita'],
            'article_numbers' => [12],
            'reading_minutes' => 4,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_open_the_lesson_detail_page(): void
    {
        $lesson = $this->lesson();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.lessons.show', $lesson))
            ->assertOk()
            ->assertSee('Ler os sinais pela forma')
            // O corpo da ficha e as ligações resolvidas por nome, não por slug cru.
            ->assertSee('Triângulo com bordo vermelho avisa de perigo.')
            ->assertSee('Curva à direita (curva-a-direita)')
            ->assertSee('Artigo 12 — Cedência de passagem')
            ->assertSee('Editar');
    }

    public function test_school_can_read_a_lesson_but_gets_no_edit_link(): void
    {
        $lesson = $this->lesson();
        $school = School::create(['name' => 'Escola Teste', 'code' => 'escola-teste', 'is_active' => true]);
        $escola = User::factory()->create(['role' => 'school', 'school_id' => $school->id]);

        $resposta = $this->actingAs($escola)->get(route('admin.lessons.show', $lesson))->assertOk();

        $resposta->assertSee('Triângulo com bordo vermelho avisa de perigo.');
        $resposta->assertDontSee(route('admin.lessons.edit', $lesson));
    }

    public function test_school_cannot_change_study_material(): void
    {
        $lesson = $this->lesson();
        $school = School::create(['name' => 'Escola Teste', 'code' => 'escola-2', 'is_active' => true]);
        $escola = User::factory()->create(['role' => 'school', 'school_id' => $school->id]);

        $this->actingAs($escola)->get(route('admin.lessons.edit', $lesson))->assertForbidden();
        $this->actingAs($escola)->delete(route('admin.lessons.destroy', $lesson))->assertForbidden();
    }

    public function test_glossary_term_has_a_detail_page(): void
    {
        $term = GlossaryTerm::create([
            'term' => 'Cedência de passagem',
            'slug' => 'cedencia-de-passagem',
            'definition' => 'Deixar passar outro veículo.',
            'article_ref' => 12,
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.glossary.show', $term))
            ->assertOk()
            ->assertSee('Deixar passar outro veículo.')
            ->assertSee('Artigo 12');
    }

    public function test_lesson_editing_round_trip_works(): void
    {
        $lesson = $this->lesson();
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.lessons.edit', $lesson))->assertOk();

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), [
            'title' => 'Ler os sinais pela forma e pela cor',
            'body' => 'Corpo atualizado.',
            'group' => 'sinalizacao',
            'reading_minutes' => 5,
            'is_active' => '1',
        ])->assertRedirect(route('admin.lessons.index'));

        $this->assertSame('Ler os sinais pela forma e pela cor', $lesson->fresh()->title);
    }
}
