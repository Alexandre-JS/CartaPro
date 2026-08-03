<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\GlossaryTerm;
use App\Models\Lesson;
use App\Models\Sign;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Material de estudo entregue ao app: sinalização, fichas, Código e glossário.
 *
 * Antes o app só conhecia artigos, que buscava em runtime página a página, e a
 * biblioteca de sinais existia na API sem nunca chegar ao aluno.
 */
class StudyContentTest extends TestCase
{
    use RefreshDatabase;

    private function seedStudy(): Topic
    {
        $topic = Topic::create(['name' => 'Sinalização', 'slug' => 'sinalizacao', 'is_active' => true]);

        Sign::create([
            'name' => 'Curva à direita',
            'category' => 'perigo',
            'topic_id' => $topic->id,
            'meaning' => 'Curva perigosa à direita',
            'description' => 'Reduz a velocidade antes de entrar na curva.',
            'article_ref' => 12,
            'file_path' => 'images/signs/curva-direita.svg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Sign::create([
            'name' => 'Sinal reservado ao plano completo',
            'category' => 'proibicao',
            'meaning' => 'Significado reservado ao plano pago',
            'file_path' => 'images/signs/proibido.svg',
            'is_locked' => true,
            'is_active' => true,
        ]);

        Article::create([
            'number' => 12,
            'chapter_number' => 2,
            'chapter_title' => 'Da circulação',
            'title' => 'Cedência de passagem',
            'text' => 'Texto do artigo 12.',
            'sort_order' => 1,
        ]);

        // Artigo sem capítulo: tem de ir para o fim, não para o início.
        Article::create([
            'number' => 99,
            'title' => 'Disposições finais',
            'text' => 'Texto do artigo 99.',
        ]);

        Lesson::create([
            'topic_id' => $topic->id,
            'slug' => 'ler-os-sinais-pela-forma',
            'title' => 'Ler os sinais pela forma',
            'summary' => 'A forma e a cor dizem o tipo de sinal.',
            'body' => "Triângulo com bordo vermelho avisa de perigo.\n\n- Círculo vermelho proíbe\n- Círculo azul obriga",
            'group' => 'sinalizacao',
            'license_categories' => ['ligeiro'],
            'sign_slugs' => ['curva-a-direita'],
            'article_numbers' => [12],
            'reading_minutes' => 4,
            'is_active' => true,
        ]);

        Lesson::create([
            'slug' => 'ficha-do-plano-completo',
            'title' => 'Ficha do plano completo',
            'body' => 'Corpo reservado ao plano pago.',
            'group' => 'conducao',
            'reading_minutes' => 6,
            'is_locked' => true,
            'is_active' => true,
        ]);

        GlossaryTerm::create([
            'term' => 'Cedência de passagem',
            'slug' => 'cedencia-de-passagem',
            'definition' => 'Deixar passar outro veículo.',
            'article_ref' => 12,
            'is_active' => true,
        ]);

        return $topic;
    }

    public function test_package_carries_the_whole_study_material(): void
    {
        $this->seedStudy();
        [, $token] = $this->paidMobileUser();

        $response = $this->withToken($token)->getJson('/api/v1/content-package')->assertOk();

        // A taxonomia viaja no pacote: o app não tem listas de categorias.
        $this->assertNotEmpty($response->json('estudo.taxonomia.categoriasSinais'));
        $this->assertNotEmpty($response->json('estudo.taxonomia.gruposLicoes'));

        $this->assertSame('perigo', $response->json('estudo.sinais.0.categoria'));
        $this->assertSame('Curva perigosa à direita', $response->json('estudo.sinais.0.significado'));
        $this->assertSame(12, $response->json('estudo.sinais.0.artigoRef'));
        // A imagem chega como URL absoluto, pronta a usar no `src`.
        $this->assertStringStartsWith('http', (string) $response->json('estudo.sinais.0.imagem'));

        $this->assertSame('ler-os-sinais-pela-forma', $response->json('estudo.licoes.0.slug'));
        $this->assertSame('sinalizacao', $response->json('estudo.licoes.0.tema'));
        $this->assertSame([12], $response->json('estudo.licoes.0.artigos'));

        $this->assertSame('cedencia-de-passagem', $response->json('estudo.glossario.0.slug'));
        $this->assertSame(12, $response->json('estudo.glossario.0.artigoRef'));
    }

