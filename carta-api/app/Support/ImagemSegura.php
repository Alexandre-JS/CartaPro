<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Guarda uma imagem enviada pelo painel, depois de a inspeccionar.
 *
 * Nasceu dentro do SignController e saiu de lá quando as perguntas passaram a
 * aceitar imagem própria: duplicar isto era duplicar as duas verificações que
 * impedem que o painel sirva ficheiros perigosos do nosso próprio domínio — e
 * cópias de código de segurança divergem sempre, ficando uma delas por
 * corrigir no dia em que aparecer um vector novo.
 *
 * Os dois riscos cobertos:
 *
 *  - **SVG é XML executável pelo browser.** Aceite sem inspecção, seria XSS
 *    servido do nosso domínio, com a sessão do administrador aberta ao lado.
 *  - **A extensão não prova nada.** Um `.php` renomeado para `.png` passaria
 *    numa validação de extensão; é o conteúdo que decide se aquilo é imagem.
 */
class ImagemSegura
{
    /** Tipos aceites na validação de quem chama — SVG e matriciais. */
    public const MIMETYPES = 'mimetypes:image/svg+xml,text/plain,image/png,image/jpeg,image/webp';

    /**
     * Valida e move o ficheiro, devolvendo o caminho público a guardar.
     *
     * @param  string  $pasta  Subpasta de `public/` (ex.: `images/signs`).
     * @param  string  $nomeBase  Nome legível do ficheiro, antes do carimbo temporal.
     */
    public static function guardar(UploadedFile $ficheiro, string $pasta, string $nomeBase): string
    {
        $extensao = strtolower($ficheiro->getClientOriginalExtension());
        $eSvg = $extensao === 'svg' || $ficheiro->getMimeType() === 'image/svg+xml';

        if ($eSvg) {
            $conteudo = $ficheiro->get();
            abort_if(
                ! str_contains(mb_strtolower($conteudo), '<svg')
                || preg_match('/<(script|iframe|object|embed|foreignobject)\b|\son\w+\s*=|javascript:|<!entity/i', $conteudo),
                422,
                'O SVG contém elementos inseguros.',
            );
            $extensao = 'svg';
        } else {
            abort_if(@getimagesize($ficheiro->getPathname()) === false, 422, 'O ficheiro não é uma imagem válida.');
        }

        $nome = (Str::slug($nomeBase) ?: 'imagem').'-'.now()->format('YmdHis').'.'.$extensao;
        $ficheiro->move(public_path($pasta), $nome);

        return '/'.trim($pasta, '/').'/'.$nome;
    }
}
