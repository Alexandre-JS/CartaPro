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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StudentExamWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_uses_session_link_and_sees_only_the_grade(): void
    {
        $school = School::create(['name' => 'Escola Teste', 'code' => 'ESC-T', 'is_active' => true]);
        $classroom = Classroom::create(['school_id' => $school->id, 'name' => 'Turma A', 'code' => 'TA', 'is_active' => true]);
        $topic = Topic::create(['name' => 'Prioridade', 'slug' => 'prioridade', 'is_active' => true]);
        $question = Question::create(['topic_id' => $topic->id, 'external_id' => 'WEB-001', 'type' => 'teorico', 'categories' => ['ligeiro'], 'statement' => 'Quem tem prioridade?', 'options' => ['A', 'B'], 'correct_index' => 1, 'explanation' => 'Explicação', 'status' => 'approved', 'is_active' => true]);
        $exam = Exam::create(['school_id' => $school->id, 'name' => 'Prova da turma', 'license_category' => 'ligeiro', 'license_categories' => ['ligeiro'], 'type' => 'teorico', 'topic_ids' => [$topic->id], 'question_count' => 1, 'pass_score' => 1, 'duration_minutes' => 30, 'is_active' => true, 'is_public' => false]);
        $exam->questions()->attach($question->id, ['sort_order' => 1]);
        $session = ExamSession::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id, 'code' => 'ABC123', 'status' => 'in_progress', 'starts_at' => now()]);

        $this->get(route('student-exam.entry', $session->code))->assertOk()->assertSee('Escola Teste');
        $this->post(route('student-exam.enter', $session->code), ['name' => 'Maria Teste', 'code' => $session->code])->assertRedirect();
        $student = Student::where('classroom_id', $classroom->id)->where('name', 'Maria Teste')->firstOrFail();

        $submitUrl = URL::temporarySignedRoute('student-exam.submit', now()->addHour(), ['code' => $session->code, 'student' => $student->id]);
        $response = $this->post($submitUrl, ['answers' => ['WEB-001' => 1]]);

        $response->assertOk()->assertSee('20')->assertDontSee('Aprovado')->assertDontSee('Reprovado');
        $this->assertDatabaseHas('exam_attempts', ['exam_session_id' => $session->id, 'student_id' => $student->id, 'score' => 1, 'total' => 1]);
        $attempt = $session->attempts()->where('student_id', $student->id)->firstOrFail();
        $this->actingAs(User::factory()->create())->get(route('admin.results.show', $attempt))
            ->assertOk()->assertSee('Conferência das respostas')->assertSee('Quem tem prioridade?')->assertSee('Resposta do estudante')->assertSee('Resposta correta')->assertSee('Somente leitura');
        foreach (['DEF456', 'GHI789'] as $code) {
            $additionalSession = ExamSession::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id, 'code' => $code, 'status' => 'finished', 'starts_at' => now(), 'ends_at' => now()]);
            $student->attempts()->create(['exam_session_id' => $additionalSession->id, 'answers' => ['WEB-001' => 1], 'score' => 1, 'total' => 1, 'passed' => true, 'weak_topics' => [], 'submitted_at' => now()]);
        }
        $this->get(route('admin.students.show', $student))->assertOk()->assertSee('Apto')->assertSee('3/3')->assertSee('Prova da turma');
        $this->post($submitUrl, ['answers' => ['WEB-001' => 1]])->assertStatus(409);
    }
}
