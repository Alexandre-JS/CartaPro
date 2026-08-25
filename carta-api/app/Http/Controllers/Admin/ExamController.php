<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\School;
use App\Models\Topic;
use App\Services\ExamBlueprint;
use App\Support\Grading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function __construct(private readonly ExamBlueprint $blueprints) {}

    public function index(Request $request): View
    {
        return view('admin.exams.index', [
            'exams' => Exam::with(['school', 'creator'])->withCount(['questions', 'sessions'])
                ->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id))
                ->latest()->paginate(10),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.exams.form', $this->formData($request, new Exam));
    }

    public function edit(Request $request, Exam $exam): View
    {
        $this->authorizeExam($request, $exam);

        return view('admin.exams.form', $this->formData($request, $exam->load('questions')));
    }

    /** Pesquisa paginada para o seletor de temas, sem despejar o catálogo inteiro no HTML. */
    public function topicOptions(Request $request): JsonResponse
    {
        $type = $request->string('type')->value();
        $category = $request->string('category')->value();
        $user = $request->user();

        $topics = Topic::where('is_active', true)
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->value().'%'))
            ->withCount(['questions as available_questions_count' => fn ($query) => $query
                ->where('status', 'approved')->where('is_active', true)
                ->when(in_array($type, ['teorico', 'pratico'], true), fn ($nested) => $nested->where('type', $type))
                ->when($category !== '', fn ($nested) => $nested->whereJsonContains('categories', $category))
                ->when($user->isSchool(), fn ($nested) => $nested->where(fn ($scope) => $scope
                    ->whereNull('school_id')->orWhere('school_id', $user->school_id)))])
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(30);

        return response()->json([
            'data' => $topics->getCollection()->map(fn (Topic $topic) => [
                'id' => $topic->id,
                'name' => $topic->name,
                'question_count' => $topic->available_questions_count,
            ])->values(),
            'next_page' => $topics->hasMorePages() ? $topics->currentPage() + 1 : null,
            'total' => $topics->total(),
        ]);
    }

    private function formData(Request $request, Exam $exam): array
    {
        $examScope = fn ($query) => $query->when($request->user()->isSchool(), fn ($nested) => $nested->where('school_id', $request->user()->school_id));
        $questions = Question::with(['topic', 'exams' => $examScope])->withCount(['exams' => $examScope])
            ->where('status', 'approved')->where('is_active', true)
            ->when($request->user()->isSchool(), fn ($query) => $query->where(fn ($nested) => $nested
                ->whereNull('school_id')->orWhere('school_id', $request->user()->school_id)))
            ->orderBy('topic_id')->orderBy('sort_order')->get();

        $selectedTopicIds = collect($request->old('blueprint_topic_ids', $exam->blueprint['topic_ids'] ?? []))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values();

        return [
            'exam' => $exam,
            // Provas já respondidas não deixam trocar as perguntas — ver
            // Exam::attempts(). O formulário mostra-as, mas travadas.
            'attemptCount' => $exam->exists ? $exam->attempts()->count() : 0,
            'schools' => School::where('is_active', true)->orderBy('name')->get(),
            'questions' => $questions,
            // Apenas os escolhidos entram no HTML; o restante catálogo é pesquisado sob demanda.
            'selectedTopics' => Topic::whereIn('id', $selectedTopicIds)
                ->withCount(['questions as available_questions_count' => fn ($query) => $query
                    ->where('status', 'approved')->where('is_active', true)])
                ->orderBy('name')->get(['id', 'name']),
            'defaultQuestionCount' => Grading::questionCount(),
            'passPercentage' => Grading::passPercentage(),
        ];
    }

    /**
     * Cria uma prova por seleção manual **ou** por blueprint (critérios +
     * sorteio do banco aprovado, distribuído pelos temas). O documento §7.2
     * previa o modo por critérios; só existia o manual, que obrigava a escola
     * a escolher 25 perguntas à mão em cada prova.
     */
    public function store(Request $request): RedirectResponse
    {
        $mode = $request->input('selection_mode', 'manual');
        $data = $this->validated($request, $mode);

        [$schoolId, $isPublic, $isLocked] = $this->access($request, $data);
        [$orderedQuestions, $blueprint] = $mode === 'blueprint'
            ? $this->questionsFromBlueprint($request, $data)
            : [$this->questionsFromSelection($request, $data), null];
        $this->assertPublicContent($isPublic, $orderedQuestions);

        $exam = Exam::create($this->attributes($data, $orderedQuestions, $blueprint, $mode) + [
            'school_id' => $schoolId,
            'created_by' => $request->user()->id,
            'is_public' => $isPublic,
            'is_locked' => $isLocked,
            'is_active' => true,
            'publication_status' => 'draft',
        ]);
        $exam->questions()->sync($orderedQuestions->mapWithKeys(fn ($question, $index) => [$question->id => ['sort_order' => $index + 1]])->all());

        return redirect()->route('admin.exams.index')->with(
            'status',
            $mode === 'blueprint'
                ? 'Prova gerada por critérios com '.$orderedQuestions->count().' perguntas sorteadas do banco aprovado.'
                : 'Prova criada com '.$orderedQuestions->count().' perguntas selecionadas.',
        );
    }

    /**
     * Actualiza uma prova existente.
     *
     * Faltava por completo — o recurso só expunha create/store/destroy, e
     * corrigir o nome ou a duração de uma prova obrigava a apagá-la e a
     * refazê-la, perdendo as sessões e os resultados pendurados nela.
     *
     * As perguntas são a excepção: depois de alguém responder, o conjunto fica
     * selado (ver Exam::attempts). O resto continua editável, porque mudar o
     * nome ou a duração de uma prova já aplicada não reescreve o passado.
     */
    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request, $exam);

        $selada = $exam->attempts()->exists();
        $mode = $selada ? null : $request->input('selection_mode', 'manual');
        $data = $this->validated($request, $mode, $exam);

        [$schoolId, $isPublic, $isLocked] = $this->access($request, $data);
        $this->assertPromotable($exam, $isPublic);
        $promovida = $isPublic && ! $exam->is_public;

        if ($selada) {
            $exam->update([
                'name' => $data['name'], 'type' => $exam->type, 'duration_minutes' => $data['duration_minutes'],
                'school_id' => $schoolId, 'is_public' => $isPublic, 'is_locked' => $isLocked,
            ] + $this->publicationReset($exam, $isPublic));

            return redirect()->route('admin.exams.index')
                ->with('status', 'Prova actualizada. As perguntas não foram tocadas: já há tentativas submetidas e alterá-las corrigiria essas provas contra outras perguntas.');
        }

        [$orderedQuestions, $blueprint] = $mode === 'blueprint'
            ? $this->questionsFromBlueprint($request, $data)
            : [$this->questionsFromSelection($request, $data), null];
        $this->assertPublicContent($isPublic, $orderedQuestions);

        $exam->update($this->attributes($data, $orderedQuestions, $blueprint, $mode) + [
            'school_id' => $schoolId,
            'is_public' => $isPublic,
            'is_locked' => $isLocked,
        ] + $this->publicationReset($exam, $isPublic));
        $exam->questions()->sync($orderedQuestions->mapWithKeys(fn ($question, $index) => [$question->id => ['sort_order' => $index + 1]])->all());

        return redirect()->route('admin.exams.index')->with('status', $this->updateMessage($exam->refresh(), $orderedQuestions->count(), $promovida));
    }

    /**
     * Uma prova que deixa de ser pública deixa de estar publicada.
     *
     * Sem isto o `publication_status` ficava a mentir: a prova saía do
     * aplicativo por deixar de ser pública, mas continuava marcada como
     * publicada — e voltar a torná-la pública repunha-a no catálogo de imediato,
     * sem passar pela revisão que o botão «Publicar no app» representa.
     *
     * @return array<string, mixed>
     */
    private function publicationReset(Exam $exam, bool $isPublic): array
    {
        return $exam->is_public && ! $isPublic
            ? ['publication_status' => 'draft', 'published_at' => null]
            : [];
    }

    private function updateMessage(Exam $exam, int $questionCount, bool $promovida): string
    {
        if ($promovida) {
            return 'Prova passada a pública com '.$questionCount.' perguntas e desligada da escola. Falta o passo final: carregue em «Publicar no app» para que fique visível no telemóvel.';
        }

        return $exam->publication_status === 'published'
            ? 'Prova actualizada com '.$questionCount.' perguntas. Está publicada: publique um novo pacote para actualizar a cópia offline.'
            : 'Prova actualizada com '.$questionCount.' perguntas.';
    }

    /**
     * Cópia pública de uma prova de escola, para o aplicativo.
     *
     * É o caminho para as provas já aplicadas em turmas, que não podem ser
     * promovidas no lugar (ver assertPromotable). Copia a configuração e as
     * perguntas, e deixa o original — e o histórico da escola — onde estava.
     */
    public function duplicatePublic(Exam $exam): RedirectResponse
    {
        abort_if($exam->is_public, 422, 'Esta prova já é pública.');

        $questions = $exam->questions()->orderByPivot('sort_order')->get();
        $this->assertPublicContent(true, $questions);

        $copia = Exam::create($exam->only([
            'license_category', 'license_categories', 'type', 'selection_mode', 'blueprint',
            'topic_ids', 'question_count', 'pass_score', 'duration_minutes',
        ]) + [
            'name' => Str::limit($exam->name, 140, '').' (cópia pública)',
            'school_id' => null,
            'created_by' => request()->user()->id,
            'is_active' => true,
            'is_public' => true,
            'is_locked' => false,
            'publication_status' => 'draft',
        ]);
        $copia->questions()->sync($questions->mapWithKeys(fn (Question $question, int $index) => [$question->id => ['sort_order' => $index + 1]])->all());

        return redirect()->route('admin.exams.index')
            ->with('status', 'Cópia pública criada com '.$questions->count().' perguntas. Carregue em «Publicar no app» para a tornar visível no telemóvel; a prova da escola ficou intacta.');
    }

    /** Campos derivados das perguntas — sempre recalculados, nunca herdados. */
    private function attributes(array $data, Collection $questions, ?array $blueprint, ?string $mode): array
    {
        $licenseCategories = $questions->pluck('categories')->flatten()->unique()->values()->all();
        $questionCount = $questions->count();
        $primaryCategory = $blueprint['category'] ?? ($licenseCategories[0] ?? 'ligeiro');

        return [
            'name' => $data['name'],
            'license_category' => $primaryCategory,
            'license_categories' => $licenseCategories,
            'type' => $data['type'],
            'selection_mode' => $mode,
            'blueprint' => $blueprint,
            'topic_ids' => $questions->pluck('topic_id')->unique()->values()->all(),
            'question_count' => $questionCount,
            // Nota de passagem vem da regra única (config/grading.php).
            'pass_score' => Grading::passScore($questionCount, $primaryCategory),
            'duration_minutes' => $data['duration_minutes'],
        ];
    }

    /** @return array{0: ?int, 1: bool, 2: bool} escola, pública, plano completo */
    private function access(Request $request, array $data): array
    {
        $schoolId = $request->user()->isSchool() ? $request->user()->school_id : ($data['school_id'] ?? null);
        $isPublic = $request->user()->isAdmin() && $data['visibility'] === 'public';
        // Só faz sentido numa prova que chega ao app; a privada é da escola.
        $isLocked = $isPublic && $request->boolean('is_locked');

        if ($isPublic) {
            // Passa a ser do catálogo e deixa de ter dono: uma escola não pode
            // editar nem apagar uma prova que está viva no aplicativo.
            $schoolId = null;
        }

        abort_unless($schoolId || $isPublic, 422, 'Selecione uma escola ou publique a prova para os utilizadores do app.');

        return [$schoolId, $isPublic, $isLocked];
    }

    /**
     * Uma prova pública não pode levar perguntas privadas de uma escola.
     *
     * O banco tem perguntas sem dono — o material do INATRO — e perguntas
     * criadas por uma escola, que só a ela pertencem. O selector mostra ambas
     * ao administrador, e nada impedia que uma prova feita para uma escola
     * fosse promovida a pública levando atrás o material dessa escola para
     * todos os utilizadores do aplicativo.
     */
    private function assertPublicContent(bool $isPublic, Collection $questions): void
    {
        if (! $isPublic) {
            return;
        }

        $privadas = $questions->filter(fn (Question $question) => $question->school_id !== null);

        abort_if($privadas->isNotEmpty(), 422, 'Uma prova pública não pode incluir perguntas privadas de uma escola: '
            .$privadas->pluck('external_id')->join(', ').'. Substitua-as por perguntas do banco geral.');
    }

    /**
     * A promoção a pública só é segura enquanto a prova não foi aplicada.
     *
     * Tornar pública anula o `school_id`, e é por esse campo que a escola vê as
     * suas provas, sessões e resultados (ver ResultController e
     * ExamSessionController). Converter uma prova já aplicada em turmas
     * retirava à escola, em silêncio, o acesso aos resultados dos seus próprios
     * alunos — daí o caminho ser antes uma cópia pública, que deixa a prova da
     * escola e o seu histórico onde estão.
     */
    private function assertPromotable(Exam $exam, bool $isPublic): void
    {
        abort_if(
            $isPublic && ! $exam->is_public && $exam->sessions()->exists(),
            422,
            'Esta prova já foi aplicada em turmas da escola. Publicá-la no aplicativo retirava à escola o acesso aos resultados dos seus alunos — use antes «Publicar cópia no app», que mantém esta prova e o histórico intactos.',
        );
    }

    /** @param  ?string  $mode  Nulo numa prova selada, onde as perguntas não são revalidadas. */
    private function validated(Request $request, ?string $mode): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'type' => ['required', 'in:teorico,pratico'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
            'visibility' => ['required', 'in:public,private'],
            'is_locked' => ['nullable', 'boolean'],
            'selection_mode' => ['nullable', 'in:manual,blueprint'],
            // Modo manual
            'question_ids' => [$mode === 'manual' ? 'required' : 'nullable', 'array', 'min:1', 'max:100'],
            'question_ids.*' => ['integer', 'distinct', 'exists:questions,id'],
            // Modo blueprint
            'blueprint_category' => [$mode === 'blueprint' ? 'required' : 'nullable', 'in:ligeiro,pesado,profissional_publico'],
            'blueprint_question_count' => [$mode === 'blueprint' ? 'required' : 'nullable', 'integer', 'min:1', 'max:100'],
            'blueprint_topic_ids' => ['nullable', 'array'],
            'blueprint_topic_ids.*' => ['integer', 'exists:topics,id'],
        ]);
    }

    private function questionsFromSelection(Request $request, array $data): Collection
    {
        $questions = Question::whereIn('id', $data['question_ids'])->where('status', 'approved')->where('is_active', true)
            ->where('type', $data['type'])
            ->when($request->user()->isSchool(), fn ($query) => $query->where(fn ($nested) => $nested
                ->whereNull('school_id')->orWhere('school_id', $request->user()->school_id)))
            ->get()->keyBy('id');
        abort_if($questions->count() !== count($data['question_ids']), 422, 'A prova contém perguntas indisponíveis, não aprovadas ou de outro tipo.');

        return collect($data['question_ids'])->map(fn ($id) => $questions->get((int) $id));
    }

    /** @return array{0: Collection, 1: array} */
    private function questionsFromBlueprint(Request $request, array $data): array
    {
        $blueprint = [
            'category' => $data['blueprint_category'],
            'type' => $data['type'],
            'question_count' => (int) $data['blueprint_question_count'],
            'topic_ids' => $data['blueprint_topic_ids'] ?? [],
        ];

        $questions = $this->blueprints->build($blueprint, $request->user());

        abort_if($questions->isEmpty(), 422, 'Não há perguntas aprovadas que cumpram estes critérios.');

        if ($questions->count() < $blueprint['question_count']) {
            session()->flash('warning', 'O banco aprovado só tem '.$questions->count().' perguntas para estes critérios; a prova foi criada com esse número.');
        }

        return [$questions, $blueprint];
    }

    public function destroy(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeExam($request, $exam);
        $exam->delete();

        return back()->with('status', 'Prova removida.');
    }

    private function authorizeExam(Request $request, Exam $exam): void
    {
        abort_if($request->user()->isSchool() && $exam->school_id !== $request->user()->school_id, 403);
    }

    public function publish(Exam $exam): RedirectResponse
    {
        abort_unless($exam->is_public, 422, 'Somente provas públicas podem ser publicadas no aplicativo.');
        abort_if($exam->questions()->count() === 0, 422, 'Adicione perguntas antes de publicar a prova.');
        $exam->update(['publication_status' => 'published', 'published_at' => now(), 'is_active' => true]);

        return back()->with('status', 'Prova publicada no aplicativo. Publique um novo pacote para atualizar também a cópia offline.');
    }

    /**
     * Prova gratuita ou do plano completo.
     *
     * Continua a alternar a partir da listagem, em vez de obrigar a abrir o
     * formulário: é um interruptor que se usa em série ao rever o catálogo.
     */
    public function plan(Exam $exam): RedirectResponse
    {
        abort_unless($exam->is_public, 422, 'Só as provas públicas chegam ao aplicativo.');

        $exam->update(['is_locked' => ! $exam->is_locked]);

        return back()->with('status', $exam->is_locked
            ? 'Prova marcada como plano completo. Publique um novo pacote para atualizar a cópia offline.'
            : 'Prova aberta ao plano gratuito. Publique um novo pacote para atualizar a cópia offline.');
    }

    public function archive(Exam $exam): RedirectResponse
    {
        abort_unless($exam->is_public, 422, 'Somente provas públicas possuem publicação no aplicativo.');
        $exam->update(['publication_status' => 'archived']);

        return back()->with('status', 'Prova arquivada e removida do catálogo online.');
    }
}
