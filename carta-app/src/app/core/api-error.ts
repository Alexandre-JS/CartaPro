import { HttpErrorResponse } from '@angular/common/http';

/** Extrai a mensagem efetivamente devolvida pela API, preservando o HTTP status. */
export function mensagemErroApi(erro: unknown): string {
    if (erro instanceof HttpErrorResponse) {
        const payload = erro.error;
        const detalhe = typeof payload === 'string'
            ? payload
            : payload && typeof payload.message === 'string'
                ? payload.message
                : erro.message;
        const origem = erro.status > 0 ? `Erro HTTP ${erro.status}` : 'Erro de ligação à API';
        return `${origem}: ${detalhe}`;
    }

    if (erro instanceof Error) {
        return erro.message;
    }

    return String(erro || 'Erro desconhecido.');
}
