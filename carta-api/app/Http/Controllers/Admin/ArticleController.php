<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.articles.index', [
            'articles' => Article::query()
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested->where('title', 'like', '%'.$request->string('q')->value().'%')->orWhere('text', 'like', '%'.$request->string('q')->value().'%')->orWhere('number', $request->input('q'))))
            ->when($request->filled('chapter'), fn ($query) => $query->where('chapter_number', $request->integer('chapter')))
            ->orderBy('chapter_number')->orderBy('sort_order')->orderBy('number')->paginate(10)->withQueryString(),
            'capitulos' => Article::whereNotNull('chapter_number')
                ->selectRaw('chapter_number, chapter_title, count(*) as total')
                ->groupBy('chapter_number', 'chapter_title')->orderBy('chapter_number')->get(),
            'semCapitulo' => Article::whereNull('chapter_number')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.articles.form', ['article' => new Article]);
    }

    public function store(Request $request): RedirectResponse
    {
        Article::create($this->validated($request));

        return redirect()->route('admin.articles.index')->with('status', 'Artigo criado.');
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.form', compact('article'));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $article->update($this->validated($request, $article));

        return redirect()->route('admin.articles.index')->with('status', 'Artigo atualizado.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return back()->with('status', 'Artigo removido.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:json', 'max:10240']]);
        $items = json_decode($request->file('file')->get(), true, flags: JSON_THROW_ON_ERROR);
        abort_unless(is_array($items), 422, 'Formato de importação inválido.');

        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                validator($item, [
                    'numero' => ['required', 'integer'],
                    'titulo' => ['required', 'string'],
                    'texto' => ['required', 'string'],
                    'capitulo' => ['nullable', 'integer'],
                    'capituloTitulo' => ['nullable', 'string'],
                ])->validate();

                Article::updateOrCreate(['number' => $item['numero']], [
                    'title' => $item['titulo'],
                    'text' => $item['texto'],
                    'chapter_number' => $item['capitulo'] ?? null,
                    'chapter_title' => $item['capituloTitulo'] ?? null,
                    'is_active' => true,
                ]);
            }
        });

        return back()->with('status', count($items).' artigos importados ou atualizados.');
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', Rule::unique('articles')->ignore($article)],
            // Sem capítulo os artigos ficavam todos numa lista única de
            // centenas de entradas, impossível de estudar.
            'chapter_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'chapter_title' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'title' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_locked' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        // Cada artigo decide o seu plano: não há regra automática por trás.
        $data['is_locked'] = $request->boolean('is_locked');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
