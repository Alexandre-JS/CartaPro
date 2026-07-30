/**
 * Leitura das respostas de erro da API.
 *
 * O app mostrava apenas `error.message`, que o Laravel compõe como
 * "Primeiro erro. (and 1 more error)": ficava em inglês a meio da frase e
 * escondia os restantes campos. Aqui devolvem-se todas as mensagens, uma por
 * campo, sem esse sufixo.
 */

/** Remove o sufixo "(and N more errors)" que o Laravel acrescenta. */
function limparSufixo(mensagem: string): string {
    return mensagem.replace(/\s*\(and \d+ more errors?\)\s*$/i, '').trim();
}

/** Todas as mensagens de validação, na ordem dos campos. */
export function mensagensDeErro(erro: any): string[] {
    const errosPorCampo = erro?.error?.errors;

    if (errosPorCampo && typeof errosPorCampo === 'object') {
        const mensagens: string[] = [];

        // Sem `flat()`: o alvo de compilação do projeto é anterior a ES2019.
        for (const valor of Object.keys(errosPorCampo).map((campo) => errosPorCampo[campo])) {
            const lista: unknown[] = Array.isArray(valor) ? valor : [valor];

            for (const item of lista) {
                if (typeof item === 'string' && mensagens.indexOf(item) === -1) {
                    mensagens.push(item);
                }
            }
        }

        if (mensagens.length) {
            return mensagens;
        }
    }

    const unica = erro?.error?.message || erro?.message;

    return unica ? [limparSufixo(String(unica))] : [];
}

/** Uma única frase, para onde só há espaço para uma linha. */
export function mensagemDeErro(erro: any, alternativa = 'Ocorreu um erro. Tenta novamente.'): string {
    if (erro?.status === 0) {
        return 'Sem ligação à internet. Verifica a rede e tenta novamente.';
    }

    if (erro?.status === 401) {
        return 'A sessão expirou. Entra novamente.';
    }

    if (erro?.status === 429) {
        return 'Demasiadas tentativas. Espera um momento e tenta novamente.';
    }

    if (erro?.status >= 500) {
        return 'O servidor está com problemas. Tenta novamente dentro de alguns minutos.';
    }

    const mensagens = mensagensDeErro(erro);

    return mensagens.length ? mensagens.join(' ') : alternativa;
}

/** Já existe conta com este email/telefone? Serve para sugerir o login. */
export function contaJaExiste(erro: any): boolean {
    const campos = erro?.error?.errors;

    if (erro?.status !== 422 || !campos) {
        return false;
    }

    return Boolean(campos['email'] || campos['phone']);
}
