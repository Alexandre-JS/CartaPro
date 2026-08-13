<?php

namespace Tests\Feature;

use App\Models\Sign;
use App\Models\SignCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SignLibraryTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $ficheirosAntesDoTeste = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ficheirosAntesDoTeste = glob(public_path('images/signs/*')) ?: [];
    }

    /** Um SVG mínimo e legítimo. */
    private function svg(string $nome = 'sinal.svg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $nome,
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>',
        );
    }

    private function categoria(): SignCategory
    {
        return SignCategory::raiz()->ordenadas()->firstOrFail();
    }

    private function campos(array $extra = []): array
    {
        return array_merge([
            'name' => 'Curva à direita',
            'sign_category_id' => $this->categoria()->id,
            'meaning' => 'Curva perigosa para a direita.',
        ], $extra);
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
     * O identificador é trabalho de máquina. Quem cataloga sinais não deve ter
     * de inventar códigos únicos à mão.
     */
    public function test_identifier_is_derived_from_the_name_when_left_blank(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.signs.store'), $this->campos(['svg' => $this->svg()]))
            ->assertRedirect(route('admin.signs.index'));

        $this->assertSame('curva-a-direita', Sign::firstOrFail()->slug);
    }

    /**
     * Dois sinais com o mesmo nome são plausíveis — variantes da mesma placa.
     * Rebentar com violação de unicidade seria castigar quem não podia saber.
     */
    public function test_repeated_names_get_distinct_identifiers(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.signs.store'), $this->campos(['svg' => $this->svg()]));
        $this->actingAs($admin)->post(route('admin.signs.store'), $this->campos(['svg' => $this->svg('outro.svg')]));

        $this->assertSame(['curva-a-direita', 'curva-a-direita-2'], Sign::orderBy('id')->pluck('slug')->all());
    }

    /** Escrito à mão, o identificador é respeitado. */
    public function test_a_written_identifier_is_kept(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.signs.store'), $this->campos(['slug' => 'b-2a', 'svg' => $this->svg()]));

        $this->assertSame('b-2a', Sign::firstOrFail()->slug);
    }

    /**
     * O ficheiro tem de ficar onde o servidor o serve. Gravá-lo noutro sítio
     * dava um sinal criado com sucesso e uma imagem em 404 — que foi
     * exactamente o que aconteceu em produção.
     */
    public function test_uploaded_file_lands_where_it_is_served_from(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.signs.store'), $this->campos(['svg' => $this->svg()]));

        $caminho = Sign::firstOrFail()->file_path;

        $this->assertStringStartsWith('/images/signs/', $caminho);
        $this->assertFileExists(public_path(ltrim($caminho, '/')));
    }

    /**
     * A guarda que faltava.
     *
     * O teste acima passa em qualquer cenário: em desenvolvimento a pasta
     * pública já é a certa por omissão, portanto ele confirmava algo que era
     * verdade de qualquer maneira. Em produção a aplicação vive numa subpasta
     * de `public_html/` e o `public_path()` do Laravel apontava para dentro da
     * pasta bloqueada — as imagens gravavam onde a web não chega.
     *
     * Quem corrige isso é o `public/index.php`, deduzindo a pasta do seu
     * próprio `__DIR__`. Não dá para exercitar num teste HTTP, porque a suite
     * arranca a aplicação sem passar por esse ficheiro; o que dá para garantir
     * é que a linha continua lá — apagá-la traz o 404 de volta em silêncio.
     */
    public function test_the_entry_point_declares_its_own_directory_as_the_public_path(): void
    {
        $entrada = file_get_contents(base_path('public/index.php'));

        $this->assertStringContainsString(
            '$app->usePublicPath(__DIR__);',
            $entrada,
            'O public/index.php deixou de fixar a pasta pública: em produção as imagens carregadas voltam a dar 404.',
        );
    }

    /** Nem toda a gente tem o sinal em SVG; um PNG resolve. */
    public function test_raster_images_are_accepted_and_keep_their_format(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.signs.store'), $this->campos(['svg' => UploadedFile::fake()->image('sinal.png', 64, 64)]))
            ->assertRedirect(route('admin.signs.index'));

        $caminho = Sign::firstOrFail()->file_path;

        $this->assertStringEndsWith('.png', $caminho);
        $this->assertFileExists(public_path(ltrim($caminho, '/')));
    }

    /**
     * Um SVG é XML que o browser executa. Servido do nosso domínio, com a
     * sessão do administrador aberta, um `<script>` lá dentro é XSS.
     */
    public function test_svg_carrying_a_script_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.signs.store'), $this->campos([
                'svg' => UploadedFile::fake()->createWithContent(
                    'mau.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                ),
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('signs', 0);
    }

    /** Um PHP renomeado para .png não é uma imagem. */
    public function test_a_file_pretending_to_be_an_image_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.signs.store'), $this->campos([
                'svg' => UploadedFile::fake()->createWithContent('falso.png', '<?php echo "olá"; ?>'),
            ]));

        $this->assertDatabaseCount('signs', 0);
    }
}
