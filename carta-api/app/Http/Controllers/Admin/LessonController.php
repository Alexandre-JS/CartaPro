<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Lesson;
use App\Models\Sign;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fichas de estudo.
 *
 * É o material que ensina: os artigos do Código, em linguagem legal, não
 * servem para estudar sozinhos, e era só isso que o app tinha na secção de
 * estudos. Cada ficha pode referenciar sinais e artigos, criando a navegação
 * cruzada que não existia.
 */
class LessonController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.lessons.index', [
            'lessons' => Lesson::with('topic')
                ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested
                    ->where('title', 'like', '%'.$request->string('q')->value().'%')
                    ->orWhere('summary', 'like', '%'.$request->string('q')->value().'%')))
                ->when($request->filled('group'), fn ($query) => $query->where('group', $request->string('group')->value()))
                ->orderBy('group')->orderBy('sort_order')->orderBy('title')
                ->paginate(20)->withQueryString(),
            'grupos' => config('estudo.grupos_licoes'),
            'porGrupo' => Lesson::selectRaw('`group`, count(*) as total')->groupBy('group')->pluck('total', 'group'),
        ]);
    }

    public function create(): View
    {
        return view('admin.lessons.form', $this->formData(new Lesson(['group' => 'codigo', 'reading_minutes' => 3, 'is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        Lesson::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.lessons.index')->with('status', 'Ficha de estudo criada.');
    }

    public function edit(Lesson $lesson): View
    {
        return view('admin.lessons.form', $this->formData($lesson));
    }

    public function update(Request $request, Lesson $lesson): RedirectResponse
    {
        $lesson->update($this->validated($request, $lesson));

        return redirect()->route('admin.lessons.index')->with('status', 'Ficha de estudo atualizada.');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $lesson->delete();

        return back()->with('status', 'Ficha de estudo removida.');
    }

    private function formData(Lesson $lesson): array
    {
        return [
            'lesson' => $lesson,
            'grupos' => config('estudo.grupos_licoes'),
            'topics' => Topic::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'signs' => Sign::where('is_active', true)->orderBy('category')->orderBy('name')->get(['slug', 'name', 'category']),
            'articles' => Article::where('is_active', true)->orderBy('number')->get(['number', 'title']),
            'categorias' => ['ligeiro' => 'Ligeiro', 'pesado' => 'Pesado', 'profissional_publico' => 'Profissional / público'],
        ];
    }

    private function validated(Request $request, ?Lesson $lesson = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'alpha_dash', 'max:200', Rule::unique('lessons')->ignore($lesson)],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'group' => ['required', Rule::in(array_keys(config('estudo.grupos_licoes')))],
            'topic_id' => ['nullable', 'exists:topics,id'],
            'license_categories' => ['nullable', 'array'],
            'license_categories.*' => ['in:ligeiro,pesado,profissional_publico'],
            'sign_slugs' => ['nullable', 'array'],
            'sign_slugs.*' => ['exists:signs,slug'],
            'article_numbers' => ['nullable', 'array'],
            'article_numbers.*' => ['integer', 'exists:articles,number'],
            'reading_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_locked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_locked'] = $request->boolean('is_locked');
        $data['license_categories'] = $data['license_categories'] ?? [];
        $data['sign_slugs'] = $data['sign_slugs'] ?? [];
        $data['article_numbers'] = array_map('intval', $data['article_numbers'] ?? []);

        return $data;
    }
}