    public function test_articles_are_grouped_by_chapter_with_orphans_last(): void
    {
        $this->seedStudy();
        [, $token] = $this->paidMobileUser();

        $capitulos = $this->withToken($token)->getJson('/api/v1/content-package')
            ->assertOk()
            ->json('estudo.capitulos');

        $this->assertSame(2, $capitulos[0]['numero']);
        $this->assertSame('Da circulação', $capitulos[0]['titulo']);
        $this->assertSame([12], $capitulos[0]['artigos']);

        // Sem capítulo atribuído fica no fim, e não a abrir o Código.
        $ultimo = end($capitulos);
        $this->assertNull($ultimo['numero']);
        $this->assertSame([99], $ultimo['artigos']);
    }

    public function test_free_plan_never_receives_locked_study_material(): void
    {
        $this->seedStudy();
        [, $token] = $this->mobileUser();

        $response = $this->withToken($token)->getJson('/api/v1/content-package')->assertOk();

        $this->assertSame('gratis', $response->json('plano'));

        /*
         * O bloqueado aparece na lista, mas vazio. Retirá-lo por completo — como
         * se fazia — tornava o cadeado invisível: o ecrã afirmava "mais N sinais"
         * e não havia nada para ver, pelo que parecia que o cadeado não fazia
         * nada. O aluno também nunca via o que estava a perder.
         */
        $sinais = collect($response->json('estudo.sinais'))->keyBy('slug');
        $this->assertTrue($sinais->has('curva-a-direita'));
        $this->assertTrue($sinais->has('sinal-reservado-ao-plano-completo'));
        $this->assertArrayHasKey('nome', $sinais['sinal-reservado-ao-plano-completo']);
        $this->assertArrayNotHasKey('significado', $sinais['sinal-reservado-ao-plano-completo']);

        $licoes = collect($response->json('estudo.licoes'))->keyBy('slug');
        $this->assertTrue($licoes->has('ler-os-sinais-pela-forma'));
        $this->assertArrayNotHasKey('corpo', $licoes->last());

        // O conteúdo pago continua a não escapar em nenhum campo do payload.
        $this->assertStringNotContainsString('Significado reservado', $response->getContent());
        $this->assertStringNotContainsString('Corpo reservado', $response->getContent());

        // Mas o app sabe quanto está por trás do cadeado, para o dizer ao aluno.
        $this->assertSame(1, $response->json('estudo.sinaisBloqueados'));
        $this->assertSame(1, $response->json('estudo.licoesBloqueadas'));
    }

    public function test_paid_plan_receives_locked_study_material(): void
    {
        $this->seedStudy();
        [, $token] = $this->paidMobileUser();

        $response = $this->withToken($token)->getJson('/api/v1/content-package')->assertOk();

        $this->assertCount(2, $response->json('estudo.sinais'));
        $this->assertCount(2, $response->json('estudo.licoes'));
        $this->assertSame(0, $response->json('estudo.sinaisBloqueados'));
        $this->assertSame(0, $response->json('estudo.licoesBloqueadas'));
    }

    public function test_inactive_study_material_is_never_published(): void
    {
        $this->seedStudy();
        Lesson::where('slug', 'ler-os-sinais-pela-forma')->update(['is_active' => false]);
        Sign::where('slug', 'curva-a-direita')->update(['is_active' => false]);
        GlossaryTerm::where('slug', 'cedencia-de-passagem')->update(['is_active' => false]);

        [, $token] = $this->paidMobileUser();

        $response = $this->withToken($token)->getJson('/api/v1/content-package')->assertOk();

        $this->assertSame(['ficha-do-plano-completo'], array_column($response->json('estudo.licoes'), 'slug'));
        $this->assertCount(1, $response->json('estudo.sinais'));
        $this->assertSame([], $response->json('estudo.glossario'));
    }
}
