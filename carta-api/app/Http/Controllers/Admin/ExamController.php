<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\School;
use App\Models\Topic;
use App\Services\ExamBlueprint;
use App\Support\Grading;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function __construct(private readonly ExamBlueprint $blueprints) {}

    public function index(Request $request): View
    {
        return view('admin.exams.index', [
            'exams' => Exam::with(['school', 'creator'])->withCount(['questions', 'sessions'])
                ->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id))
                ->latest()->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        $examScope = fn ($query) => $query->when($request->user()->isSchool(), fn ($nested) => $nested->where('school_id', $request->user()->school_id));
        $questions = Question::with(['topic', 'exams' => $examScope])->withCount(['exams' => $examScope])
            ->where('status', 'approved')->where('is_active', true)
            ->when($request->user()->isSchool(), fn ($query) => $query->where(fn ($nested) => $nested
                ->whereNull('school_id')->orWhere('school_id', $request->user()->school_id)))
            ->orderBy('topic_id')->orderBy('sort_order')->get();

        return view('admin.exams.form', [
            'schools' => School::where('is_active', true)->orderBy('name')->get(),
            'questions' => $questions,
            // Para o modo por critérios (blueprint).
            'topics' => Topic::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'defaultQuestionCount' => Grading::questionCount(),
            'passPercentage' => Grading::passPercentage(),
        ]);
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

        $data = $request->validate([
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

        $schoolId = $request->user()->isSchool() ? $request->user()->school_id : ($data['school_id'] ?? null);
        $isPublic = $request->user()->isAdmin() && $data['visibility'] === 'public';
        // Só faz sentido numa prova que chega ao app; a privada é da escola.
        $isLocked = $isPublic && $request->boolean('is_locked');
        if ($isPublic) {
            $schoolId = null;
        }
        abort_unless($schoolId || $isPublic, 422, 'Selecione uma escola ou publique a prova para os utilizadores do app.');

        [$orderedQuestions, $blueprint] = $mode === 'blueprint'
            ? $this->questionsFromBlueprint($request, $data)
            : [$this->questionsFromSelection($request, $data), null];

        $licenseCategories = $orderedQuestions->pluck('categories')->flatten()->unique()->values()->all();
        $topicIds = $orderedQuestions->pluck('topic_id')->unique()->values()->all();
        $questionCount = $orderedQuestions->count();
        $primaryCategory = $blueprint['category'] ?? ($licenseCategories[0] ?? 'ligeiro');

        $exam = Exam::create([
            'school_id' => $schoolId, 'created_by' => $request->user()->id, 'name' => $data['name'],
            'license_category' => $primaryCategory, 'license_categories' => $licenseCategories, 'type' => $data['type'],
            'selection_mode' => $mode, 'blueprint' => $blueprint,
            'topic_ids' => $topicIds, 'question_count' => $questionCount,
            // Nota de passagem vem da regra única (config/grading.php).
            'pass_score' => Grading::passScore($questionCount, $primaryCategory), 'is_active' => true,
            'duration_minutes' => $data['duration_minutes'], 'is_public' => $isPublic,
            'is_locked' => $isLocked,
            'publication_status' => 'draft',
        ]);
        $exam->questions()->sync($orderedQuestions->mapWithKeys(fn ($question, $index) => [$question->id => ['sort_order' => $index + 1]])->all());

        return redirect()->route('admin.exams.index')->with(
            'status',
            $mode === 'blueprint'
                ? 'Prova gerada por critérios com '.$questionCount.' perguntas sorteadas do banco aprovado.'
                : 'Prova criada com '.$questionCount.' perguntas selecionadas.',
        );
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
        abort_if($request->user()->isSchool() && $exam->school_id !== $request->user()->school_id, 403);
        $exam->delete();

        return back()->with('status', 'Prova removida.');
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
     * Alterna em vez de editar porque as provas não têm formulário de edição —
     * o recurso só expõe create/store/destroy —, e obrigar a apagar e recriar
     * uma prova só para mudar o plano seria absurdo.
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
