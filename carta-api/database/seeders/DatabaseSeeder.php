<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\LicenseCategory;
use App\Models\Question;
use App\Models\School;
use App\Models\Sign;
use App\Models\Student;
use App\Models\Topic;
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
        User::firstOrCreate(['email' => 'admin@cartapro.co.mz'], [
            'name' => 'Administrador CartaPro',
            'password' => 'CartaPro@2026',
        ]);

        collect([
            ['name' => 'Velocidade e condução segura', 'slug' => 'velocidade', 'sort_order' => 1],
            ['name' => 'Sinais de perigo', 'slug' => 'sinais_perigo', 'sort_order' => 2],
            ['name' => 'Prioridade e cruzamentos', 'slug' => 'prioridade', 'sort_order' => 3],
        ])->each(fn (array $topic) => Topic::updateOrCreate(['slug' => $topic['slug']], $topic + ['is_active' => true]));

        collect([
            ['name' => 'Ligeiro', 'slug' => 'ligeiro', 'sort_order' => 1],
            ['name' => 'Pesado', 'slug' => 'pesado', 'sort_order' => 2],
            ['name' => 'Profissional/público', 'slug' => 'profissional_publico', 'sort_order' => 3],
        ])->each(fn (array $category) => LicenseCategory::updateOrCreate(['slug' => $category['slug']], $category + ['is_active' => true]));

        collect([
            ['number' => 7, 'title' => 'Sinalização', 'text' => 'Os sinais de trânsito destinam-se a regular e orientar a circulação rodoviária.'],
            ['number' => 18, 'title' => 'Distância entre veículos', 'text' => 'O condutor deve manter distância suficiente para evitar acidentes em caso de paragem súbita.'],
            ['number' => 24, 'title' => 'Princípios gerais de velocidade', 'text' => 'A velocidade deve ser regulada considerando as características da via, do veículo e as condições de circulação.'],
            ['number' => 30, 'title' => 'Regra geral de prioridade', 'text' => 'Nos cruzamentos e entroncamentos o condutor deve ceder passagem aos veículos que se apresentem pela direita.'],
            ['number' => 31, 'title' => 'Rotundas', 'text' => 'Ao entrar numa rotunda, o condutor deve ceder passagem aos veículos que nela circulam.'],
        ])->each(fn (array $article) => Article::updateOrCreate(['number' => $article['number']], $article + ['is_active' => true]));

        Sign::updateOrCreate(['slug' => 'curva-perigosa'], [
            'name' => 'Curva perigosa',
            'category' => 'perigo',
            'meaning' => 'Aviso de curva perigosa à frente.',
            'file_path' => '/images/signs/curva-perigosa.svg',
            'is_active' => true,
        ]);

        $this->call(DemoContentSeeder::class);

        $school = School::updateOrCreate(['code' => 'ESC-DEMO'], [
            'name' => 'Escola de Condução Segura', 'email' => 'escola@cartapro.co.mz', 'phone' => '+258 84 000 0000',
            'address' => 'Maputo', 'contact_person' => 'Responsável de Formação', 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'escola@cartapro.co.mz'], [
            'school_id' => $school->id, 'name' => 'Gestor Escola Segura', 'password' => 'Escola@2026', 'role' => 'school', 'is_active' => true,
        ]);
        $classroom = Classroom::updateOrCreate(['school_id' => $school->id, 'code' => 'TURMA-A'], ['name' => 'Turma A', 'year' => now()->year, 'is_active' => true]);
        $studentA = Student::firstOrCreate(['classroom_id' => $classroom->id, 'identifier' => 'AL-001'], ['name' => 'Ana Mucavele', 'phone' => '+258 84 111 1111', 'is_active' => true]);
        $studentB = Student::firstOrCreate(['classroom_id' => $classroom->id, 'identifier' => 'AL-002'], ['name' => 'Carlos Mondlane', 'phone' => '+258 85 222 2222', 'is_active' => true]);
        $approved = Question::where('status', 'approved')->orderBy('sort_order')->get();
        if ($approved->isNotEmpty()) {
            $exam = Exam::firstOrCreate(['school_id' => $school->id, 'name' => 'Prova de demonstração'], [
                'created_by' => User::where('email', 'admin@cartapro.co.mz')->value('id'), 'license_category' => 'ligeiro',
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
            'unlocked_at' => now()->subDays(2), 'is_active' => true, 'created_by' => User::where('email', 'admin@cartapro.co.mz')->value('id'),
        ]);
    }
}
