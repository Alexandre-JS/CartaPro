<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlossaryTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Glossário: definições curtas e pesquisáveis dos termos do Código. */
class GlossaryController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.glossary.index', [
            'terms' => GlossaryTerm::query()
                ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested
                    ->where('term', 'like', '%'.$request->string('q')->value().'%')
                    ->orWhere('definition', 'like', '%'.$request->string('q')->value().'%')))
                ->orderBy('sort_order')->orderBy('term')->paginate(10)->withQueryString(),
            'total' => GlossaryTerm::count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        GlossaryTerm::create($this->validated($request));

        return back()->with('status', 'Termo adicionado ao glossário.');
    }

    public function update(Request $request, GlossaryTerm $glossary): RedirectResponse
    {
        $glossary->update($this->validated($request, $glossary));

        return back()->with('status', 'Termo atualizado.');
    }

    public function destroy(GlossaryTerm $glossary): RedirectResponse
    {
        $glossary->delete();

        return back()->with('status', 'Termo removido.');
    }

    private function validated(Request $request, ?GlossaryTerm $term = null): array
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'alpha_dash', 'max:150', Rule::unique('glossary_terms')->ignore($term)],
            'definition' => ['required', 'string', 'max:2000'],
            'article_ref' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_locked' => ['nullable', 'boolean'],
        ]);
        // Cada termo decide o seu plano: não há regra automática por trás.
        $data['is_locked'] = $request->boolean('is_locked');

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['term']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
