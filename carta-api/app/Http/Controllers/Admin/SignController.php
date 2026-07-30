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

    private function validated(Request $request, ?Sign $sign = null): array
    {
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
            'svg' => [$sign?->exists ? 'nullable' : 'required', 'file', 'mimetypes:image/svg+xml,text/plain', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        unset($data['svg']);
        if ($request->hasFile('svg')) {
            $svg = $request->file('svg')->get();
            abort_if(
                ! str_contains(mb_strtolower($svg), '<svg')
                || preg_match('/<(script|iframe|object|embed|foreignobject)\b|\son\w+\s*=|javascript:|<!entity/i', $svg),
                422,
                'O SVG contém elementos inseguros.',
            );
            $filename = Str::slug($data['slug']).'-'.now()->format('YmdHis').'.svg';
            $request->file('svg')->move(public_path('images/signs'), $filename);
            $data['file_path'] = '/images/signs/'.$filename;
        }
        $data['is_active'] = $request->boolean('is_active');
        $data['is_locked'] = $request->boolean('is_locked');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
