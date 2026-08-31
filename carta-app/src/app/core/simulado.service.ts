import { Injectable } from '@angular/core';
import {
    JANELA_RECENCIA,
    PENALIZACAO_RECENCIA,
    PESO_MINIMO_TEMA,
    PESO_TEMA_NAO_PRATICADO,
    QUOTA_TEMAS_FRACOS,
    TAMANHO_SESSAO_ESTUDO,
    TAMANHO_SIMULADO,
} from '../config/simulado.config';
import { CategoriaCarta, Pergunta } from '../models/pergunta.model';
import { ProgressoTema } from '../models/progresso.model';
import { amostrarPonderado, embaralhar } from './aleatorio';
import { ContentService } from './content.service';
import { ProgressoService } from './progresso.service';
import { StorageService } from './storage.service';
import { TreinoSinaisService } from './treino-sinais.service';

@Injectable({ providedIn: 'root' })
export class SimuladoService {
    constructor(
        private readonly content: ContentService,
        private readonly progresso: ProgressoService,
        private readonly storage: StorageService,
        private readonly treinoSinais: TreinoSinaisService,
    ) {}

    /**
     * Monta um simulado.
     *
     * No modo adaptativo a seleção é **ponderada**, não filtrada: os temas
     * fracos recebem mais peso mas o exame conserva uma quota de cobertura dos
     * restantes, para continuar a parecer o exame real. Antes o código
     * concatenava "fracas primeiro" e cortava em 25, o que produzia provas
     * 100% de temas fracos — e, para alunos novos (em que todos os temas
     * contavam como fracos), tornava o modo adaptativo idêntico ao normal.
     */
    async montarSimulado(categoria: CategoriaCarta, adaptativo = false, tamanho = TAMANHO_SIMULADO): Promise<Pergunta[]> {
        const perguntas = await this.content.listarPerguntas({ categoria });

        if (!perguntas.length) {
            return [];
        }

        const alvo = Math.min(tamanho, perguntas.length);

        if (!adaptativo) {
            return this.selecionarEquilibrado(perguntas, alvo);
        }

        return this.selecionarAdaptativo(perguntas, alvo);
    }

    async perguntasPorTema(tema: string, categoria: CategoriaCarta, tamanho = TAMANHO_SESSAO_ESTUDO): Promise<Pergunta[]> {
        const perguntas = await this.content.listarPerguntas({ tema, categoria });
        const penalizacao = await this.penalizacaoPorRecencia();

        return amostrarPonderado(perguntas, (pergunta) => penalizacao(pergunta.id), Math.min(tamanho, perguntas.length));
    }

    /** Perguntas que o aluno já errou, da falha mais recente para a mais antiga. */
    async perguntasErradas(categoria: CategoriaCarta, tamanho = TAMANHO_SESSAO_ESTUDO): Promise<Pergunta[]> {
        const [historico, perguntas] = await Promise.all([
            this.storage.listarRespostas(),
            this.content.listarPerguntas({ categoria }),
        ]);
        const porId = new Map(perguntas.map((pergunta) => [pergunta.id, pergunta]));
        const usadas = new Set<string>();
        const erradas: Pergunta[] = [];

        for (const resposta of [...historico].reverse()) {
            if (resposta.acertou || usadas.has(resposta.perguntaId)) continue;
            const pergunta = porId.get(resposta.perguntaId);
            if (!pergunta) continue;
            usadas.add(resposta.perguntaId);
            erradas.push(pergunta);
            if (erradas.length >= tamanho) break;
        }

        return erradas;
    }

    /** Perguntas do pacote atual que ainda não constam do histórico local. */
    async perguntasNuncaRespondidas(categoria: CategoriaCarta, tamanho = TAMANHO_SESSAO_ESTUDO): Promise<Pergunta[]> {
        const [historico, perguntas] = await Promise.all([
            this.storage.listarRespostas(),
            this.content.listarPerguntas({ categoria }),
        ]);
        const respondidas = new Set(historico.map((resposta) => resposta.perguntaId));
        const novas = perguntas.filter((pergunta) => !respondidas.has(pergunta.id));
        return embaralhar(novas).slice(0, Math.min(tamanho, novas.length));
    }

    /** Perguntas com revisão vencida, em qualquer tema (fila "Revisões de hoje"). */
    async perguntasParaRevisao(categoria: CategoriaCarta, tamanho = TAMANHO_SESSAO_ESTUDO): Promise<Pergunta[]> {
        const [pendentes, perguntas] = await Promise.all([
            this.storage.listarRevisoesPendentes(),
            this.content.listarPerguntas({ categoria }),
        ]);

        const porId = new Map(perguntas.map((pergunta) => [pergunta.id, pergunta]));

        // Mais atrasadas primeiro: são as que estão mais perto de ser esquecidas.
        const fila: Pergunta[] = [];

        for (const revisao of pendentes) {
            if (fila.length >= tamanho) {
                break;
            }

            // As perguntas de sinais são geradas e não estão no banco: sem esta
            // reconstrução ficavam eternamente pendentes, sem nunca aparecer.
            const pergunta = TreinoSinaisService.ehPerguntaDeSinal(revisao.perguntaId)
                ? await this.treinoSinais.perguntaDoSinal(revisao.perguntaId)
                : porId.get(revisao.perguntaId);

            if (pergunta) {
                fila.push(pergunta);
            }
        }

        return fila;
    }

