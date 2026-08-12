<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\LicenseCategory;
use App\Models\Question;
use App\Models\Sign;
use App\Models\Topic;
use App\Support\ImagemSegura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $questions = Question::with(['topic', 'author', 'school'])
            ->when($request->user()->isSchool(), fn ($query) => $query->where('school_id', $request->user()->school_id))
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('statement', 'like', '%'.$request->string('q')->value().'%')
                ->orWhere('external_id', 'like', '%'.$request->string('q')->value().'%')))
            ->when($request->filled('topic'), fn ($query) => $query->where('topic_id', $request->integer('topic')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->value()))
            ->when($request->filled('category'), fn ($query) => $query->whereJsonContains('categories', $request->string('category')->value()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.questions.index', [
            'questions' => $questions,
            'topics' => Topic::orderBy('name')->get(),
            'categories' => LicenseCategory::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.questions.form', $this->formData(new Question));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['status'] = $request->user()->isSchool() || $request->input('action') === 'review' ? 'review' : ($request->input('action') === 'approve' ? 'approved' : 'draft');
        $data['author_id'] = $request->user()->id;
        $data['school_id'] = $request->user()->school_id;

        // Duas escritas porque o identificador depende do `id`, que só existe
        // depois da inserção; a transacção impede que uma falha deixe para trás
        // uma pergunta com o identificador provisório.
        $question = DB::transaction(function () use ($data): Question {
            $question = Question::create($data + ['external_id' => 'rascunho-'.Str::uuid()]);
            $question->update(['external_id' => $this->identificadorAutomatico($question)]);

            return $question;
        });

        return redirect()->route('admin.questions.index')->with('status', "Pergunta {$question->external_id} criada com sucesso.");
    }

    public function edit(Question $question): View
    {
        $this->authorizeQuestion(request(), $question);

        return view('admin.questions.form', $this->formData($question));
    }

    public function update(Request $request, Question $question): RedirectResponse
    {
        $this->authorizeQuestion($request, $question);
        $data = $this->validated($request, $question);
        if ($request->user()->isSchool() || $request->input('action') === 'review') {
            $data['status'] = 'review';
            $data['rejection_reason'] = null;
        } elseif ($request->input('action') === 'approve') {
            $data['status'] = 'approved';
            $data['reviewed_by'] = $request->user()->id;
            $data['reviewed_at'] = now();
        } elseif ($request->input('action') === 'draft') {
            $data['status'] = 'draft';
        }
        $question->update($data);

        return redirect()->route('admin.questions.index')->with('status', 'Pergunta atualizada com sucesso.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $this->authorizeQuestion(request(), $question);
        $question->delete();

        return back()->with('status', 'Pergunta removida.');
    }

    private function validated(Request $request, ?Question $question = null): array
    {
        $data = $request->validate([
            'topic_id' => ['required', 'exists:topics,id'],
            'type' => ['required', Rule::in(['teorico', 'pratico'])],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => [Rule::exists('license_categories', 'slug')->where('is_active', true)],
            'statement' => ['required', 'string'],
            'sign_id' => ['nullable', 'exists:signs,id'],
            // Imagem própria, para o que não está — nem faz sentido estar — na
            // biblioteca de sinais: uma fotografia de cruzamento, um esquema de
            // manobra. Aceita os mesmos formatos e passa pelas mesmas
            // verificações de segurança que as imagens dos sinais.
            'image_file' => ['nullable', 'file', ImagemSegura::MIMETYPES, 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'option_items' => ['required', 'array', 'min:2'],
            'option_items.*' => ['required', 'string', 'max:500'],
            'correct_index' => ['required', 'integer', 'min:0'],
            // Opcional: ver a migração que tornou a coluna anulável.
            'explanation' => ['nullable', 'string'],
            'article_ref' => ['nullable', 'integer', 'min:1'],
            'article_id' => ['nullable', 'exists:articles,id'],
            // Vazia significa "a seguir à última"; escrita, manda quem escreveu.
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_locked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $options = array_values(array_filter(array_map('trim', $data['option_items'])));
        abort_if(count($options) < 2, 422, 'Indique pelo menos duas opções.');
        abort_if((int) $data['correct_index'] >= count($options), 422, 'A opção correta não existe.');

        unset($data['option_items'], $data['image_file'], $data['remove_image']);
        $data['options'] = $options;
        $data['explanation'] = filled($data['explanation'] ?? null) ? $data['explanation'] : null;
        $data['sort_order'] = $data['sort_order'] ?? $question?->sort_order ?? $this->proximaOrdem((int) $data['topic_id']);

        /*
         * Sinal e imagem própria são exclusivos, e o sinal ganha.
         *
         * A coluna `image` deixou de ser cópia do caminho do sinal (ver
         * Question::imagemPublica): com sinal escolhido, esvazia-se, senão
         * ficava um caminho órfão a competir com a biblioteca no dia em que o
         * sinal trocasse de ficheiro.
         */
        if ($data['sign_id'] ?? null) {
            $data['image'] = null;
        } elseif ($request->hasFile('image_file')) {
            $data['image'] = ImagemSegura::guardar($request->file('image_file'), 'images/questions', $data['statement']);
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        } elseif ($question?->exists) {
            $data['image'] = $question->image;
        }

        if ($data['article_id'] ?? null) {
            $data['article_ref'] = Article::find($data['article_id'])?->number;
        }
        $data['is_locked'] = $request->boolean('is_locked');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /**
     * Identificador estável: tema mais a chave primária da pergunta.
     *
     * Era escrito à mão e obrigatório, o que pedia ao utilizador exactamente
     * aquilo em que os humanos são maus — inventar chaves únicas. E não é um
     * rótulo qualquer: as respostas das provas são guardadas com o
     * `external_id` por chave (ver ExamScorer) e o app identifica por ele as
     * perguntas do pacote offline. Um duplicado, ou um valor reaproveitado,
     * não dá erro nenhum: passa a corrigir tentativas antigas contra a
     * pergunta errada.
     *
     * Daí vir do `id` e não de um contador sobre as perguntas existentes.
     * Contar reutilizaria o número da última pergunta apagada; procurar o maior
     * sufixo em uso reutilizá-lo-ia na mesma quando a apagada fosse a última.
     * A chave primária é o único número da tabela que a base nunca repete.
     */
    private function identificadorAutomatico(Question $question): string
    {
        $base = Str::slug($question->topic?->slug ?? 'pergunta');
        $identificador = sprintf('%s-%03d', $base, $question->id);

        // Só entra em jogo com identificadores antigos escritos à mão que, por
        // acaso, tenham a mesma forma.
        for ($i = 2; Question::where('external_id', $identificador)->whereKeyNot($question->id)->exists(); $i++) {
            $identificador = sprintf('%s-%03d-%d', $base, $question->id, $i);
        }

        return $identificador;
    }

    /** Ordem sugerida: a seguir à última pergunta do mesmo tema. */
    private function proximaOrdem(int $topicId): int
    {
        return (int) Question::where('topic_id', $topicId)->max('sort_order') + 1;
    }

    private function formData(Question $question): array
    {
        return [
            'question' => $question,
            'topics' => Topic::where('is_active', true)->orderBy('name')->get(),
            'categories' => LicenseCategory::where('is_active', true)->orderBy('sort_order')->get(),
            'signs' => Sign::with('category')->where('is_active', true)->orderBy('name')->get(),
            'articles' => Article::where('is_active', true)->orderBy('number')->get(),
            // Sugestão para o campo de ordem, que fica vazio a dizer o que fará.
            'proximaOrdem' => $question->exists ? $question->sort_order : null,
        ];
    }

    private function authorizeQuestion(Request $request, Question $question): void
    {
        abort_if($request->user()->isSchool() && ($question->school_id !== $request->user()->school_id || $question->status === 'approved'), 403);
    }
}
