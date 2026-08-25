<?php

namespace Tests\Feature;

use App\Models\LicenseCategory;
use App\Models\Question;
use App\Models\Sign;
use App\Models\SignCategory;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuestionAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@prontovia.test', 'password' => 'segredo123', 'role' => 'admin', 'is_active' => true]);
        $this->topic = Topic::create(['name' => 'Velocidade', 'slug' => 'velocidade', 'is_active' => true]);
        LicenseCategory::create(['name' => 'Ligeiro', 'slug' => 'ligeiro', 'sort_order' => 1, 'is_active' => true]);
    }

    /** @param  array<string, mixed>  $override */
    private function payload(array $override = []): array
    {
        return array_merge([
            'topic_id' => $this->topic->id,
            'type' => 'teorico',
            'categories' => ['ligeiro'],
            'statement' => 'Qual é o limite dentro das localidades?',
            'option_items' => ['60 km/h', '90 km/h'],
            'correct_index' => 0,
            'action' => 'approve',
            'is_active' => 1,
        ], $override);
    }

    private function sinal(string $nome, string $ficheiro = '/images/signs/stop.svg'): Sign
    {
        return Sign::create([
            'name' => $nome,
            'sign_category_id' => SignCategory::where('slug', 'perigo')->value('id'),
            'meaning' => 'Significado do sinal.',
            'file_path' => $ficheiro,
            'is_active' => true,
        ]);
    }

    public function test_o_formulario_abre_para_criar_e_para_editar(): void
    {
        $this->sinal('Paragem obrigatória');

        $this->actingAs($this->admin)->get(route('admin.questions.create'))
            ->assertOk()
            ->assertSee('Gerado automaticamente')
            ->assertSee('Da biblioteca de sinais')
            ->assertSee('Selecionar sinal')
            ->assertSee('Pesquisar pelo nome ou significado')
            ->assertSee('Paragem obrigatória');

        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload());

        $this->actingAs($this->admin)->get(route('admin.questions.edit', Question::firstOrFail()))
            ->assertOk()
            ->assertSee('velocidade-001');
    }

    public function test_o_banco_pesquisa_temas_sem_renderizar_o_catalogo_e_controla_a_paginacao(): void
    {
        $prioridade = Topic::create(['name' => 'Prioridade', 'slug' => 'prioridade', 'is_active' => true]);
        collect(range(1, 35))->each(fn (int $index) => Question::create([
            'topic_id' => $prioridade->id,
            'external_id' => sprintf('prioridade-%03d', $index),
            'type' => 'teorico',
            'categories' => ['ligeiro'],
            'statement' => 'Pergunta de prioridade '.$index,
            'options' => ['Certa', 'Errada'],
            'correct_index' => 0,
            'status' => 'approved',
            'is_active' => true,
            'sort_order' => $index,
        ]));
        collect(range(1, 40))->each(fn (int $index) => Topic::create([
            'name' => sprintf('Tema sem perguntas %02d', $index),
            'slug' => sprintf('tema-sem-perguntas-%02d', $index),
            'is_active' => true,
        ]));

        $this->actingAs($this->admin)->get(route('admin.questions.index'))
            ->assertOk()
            ->assertViewHas('questions', fn ($questions) => $questions->count() === 10
                && $questions->perPage() === 10);

        $this->actingAs($this->admin)->get(route('admin.questions.index', [
            'topic' => 'Prioridade',
            'per_page' => 30,
            'sort' => 'topic',
        ]))
            ->assertOk()
            ->assertSee('Filtros')
            ->assertSee('aria-label="Paginação"', false)
            ->assertDontSee('Tema sem perguntas 40')
            ->assertViewHas('questions', fn ($questions) => $questions->total() === 35
                && $questions->count() === 30
                && $questions->perPage() === 30);
    }

    public function test_o_banco_usa_os_componentes_acessiveis_do_painel(): void
    {
        $this->actingAs($this->admin)->get(route('admin.questions.index'))
            ->assertOk()
            ->assertSee('aria-labelledby="question-bank-title"', false)
            ->assertSee('Nenhuma pergunta encontrada')
            ->assertSee('class="empty-state"', false)
            ->assertSee('aria-label="Pesquisar perguntas"', false);

        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload());
        $question = Question::firstOrFail();

        $this->actingAs($this->admin)->get(route('admin.questions.index'))
            ->assertOk()
            ->assertSee('class="status approved"', false)
            ->assertSee('data-dialog-open="delete-question-'.$question->id.'"', false)
            ->assertSee('id="delete-question-'.$question->id.'"', false)
            ->assertSee('aria-labelledby="delete-question-'.$question->id.'-title"', false)
            ->assertDontSee("confirm('Remover esta pergunta?')", false);
    }

    public function test_o_identificador_e_gerado_a_partir_do_tema(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload())->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload())->assertRedirect();

        $this->assertSame(['velocidade-001', 'velocidade-002'], Question::orderBy('id')->pluck('external_id')->all());
    }

    public function test_o_identificador_nao_reutiliza_o_de_uma_pergunta_apagada(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload());
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload());
        Question::where('external_id', 'velocidade-002')->delete();

        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload());

        // Contar em vez de olhar para o maior sufixo daria velocidade-002 outra
        // vez, e as respostas antigas guardadas com essa chave passariam a
        // apontar para uma pergunta diferente.
        $this->assertSame('velocidade-003', Question::latest('id')->value('external_id'));
    }

    public function test_o_identificador_nao_muda_ao_editar(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload());
        $question = Question::firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.questions.update', $question), $this->payload([
            'external_id' => 'outro-identificador',
            'statement' => 'Enunciado corrigido.',
        ]))->assertRedirect();

        $question->refresh();
        $this->assertSame('velocidade-001', $question->external_id);
        $this->assertSame('Enunciado corrigido.', $question->statement);
    }

    public function test_a_explicacao_e_opcional(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(Question::firstOrFail()->explanation);
    }

    public function test_a_ordem_segue_a_ultima_do_tema_e_aceita_um_valor_escrito(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload());
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload());

        $this->assertSame([1, 2], Question::orderBy('id')->pluck('sort_order')->all());

        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload(['sort_order' => 10]));
        $this->assertSame(10, Question::latest('id')->value('sort_order'));

        // Escrita uma ordem alta, a seguinte continua a partir dela.
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload());
        $this->assertSame(11, Question::latest('id')->value('sort_order'));
    }

    public function test_a_imagem_pode_vir_do_banco_de_sinais(): void
    {
        $sinal = $this->sinal('Paragem obrigatória');

        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload(['sign_id' => $sinal->id]));

        $question = Question::firstOrFail();
        $this->assertSame($sinal->id, $question->sign_id);
        $this->assertSame('/images/signs/stop.svg', $question->imagemPublica());
        // A coluna não guarda cópia do caminho: a biblioteca é a fonte.
        $this->assertNull($question->image);
    }

    public function test_trocar_a_imagem_do_sinal_chega_as_perguntas_que_o_usam(): void
    {
        $sinal = $this->sinal('Paragem obrigatória');
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload(['sign_id' => $sinal->id]));

        $sinal->update(['file_path' => '/images/signs/stop-novo.svg']);

        $question = Question::with('sign')->firstOrFail();
        $this->assertSame('/images/signs/stop-novo.svg', $question->imagemPublica());
        $this->assertStringContainsString('/images/signs/stop-novo.svg', $question->toPackageArray()['imagem']);
        $this->assertSame($sinal->slug, $question->toPackageArray()['sinal']);
    }

    public function test_um_sinal_ainda_sem_imagem_nao_inventa_um_caminho(): void
    {
        $sinal = $this->sinal('Báscula por ilustrar', '');
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload(['sign_id' => $sinal->id]));

        $question = Question::with('sign')->firstOrFail();
        $this->assertNull($question->imagemPublica());
        $this->assertNull($question->toPackageArray()['imagem']);
    }

    public function test_a_pergunta_aceita_uma_imagem_fora_do_banco_de_sinais(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload([
            'image_file' => UploadedFile::fake()->image('cruzamento.png', 40, 40),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $question = Question::firstOrFail();
        $this->assertNull($question->sign_id);
        $this->assertStringStartsWith('/images/questions/', (string) $question->image);
        $this->assertFileExists(public_path($question->image));
        @unlink(public_path($question->image));
    }

    public function test_a_imagem_da_pergunta_passa_pelas_verificacoes_de_seguranca(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload([
            'image_file' => UploadedFile::fake()->createWithContent('mapa.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ]))->assertStatus(422);

        $this->assertSame(0, Question::count());
    }

    public function test_escolher_um_sinal_apaga_a_imagem_propria_anterior(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload([
            'image_file' => UploadedFile::fake()->image('cruzamento.png', 40, 40),
        ]));
        $question = Question::firstOrFail();
        $anterior = public_path((string) $question->image);
        $sinal = $this->sinal('Paragem obrigatória');

        $this->actingAs($this->admin)->put(route('admin.questions.update', $question), $this->payload(['sign_id' => $sinal->id]));

        $question->refresh();
        $this->assertNull($question->image, 'Com sinal escolhido não pode sobrar um caminho órfão a competir com a biblioteca.');
        $this->assertSame($sinal->id, $question->sign_id);
        @unlink($anterior);
    }

    public function test_editar_sem_enviar_ficheiro_mantem_a_imagem_propria(): void
    {
        $this->actingAs($this->admin)->post(route('admin.questions.store'), $this->payload([
            'image_file' => UploadedFile::fake()->image('cruzamento.png', 40, 40),
        ]));
        $question = Question::firstOrFail();
        $caminho = $question->image;

        $this->actingAs($this->admin)->put(route('admin.questions.update', $question), $this->payload(['statement' => 'Outro enunciado.']));

        $this->assertSame($caminho, $question->refresh()->image);
        @unlink(public_path($caminho));
    }
}
