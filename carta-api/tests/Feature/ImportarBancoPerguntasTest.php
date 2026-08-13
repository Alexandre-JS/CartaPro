<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\LicenseCategory;
use App\Models\Question;
use App\Models\Sign;
use App\Models\Topic;
use App\Support\BancoPerguntas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportarBancoPerguntasTest extends TestCase
{
    use RefreshDatabase;

    /** Duas perguntas com sinal e uma sem, no formato exacto do banco. */
    private function banco(): string
    {
        return <<<'MD'
        # INATRO — Banco de Exames

        ## Exame n.º 01

        **1.** O titular de carta da categoria C está habilitado a conduzir:
        - **A)** Veículos articulados da subcategoria C1E
        - **B)** Veículos da subcategoria C1 ✔
        *Habilitação legal para conduzir — Art. 127 CE*

        **2.** Este sinal indica:
        > IMAGEM DO SINAL ▸ N29 – «Báscula»
        - **A)** A proibição de trânsito a veículos de mercadorias
        - **B)** Sentido obrigatório, para pesagem ✔
        *Sinais de cedência de passagem e combinados — Art. 38 RST*

        **3.** Estas marcas consistem numa:
        > IMAGEM DO SINAL ▸ P1 e P2 – «Linha de paragem» e «linha com STOP»
        - **A)** Linha transversal contínua ✔
        - **B)** Linha longitudinal descontínua
        *Sinalização horizontal (marcas no pavimento) — Art. 42 RST*
        MD;
    }

    private function escrever(string $conteudo): string
    {
        $caminho = base_path('banco-teste-'.uniqid().'.md');
        file_put_contents($caminho, $conteudo);
        $this->beforeApplicationDestroyed(fn () => @unlink($caminho));

        return $caminho;
    }

    public function test_le_enunciados_alineas_e_a_resposta_assinalada(): void
    {
        $banco = new BancoPerguntas($this->banco());

        $this->assertSame([], $banco->erros());
        $this->assertCount(3, $banco->perguntas());

        [$primeira, $segunda, $terceira] = $banco->perguntas();
        $this->assertSame(1, $primeira['exame']);
        $this->assertSame('O titular de carta da categoria C está habilitado a conduzir:', $primeira['enunciado']);
        $this->assertSame(1, $primeira['correta']);
        $this->assertSame('Veículos da subcategoria C1', $primeira['opcoes'][1], 'A marca ✔ não deve ficar no texto da alínea.');
        $this->assertSame('Habilitação legal para conduzir', $primeira['tema']);
        $this->assertSame('Art. 127 CE', $primeira['referencia']);

        $this->assertNull($primeira['sinal']);
        $this->assertSame(['N29'], $segunda['sinal']['codigos']);
        $this->assertSame(['Báscula'], $segunda['sinal']['nomes']);
        $this->assertSame(['P1', 'P2'], $terceira['sinal']['codigos'], 'Uma linha pode citar mais do que um sinal.');
    }

    public function test_acusa_pergunta_sem_resposta_assinalada(): void
    {
        $banco = new BancoPerguntas(<<<'MD'
        ## Exame n.º 01

        **1.** Pergunta sem gabarito:
        - **A)** Primeira
        - **B)** Segunda
        *Velocidade — Art. 33 CE*
        MD);

        $this->assertCount(1, $banco->erros());
        $this->assertStringContainsString('não tem alínea assinalada', $banco->erros()[0]);
    }

    public function test_importa_perguntas_temas_sinais_e_provas(): void
    {
        LicenseCategory::create(['name' => 'Ligeiro', 'slug' => 'ligeiro', 'sort_order' => 1, 'is_active' => true]);
        $caminho = $this->escrever($this->banco());

        $this->artisan('inatro:importar', ['ficheiro' => $caminho])->assertSuccessful();

        $this->assertSame(3, Question::count());
        $pergunta = Question::where('external_id', 'inatro-e01-q01')->firstOrFail();
        $this->assertSame('approved', $pergunta->status);
        $this->assertSame(1, $pergunta->correct_index);
        $this->assertSame(['ligeiro'], $pergunta->categories);
        $this->assertSame(127, $pergunta->article_ref, 'O artigo do Código da Estrada é ligado.');
        $this->assertStringContainsString('Art. 127 CE', $pergunta->explanation);

        // Temas: os vinte do banco entram pelo slug do mapa, não pelo nome.
        $this->assertNotNull(Topic::where('slug', 'habilitacao_conduzir')->first());
        $this->assertNotNull(Topic::where('slug', 'sinalizacao_horizontal')->first());

        // Sinais: um por código citado, sem imagem e na categoria certa.
        $this->assertSame(3, Sign::count());
        $bascula = Sign::where('name', 'like', 'N29%')->firstOrFail();
        $this->assertSame('', $bascula->file_path);
        $this->assertSame($bascula->id, Question::where('external_id', 'inatro-e01-q02')->value('sign_id'));

        // Provas: uma por exame, em rascunho.
        $prova = Exam::whereNull('school_id')->firstOrFail();
        $this->assertSame('INATRO — Exame n.º 01', $prova->name);
        $this->assertSame('draft', $prova->publication_status);
        $this->assertFalse($prova->is_public);
        $this->assertSame(3, $prova->questions()->count());
    }

    public function test_o_artigo_do_regulamento_de_sinais_nao_e_ligado_ao_codigo_da_estrada(): void
    {
        LicenseCategory::create(['name' => 'Ligeiro', 'slug' => 'ligeiro', 'sort_order' => 1, 'is_active' => true]);
        $this->artisan('inatro:importar', ['ficheiro' => $this->escrever($this->banco())])->assertSuccessful();

        $this->assertNull(
            Question::where('external_id', 'inatro-e01-q02')->value('article_ref'),
            'O art. 38 do RST não é o art. 38 do Código da Estrada.'
        );
    }

    public function test_correr_duas_vezes_nao_duplica_nem_desfaz_correccoes_manuais(): void
    {
        LicenseCategory::create(['name' => 'Ligeiro', 'slug' => 'ligeiro', 'sort_order' => 1, 'is_active' => true]);
        $caminho = $this->escrever($this->banco());

        $this->artisan('inatro:importar', ['ficheiro' => $caminho])->assertSuccessful();
        Question::where('external_id', 'inatro-e01-q01')->update(['explanation' => 'Explicação revista pelo instrutor.']);

        $this->artisan('inatro:importar', ['ficheiro' => $caminho])->assertSuccessful();

        $this->assertSame(3, Question::count());
        $this->assertSame(1, Exam::whereNull('school_id')->count());
        $this->assertSame('Explicação revista pelo instrutor.', Question::where('external_id', 'inatro-e01-q01')->value('explanation'));

        $this->artisan('inatro:importar', ['ficheiro' => $caminho, '--forcar' => true])->assertSuccessful();
        $this->assertStringContainsString('Base legal', Question::where('external_id', 'inatro-e01-q01')->value('explanation'));
    }

    public function test_a_simulacao_nao_grava_nada(): void
    {
        LicenseCategory::create(['name' => 'Ligeiro', 'slug' => 'ligeiro', 'sort_order' => 1, 'is_active' => true]);

        $this->artisan('inatro:importar', ['ficheiro' => $this->escrever($this->banco()), '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, Question::count());
        $this->assertSame(0, Exam::count());
    }

    public function test_recusa_ficheiro_com_perguntas_partidas(): void
    {
        $caminho = $this->escrever(<<<'MD'
        ## Exame n.º 01

        **1.** Pergunta sem gabarito:
        - **A)** Primeira
        - **B)** Segunda
        *Velocidade — Art. 33 CE*
        MD);

        $this->artisan('inatro:importar', ['ficheiro' => $caminho])->assertFailed();
        $this->assertSame(0, Question::count());
    }
}
