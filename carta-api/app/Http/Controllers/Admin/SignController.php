<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sign;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SignController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.signs.index', [
            'categorias' => config('estudo.categorias_sinais'),
            'porCategoria' => Sign::selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category'),
            'signs' => Sign::query()->with('topic')
                ->when($request->filled('q'), fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$request->string('q')->value().'%')->orWhere('meaning', 'like', '%'.$request->string('q')->value().'%')))
                ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->value()))
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
            // Categorias vindas de config/estudo.php — inclui marcas
            // rodoviárias, semáforos e sinais dos agentes, que antes não
            // tinham categoria possível.
            'categorias' => config('estudo.categorias_sinais'),
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
            'category' => ['required', Rule::in(array_keys(config('estudo.categorias_sinais')))],
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
