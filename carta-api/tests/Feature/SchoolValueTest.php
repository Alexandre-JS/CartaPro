<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\School;
use App\Models\Student;
use App\Models\Topic;
use App\Models\User;
use App\Services\ClassroomAnalytics;
use App\Services\ExamBlueprint;
use App\Services\ExamScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o que as escolas pagam e não existia: analítica por turma (D1)
 * e geração de prova por critérios (D2).
 */
class SchoolValueTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'Escola Valor', 'code' => 'escola-valor', 'is_active' => true]);
        $this->classroom = Classroom::create(['school_id' => $this->school->id, 'name' => 'Turma B', 'code' => 'turma-b', 'is_active' => true]);
    }

    private function makeQuestions(string $topicSlug, int $count, int $startAt = 1): array
    {
        $topic = Topic::firstOrCreate(['slug' => $topicSlug], ['name' => ucfirst($topicSlug), 'is_active' => true]);

        return collect(range($startAt, $startAt + $count - 1))->map(fn ($index) => Question::create([
            'topic_id' => $topic->id,
            'external_id' => $topicSlug.'-'.$index,
            'type' => 'teorico',
            'categories' => ['ligeiro'],
            'statement' => 'Pergunta '.$topicSlug.' '.$index,
            'options' => ['A', 'B'],
            'correct_index' => 0,
            'explanation' => 'Explicação.',
            'is_active' => true,
            'status' => 'approved',
        ]))->all();
    }

    public function test_blueprint_distributes_questions_across_topics(): void
    {
        // Um tema com muito conteúdo e dois com pouco: a prova não pode sair
        // toda do tema maior, senão deixa de parecer o exame real.
        $this->makeQuestions('sinais', 40);
        $this->makeQuestions('velocidade', 5);
        $this->makeQuestions('prioridade', 5);

        $questions = app(ExamBlueprint::class)->build([
            'category' => 'ligeiro',
            'type' => 'teorico',
            'question_count' => 15,
        ]);

        $this->assertCount(15, $questions);

        $byTopic = $questions->groupBy(fn ($question) => $question->topic->slug)->map->count();
        $this->assertCount(3, $byTopic, 'Os três temas devem estar representados.');
        $this->assertLessThanOrEqual(6, $byTopic['sinais'], 'O tema maior não deve dominar a prova.');
    }

    public function test_blueprint_respects_selected_topics(): void
    {
        $this->makeQuestions('sinais', 10);
        $velocidade = $this->makeQuestions('velocidade', 10);

        $questions = app(ExamBlueprint::class)->build([
            'category' => 'ligeiro',
            'type' => 'teorico',
            'question_count' => 8,
            'topic_ids' => [$velocidade[0]->topic_id],
        ]);

        $this->assertCount(8, $questions);
        $this->assertSame(['velocidade'], $questions->pluck('topic.slug')->unique()->all());
    }

    public function test_blueprint_returns_what_exists_when_bank_is_short(): void
    {
        $this->makeQuestions('sinais', 4);

        $questions = app(ExamBlueprint::class)->build([
            'category' => 'ligeiro', 'type' => 'teorico', 'question_count' => 25,
        ]);

        $this->assertCount(4, $questions);
    }

    public function test_exam_created_by_blueprint_stores_criteria_and_derived_pass_score(): void
    {
        $this->makeQuestions('sinais', 30);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.exams.store'), [
            'name' => 'Prova gerada', 'type' => 'teorico', 'duration_minutes' => 30,
            'visibility' => 'private', 'school_id' => $this->school->id,
            'selection_mode' => 'blueprint',
            'blueprint_category' => 'ligeiro',
            'blueprint_question_count' => 25,
        ])->assertRedirect();

        $exam = Exam::where('name', 'Prova gerada')->firstOrFail();

        $this->assertSame('blueprint', $exam->selection_mode);
        $this->assertSame('ligeiro', $exam->blueprint['category']);
        $this->assertSame(25, $exam->questions()->count());
        // 72% de 25 = 18 — derivado de config/grading.php, não hardcoded.
        $this->assertSame(18, $exam->pass_score);
    }

    public function test_classroom_analytics_reports_averages_weak_topics_and_readiness(): void
    {
        $sinais = $this->makeQuestions('sinais', 2);
        $velocidade = $this->makeQuestions('velocidade', 2);

        $exam = Exam::create([
            'school_id' => $this->school->id, 'name' => 'Prova B', 'license_category' => 'ligeiro',
            'license_categories' => ['ligeiro'], 'type' => 'teorico', 'question_count' => 4,
            'pass_score' => 3, 'duration_minutes' => 30, 'is_active' => true, 'is_public' => false,
        ]);
        $exam->questions()->sync(collect([...$sinais, ...$velocidade])->mapWithKeys(fn ($q, $i) => [$q->id => ['sort_order' => $i + 1]])->all());
        $exam->load('questions.topic');

        $session = ExamSession::create([
            'exam_id' => $exam->id, 'classroom_id' => $this->classroom->id,
            'code' => 'XYZ999', 'status' => 'in_progress', 'starts_at' => now(),
        ]);

        $ana = Student::create(['classroom_id' => $this->classroom->id, 'name' => 'Ana', 'is_active' => true]);
        $bruno = Student::create(['classroom_id' => $this->classroom->id, 'name' => 'Bruno', 'is_active' => true]);
        Student::create(['classroom_id' => $this->classroom->id, 'name' => 'Carla', 'is_active' => true]);

        $scorer = app(ExamScorer::class);
        // Ana acerta tudo; Bruno falha os dois de velocidade.
        $scorer->score($session, $ana, ['sinais-1' => 0, 'sinais-2' => 0, 'velocidade-1' => 0, 'velocidade-2' => 0], 300);
        $scorer->score($session, $bruno, ['sinais-1' => 0, 'sinais-2' => 0, 'velocidade-1' => 1, 'velocidade-2' => 1], 500);

        $analytics = app(ClassroomAnalytics::class);

        $summary = $analytics->summary($this->classroom);
        $this->assertSame(2, $summary['tentativas']);
        $this->assertSame(3, $summary['alunosNaTurma']);
        $this->assertSame(75.0, $summary['mediaPercentagem']); // (100 + 50) / 2
        $this->assertSame(400, $summary['tempoMedioSegundos']);

        // O tema onde a turma erra mais aparece primeiro — é isto que o
        // instrutor precisa para preparar a aula seguinte.
        $weakest = $analytics->weakestTopics($this->classroom);
        $this->assertSame('velocidade', $weakest->first()['tema']);
        $this->assertSame(50.0, $weakest->first()['taxa']);

        $readiness = $analytics->studentReadiness($this->classroom)->keyBy('nome');
        $this->assertSame('em_progresso', $readiness['Ana']['estado']);
        $this->assertSame('em_risco', $readiness['Bruno']['estado']);
        $this->assertSame('sem_provas', $readiness['Carla']['estado']);

        $progress = $analytics->progressBySession($this->classroom);
        $this->assertSame('XYZ999', $progress->first()['codigo']);
        $this->assertSame(75.0, $progress->first()['media']);
    }

    public function test_school_cannot_open_another_schools_classroom_analytics(): void
    {
        $other = School::create(['name' => 'Outra', 'code' => 'outra', 'is_active' => true]);
        $otherClassroom = Classroom::create(['school_id' => $other->id, 'name' => 'Turma X', 'code' => 'turma-x', 'is_active' => true]);
        $schoolUser = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id]);

        $this->actingAs($schoolUser)->get(route('admin.results.classroom', $otherClassroom))->assertForbidden();
        $this->actingAs($schoolUser)->get(route('admin.results.classroom', $this->classroom))->assertOk();
    }

    public function test_school_dashboard_turns_recent_results_into_actionable_indicators(): void
    {
        $questions = $this->makeQuestions('velocidade', 2);
        $exam = Exam::create([
            'school_id' => $this->school->id, 'name' => 'Diagnóstico', 'license_category' => 'ligeiro',
            'license_categories' => ['ligeiro'], 'type' => 'teorico', 'question_count' => 2,
            'pass_score' => 2, 'duration_minutes' => 30, 'is_active' => true, 'is_public' => false,
        ]);
        $exam->questions()->sync(collect($questions)->mapWithKeys(fn ($question, $index) => [$question->id => ['sort_order' => $index + 1]])->all());
        $exam->load('questions.topic');
        $session = ExamSession::create(['exam_id' => $exam->id, 'classroom_id' => $this->classroom->id, 'code' => 'DASH01', 'status' => 'in_progress']);
        $student = Student::create(['classroom_id' => $this->classroom->id, 'name' => 'Marta', 'is_active' => true]);
        app(ExamScorer::class)->score($session, $student, ['velocidade-1' => 0, 'velocidade-2' => 1], 120);
        $schoolUser = User::factory()->create(['role' => 'school', 'school_id' => $this->school->id]);

        $this->actingAs($schoolUser)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Desempenho da escola')
            ->assertSee('Alunos ativos')
            ->assertSee('Média recente')
            ->assertSee('50%')
            ->assertSee('Temas a reforçar')
            ->assertSee('Velocidade')
            ->assertSee('Sessões em curso');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Atividade das escolas')
            ->assertSee('Escola Valor')
            ->assertSee('1 provas');
    }
}
