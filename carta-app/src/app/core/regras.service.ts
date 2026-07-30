import { inject, Injectable } from '@angular/core';
import { DURACAO_SIMULADO_SEGUNDOS, PERCENTAGEM_PASSAGEM_PADRAO, TAMANHO_SIMULADO } from '../config/simulado.config';
import { RegraCategoria, RegrasPacote } from '../models/pacote.model';
import { CategoriaCarta } from '../models/pergunta.model';
import { ContentService } from './content.service';

const RECURSO_EMERGENCIA: RegraCategoria = {
    totalPerguntas: TAMANHO_SIMULADO,
    percentagemPassagem: PERCENTAGEM_PASSAGEM_PADRAO,
    notaPassagem: Math.ceil((TAMANHO_SIMULADO * PERCENTAGEM_PASSAGEM_PADRAO) / 100),
    valoresPassagem: Number(((PERCENTAGEM_PASSAGEM_PADRAO / 100) * 20).toFixed(1)),
    minutos: DURACAO_SIMULADO_SEGUNDOS / 60,
};

/**
 * Regra de aprovação — uma só, vinda do servidor.
 *
 * Antes existiam cinco definições em conflito: NOTA_PASSAGEM = 24 de 25 (96%)
 * no app, 72% no ExamController, 72%/14,4 hardcoded no MobileController, 14
 * valores no ExamAttempt e 3 notas válidas no painel. O mesmo desempenho podia
 * dar "aprovado" num ecrã e "reprovado" noutro.
 */
@Injectable({ providedIn: 'root' })
export class RegrasService {
    private readonly content = inject(ContentService);
    private regras?: RegrasPacote;

    async carregar(): Promise<RegrasPacote | undefined> {
        this.regras ??= await this.content.obterRegras();
        return this.regras;
    }

    /** Regra aplicável a uma categoria de carta. */
    para(categoria?: CategoriaCarta | string): RegraCategoria {
        if (!this.regras) {
            return RECURSO_EMERGENCIA;
        }

        return (categoria && this.regras.porCategoria?.[categoria]) || this.regras.omissao || RECURSO_EMERGENCIA;
    }

    /** Nº mínimo de acertos para aprovar numa prova com este total. */
    notaPassagem(total: number, categoria?: CategoriaCarta | string): number {
        if (total <= 0) {
            return 0;
        }

        return Math.ceil((total * this.para(categoria).percentagemPassagem) / 100);
    }

    aprovado(acertos: number, total: number, categoria?: CategoriaCarta | string): boolean {
        return total > 0 && acertos >= this.notaPassagem(total, categoria);
    }

    percentagemPassagem(categoria?: CategoriaCarta | string): number {
        return this.para(categoria).percentagemPassagem;
    }

    duracaoSegundos(categoria?: CategoriaCarta | string): number {
        return this.para(categoria).minutos * 60;
    }

    valoresMaximos(): number {
        return this.regras?.valoresMaximos ?? 20;
    }

    /** Nota na escala 0-20 usada pelas escolas. */
    valores(acertos: number, total: number): number {
        return total > 0 ? Number(((acertos / total) * this.valoresMaximos()).toFixed(1)) : 0;
    }
}
