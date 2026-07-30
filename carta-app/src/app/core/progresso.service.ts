import { Injectable } from '@angular/core';
import {
    FACILIDADE_INICIAL,
    FACILIDADE_MAXIMA,
    FACILIDADE_MINIMA,
    INTERVALO_MAXIMO_DIAS,
    INTERVALO_PRIMEIRA_REVISAO,
    INTERVALO_SEGUNDA_REVISAO,
    JANELA_MAESTRIA,
    LIMITE_MAESTRIA,
    MINIMO_AMOSTRA_DIAGNOSTICO,
    MINIMO_AMOSTRA_MAESTRIA,
    MS_MINIMO_RESPOSTA_CONSIDERADA,
    TAMANHO_SESSAO_ESTUDO,
} from '../config/simulado.config';
import { Pergunta } from '../models/pergunta.model';
import {
    EstadoTema,
    OrigemResposta,
    ProgressoTema,
    RecomendacaoEstudo,
    RespostaRegisto,
    RevisaoAgendada,
} from '../models/progresso.model';
import { StorageService } from './storage.service';

export interface ContextoResposta {
    /** Opção escolhida pelo aluno. */
    escolhida: number;
    /** Tempo desde que a pergunta apareceu, em ms. */
    duracaoMs?: number;
    origem?: OrigemResposta;
}

@Injectable({ providedIn: 'root' })
export class ProgressoService {
    constructor(private readonly storage: StorageService) {}

    /**
     * Registo de resposta com telemetria.
     *
     * Antes só se guardava acertou/errou. Sem a opção escolhida e o tempo não é
     * possível medir a dificuldade real de cada pergunta, detetar adivinhação
     * nem estimar probabilidade de aprovação.
     */
    async registarResposta(pergunta: Pergunta, contexto: ContextoResposta | number): Promise<boolean> {
        const dados: ContextoResposta = typeof contexto === 'number' ? { escolhida: contexto } : contexto;
        const acertou = dados.escolhida === pergunta.correta;

        await this.storage.registarResposta({
            perguntaId: pergunta.id,
            tema: pergunta.tema,
            acertou,
            data: Date.now(),
            escolhida: dados.escolhida,
            duracaoMs: dados.duracaoMs ?? null,
            origem: dados.origem ?? 'simulado',
        });

        await this.agendarRevisao(pergunta, acertou, dados.duracaoMs);

        return acertou;
    }

    async estatisticasPorTema(temas: string[]): Promise<ProgressoTema[]> {
        const [respostas, revisoes] = await Promise.all([
            this.storage.listarRespostas(),
            this.storage.listarRevisoesPendentes(),
        ]);

        const pendentesPorTema = new Map<string, number>();
        for (const revisao of revisoes) {
            pendentesPorTema.set(revisao.tema, (pendentesPorTema.get(revisao.tema) ?? 0) + 1);
        }

        return temas.map((tema) =>
            this.calcularTema(
                tema,
                respostas.filter((resposta) => resposta.tema === tema),
                pendentesPorTema.get(tema) ?? 0,
            ),
        );
    }

    /** Temas com desempenho sólido — exige amostra suficiente. */
    temasFortes(estatisticas: ProgressoTema[]): ProgressoTema[] {
        return estatisticas.filter((tema) => tema.estado === 'dominado' || tema.estado === 'solido');
    }

    /**
     * Temas fracos: **só** os que têm amostra e desempenho abaixo do limite.
     *
     * Antes incluía os temas com zero respostas, o que tinha duas consequências
     * graves: o modo adaptativo ficava igual ao normal para qualquer aluno novo,
     * e o ecrã de resultado listava como "fracos" dezenas de temas nunca vistos.
     */
    temasFracos(estatisticas: ProgressoTema[]): ProgressoTema[] {
        return estatisticas.filter((tema) => tema.estado === 'fraco');
    }

    /** Temas que o aluno ainda não praticou — categoria distinta de "fraco". */
    temasNaoPraticados(estatisticas: ProgressoTema[]): ProgressoTema[] {
        return estatisticas.filter((tema) => tema.estado === 'nao_praticado');
    }

    /** Temas com dados insuficientes para julgar. */
    temasEmAvaliacao(estatisticas: ProgressoTema[]): ProgressoTema[] {
        return estatisticas.filter((tema) => tema.estado === 'em_avaliacao');
    }

