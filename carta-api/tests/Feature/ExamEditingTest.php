<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\School;
use App\Models\Student;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ExamEditingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Collection $questions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@cartapro.test', 'password' => 'segredo123', 'role' => 'admin', 'is_active' => true]);
        $topic = Topic::create(['name' => 'Velocidade', 'slug' => 'velocidade', 'is_active' => true]);

        $this->questions = collect(range(1, 4))->map(fn (int $n) => Question::create([
            'topic_id' => $topic->id, 'external_id' => 'vel-00'.$n, 'type' => 'teorico',
            'categories' => ['ligeiro'], 'statement' => 'Pergunta '.$n,
            'options' => ['Certa', 'Errada'], 'correct_index' => 0,
            'explanation' => 'Porque sim.', 'status' => 'approved', 'is_active' => true, 'sort_order' => $n,
        ]));
    }

    private function criar(array $override = []): Exam
    {
        $this->actingAs($this->admin)->post(route('admin.exams.store'), array_merge([
            'name' => 'Prova de aula', 'type' => 'teorico', 'duration_minutes' => 30,
            'visibility' => 'public', 'selection_mode' => 'manual',
            'question_ids' => $this->questions->take(2)->pluck('id')->all(),
        ], $override))->assertRedirect();

        return Exam::latest('id')->firstOrFail();
    }

    /** Uma tentativa submetida nesta prova, através de uma sessão de turma. */
    private function responder(Exam $exam): void
    {
        $school = School::create(['name' => 'Escola', 'code' => 'ESC-1', 'is_active' => true]);
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Turma A', 'code' => 'A', 'is_active' => true]);
        $student = Student::create(['classroom_id' => $classroom->id, 'name' => 'Aluna', 'is_active' => true]);
        $session = ExamSession::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id, 'code' => 'CODE01', 'status' => 'finished']);

        ExamAttempt::create([
            'exam_session_id' => $session->id, 'student_id' => $student->id,
            'answers' => ['vel-001' => 0, 'vel-002' => 1], 'score' => 1, 'total' => 2,
            'passed' => false, 'weak_topics' => [], 'submitted_at' => now(),
        ]);
    }

    public function test_o_formulario_de_edicao_abre_com_a_prova_carregada(): void
    {
        $exam = $this->criar();

        $this->actingAs($this->admin)->get(route('admin.exams.edit', $exam))
            ->assertOk()
            ->assertSee('Prova de aula')
            ->assertSee('Guardar alterações');
    }

    public function test_edita_a_configuracao_da_prova(): void
    {
        $exam = $this->criar();

        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), [
            'name' => 'Prova revista', 'type' => 'teorico', 'duration_minutes' => 45,
            'visibility' => 'public', 'selection_mode' => 'manual',
            'question_ids' => $this->questions->take(2)->pluck('id')->all(),
        ])->assertRedirect(route('admin.exams.index'));

        $exam->refresh();
        $this->assertSame('Prova revista', $exam->name);
        $this->assertSame(45, $exam->duration_minutes);
    }

    public function test_trocar_as_perguntas_recalcula_os_campos_derivados(): void
    {
        $exam = $this->criar();
        $this->assertSame(2, $exam->question_count);

        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), [
            'name' => 'Prova de aula', 'type' => 'teorico', 'duration_minutes' => 30,
            'visibility' => 'public', 'selection_mode' => 'manual',
            'question_ids' => $this->questions->pluck('id')->all(),
        ])->assertRedirect();

        $exam->refresh();
        $this->assertSame(4, $exam->questions()->count());
        $this->assertSame(4, $exam->question_count);
        // 72% de 4 = 2,88 → 3 acertos. A nota de passagem não pode ficar na
        // regra da contagem anterior.
        $this->assertSame(3, $exam->pass_score);
    }

    public function test_a_ordem_das_perguntas_segue_a_seleccao(): void
    {
        $exam = $this->criar();
        $escolhidas = $this->questions->reverse()->pluck('id')->all();

        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), [
            'name' => 'Prova de aula', 'type' => 'teorico', 'duration_minutes' => 30,
            'visibility' => 'public', 'selection_mode' => 'manual', 'question_ids' => $escolhidas,
        ]);

        $this->assertSame($escolhidas, $exam->questions()->pluck('questions.id')->all());
    }

    public function test_uma_prova_ja_respondida_nao_deixa_trocar_as_perguntas(): void
    {
        $exam = $this->criar();
        $this->responder($exam);
        $originais = $exam->questions()->pluck('questions.id')->all();

        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), [
            'name' => 'Prova aplicada', 'type' => 'teorico', 'duration_minutes' => 50,
            'visibility' => 'public', 'selection_mode' => 'manual',
            'question_ids' => $this->questions->pluck('id')->all(),
        ])->assertRedirect();

        $exam->refresh();
        $this->assertSame($originais, $exam->questions()->pluck('questions.id')->all(), 'As respostas já submetidas são corrigidas contra estas perguntas.');
        // O resto continua editável: selar as perguntas não é congelar a prova.
        $this->assertSame('Prova aplicada', $exam->name);
        $this->assertSame(50, $exam->duration_minutes);
    }

    public function test_o_formulario_avisa_e_sela_as_perguntas_de_uma_prova_respondida(): void
    {
        $exam = $this->criar();
        $this->responder($exam);

        $this->actingAs($this->admin)->get(route('admin.exams.edit', $exam))
            ->assertOk()
            ->assertSee('tentativa submetida')
            ->assertDontSee('name="question_ids[]"', false);
    }

    public function test_a_escola_nao_edita_a_prova_de_outra_escola(): void
    {
        $exam = $this->criar();
        $outra = School::create(['name' => 'Outra', 'code' => 'ESC-2', 'is_active' => true]);
        $gestor = User::create(['name' => 'Gestor', 'email' => 'gestor@escola.test', 'password' => 'segredo123', 'role' => 'school', 'school_id' => $outra->id, 'is_active' => true]);

        $this->actingAs($gestor)->get(route('admin.exams.edit', $exam))->assertForbidden();
        $this->actingAs($gestor)->put(route('admin.exams.update', $exam), [
            'name' => 'Roubada', 'type' => 'teorico', 'duration_minutes' => 30,
            'visibility' => 'private', 'selection_mode' => 'manual',
            'question_ids' => $this->questions->take(1)->pluck('id')->all(),
        ])->assertForbidden();

        $this->assertSame('Prova de aula', $exam->refresh()->name);
    }

    /** Prova privada de uma escola, criada directamente para não passar pelo formulário. */
    private function provaDaEscola(School $school, array $questions): Exam
    {
        $exam = Exam::create([
            'school_id' => $school->id, 'created_by' => $this->admin->id, 'name' => 'Prova da escola',
            'license_category' => 'ligeiro', 'license_categories' => ['ligeiro'], 'type' => 'teorico',
            'topic_ids' => [], 'question_count' => count($questions), 'pass_score' => 2,
            'duration_minutes' => 30, 'is_active' => true, 'is_public' => false, 'publication_status' => 'draft',
        ]);
        $exam->questions()->sync(collect($questions)->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i + 1]])->all());

        return $exam;
    }

    private function payloadPublico(array $questionIds): array
    {
        return [
            'name' => 'Prova da escola', 'type' => 'teorico', 'duration_minutes' => 30,
            'visibility' => 'public', 'selection_mode' => 'manual', 'question_ids' => $questionIds,
        ];
    }

    public function test_uma_prova_privada_passa_a_publica_e_chega_ao_telemovel(): void
    {
        $school = School::create(['name' => 'Escola', 'code' => 'ESC-1', 'is_active' => true]);
        $exam = $this->provaDaEscola($school, $this->questions->take(2)->pluck('id')->all());

        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), $this->payloadPublico($this->questions->take(2)->pluck('id')->all()))
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'Publicar no app'));

        $exam->refresh();
        $this->assertTrue($exam->is_public);
        $this->assertNull($exam->school_id, 'Deixa de ter dono: uma escola não pode editar uma prova viva no aplicativo.');
        // Pública ainda não é visível: falta o passo de publicação.
        $this->assertSame('draft', $exam->publication_status);

        [$user, $token] = $this->mobileUser();
        $this->withToken($token)->getJson('/api/v1/mobile/exams')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->admin)->patch(route('admin.exams.publish', $exam))->assertRedirect();

        $this->withToken($token)->getJson('/api/v1/mobile/exams')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Prova da escola');
    }

    public function test_voltar_a_privada_retira_a_prova_do_catalogo_publicado(): void
    {
        $exam = $this->criar();
        $this->actingAs($this->admin)->patch(route('admin.exams.publish', $exam));
        $school = School::create(['name' => 'Escola', 'code' => 'ESC-1', 'is_active' => true]);

        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), [
            'name' => 'Prova de aula', 'type' => 'teorico', 'duration_minutes' => 30,
            'visibility' => 'private', 'school_id' => $school->id,
            'selection_mode' => 'manual', 'question_ids' => $this->questions->take(2)->pluck('id')->all(),
        ])->assertRedirect();

        $exam->refresh();
        $this->assertFalse($exam->is_public);
        // Sem esta reposição, voltar a torná-la pública repunha-a no catálogo
        // de imediato, sem passar pela revisão do botão «Publicar no app».
        $this->assertSame('draft', $exam->publication_status);
        $this->assertNull($exam->published_at);
    }

    public function test_uma_prova_publica_nao_pode_levar_perguntas_privadas_de_uma_escola(): void
    {
        $school = School::create(['name' => 'Escola', 'code' => 'ESC-1', 'is_active' => true]);
        $daEscola = Question::create([
            'topic_id' => $this->questions->first()->topic_id, 'external_id' => 'escola-001', 'type' => 'teorico',
            'categories' => ['ligeiro'], 'statement' => 'Pergunta da escola', 'options' => ['a', 'b'],
            'correct_index' => 0, 'status' => 'approved', 'is_active' => true, 'school_id' => $school->id,
        ]);
        $exam = $this->provaDaEscola($school, [$this->questions->first()->id, $daEscola->id]);

        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), $this->payloadPublico([$this->questions->first()->id, $daEscola->id]))
            ->assertStatus(422);

        $this->assertFalse($exam->refresh()->is_public);
    }

    public function test_uma_prova_ja_aplicada_nao_e_promovida_no_lugar_mas_pode_ser_copiada(): void
    {
        $school = School::create(['name' => 'Escola', 'code' => 'ESC-1', 'is_active' => true]);
        $exam = $this->provaDaEscola($school, $this->questions->take(2)->pluck('id')->all());
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Turma A', 'code' => 'A', 'is_active' => true]);
        ExamSession::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id, 'code' => 'CODE01', 'status' => 'finished']);

        // Promover no lugar anularia o school_id e a escola perdia o acesso aos
        // resultados dos seus alunos, que são filtrados por esse campo.
        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), $this->payloadPublico($this->questions->take(2)->pluck('id')->all()))
            ->assertStatus(422);
        $this->assertSame($school->id, $exam->refresh()->school_id);

        $this->actingAs($this->admin)->post(route('admin.exams.duplicate-public', $exam))->assertRedirect();

        $copia = Exam::where('is_public', true)->firstOrFail();
        $this->assertSame('Prova da escola (cópia pública)', $copia->name);
        $this->assertNull($copia->school_id);
        $this->assertSame('draft', $copia->publication_status);
        $this->assertSame($exam->questions()->pluck('questions.id')->all(), $copia->questions()->pluck('questions.id')->all());
        // O original e o seu histórico ficam onde estavam.
        $this->assertSame($school->id, $exam->refresh()->school_id);
        $this->assertSame(1, $exam->sessions()->count());
    }

    public function test_editar_uma_prova_publicada_avisa_para_republicar_o_pacote(): void
    {
        $exam = $this->criar();
        $exam->update(['publication_status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->admin)->put(route('admin.exams.update', $exam), [
            'name' => 'Prova de aula', 'type' => 'teorico', 'duration_minutes' => 30,
            'visibility' => 'public', 'selection_mode' => 'manual',
            'question_ids' => $this->questions->pluck('id')->all(),
        ])->assertSessionHas('status', fn (string $status) => str_contains($status, 'pacote'));
    }
}
