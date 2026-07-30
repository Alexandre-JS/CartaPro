import { inject, Injectable } from '@angular/core';
import { EstadoAcesso } from '../models/progresso.model';
import { ApiService } from './api.service';
import { StorageService } from './storage.service';

export interface PlanoVenda {
    chave: string;
    nome: string;
    descricao?: string;
    preco: number;
    dias: number;
    /** Rótulo legível: "3 meses" lê-se melhor do que "90 dias". */
    periodo: string;
}

export interface MetodoPagamento {
    chave: string;
    nome: string;
    operadora: string;
    /** Dois dígitos nacionais que identificam a operadora (84, 86, …). */
    prefixos: string[];
}

export interface CatalogoPlanos {
    moeda: string;
    /** Uma frase. */
    promessa: string;
    /** Compromisso comercial, quando existe. Vazio = não se mostra nada. */
    garantia: string | null;
    telefone: string;
    telefoneSugerido: string;
    metodoSugerido: string | null;
    metodos: MetodoPagamento[];
    planos: PlanoVenda[];
    acesso: EstadoAcesso;
}

export type EstadoPagamento = 'pendente' | 'pago' | 'falhado' | 'expirado';

export interface Pagamento {
    id: number;
    estado: EstadoPagamento;
    metodo: string;
    referencia: string;
    telefone: string;
    valor: number;
    moeda: string;
    mensagem?: string | null;
    transacao?: string | null;
    /** Só a e-Mola: página do agregador onde o pagamento se conclui. */
    checkoutUrl?: string | null;
    acesso: EstadoAcesso;
}

/** Espaçamento entre sondagens enquanto o aluno confirma. */
const INTERVALO_MS = 3000;

/** Desiste ao fim de dois minutos: o pedido de PIN expira antes disso. */
const TENTATIVAS_MAX = 40;

/**
 * Pagamento dentro do app (M-Pesa e e-Mola).
 *
 * O plano é sempre decidido pelo servidor: este serviço nunca grava 'pago' por
 * sua conta. O que guarda é o `EstadoAcesso` que vem na resposta.
 */
@Injectable({ providedIn: 'root' })
export class PagamentoService {
    private readonly api = inject(ApiService);
    private readonly storage = inject(StorageService);

    catalogo(): Promise<CatalogoPlanos> {
        return this.api.get<CatalogoPlanos>('mobile/payments/plans', true);
    }

    /**
     * Inicia a cobrança na carteira indicada.
     *
     * `carteira` só vai quando o aluno corrige o número — se ficar vazio, o
     * servidor usa o da conta.
     */
    async iniciar(plano: string, metodo: string, carteira?: string): Promise<Pagamento> {
        return this.guardar(await this.api.post<Pagamento>('mobile/payments', {
            plan: plano,
            method: metodo,
            wallet_phone: carteira || null,
        }, true));
    }

    async estado(id: number): Promise<Pagamento> {
        return this.guardar(await this.api.get<Pagamento>(`mobile/payments/${id}`, true));
    }

    /**
     * Sonda até a transação deixar de estar pendente.
     *
     * Nem o M-Pesa nem a e-Mola são instantâneos — o aluno tem de destrancar o
     * telemóvel e confirmar —, por isso não há uma resposta única a esperar.
     */
    async aguardar(pagamento: Pagamento, aoSondar?: (tentativa: number) => void): Promise<Pagamento> {
        let atual = pagamento;

        for (let tentativa = 1; atual.estado === 'pendente' && tentativa <= TENTATIVAS_MAX; tentativa++) {
            await new Promise((resolver) => setTimeout(resolver, INTERVALO_MS));
            aoSondar?.(tentativa);
            atual = await this.estado(atual.id);
        }

        return atual;
    }

    /**
     * Que método serve este número.
     *
     * Em Moçambique o prefixo identifica a operadora e, com ela, a carteira:
     * escrever um 86 e carregar em M-Pesa é uma transação que falha de certeza.
     * `null` quando o prefixo é desconhecido — aí não se impede nada.
     */
    metodoParaNumero(numero: string, metodos: MetodoPagamento[]): MetodoPagamento | null {
        const digitos = (numero || '').replace(/\D+/g, '');
        const nacional = digitos.startsWith('258') ? digitos.slice(3) : digitos;

        if (nacional.length < 2) {
            return null;
        }

        return metodos.find((metodo) => metodo.prefixos.includes(nacional.slice(0, 2))) ?? null;
    }

    private async guardar(pagamento: Pagamento): Promise<Pagamento> {
        if (pagamento.acesso) {
            await this.storage.guardarEstadoAcesso({ ...pagamento.acesso, verificadoEm: Date.now() });
        }

        return pagamento;
    }
}
