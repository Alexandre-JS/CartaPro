<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\School;
use App\Models\Student;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre as duas falhas graves do fluxo antigo da API:
 * pauta da turma pública e submissão em nome de outro aluno.
 */
class ExamSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private ExamSession $session;

    private Student $ana;

    private Student $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::create(['name' => 'Escola Teste', 'code' => 'escola-teste', 'is_active' => true]);
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Turma A', 'code' => 'turma-a', 'is_active' => true]);
        $this->ana = Student::create(['classroom_id' => $classroom->id, 'name' => 'Ana Silva', 'identifier' => 'BI-123', 'is_active' => true]);
        $this->bruno = Student::create(['classroom_id' => $classroom->id, 'name' => 'Bruno Costa', 'identifier' => 'BI-456', 'is_active' => true]);

        $topic = Topic::create(['name' => 'Sinais', 'slug' => 'sinais', 'is_active' => true]);
        $questions = collect(range(1, 4))->map(fn ($index) => Question::create([
            'topic_id' => $topic->id,
            'external_id' => 'sec-'.$index,
            'categories' => ['ligeiro'],
            'statement' => 'Pergunta '.$index,
            'options' => ['A', 'B'],
            'correct_index' => 0,
            'explanation' => 'Explicação '.$index,
            'is_active' => true,
            'status' => 'approved',
        ]));

        $exam = Exam::create([
            'school_id' => $school->id, 'name' => 'Prova Turma A', 'license_category' => 'ligeiro',
            'license_categories' => ['ligeiro'], 'type' => 'teorico', 'question_count' => 4,
            'pass_score' => 3, 'duration_minutes' => 30, 'is_active' => true, 'is_public' => false,
        ]);
        $exam->questions()->sync($questions->mapWithKeys(fn ($q, $i) => [$q->id => ['sort_order' => $i + 1]])->all());

        $this->session = ExamSession::create([
            'exam_id' => $exam->id, 'classroom_id' => $classroom->id,
            'code' => 'ABC123', 'status' => 'in_progress',
        ]);
    }

    public function test_session_lookup_no_longer_leaks_the_class_roster(): void
    {
        $response = $this->getJson('/api/v1/sessions/ABC123')->assertOk();

        // Antes devolvia nome e identificador de todos os alunos da turma.
        $body = $response->getContent();
        $this->assertStringNotContainsString('Ana Silva', $body);
        $this->assertStringNotContainsString('BI-123', $body);
        $this->assertNull($response->json('alunos'));

        // Nem as perguntas são expostas sem bilhete.
        $this->assertNull($response->json('perguntas'));
    }

    public function test_questions_require_a_ticket(): void
    {
        $this->getJson('/api/v1/sessions/ABC123/perguntas')->assertUnauthorized();
        $this->postJson('/api/v1/sessions/ABC123/submeter', ['answers' => []])->assertUnauthorized();
    }

    public function test_student_can_enter_and_submit_with_a_ticket(): void
    {
        $ticket = $this->postJson('/api/v1/sessions/ABC123/entrar', ['nome' => 'ana silva'])
            ->assertOk()->json('bilhete');

        $questions = $this->withHeader('X-Exam-Ticket', $ticket)
            ->getJson('/api/v1/sessions/ABC123/perguntas')->assertOk();

        // A resposta correta nunca viaja para o cliente durante a prova.
        $this->assertStringNotContainsString('correta', $questions->getContent());

        $this->withHeader('X-Exam-Ticket', $ticket)
            ->postJson('/api/v1/sessions/ABC123/submeter', [
                'answers' => ['sec-1' => 0, 'sec-2' => 0, 'sec-3' => 0, 'sec-4' => 1],
                'tempoSegundos' => 420,
            ])
            ->assertCreated()
            ->assertJsonPath('pontuacao', 3)
            ->assertJsonPath('aprovado', true);

        $this->assertDatabaseHas('exam_attempts', [
            'student_id' => $this->ana->id,
            'score' => 3,
            'duration_seconds' => 420,
        ]);
    }

    public function test_ticket_cannot_be_used_to_submit_for_another_student(): void
    {
        $anaTicket = $this->postJson('/api/v1/sessions/ABC123/entrar', ['nome' => 'Ana Silva'])->assertOk()->json('bilhete');

        // O antigo endpoint aceitava `student_id` arbitrário; o bilhete é que
        // determina o aluno, pelo que este campo é agora irrelevante.
        $this->withHeader('X-Exam-Ticket', $anaTicket)
            ->postJson('/api/v1/sessions/ABC123/submeter', [
                'student_id' => $this->bruno->id,
                'answers' => ['sec-1' => 1, 'sec-2' => 1, 'sec-3' => 1, 'sec-4' => 1],
            ])->assertCreated();

        // A tentativa ficou registada na Ana, não no Bruno.
        $this->assertDatabaseHas('exam_attempts', ['student_id' => $this->ana->id]);
        $this->assertDatabaseMissing('exam_attempts', ['student_id' => $this->bruno->id]);
    }

    public function test_unknown_name_cannot_burn_a_students_attempt(): void
    {
        $this->postJson('/api/v1/sessions/ABC123/entrar', ['nome' => 'Nome Inventado'])->assertNotFound();
        $this->assertDatabaseCount('exam_attempts', 0);
    }

    public function test_weak_topics_use_a_rate_not_a_single_error(): void
    {
        $ticket = $this->postJson('/api/v1/sessions/ABC123/entrar', ['nome' => 'Bruno Costa'])->assertOk()->json('bilhete');

        // 3 de 4 no tema (75%) fica acima do limiar: não é tema fraco.
        // Antes bastava um erro para marcar o tema como fraco.
        $response = $this->withHeader('X-Exam-Ticket', $ticket)
            ->postJson('/api/v1/sessions/ABC123/submeter', [
                'answers' => ['sec-1' => 0, 'sec-2' => 0, 'sec-3' => 0, 'sec-4' => 1],
            ])->assertCreated();

        $this->assertSame([], $response->json('temasFracos'));
        $this->assertSame(0.75, $response->json('detalhePorTema.sinais.taxa'));
    }
}
