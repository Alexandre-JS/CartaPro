<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Classroom;
use App\Models\ContentPackage;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSession;
use App\Models\GlossaryTerm;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\School;
use App\Models\Sign;
use App\Models\Topic;
use App\Models\Unlock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DetailController extends Controller
{
    public function question(Request $request, Question $question): View
    {
        abort_if($request->user()->isSchool() && $question->school_id !== $request->user()->school_id, 403);
        $question->load(['topic', 'author', 'school', 'article', 'sign']);

        return $this->detail('Pergunta '.$question->external_id, 'Consulta completa da pergunta.', route('admin.questions.index'), [
            'Enunciado' => $question->statement, 'Tema' => $question->topic->name, 'Tipo' => ucfirst($question->type),
            'Categorias' => implode(', ', $question->categories), 'Estado' => $this->status($question->status),
            'Autor' => $question->author?->name ?? 'Sistema', 'Escola' => $question->school?->name ?? 'Autoria interna',
            'Explicação' => $question->explanation, 'Artigo' => $question->article ? 'Artigo '.$question->article->number.' — '.$question->article->title : ($question->article_ref ? 'Artigo '.$question->article_ref : '—'),
            'Sinal/Imagem' => $question->sign?->name ?? $question->image ?? '—', 'Acesso' => $question->is_locked ? 'Bloqueada' : 'Livre',
            'Atualizada em' => $question->updated_at->format('d/m/Y H:i'),
        ], $request->user()->isAdmin() || $question->status !== 'approved' ? route('admin.questions.edit', $question) : null, $question->options, $question->correct_index, $question->image);
    }

    public function topic(Request $request, Topic $topic): View
    {
        return $this->detail($topic->name, 'Detalhes do tema.', route('admin.topics.index'), ['Identificador' => $topic->slug, 'Descrição' => $topic->description ?: '—', 'Perguntas' => $topic->questions()->count(), 'Ordem' => $topic->sort_order, 'Estado' => $topic->is_active ? 'Ativo' : 'Inativo'], $request->user()->isAdmin() ? route('admin.topics.edit', $topic) : null);
    }

    public function sign(Request $request, Sign $sign): View
    {
        return $this->detail($sign->name, 'Detalhes do sinal.', route('admin.signs.index'), ['Identificador' => $sign->slug, 'Categoria' => ucfirst($sign->category), 'Significado' => $sign->meaning, 'Estado' => $sign->is_active ? 'Ativo' : 'Inativo'], $request->user()->isAdmin() ? route('admin.signs.edit', $sign) : null, image: $sign->file_path);
    }

    public function article(Request $request, Article $article): View
    {
        return $this->detail('Artigo '.$article->number, $article->title, route('admin.articles.index'), ['Número' => $article->number, 'Título' => $article->title, 'Texto integral' => $article->text, 'Estado' => $article->is_active ? 'Ativo' : 'Inativo'], $request->user()->isAdmin() ? route('admin.articles.edit', $article) : null);
    }

    /**
     * Ficha de estudo.
     *
     * As fichas eram o único conteúdo do painel sem página de detalhe: as
     * escolas viam a lista mas nunca o corpo da ficha, e o URL `admin/lessons/2`
     * — a forma que todos os outros recursos usam — respondia 405.
     */
    public function lesson(Request $request, Lesson $lesson): View
    {
        $lesson->load('topic', 'creator');

        $sinais = Sign::whereIn('slug', $lesson->sign_slugs ?: [])->orderBy('name')->pluck('name', 'slug');
        $artigos = Article::whereIn('number', $lesson->article_numbers ?: [])->orderBy('number')->get();

        return $this->detail($lesson->title, $lesson->summary ?: 'Ficha de estudo.', route('admin.lessons.index'), [
            'Identificador' => $lesson->slug,
            'Área de estudo' => $lesson->grupoNome(),
            'Tema' => $lesson->topic?->name ?? '—',
            'Categorias de carta' => $lesson->license_categories ? implode(', ', $lesson->license_categories) : 'Todas',
            'Tempo de leitura' => $lesson->reading_minutes.' min',
            'Sinais ligados' => $sinais->isNotEmpty()
                ? $sinais->map(fn (string $nome, string $slug) => $nome.' ('.$slug.')')->implode("\n")
                : '—',
            'Artigos ligados' => $artigos->isNotEmpty()
                ? $artigos->map(fn (Article $artigo) => 'Artigo '.$artigo->number.' — '.$artigo->title)->implode("\n")
                : '—',
            'Texto da ficha' => $lesson->body,
            'Criada por' => $lesson->creator?->name ?? '—',
            'Estado' => ($lesson->is_active ? 'Ativa' : 'Inativa').($lesson->is_locked ? ' · só plano completo' : ''),
        ], $request->user()->isAdmin() ? route('admin.lessons.edit', $lesson) : null);
    }

    public function glossaryTerm(Request $request, GlossaryTerm $term): View
    {
        return $this->detail($term->term, 'Termo do glossário.', route('admin.glossary.index'), [
            'Identificador' => $term->slug,
            'Definição' => $term->definition,
            'Artigo de referência' => $term->article_ref ? 'Artigo '.$term->article_ref : '—',
            'Estado' => $term->is_active ? 'Ativo' : 'Inativo',
        ]);
    }

    public function school(School $school): View
    {
        $school->load('users')->loadCount('classrooms');

        return $this->detail($school->name, 'Dados da escola parceira.', route('admin.schools.index'), ['Código' => $school->code, 'Contacto responsável' => $school->contact_person ?: '—', 'Email' => $school->email ?: '—', 'Telefone' => $school->phone ?: '—', 'Endereço' => $school->address ?: '—', 'Contas associadas' => $school->users->pluck('name')->join(', ') ?: '—', 'Turmas' => $school->classrooms_count, 'Estado' => $school->is_active ? 'Ativa' : 'Inativa'], route('admin.schools.edit', $school));
    }

    public function user(User $user): View
    {
        $user->load('school');

        return $this->detail($user->name, 'Conta de acesso ao painel.', route('admin.users.index'), ['Email' => $user->email, 'Papel' => $user->isAdmin() ? 'Administrador' : 'Escola', 'Escola' => $user->school?->name ?? '—', 'Estado' => $user->is_active ? 'Ativo' : 'Inativo', 'Criado em' => $user->created_at->format('d/m/Y H:i')], route('admin.users.edit', $user));
    }

    public function classroom(Request $request, Classroom $classroom): View
    {
        abort_if($request->user()->isSchool() && $classroom->school_id !== $request->user()->school_id, 403);
        $classroom->load(['school', 'students'])->loadCount('sessions');

        return $this->detail($classroom->name, 'Turma e alunos associados.', route('admin.classrooms.index'), ['Escola' => $classroom->school->name, 'Código' => $classroom->code, 'Ano' => $classroom->year ?: '—', 'Alunos' => $classroom->students->pluck('name')->join(', ') ?: 'Sem alunos', 'Sessões' => $classroom->sessions_count, 'Estado' => $classroom->is_active ? 'Ativa' : 'Inativa']);
    }

    public function exam(Request $request, Exam $exam): View
    {
        $this->assertExamAccess($request, $exam);
        $exam->load(['school', 'creator', 'questions'])->loadCount('sessions');

        return $this->detail($exam->name, 'Configuração e perguntas da prova.', route('admin.exams.index'), ['Acesso' => $exam->is_public ? 'Pública — aplicativo' : 'Privada — escola', 'Escola' => $exam->school?->name ?? 'Não associada', 'Criada por' => $exam->creator?->name ?? '—', 'Categorias' => implode(', ', $exam->license_categories ?: [$exam->license_category]), 'Tipo' => ucfirst($exam->type), 'Perguntas selecionadas' => $exam->questions->map(fn ($question, $index) => ($index + 1).'. '.$question->statement)->join("\n"), 'Nota de passagem' => $exam->pass_score.'/'.$exam->question_count.' (72%)', 'Sessões' => $exam->sessions_count, 'Estado' => $exam->is_active ? 'Ativa' : 'Inativa']);
    }

    public function session(Request $request, ExamSession $session): View
    {
        $session->load(['exam.school', 'classroom', 'attempts.student']);
        $this->assertExamAccess($request, $session->exam);

        return $this->detail('Sessão '.$session->code, 'Aplicação de prova.', route('admin.sessions.index'), ['Prova' => $session->exam->name, 'Escola' => $session->exam->school?->name ?? '—', 'Turma' => $session->classroom->name, 'Estado' => $this->status($session->status), 'Submissões' => $session->attempts->count(), 'Alunos submetidos' => $session->attempts->pluck('student.name')->join(', ') ?: 'Nenhum', 'Início' => $session->starts_at?->format('d/m/Y H:i') ?? '—', 'Fim' => $session->ends_at?->format('d/m/Y H:i') ?? '—']);
    }

    public function result(Request $request, ExamAttempt $attempt): View
    {
        $attempt->load(['student', 'session.exam.school', 'session.exam.questions.topic', 'session.classroom']);
        $this->assertExamAccess($request, $attempt->session->exam);
        $answers = $attempt->answers ?? [];
        $answerReview = $attempt->session->exam->questions->map(function (Question $question) use ($answers): array {
            $hasAnswer = array_key_exists($question->external_id, $answers);
            $selectedIndex = $hasAnswer ? (int) $answers[$question->external_id] : null;

            return [
                'question' => $question,
                'selected_index' => $selectedIndex,
                'selected_answer' => $selectedIndex !== null ? ($question->options[$selectedIndex] ?? 'Opção inválida') : 'Não respondeu',
                'correct_answer' => $question->options[$question->correct_index] ?? '—',
                'is_correct' => $selectedIndex !== null && $selectedIndex === $question->correct_index,
            ];
        });

        return view('admin.results.show', [
            'attempt' => $attempt,
            'answerReview' => $answerReview,
            'percentage' => $attempt->percentage(),
            'values' => $attempt->gradeValues(),
        ]);
    }

    public function publication(ContentPackage $package): View
    {
        $package->load('publisher');

        return $this->detail('Pacote '.$package->version, 'Detalhes da publicação.', route('admin.publications.index'), ['Versão' => $package->version, 'Estado' => $package->status === 'published' ? 'Publicado' : 'Arquivado', 'Perguntas' => $package->question_count, 'Publicado por' => $package->publisher?->name ?? '—', 'Data' => $package->published_at?->format('d/m/Y H:i') ?? '—', 'Notas' => $package->notes ?: '—', 'Ficheiro' => $package->file_path ?: '—']);
    }

    public function unlock(Unlock $unlock): View
    {
        $unlock->load('creator');

        return $this->detail($unlock->phone, 'Detalhes do desbloqueio.', route('admin.unlocks.index'), ['Plano' => $unlock->plan, 'Método' => strtoupper($unlock->payment_method ?: '—'), 'Referência' => $unlock->payment_reference ?: '—', 'Desbloqueado em' => $unlock->unlocked_at->format('d/m/Y H:i'), 'Expira em' => $unlock->expires_at?->format('d/m/Y H:i') ?? 'Sem expiração', 'Registado por' => $unlock->creator?->name ?? '—', 'Notas' => $unlock->notes ?: '—', 'Estado' => $unlock->is_active ? 'Ativo' : 'Inativo']);
    }

    private function detail(string $title, string $subtitle, string $backUrl, array $fields, ?string $editUrl = null, array $options = [], ?int $correctIndex = null, ?string $image = null): View
    {
        return view('admin.details.show', compact('title', 'subtitle', 'backUrl', 'fields', 'editUrl', 'options', 'correctIndex', 'image'));
    }

    private function status(string $status): string
    {
        return ['draft' => 'Rascunho', 'review' => 'Em revisão', 'approved' => 'Aprovada', 'rejected' => 'Rejeitada', 'scheduled' => 'Agendada', 'in_progress' => 'Em curso', 'finished' => 'Terminada'][$status] ?? ucfirst($status);
    }

    private function assertExamAccess(Request $request, Exam $exam): void
    {
        abort_if($request->user()->isSchool() && $exam->school_id !== $request->user()->school_id, 403);
    }
}