    /**
     * Seleção equilibrada por tema: distribui o alvo em rondas pelos temas
     * disponíveis, para o banco maior não dominar a prova.
     */
    private selecionarEquilibrado(perguntas: Pergunta[], alvo: number): Pergunta[] {
        const porTema = new Map<string, Pergunta[]>();
        for (const pergunta of perguntas) {
            const lista = porTema.get(pergunta.tema) ?? [];
            lista.push(pergunta);
            porTema.set(pergunta.tema, lista);
        }

        const filas = [...porTema.values()].map((lista) => embaralhar(lista));
        const selecionadas: Pergunta[] = [];

        for (let ronda = 0; selecionadas.length < alvo; ronda++) {
            let adicionadas = 0;

            for (const fila of filas) {
                if (selecionadas.length >= alvo) {
                    break;
                }
                if (fila[ronda]) {
                    selecionadas.push(fila[ronda]);
                    adicionadas++;
                }
            }

            if (adicionadas === 0) {
                break;
            }
        }

        return embaralhar(selecionadas);
    }

    private async selecionarAdaptativo(perguntas: Pergunta[], alvo: number): Promise<Pergunta[]> {
        const temas = [...new Set(perguntas.map((pergunta) => pergunta.tema))];
        const estatisticas = await this.progresso.estatisticasPorTema(temas);
        const porTema = new Map(estatisticas.map((tema) => [tema.tema, tema]));
        const penalizacao = await this.penalizacaoPorRecencia();

        const fracos = new Set(this.progresso.temasFracos(estatisticas).map((tema) => tema.tema));
        const perguntasFracas = perguntas.filter((pergunta) => fracos.has(pergunta.tema));
        const perguntasRestantes = perguntas.filter((pergunta) => !fracos.has(pergunta.tema));

        const peso = (pergunta: Pergunta): number => {
            const tema = porTema.get(pergunta.tema);
            const base = tema ? this.pesoDoTema(tema) : 1;
            return Math.max(PESO_MINIMO_TEMA, base) * penalizacao(pergunta.id);
        };

        // Quota para temas fracos, com o resto reservado à cobertura.
        const quotaFracos = Math.min(perguntasFracas.length, Math.round(alvo * QUOTA_TEMAS_FRACOS));
        const escolhidasFracas = amostrarPonderado(perguntasFracas, peso, quotaFracos);

        const faltam = alvo - escolhidasFracas.length;
        const escolhidasRestantes = amostrarPonderado(perguntasRestantes, peso, Math.min(faltam, perguntasRestantes.length));

        let selecionadas = [...escolhidasFracas, ...escolhidasRestantes];

        // Banco pequeno: completa com o que sobrar, sem repetir.
        if (selecionadas.length < alvo) {
            const usadas = new Set(selecionadas.map((pergunta) => pergunta.id));
            const sobras = perguntas.filter((pergunta) => !usadas.has(pergunta.id));
            selecionadas = [...selecionadas, ...amostrarPonderado(sobras, peso, alvo - selecionadas.length)];
        }

        return embaralhar(selecionadas);
    }

    private pesoDoTema(tema: ProgressoTema): number {
        if (tema.estado === 'nao_praticado') {
            // Nunca praticado não é o mesmo que fraco: merece exposição, mas
            // não a prioridade máxima de um tema medido e reprovado.
            return PESO_TEMA_NAO_PRATICADO;
        }

        // Revisões pendentes empurram o tema para cima.
        const bonusRevisao = Math.min(0.5, tema.revisoesPendentes * 0.1);

        return Math.max(PESO_MINIMO_TEMA, (1 - tema.taxaRecente) * 2 + bonusRevisao);
    }

    /**
     * Multiplicador que despriorizza perguntas vistas há pouco.
     * O documento pedia "evitando repetir as mesmas de imediato" e não existia
     * qualquer histórico de recência na seleção.
     */
    private async penalizacaoPorRecencia(): Promise<(perguntaId: string) => number> {
        const respostas = await this.storage.listarRespostas();
        const recentes = respostas.slice(-JANELA_RECENCIA);
        const vistas = new Set(recentes.map((resposta) => resposta.perguntaId));

        return (perguntaId: string) => (vistas.has(perguntaId) ? PENALIZACAO_RECENCIA : 1);
    }
}
