<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SignCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Gestão das categorias e subcategorias de sinais.
 *
 * Até aqui a lista vivia em config/estudo.php e mudá-la exigia um deploy —
 * inviável para quem cataloga sinalização e não mexe em PHP.
 */
class SignCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.sign-categories.index', [
            'categorias' => SignCategory::raiz()
                ->ordenadas()
                ->with('children')
                ->withCount('signs')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.sign-categories.form', $this->formData(
            new SignCategory(['parent_id' => $request->integer('parent') ?: null]),
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        SignCategory::create($this->validated($request));

        return redirect()->route('admin.sign-categories.index')->with('status', 'Categoria adicionada.');
    }

    public function edit(SignCategory $signCategory): View
    {
        return view('admin.sign-categories.form', $this->formData($signCategory));
    }

    public function update(Request $request, SignCategory $signCategory): RedirectResponse
    {
        $signCategory->update($this->validated($request, $signCategory));

        return redirect()->route('admin.sign-categories.index')->with('status', 'Categoria atualizada.');
    }

    /**
     * Apagar uma categoria com sinais deixaria registos órfãos e o catálogo
     * incoerente. A chave estrangeira já o impede na base de dados; aqui a
     * recusa é explicada em vez de aparecer como erro de SQL.
     */
    public function destroy(SignCategory $signCategory): RedirectResponse
    {
        $sinais = $signCategory->signs()->count();

        if ($sinais > 0) {
            return back()->withErrors([
                'categoria' => "Esta categoria tem {$sinais} sinal(is). Muda-os de categoria antes de a apagar.",
            ]);
        }

        // As subcategorias caem com o pai (cascade), mas se alguma delas tiver
        // sinais o mesmo raciocínio aplica-se: não se apaga por baixo deles.
        $emSubcategorias = SignCategory::where('parent_id', $signCategory->id)
            ->withCount('signs')->get()->sum('signs_count');

        if ($emSubcategorias > 0) {
            return back()->withErrors([
                'categoria' => "As subcategorias desta categoria têm {$emSubcategorias} sinal(is). Muda-os antes de a apagar.",
            ]);
        }

        $signCategory->delete();

        return back()->with('status', 'Categoria removida.');
    }

    private function formData(SignCategory $categoria): array
    {
        return [
            'categoria' => $categoria,
            // Só categorias de topo podem ser pai: a hierarquia pára no segundo
            // nível, e uma categoria não pode ser pai de si própria.
            'paisPossiveis' => SignCategory::raiz()
                ->when($categoria->exists, fn ($query) => $query->whereKeyNot($categoria->getKey()))
                ->ordenadas()
                ->get(['id', 'name']),
        ];
    }

    private function validated(Request $request, ?SignCategory $categoria = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('sign_categories')->ignore($categoria)],
            'parent_id' => ['nullable', 'exists:sign_categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        /*
         * Uma categoria com pai é uma subcategoria. Deixá-la escolher um pai
         * que já é subcategoria criava um terceiro nível que o formulário de
         * sinais não sabe mostrar — e uma categoria com filhos não pode passar
         * a filha de outra sem levar netos atrás.
         */
        if (filled($data['parent_id'] ?? null)) {
            $pai = SignCategory::findOrFail($data['parent_id']);

            if ($pai->isSubcategoria()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Uma subcategoria não pode ter subcategorias.',
                ]);
            }

            if ($categoria?->exists && $categoria->children()->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Esta categoria tem subcategorias. Move-as primeiro.',
                ]);
            }
        }

        // Ausente do pedido, `validate()` não o devolve — daí o ?? antes do ?:
        $data['parent_id'] = ($data['parent_id'] ?? null) ?: null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
