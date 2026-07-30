<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AmostraGratuita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Define o que o plano gratuito mostra.
 *
 * Existia o campo `is_locked` em cada conteúdo desde o início, e um visto no
 * formulário de cada item — mas marcar trezentas perguntas uma a uma não é
 * trabalho que alguém faça. Era isso, e não a falta de mecanismo, que mantinha
 * todo o catálogo gratuito.
 */
class AmostraController extends Controller
{
    /** Valores de arranque do formulário, quando não vem nada submetido. */
    private const SUGESTAO = [
        'exames' => 2,
        'perguntas' => 5,
        'sinais' => 5,
        'fichas' => 2,
        'artigos' => 3,
        'glossario' => 10,
    ];

    public function __construct(private readonly AmostraGratuita $amostra) {}

    public function index(Request $request): View
    {
        $limites = $this->limites($request);

        return view('admin.amostra.index', [
            'limites' => $limites,
            'plano' => $this->amostra->simular($limites),
            'aplicado' => (bool) $request->session()->get('status'),
        ]);
    }

    /**
     * Aplica a regra a todo o catálogo.
     *
     * Só depois de o operador ter visto a simulação na mesma página: é uma
     * alteração que toca em todos os conteúdos de uma vez.
     */
    public function store(Request $request): RedirectResponse
    {
        $limites = $this->limites($request);
        $resultado = $this->amostra->aplicar($limites);

        $resumo = collect($resultado)
            ->map(fn (array $dados, string $frente) => "{$dados['livres']} {$frente}")
            ->implode(', ');

        return redirect()->route('admin.amostra.index', $limites)->with(
            'status',
            "Amostra gratuita aplicada: {$resumo} à vista. Publica o pacote para o app receber a alteração.",
        );
    }

    private function limites(Request $request): array
    {
        $dados = $request->validate([
            'exames' => ['nullable', 'integer', 'min:0', 'max:999'],
            'perguntas' => ['nullable', 'integer', 'min:0', 'max:999'],
            'sinais' => ['nullable', 'integer', 'min:0', 'max:999'],
            'fichas' => ['nullable', 'integer', 'min:0', 'max:999'],
            'artigos' => ['nullable', 'integer', 'min:0', 'max:999'],
            'glossario' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return collect(self::SUGESTAO)
            ->map(fn (int $padrao, string $chave) => (int) ($dados[$chave] ?? $padrao))
            ->all();
    }
}