    /**
     * Peso de um tema na seleção adaptativa: quanto pior o desempenho recente,
     * maior o peso. Nunca zero, para o exame manter cobertura.
     */
    pesoTema(tema: ProgressoTema): number {
        if (tema.estado === 'nao_praticado') {
            return 1;
        }

        return Math.max(0, 1 - tema.taxaRecente);
    }

    recomendarEstudo(estatisticas: ProgressoTema[], totalPerguntas = TAMANHO_SESSAO_ESTUDO, temaRevisao?: string): RecomendacaoEstudo | null {
        if (!estatisticas.length) {
            return null;
        }

        // Revisão pendente vence sempre: é conhecimento a caminho de se perder.
        if (temaRevisao) {
            const tema = estatisticas.find((item) => item.tema === temaRevisao);
            if (tema) {
                return this.montarRecomendacao(tema, 'revisar', `Tens ${tema.revisoesPendentes || 1} revisão(ões) pendente(s) neste tema.`, totalPerguntas);
            }
        }

        const fracos = this.temasFracos(estatisticas);
        if (fracos.length) {
            const pior = [...fracos].sort((a, b) => a.taxaRecente - b.taxaRecente)[0];
            return this.montarRecomendacao(pior, 'reforcar', `É o tema que mais precisa de atenção (${Math.round(pior.taxaRecente * 100)}% nas últimas respostas).`, totalPerguntas);
        }

        const emAvaliacao = this.temasEmAvaliacao(estatisticas);
        if (emAvaliacao.length) {
            const tema = [...emAvaliacao].sort((a, b) => a.respondidas - b.respondidas)[0];
            return this.montarRecomendacao(tema, 'reforcar', `Faltam ${Math.max(1, MINIMO_AMOSTRA_DIAGNOSTICO - tema.respondidas)} respostas para avaliarmos este tema.`, totalPerguntas);
        }

        const naoPraticados = this.temasNaoPraticados(estatisticas);
        if (naoPraticados.length) {
            return this.montarRecomendacao(naoPraticados[0], 'comecar', 'Ainda não praticaste este tema.', totalPerguntas);
        }

        const menosRecente = [...estatisticas].sort((a, b) => a.taxaRecente - b.taxaRecente)[0];

        return this.montarRecomendacao(menosRecente, 'continuar', 'Mantém o conhecimento ativo com uma prática curta.', totalPerguntas);
    }

    /**
     * Sinais de risco úteis ao aluno e à escola: perguntas provavelmente
     * adivinhadas (acertou muito depressa) e distratores que mais enganam.
     */
    async diagnosticoAvancado(): Promise<{
        acertosSuspeitos: number;
        totalComTelemetria: number;
        distratoresFrequentes: Array<{ perguntaId: string; opcao: number; vezes: number }>;
    }> {
        const respostas = await this.storage.listarRespostas();
        const comTempo = respostas.filter((resposta) => typeof resposta.duracaoMs === 'number' && resposta.duracaoMs! > 0);

        const acertosSuspeitos = comTempo.filter(
            (resposta) => resposta.acertou && resposta.duracaoMs! < MS_MINIMO_RESPOSTA_CONSIDERADA,
        ).length;

        const contagem = new Map<string, number>();
        for (const resposta of respostas) {
            if (resposta.acertou || resposta.escolhida === null || resposta.escolhida === undefined) {
                continue;
            }
            const chave = `${resposta.perguntaId}#${resposta.escolhida}`;
            contagem.set(chave, (contagem.get(chave) ?? 0) + 1);
        }

        const distratoresFrequentes = [...contagem.entries()]
            .map(([chave, vezes]) => {
                const [perguntaId, opcao] = chave.split('#');
                return { perguntaId, opcao: Number(opcao), vezes };
            })
            .sort((a, b) => b.vezes - a.vezes)
            .slice(0, 10);

        return { acertosSuspeitos, totalComTelemetria: comTempo.length, distratoresFrequentes };
    }

