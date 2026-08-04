<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sign;
use App\Models\SignCategory;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SignController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.signs.index', [
            'categorias' => SignCategory::raiz()->ordenadas()->get(),
            'porCategoria' => Sign::selectRaw('sign_category_id, count(*) as total')->groupBy('sign_category_id')->pluck('total', 'sign_category_id'),
            'signs' => Sign::query()->with(['topic', 'category', 'subcategory'])
                ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$request->string('q')->value().'%')->orWhere('meaning', 'like', '%'.$request->string('q')->value().'%')))
                ->when($request->filled('category'), fn ($query) => $query->where('sign_category_id', $request->integer('category')))
                ->orderBy('sort_order')->orderBy('name')->paginate(18)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.signs.form', $this->formData(new Sign));
    }

    public function store(Request $request): RedirectResponse
    {
        Sign::create($this->validated($request));

        return redirect()->route('admin.signs.index')->with('status', 'Sinal adicionado à biblioteca.');
    }

    public function edit(Sign $sign): View
    {
        return view('admin.signs.form', $this->formData($sign));
    }

    private function formData(Sign $sign): array
    {
        return [
            'sign' => $sign,
            // Só as activas: uma categoria desactivada deixa de ser oferecida
            // para novos sinais, mas os que já lhe pertencem não se perdem.
            'categorias' => SignCategory::raiz()->ativas()->ordenadas()->with(['children' => fn ($q) => $q->where('is_active', true)])->get(),
            'topics' => Topic::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ];
    }

    public function update(Request $request, Sign $sign): RedirectResponse
    {
        $sign->update($this->validated($request, $sign));

        return redirect()->route('admin.signs.index')->with('status', 'Sinal atualizado.');
    }

    public function destroy(Sign $sign): RedirectResponse
    {
        $sign->delete();

        return back()->with('status', 'Sinal removido da biblioteca.');
    }

    /**
     * Um identificador legível, derivado do nome quando não for escrito.
     *
     * Existe porque ninguém que cataloga sinais quer inventar códigos únicos à
     * mão — e um humano apressado escreve "stop" duas vezes e bate na restrição
     * de unicidade sem perceber porquê. O sufixo numérico resolve a colisão em
     * silêncio; quem quiser um identificador próprio continua a poder escrevê-lo.
     */
    private function slugAutomatico(string $nome, ?Sign $sign): string
    {
        $base = Str::slug($nome) ?: 'sinal';
        $slug = $base;

        for ($i = 2; Sign::where('slug', $slug)->when($sign?->exists, fn ($q) => $q->whereKeyNot($sign->getKey()))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function validated(Request $request, ?Sign $sign = null): array
    {
        // Gerado antes de validar para que a regra de unicidade o veja: o campo
        // é opcional no formulário, e vazio significa "decide tu".
        if (blank($request->input('slug')) && filled($request->input('name'))) {
            $request->merge(['slug' => $this->slugAutomatico((string) $request->input('name'), $sign)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique('signs')->ignore($sign)],
            // Categoria obrigatória e subcategoria opcional: nem todo o sinal
            // precisa de refinamento, mas todos têm de pertencer a algum lado.
            'sign_category_id' => ['required', Rule::exists('sign_categories', 'id')->whereNull('parent_id')],
            'sign_subcategory_id' => ['nullable', 'exists:sign_categories,id'],
            'topic_id' => ['nullable', 'exists:topics,id'],
            'meaning' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'article_ref' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_locked' => ['nullable', 'boolean'],
            /*
             * SVG e matriciais. O SVG é o formato certo para sinalização —
             * escala sem perder nitidez — mas obrigar a ele travava quem só
             * tem um PNG à mão.
             *
             * Converter PNG para SVG não é uma opção: passar de pixels para
             * vectores exige tracing, que nenhuma extensão de PHP faz, e
             * embrulhar o PNG dentro de um `<svg><image>` daria um ficheiro
             * ~33% maior sem ganhar escalabilidade nenhuma. Guarda-se cada um
             * no seu formato; o que mostra a imagem não precisa de saber qual é.
             */
            'svg' => [$sign?->exists ? 'nullable' : 'required', 'file', 'mimetypes:image/svg+xml,text/plain,image/png,image/jpeg,image/webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        unset($data['svg']);

        /*
         * A subcategoria tem de ser filha da categoria escolhida. Sem esta
         * verificação, um pedido forjado — ou o formulário deixado a meio
         * depois de trocar a categoria — gravava um sinal em "Sinais de
         * perigo / Limite de velocidade", uma combinação que não existe.
         */
        if (filled($data['sign_subcategory_id'] ?? null)) {
            $subcategoria = SignCategory::find($data['sign_subcategory_id']);

            if (! $subcategoria || (int) $subcategoria->parent_id !== (int) $data['sign_category_id']) {
                throw ValidationException::withMessages([
                    'sign_subcategory_id' => 'Essa subcategoria não pertence à categoria escolhida.',
                ]);
            }
        }

        // Ausente do pedido, `validate()` não o devolve — daí o ?? antes do ?:
        $data['sign_subcategory_id'] = ($data['sign_subcategory_id'] ?? null) ?: null;
        if ($request->hasFile('svg')) {
            $ficheiro = $request->file('svg');
            $extensao = strtolower($ficheiro->getClientOriginalExtension());
            $eSvg = $extensao === 'svg' || $ficheiro->getMimeType() === 'image/svg+xml';

            if ($eSvg) {
                /*
                 * Um SVG é XML executável pelo browser: aceite sem inspeção,
                 * seria um vector de XSS servido do nosso próprio domínio, com
                 * a sessão do administrador aberta ao lado.
                 */
                $conteudo = $ficheiro->get();
                abort_if(
                    ! str_contains(mb_strtolower($conteudo), '<svg')
                    || preg_match('/<(script|iframe|object|embed|foreignobject)\b|\son\w+\s*=|javascript:|<!entity/i', $conteudo),
                    422,
                    'O SVG contém elementos inseguros.',
                );
                $extensao = 'svg';
            } else {
                // Só confiar na extensão deixava passar um PHP renomeado para
                // .png; é o conteúdo que decide se isto é mesmo uma imagem.
                abort_if(@getimagesize($ficheiro->getPathname()) === false, 422, 'O ficheiro não é uma imagem válida.');
            }

            $filename = Str::slug($data['slug']).'-'.now()->format('YmdHis').'.'.$extensao;
            $ficheiro->move(public_path('images/signs'), $filename);
            $data['file_path'] = '/images/signs/'.$filename;
        }
        $data['is_active'] = $request->boolean('is_active');
        $data['is_locked'] = $request->boolean('is_locked');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
