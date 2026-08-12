<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentPackage;
use App\Models\LicenseCategory;
use App\Models\Question;
use App\Models\Sign;
use App\Models\Topic;
use App\Services\EntitlementService;
use App\Services\PackagePublisher;
use App\Support\Grading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Entrega de conteúdo ao app.
 *
 * Todas as rotas deste controlador exigem sessão móvel (ver routes/api.php):
 * o pacote transporta a resposta correta e a explicação de cada pergunta, pelo
 * que nunca pode ser servido anonimamente. O que cada conta recebe é decidido
 * pelo EntitlementService, no servidor.
 */
class ContentController extends Controller
{
    public function __construct(
        private readonly EntitlementService $entitlements,
        private readonly PackagePublisher $publisher,
    ) {}

    /** Temas com nome e descrição — o app deixa de ter mapas hardcoded. */
    public function topics(): JsonResponse
    {
        $topics = Topic::where('is_active', true)->withCount([
            'questions' => fn ($query) => $query->where('is_active', true)->where('status', 'approved'),
        ])->orderBy('sort_order')->get(['id', 'name', 'slug', 'description']);

        return response()->json(['data' => $topics]);
    }

    public function questions(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'topic' => ['nullable', 'string'],
            'category' => ['nullable', 'in:ligeiro,pesado,profissional_publico'],
        ]);

        // `include_locked` deixou de ser aceite do cliente: quem decide é o plano.
        $paid = $this->entitlements->isPaid($request->user());

        $questions = Question::query()->with(['topic:id,slug,name', 'sign:id,slug,file_path'])->where('is_active', true)->where('status', 'approved')
            ->whereHas('topic', fn ($query) => $query->where('is_active', true))
            ->when($filters['topic'] ?? null, fn ($query, $topic) => $query->whereHas('topic', fn ($q) => $q->where('slug', $topic)))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereJsonContains('categories', $category))
            ->when(! $paid, fn ($query) => $query->where('is_locked', false))
            ->orderBy('sort_order')->get();

        return response()->json([
            'version' => now()->format('Y-m-d'),
            'plano' => $paid ? 'pago' : 'gratis',
            'data' => $questions->map(fn (Question $question) => $question->toPackageArray()),
        ]);
    }

    /**
     * Pacote offline. O snapshot guardado é sempre completo; a filtragem por
     * plano acontece na entrega, para que uma renovação não obrigue a republicar.
     */
    public function package(Request $request): JsonResponse
    {
        $paid = $this->entitlements->isPaid($request->user());
        $published = ContentPackage::where('status', 'published')->latest('published_at')->first();

        $payload = $published
            ? $published->payload
            : $this->liveFallbackPayload();

        $payload['regras'] = Grading::packageRules();
        $payload['temasDetalhe'] = $this->topicDetails();
        // Pacotes publicados antes do material de estudo não trazem esta
        // chave; serve-se o conteúdo atual para o app não ficar sem estudos.
        $payload['estudo'] ??= $this->publisher->buildStudyPayload();

        return response()->json($this->entitlements->filterPackage($payload, $paid));
    }

    public function signs(): JsonResponse
    {
        return response()->json(['data' => Sign::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'category', 'meaning', 'file_path'])]);
    }

    public function articles(Request $request): JsonResponse
    {
        return response()->json(['data' => Article::where('is_active', true)
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested->where('title', 'like', '%'.$request->string('q')->value().'%')->orWhere('text', 'like', '%'.$request->string('q')->value().'%')))
            ->orderBy('number')->paginate((int) $request->integer('per_page', 200))]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => LicenseCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'description'])]);
    }

    /** Metadados dos pacotes — sem payload, para não vazar o banco. */
    public function packages(): JsonResponse
    {
        return response()->json(['data' => ContentPackage::latest('published_at')->get(['version', 'status', 'question_count', 'published_at'])]);
    }

    /** Usado quando ainda não houve publicação (ambiente novo). */
    private function liveFallbackPayload(): array
    {
        $questions = Question::with(['topic', 'sign'])->where('is_active', true)->where('status', 'approved')
            ->whereHas('topic', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->get();

        return [
            'versao' => now()->format('Y-m-d'),
            'temas' => $questions->pluck('topic.slug')->unique()->values()->all(),
            'perguntas' => $questions->map(fn (Question $question) => $question->toPackageArray())->all(),
            'provas' => [],
            'estudo' => $this->publisher->buildStudyPayload(),
        ];
    }

    private function topicDetails(): array
    {
        return Topic::where('is_active', true)->orderBy('sort_order')
            ->get(['slug', 'name', 'description'])
            ->map(fn (Topic $topic) => ['slug' => $topic->slug, 'nome' => $topic->name, 'descricao' => $topic->description])
            ->all();
    }
}
