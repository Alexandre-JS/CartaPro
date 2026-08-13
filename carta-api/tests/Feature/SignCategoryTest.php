<?php

namespace Tests\Feature;

use App\Models\Sign;
use App\Models\SignCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SignCategoryTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $ficheirosAntesDoTeste = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ficheirosAntesDoTeste = glob(public_path('images/signs/*')) ?: [];
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function svg(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'sinal.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>',
        );
    }

    protected function tearDown(): void
    {
        $criadosPeloTeste = array_diff(glob(public_path('images/signs/*')) ?: [], $this->ficheirosAntesDoTeste);
        foreach ($criadosPeloTeste as $ficheiro) {
            @unlink($ficheiro);
        }

        parent::tearDown();
    }

    /**
     * A migração tem de trazer as categorias que viviam em configuração — sem
     * isto, uma instalação existente ficava sem catálogo depois de migrar.
     */
    public function test_the_categories_that_lived_in_configuration_survive_the_move(): void
    {
        $this->assertSame(9, SignCategory::raiz()->count());
        $this->assertNotNull(SignCategory::where('slug', 'perigo')->first());
    }

    public function test_an_admin_can_create_a_category(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.sign-categories.store'), [
                'name' => 'Sinalização temporária',
                'description' => 'Obras e desvios.',
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.sign-categories.index'));

        $criada = SignCategory::where('name', 'Sinalização temporária')->firstOrFail();

        // O identificador é derivado do nome, como nos sinais.
        $this->assertSame('sinalizacao-temporaria', $criada->slug);
        $this->assertNull($criada->parent_id);
    }

    public function test_a_category_with_a_parent_is_a_subcategory(): void
    {
        $pai = SignCategory::where('slug', 'proibicao')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.sign-categories.store'), [
            'name' => 'Limites de velocidade',
            'parent_id' => $pai->id,
            'is_active' => 1,
        ]);

        $sub = SignCategory::where('slug', 'limites-de-velocidade')->firstOrFail();

        $this->assertTrue($sub->isSubcategoria());
        $this->assertTrue($pai->children->contains($sub));
    }

    /**
     * A hierarquia pára no segundo nível. Um terceiro traria árvores que o
     * formulário de sinais não sabe mostrar.
     */
    public function test_a_subcategory_cannot_have_subcategories(): void
    {
        $pai = SignCategory::where('slug', 'proibicao')->firstOrFail();
        $sub = SignCategory::create(['name' => 'Limites', 'parent_id' => $pai->id, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->post(route('admin.sign-categories.store'), ['name' => 'Neto', 'parent_id' => $sub->id])
            ->assertSessionHasErrors('parent_id');
    }

    /**
     * Apagar uma categoria com sinais deixaria o catálogo incoerente. A recusa
     * é explicada, em vez de aparecer como erro de base de dados.
     */
    public function test_a_category_holding_signs_is_not_deleted(): void
    {
        $categoria = SignCategory::where('slug', 'perigo')->firstOrFail();

        Sign::create([
            'name' => 'Curva',
            'sign_category_id' => $categoria->id,
            'meaning' => 'Curva perigosa.',
            'file_path' => '/images/signs/curva.svg',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.sign-categories.destroy', $categoria))
            ->assertSessionHasErrors('categoria');

        $this->assertDatabaseHas('sign_categories', ['id' => $categoria->id]);
    }

    public function test_an_empty_category_is_deleted(): void
    {
        $categoria = SignCategory::create(['name' => 'Provisória', 'is_active' => true]);

        $this->actingAs($this->admin())->delete(route('admin.sign-categories.destroy', $categoria));

        $this->assertDatabaseMissing('sign_categories', ['id' => $categoria->id]);
    }

    // ---- O sinal: categoria obrigatória, subcategoria opcional ----

    public function test_a_sign_cannot_be_created_without_a_category(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.signs.store'), [
                'name' => 'Sem categoria',
                'meaning' => 'Qualquer coisa.',
                'svg' => $this->svg(),
            ])
            ->assertSessionHasErrors('sign_category_id');

        $this->assertDatabaseCount('signs', 0);
    }

    public function test_a_sign_is_created_without_a_subcategory(): void
    {
        $categoria = SignCategory::where('slug', 'perigo')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.signs.store'), [
                'name' => 'Curva à direita',
                'sign_category_id' => $categoria->id,
                'meaning' => 'Curva perigosa.',
                'svg' => $this->svg(),
            ])
            ->assertRedirect(route('admin.signs.index'));

        $sinal = Sign::firstOrFail();

        $this->assertSame($categoria->id, $sinal->sign_category_id);
        $this->assertNull($sinal->sign_subcategory_id);
    }

    public function test_a_sign_can_carry_a_subcategory_of_its_category(): void
    {
        $categoria = SignCategory::where('slug', 'proibicao')->firstOrFail();
        $sub = SignCategory::create(['name' => 'Limites', 'parent_id' => $categoria->id, 'is_active' => true]);

        $this->actingAs($this->admin())->post(route('admin.signs.store'), [
            'name' => 'Velocidade máxima 50',
            'sign_category_id' => $categoria->id,
            'sign_subcategory_id' => $sub->id,
            'meaning' => 'Não excedas 50 km/h.',
            'svg' => $this->svg(),
        ]);

        $this->assertSame($sub->id, Sign::firstOrFail()->sign_subcategory_id);
    }

    /**
     * Um pedido forjado — ou o formulário deixado a meio depois de trocar a
     * categoria — gravaria uma combinação que não existe no catálogo.
     */
    public function test_a_subcategory_from_another_category_is_refused(): void
    {
        $perigo = SignCategory::where('slug', 'perigo')->firstOrFail();
        $proibicao = SignCategory::where('slug', 'proibicao')->firstOrFail();
        $sub = SignCategory::create(['name' => 'Limites', 'parent_id' => $proibicao->id, 'is_active' => true]);

        $this->actingAs($this->admin())
            ->post(route('admin.signs.store'), [
                'name' => 'Errado',
                'sign_category_id' => $perigo->id,
                'sign_subcategory_id' => $sub->id,
                'meaning' => 'Combinação impossível.',
                'svg' => $this->svg(),
            ])
            ->assertSessionHasErrors('sign_subcategory_id');

        $this->assertDatabaseCount('signs', 0);
    }

    /** Uma categoria de topo não pode ser escolhida como subcategoria. */
    public function test_a_top_level_category_cannot_be_used_as_a_subcategory(): void
    {
        $perigo = SignCategory::where('slug', 'perigo')->firstOrFail();
        $proibicao = SignCategory::where('slug', 'proibicao')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.signs.store'), [
                'name' => 'Errado',
                'sign_category_id' => $perigo->id,
                'sign_subcategory_id' => $proibicao->id,
                'meaning' => 'Combinação impossível.',
                'svg' => $this->svg(),
            ])
            ->assertSessionHasErrors('sign_subcategory_id');
    }

    /**
     * O app lê `categoria` como slug. Mudar a fonte de configuração para
     * tabela não pode alterar o contrato — um app já instalado continuaria a
     * pedir o mesmo formato.
     */
    public function test_the_offline_package_keeps_the_slug_contract(): void
    {
        $categoria = SignCategory::where('slug', 'perigo')->firstOrFail();
        $sub = SignCategory::create(['name' => 'Curvas', 'parent_id' => $categoria->id, 'is_active' => true]);

        $sinal = Sign::create([
            'name' => 'Curva à direita',
            'sign_category_id' => $categoria->id,
            'sign_subcategory_id' => $sub->id,
            'meaning' => 'Curva perigosa.',
            'file_path' => '/images/signs/curva.svg',
            'is_active' => true,
        ]);

        $pacote = $sinal->fresh()->toPackageArray();

        $this->assertSame('perigo', $pacote['categoria']);
        $this->assertSame('curvas', $pacote['subcategoria']);
    }
}
