<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Classroom;
use App\Models\ContentPackage;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\LicenseCategory;
use App\Models\Plan;
use App\Models\Question;
use App\Models\School;
use App\Models\Sign;
use App\Models\Topic;
use App\Models\Unlock;
use App\Models\User;
use App\Services\ClassroomAnalytics;
use App\Services\PackagePublisher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManagementController extends Controller
{
    public function questions(Request $request): JsonResponse
    {
        $items = Question::with(['topic', 'author:id,name', 'school:id,name', 'article', 'sign'])
            ->when($request->user()->isSchool(), fn (Builder $query) => $query->where('school_id', $request->user()->school_id))
            ->when($request->filled('estado'), fn (Builder $query) => $query->where('status', $request->string('estado')))
            ->when($request->filled('tema'), fn (Builder $query) => $query->where('topic_id', $request->integer('tema')))
            ->when($request->filled('tipo'), fn (Builder $query) => $query->where('type', $request->string('tipo')))
            ->when($request->filled('q'), fn (Builder $query) => $query->where('statement', 'like', '%'.$request->string('q').'%'))
            ->latest()->paginate($request->integer('por_pagina', 20));

        return response()->json($items);
    }

    public function storeQuestion(Request $request): JsonResponse
    {
        $data = $this->questionData($request);
        $data['author_id'] = $request->user()->id;
        $data['school_id'] = $request->user()->school_id;
        $data['status'] = $request->user()->isSchool() ? 'review' : ($data['status'] ?? 'draft');

        return response()->json(Question::create($data)->load(['topic', 'author', 'school']), 201);
    }

    public function updateQuestion(Request $request, Question $question): JsonResponse
    {
        $this->assertQuestionAccess($request, $question);
        $data = $this->questionData($request, $question);
        if ($request->user()->isSchool()) {
            $data['status'] = 'review';
            $data['rejection_reason'] = null;
        }
        $question->update($data);

        return response()->json($question->fresh(['topic', 'author', 'school']));
    }

    public function approveQuestion(Request $request, Question $question): JsonResponse
    {
        $question->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'rejection_reason' => null]);

        return response()->json($question->fresh());
    }

    public function rejectQuestion(Request $request, Question $question): JsonResponse
    {
        $reason = $request->validate(['motivo' => ['required', 'string', 'max:1000']])['motivo'];
        $question->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'rejection_reason' => $reason]);

        return response()->json($question->fresh());
    }

    public function signs(Request $request): JsonResponse
    {
        return response()->json(Sign::query()->when($request->filled('q'), fn (Builder $q) => $q->where('name', 'like', '%'.$request->string('q').'%'))->orderBy('name')->paginate(30));
    }

    public function storeSign(Request $request): JsonResponse
    {
        return response()->json(Sign::create($this->signData($request)), 201);
    }

    public function updateSign(Request $request, Sign $sign): JsonResponse
    {
        $sign->update($this->signData($request, $sign));

        return response()->json($sign->fresh());
    }

    public function articles(Request $request): JsonResponse
    {
        return response()->json(Article::query()->when($request->filled('q'), fn (Builder $q) => $q->where('title', 'like', '%'.$request->string('q').'%'))->orderBy('number')->paginate(30));
    }

    public function importArticles(Request $request): JsonResponse
    {
        $items = $request->validate(['artigos' => ['required', 'array'], 'artigos.*.numero' => ['required', 'integer'], 'artigos.*.titulo' => ['required', 'string'], 'artigos.*.texto' => ['required', 'string']])['artigos'];
        foreach ($items as $item) {
            Article::updateOrCreate(['number' => $item['numero']], ['title' => $item['titulo'], 'text' => $item['texto'], 'is_active' => true]);
        }

        return response()->json(['importados' => count($items)]);
    }

    public function topics(): JsonResponse
    {
        return response()->json(Topic::withCount('questions')->orderBy('sort_order')->get());
    }

    public function storeTopic(Request $request): JsonResponse
    {
        return response()->json(Topic::create($this->taxonomyData($request, 'topics')), 201);
    }

    public function updateTopic(Request $request, Topic $topic): JsonResponse
    {
        $topic->update($this->taxonomyData($request, 'topics', $topic->id));

        return response()->json($topic->fresh());
    }

    public function categories(): JsonResponse
    {
        return response()->json(LicenseCategory::orderBy('sort_order')->get());
    }

    public function storeCategory(Request $request): JsonResponse
    {
        return response()->json(LicenseCategory::create($this->taxonomyData($request, 'license_categories')), 201);
    }

    public function updateCategory(Request $request, LicenseCategory $category): JsonResponse
    {
        $category->update($this->taxonomyData($request, 'license_categories', $category->id));

        return response()->json($category->fresh());
    }

    public function publish(Request $request, PackagePublisher $publisher): JsonResponse
    {
        // Delega no serviço único: esta via escrevia o pacote em public/packages
        // (acessível sem autenticação) e com um payload diferente do do painel.
        return response()->json($publisher->publish($request->user(), $request->input('notas')), 201);
    }

    public function managedPackages(): JsonResponse
    {
        return response()->json(ContentPackage::with('publisher:id,name')->latest('published_at')->paginate(20));
    }

    public function schools(): JsonResponse
    {
        return response()->json(School::withCount(['users'])->latest()->paginate(20));
    }

    public function plans(): JsonResponse
    {
        return response()->json(Plan::orderBy('sort_order')->get());
    }

    public function updatePlan(Request $request, Plan $plan): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', 'max:80'],
            'is_purchasable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $plan->update($data);

        return response()->json($plan->fresh());
    }

    public function storeSchool(Request $request): JsonResponse
    {
        return response()->json(School::create($this->schoolData($request)), 201);
    }

    public function updateSchool(Request $request, School $school): JsonResponse
    {
        $school->update($this->schoolData($request, $school));

        return response()->json($school->fresh());
    }

    public function users(): JsonResponse
    {
        return response()->json(User::with('school:id,name')->latest()->paginate(20));
    }

    public function storeUser(Request $request): JsonResponse
    {
        return response()->json(User::create($this->userData($request)), 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $user->update($this->userData($request, $user));

        return response()->json($user->fresh('school'));
    }

    public function unlocks(Request $request): JsonResponse
    {
        return response()->json(Unlock::when($request->filled('q'), fn (Builder $q) => $q->where('phone', 'like', '%'.$request->string('q').'%'))->latest('unlocked_at')->paginate(20));
    }

    public function storeUnlock(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30', 'unique:unlocks'], 'plan' => ['required', Rule::in([Plan::PLUS, Plan::LEGACY_COMPLETE])], 'payment_method' => ['nullable', 'string'], 'payment_reference' => ['nullable', 'string', 'unique:unlocks'], 'expires_at' => ['nullable', 'date']]);

        $data['plan'] = Plan::canonical($data['plan']);

        return response()->json(Unlock::create($data + ['unlocked_at' => now(), 'is_active' => true, 'created_by' => $request->user()->id]), 201);
    }

    public function classrooms(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);

        return response()->json($school->classrooms()->withCount('students')
            ->when($request->user()->isInstructor(), fn (Builder $query) => $query->whereHas('instructors', fn (Builder $instructors) => $instructors->where('user_id', $request->user()->id)))
            ->get());
    }

    public function storeClassroom(Request $request, School $school): JsonResponse
    {
        $this->assertSchoolAccess($request, $school);
        $data = $request->validate(['name' => ['required', 'string'], 'code' => ['required', 'alpha_dash', Rule::unique('classrooms')->where('school_id', $school->id)], 'year' => ['nullable', 'integer']]);

        return response()->json($school->classrooms()->create($data + ['is_active' => true]), 201);
    }

    public function students(Request $request, Classroom $classroom): JsonResponse
    {
        $this->assertClassroomAccess($request, $classroom);

        return response()->json($classroom->students()->get());
    }

    public function storeStudent(Request $request, Classroom $classroom): JsonResponse
    {
        $this->assertClassroomAccess($request, $classroom);
        $data = $request->validate(['name' => ['required', 'string'], 'identifier' => ['nullable', 'string'], 'phone' => ['nullable', 'string']]);

        return response()->json($classroom->students()->create($data + ['is_active' => true]), 201);
    }

    public function exams(Request $request): JsonResponse
    {
        return response()->json(Exam::withCount(['questions', 'sessions'])->when($request->user()->isSchool(), fn (Builder $q) => $q->where('school_id', $request->user()->school_id))->latest()->paginate(20));
    }

    public function storeExam(Request $request): JsonResponse
    {
        $data = $request->validate(['school_id' => ['nullable', 'exists:schools,id'], 'name' => ['required', 'string'], 'type' => ['required', 'in:teorico,pratico'], 'question_ids' => ['required', 'array', 'min:1', 'max:100'], 'question_ids.*' => ['integer', 'distinct', 'exists:questions,id'], 'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:300'], 'visibility' => ['required', 'in:public,private']]);
        $isPublic = $request->user()->isAdmin() && $data['visibility'] === 'public';
        $schoolId = $isPublic ? null : ($request->user()->isSchool() ? $request->user()->school_id : ($data['school_id'] ?? null));
        abort_unless($schoolId || $isPublic, 422, 'Selecione uma escola para a prova privada.');
        $questions = Question::whereIn('id', $data['question_ids'])->where(['status' => 'approved', 'is_active' => true])->where('type', $data['type'])
            ->when($request->user()->isSchool(), fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->whereNull('school_id')->orWhere('school_id', $request->user()->school_id)))
            ->get()->keyBy('id');
        abort_if($questions->count() !== count($data['question_ids']), 422, 'A prova contém perguntas indisponíveis, não aprovadas ou de outro tipo.');
        $orderedQuestions = collect($data['question_ids'])->map(fn ($id) => $questions->get((int) $id));
        $categories = $orderedQuestions->pluck('categories')->flatten()->unique()->values();
        $questionCount = $orderedQuestions->count();
        $exam = Exam::create(['school_id' => $schoolId, 'created_by' => $request->user()->id, 'name' => $data['name'], 'license_category' => $categories->first(), 'license_categories' => $categories->all(), 'type' => $data['type'], 'topic_ids' => $orderedQuestions->pluck('topic_id')->unique()->values()->all(), 'question_count' => $questionCount, 'pass_score' => (int) ceil($questionCount * 0.72), 'duration_minutes' => $data['duration_minutes'] ?? 60, 'is_public' => $isPublic, 'publication_status' => 'draft', 'is_active' => true]);
        $exam->questions()->sync($orderedQuestions->mapWithKeys(fn ($question, $index) => [$question->id => ['sort_order' => $index + 1]])->all());

        return response()->json($exam->load('questions'), 201);
    }

    public function applyExam(Request $request, Exam $exam): JsonResponse
    {
        $this->assertExamAccess($request, $exam);
        $classroom = Classroom::whereKey($request->validate(['classroom_id' => ['required', 'exists:classrooms,id']])['classroom_id'])->where('school_id', $exam->school_id)->firstOrFail();
        abort_unless($request->user()->canAccessClassroom($classroom), 403);
        do {
            $code = Str::upper(Str::random(6));
        } while (ExamSession::where('code', $code)->exists());

        return response()->json(ExamSession::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id, 'code' => $code, 'status' => 'in_progress', 'starts_at' => now()]), 201);
    }

    public function examResults(Request $request, Exam $exam): JsonResponse
    {
        $this->assertExamAccess($request, $exam);

        return response()->json($exam->sessions()->with(['classroom', 'attempts.student'])
            ->when($request->user()->isInstructor(), fn (Builder $query) => $query->whereHas('classroom.instructors', fn (Builder $instructors) => $instructors->where('user_id', $request->user()->id)))
            ->get());
    }

    /**
     * Analítica da turma para a escola: médias, temas onde a turma erra mais,
     * evolução por sessão e quem está pronto. O documento §7.4 chama a isto
     * "o valor que a escola paga" e não existia em nenhuma via.
     */
    public function classroomAnalytics(Request $request, Classroom $classroom, ClassroomAnalytics $analytics): JsonResponse
    {
        $this->assertClassroomAccess($request, $classroom);

        return response()->json([
            'turma' => ['id' => $classroom->id, 'nome' => $classroom->name],
            'resumo' => $analytics->summary($classroom),
            'temasMaisFalhados' => $analytics->weakestTopics($classroom),
            'evolucao' => $analytics->progressBySession($classroom),
            'prontidao' => $analytics->studentReadiness($classroom),
        ]);
    }

    private function questionData(Request $request, ?Question $question = null): array
    {
        $data = $request->validate(['topic_id' => ['required', 'exists:topics,id'], 'external_id' => ['required', 'alpha_dash', Rule::unique('questions')->ignore($question)], 'type' => ['required', 'in:teorico,pratico'], 'categories' => ['required', 'array', 'min:1'], 'categories.*' => ['exists:license_categories,slug'], 'statement' => ['required', 'string'], 'options' => ['required', 'array', 'min:2'], 'options.*' => ['required', 'string'], 'correct_index' => ['required', 'integer', 'min:0'], 'explanation' => ['required', 'string'], 'article_id' => ['nullable', 'exists:articles,id'], 'sign_id' => ['nullable', 'exists:signs,id'], 'is_locked' => ['boolean'], 'is_active' => ['boolean'], 'status' => ['nullable', 'in:draft,review,approved']]);
        abort_if($data['correct_index'] >= count($data['options']), 422, 'A opção correta não existe.');
        if ($data['article_id'] ?? null) {
            $data['article_ref'] = Article::find($data['article_id'])->number;
        }
        if ($data['sign_id'] ?? null) {
            $data['image'] = Sign::find($data['sign_id'])->file_path;
        }

        return $data + ['is_active' => true, 'is_locked' => false, 'sort_order' => 0];
    }

    private function signData(Request $request, ?Sign $sign = null): array
    {
        return $request->validate(['name' => ['required', 'string'], 'slug' => ['required', 'alpha_dash', Rule::unique('signs')->ignore($sign)], 'category' => ['required', 'string'], 'meaning' => ['required', 'string'], 'file_path' => ['required', 'string'], 'is_active' => ['boolean']]);
    }

    private function taxonomyData(Request $request, string $table, ?int $id = null): array
    {
        return $request->validate(['name' => ['required', 'string'], 'slug' => ['required', 'alpha_dash', Rule::unique($table)->ignore($id)], 'description' => ['nullable', 'string'], 'sort_order' => ['integer'], 'is_active' => ['boolean']]);
    }

    private function schoolData(Request $request, ?School $school = null): array
    {
        return $request->validate(['name' => ['required', 'string'], 'code' => ['required', 'alpha_dash', Rule::unique('schools')->ignore($school)], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string'], 'address' => ['nullable', 'string'], 'contact_person' => ['nullable', 'string'], 'is_active' => ['boolean']]);
    }

    private function userData(Request $request, ?User $user = null): array
    {
        $data = $request->validate(['name' => ['required', 'string'], 'email' => ['required', 'email', Rule::unique('users')->ignore($user)], 'role' => ['required', Rule::in(['admin', 'school', 'platform_admin', 'school_owner', 'school_admin', 'content_author', 'content_reviewer'])], 'school_id' => ['nullable', 'exists:schools,id'], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'], 'is_active' => ['boolean']]);
        if (in_array($data['role'], ['school', 'school_owner', 'school_admin'], true)) {
            abort_unless($data['school_id'] ?? null, 422, 'A escola é obrigatória.');
        } else {
            $data['school_id'] = null;
        } if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    private function assertQuestionAccess(Request $request, Question $question): void
    {
        abort_if($request->user()->isSchool() && ($question->school_id !== $request->user()->school_id || $question->status === 'approved'), 403);
    }

    private function assertSchoolAccess(Request $request, School $school): void
    {
        abort_if($request->user()->isSchool() && $request->user()->school_id !== $school->id, 403);
    }

    private function assertClassroomAccess(Request $request, Classroom $classroom): void
    {
        abort_unless($request->user()->canAccessClassroom($classroom), 403);
    }

    private function assertExamAccess(Request $request, Exam $exam): void
    {
        abort_if($request->user()->isSchool() && $request->user()->school_id !== $exam->school_id, 403);
    }
}
