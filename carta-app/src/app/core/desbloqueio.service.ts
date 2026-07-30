import { inject, Injectable } from '@angular/core';
import { EstadoAcesso } from '../models/progresso.model';
import { StorageService } from './storage.service';
import { ApiService } from './api.service';

interface RespostaPedido {
    estado: 'codigo_enviado' | 'ja_ativo';
    telefone?: string;
    expiraEmMinutos?: number;
}

interface RespostaConfirmacao extends EstadoAcesso {
    estado: 'ativado';
}

/**
 * Desbloqueio ligado à conta.
 *
 * Antes o aluno escrevia **qualquer** número e um endpoint público confirmava:
 * um número pago servia turmas inteiras, e a mesma rota permitia enumerar quem
 * tinha pagado. Agora o número usado é o da conta, é exigido um código por SMS
 * e o desbloqueio fica preso à primeira conta que o reclama.
 */
@Injectable({ providedIn: 'root' })
export class DesbloqueioService {
    private readonly api = inject(ApiService);
    private readonly storage = inject(StorageService);

    /** Estado em cache (uso offline). */
    obterEstado(): Promise<EstadoAcesso> {
        return this.storage.obterEstadoAcesso();
    }

    /**
     * Revalida o plano no servidor.
     *
     * O estado local deixou de ser autoritativo: antes, uma vez gravado
     * 'pago' em Preferences, o acesso ficava vitalício mesmo depois de expirar.
     */
    async revalidar(): Promise<EstadoAcesso> {
        try {
            const estado = await this.api.get<EstadoAcesso>('mobile/unlock', true);
            const atualizado: EstadoAcesso = { ...estado, verificadoEm: Date.now() };
            await this.storage.guardarEstadoAcesso(atualizado);
            return atualizado;
        } catch {
            // Sem rede mantém-se o último estado conhecido.
            return this.obterEstado();
        }
    }

    /** Pede o código de ativação por SMS para o número da conta. */
    async pedirCodigo(): Promise<RespostaPedido> {
        return this.api.post<RespostaPedido>('mobile/unlock/request', {}, true);
    }

    /** Confirma o código e ativa o plano. */
    async confirmarCodigo(codigo: string): Promise<EstadoAcesso> {
        const resposta = await this.api.post<RespostaConfirmacao>('mobile/unlock/confirm', { code: codigo.trim() }, true);
        const estado: EstadoAcesso = { ...resposta, verificadoEm: Date.now() };
        await this.storage.guardarEstadoAcesso(estado);
        return estado;
    }
}
