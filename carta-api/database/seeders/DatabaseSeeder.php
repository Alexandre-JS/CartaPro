<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\School;
use App\Models\Sign;
use App\Models\SignCategory;
use App\Models\Student;
use App\Models\Unlock;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Administrador e catálogo base. Vive à parte para se poder repor uma
        // instalação sem os dados de demonstração que se seguem.
        $this->call(EssenciaisSeeder::class);

        Sign::updateOrCreate(['slug' => 'curva-perigosa'], [
            'name' => 'Curva perigosa',
            'sign_category_id' => SignCategory::where('slug', 'perigo')->value('id'),
            'meaning' => 'Aviso de curva perigosa à frente.',
            'file_path' => '/images/signs/curva-perigosa.svg',
            'is_active' => true,
        ]);

        $this->call(DemoContentSeeder::class);

        $school = School::updateOrCreate(['code' => 'ESC-DEMO'], [
            'name' => 'Escola de Condução Segura', 'email' => 'escola@prontovia.co.mz', 'phone' => '+258 84 000 0000',
            'address' => 'Maputo', 'contact_person' => 'Responsável de Formação', 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'escola@prontovia.co.mz'], [
            'school_id' => $school->id, 'name' => 'Gestor Escola Segura', 'password' => 'Escola@2026', 'role' => 'school', 'is_active' => true,
        ]);
        $classroom = Classroom::updateOrCreate(['school_id' => $school->id, 'code' => 'TURMA-A'], ['name' => 'Turma A', 'year' => now()->year, 'is_active' => true]);
        $studentA = Student::firstOrCreate(['classroom_id' => $classroom->id, 'identifier' => 'AL-001'], ['name' => 'Ana Mucavele', 'phone' => '+258 84 111 1111', 'is_active' => true]);
        $studentB = Student::firstOrCreate(['classroom_id' => $classroom->id, 'identifier' => 'AL-002'], ['name' => 'Carlos Mondlane', 'phone' => '+258 85 222 2222', 'is_active' => true]);
        $approved = Question::where('status', 'approved')->orderBy('sort_order')->get();
        if ($approved->isNotEmpty()) {
            $exam = Exam::firstOrCreate(['school_id' => $school->id, 'name' => 'Prova de demonstração'], [
                'created_by' => User::where('email', 'admin@prontovia.co.mz')->value('id'), 'license_category' => 'ligeiro',
                'type' => 'teorico', 'topic_ids' => [], 'question_count' => $approved->count(), 'pass_score' => min(2, $approved->count()), 'is_active' => true,
            ]);
            $exam->update(['question_count' => $approved->count(), 'pass_score' => (int) ceil($approved->count() * 0.72), 'license_categories' => ['ligeiro', 'pesado', 'profissional_publico'], 'duration_minutes' => 60, 'is_public' => true, 'publication_status' => 'published', 'published_at' => now(), 'is_active' => true]);
            $exam->questions()->syncWithoutDetaching($approved->values()->mapWithKeys(fn ($question, $index) => [$question->id => ['sort_order' => $index + 1]])->all());
            $session = ExamSession::firstOrCreate(['code' => 'DEMO26'], [
                'exam_id' => $exam->id, 'classroom_id' => $classroom->id, 'status' => 'finished',
                'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour(),
            ]);
            ExamAttempt::firstOrCreate(['exam_session_id' => $session->id, 'student_id' => $studentA->id], [
                'answers' => [], 'score' => $approved->count(), 'total' => $approved->count(), 'passed' => true, 'weak_topics' => [], 'submitted_at' => now()->subDay()->addMinutes(35),
            ]);
            ExamAttempt::firstOrCreate(['exam_session_id' => $session->id, 'student_id' => $studentB->id], [
                'answers' => [], 'score' => 1, 'total' => $approved->count(), 'passed' => false, 'weak_topics' => ['velocidade', 'prioridade'], 'submitted_at' => now()->subDay()->addMinutes(42),
            ]);
        }
        Unlock::firstOrCreate(['phone' => '+258841234567'], [
            'plan' => 'completo', 'payment_method' => 'mpesa', 'payment_reference' => 'MPESA-DEMO-001',
            'unlocked_at' => now()->subDays(2), 'is_active' => true, 'created_by' => User::where('email', 'admin@prontovia.co.mz')->value('id'),
        ]);
    }
}