    /**
     * Agendamento SM-2 simplificado.
     *
     * A escada fixa [1,3,7,14,30] reiniciava a zero em cada erro e perdia toda
     * a informação acumulada. Agora cada pergunta guarda o seu fator de
     * facilidade, que sobe com acertos e desce com lapsos.
     */
    private async agendarRevisao(pergunta: Pergunta, acertou: boolean, duracaoMs?: number): Promise<void> {
        const existente = await this.storage.obterRevisao(pergunta.id);

        const facilidadeAtual = existente?.facilidade ?? FACILIDADE_INICIAL;
        const repeticoesAtuais = existente?.repeticoes ?? 0;
        const lapsosAtuais = existente?.lapsos ?? 0;
        const intervaloAtual = existente?.intervaloDias ?? 0;

        // Acerto muito rápido conta como acerto, mas não aumenta a facilidade:
        // pode ter sido adivinha ou pergunta já memorizada por repetição.
        const acertoConfiante = acertou && (duracaoMs === undefined || duracaoMs >= MS_MINIMO_RESPOSTA_CONSIDERADA);

        let facilidade = facilidadeAtual;
        let repeticoes = repeticoesAtuais;
        let lapsos = lapsosAtuais;
        let intervaloDias: number;

        if (acertou) {
            repeticoes += 1;
            if (acertoConfiante) {
                facilidade = Math.min(FACILIDADE_MAXIMA, facilidadeAtual + 0.1);
            }

            if (repeticoes === 1) {
                intervaloDias = INTERVALO_PRIMEIRA_REVISAO;
            } else if (repeticoes === 2) {
                intervaloDias = INTERVALO_SEGUNDA_REVISAO;
            } else {
                intervaloDias = Math.min(
                    INTERVALO_MAXIMO_DIAS,
                    Math.max(INTERVALO_SEGUNDA_REVISAO + 1, Math.round(Math.max(1, intervaloAtual) * facilidade)),
                );
            }
        } else {
            // Erro: volta à fila de hoje, mas a facilidade retém a história.
            lapsos += 1;
            repeticoes = 0;
            facilidade = Math.max(FACILIDADE_MINIMA, facilidadeAtual - 0.2);
            intervaloDias = 0;
        }

        const inicioHoje = new Date();
        inicioHoje.setHours(0, 0, 0, 0);

        await this.storage.guardarRevisao({
            perguntaId: pergunta.id,
            tema: pergunta.tema,
            agendadaPara: acertou ? Date.now() + intervaloDias * 24 * 60 * 60 * 1000 : inicioHoje.getTime(),
            intervaloDias,
            facilidade: Number(facilidade.toFixed(2)),
            repeticoes,
            lapsos,
            ultimaRevisaoEm: Date.now(),
        });
    }

    private montarRecomendacao(tema: ProgressoTema, acao: RecomendacaoEstudo['acao'], motivo: string, totalPerguntas: number): RecomendacaoEstudo {
        return {
            tema: tema.tema,
            acao,
            motivo,
            totalPerguntas,
            minutosEstimados: Math.max(4, Math.ceil(totalPerguntas * 1.5)),
        };
    }

    private calcularTema(tema: string, respostas: RespostaRegisto[], revisoesPendentes: number): ProgressoTema {
        const recentes = respostas.slice(-JANELA_MAESTRIA);
        const acertos = respostas.filter((resposta) => resposta.acertou).length;
        const acertosRecentes = recentes.filter((resposta) => resposta.acertou).length;

        const taxaAcerto = respostas.length ? acertos / respostas.length : 0;
        const taxaRecente = recentes.length ? acertosRecentes / recentes.length : 0;

        const comTempo = respostas.filter((resposta) => typeof resposta.duracaoMs === 'number' && resposta.duracaoMs! > 0);
        const tempoMedioMs = comTempo.length
            ? Math.round(comTempo.reduce((total, resposta) => total + resposta.duracaoMs!, 0) / comTempo.length)
            : 0;

        /*
         * Maestria corrigida.
         *
         * A condição anterior era `recentes.length >= Math.min(JANELA, n)`, que
         * é sempre verdadeira porque `recentes` é `slice(-JANELA)`. Resultado:
         * uma única resposta certa marcava o tema como dominado. Agora exige-se
         * MINIMO_AMOSTRA_MAESTRIA respostas antes de afirmar domínio.
         */
        const amostraSuficiente = respostas.length >= MINIMO_AMOSTRA_MAESTRIA;
        const graduado = amostraSuficiente && taxaRecente >= LIMITE_MAESTRIA;

        return {
            tema,
            respondidas: respostas.length,
            acertos,
            taxaAcerto,
            taxaRecente,
            estado: this.classificar(respostas.length, taxaRecente, graduado),
            graduado,
            revisoesPendentes,
            tempoMedioMs,
        };
    }

    private classificar(respondidas: number, taxaRecente: number, graduado: boolean): EstadoTema {
        if (respondidas === 0) {
            return 'nao_praticado';
        }

        if (respondidas < MINIMO_AMOSTRA_DIAGNOSTICO) {
            return 'em_avaliacao';
        }

        if (taxaRecente < LIMITE_MAESTRIA) {
            return 'fraco';
        }

        return graduado ? 'dominado' : 'solido';
    }
}
