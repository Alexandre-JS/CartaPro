import { QUOTA_TEMAS_FRACOS, TAMANHO_SIMULADO } from '../config/simulado.config';
import { CategoriaCarta, Pergunta } from '../models/pergunta.model';
import { RespostaRegisto, RevisaoAgendada } from '../models/progresso.model';
import { ContentService } from './content.service';
import { ProgressoService } from './progresso.service';
import { SimuladoService } from './simulado.service';
import { StorageService } from './storage.service';
import { TreinoSinaisService } from './treino-sinais.service';

function pergunta(id: string, tema: string): Pergunta {
    return {
        id,
        tipo: 'teorico',
        tema,
        categoriaCarta: ['ligeiro'],
        enunciado: `Pergunta ${id}`,
        imagem: null,
        opcoes: ['A', 'B', 'C'],
        correta: 0,
        explicacao: 'Explicação.',
        artigoRef: null,
        bloqueado: false,
    };
}

function banco(distribuicao: Record<string, number>): Pergunta[] {
    const perguntas: Pergunta[] = [];
    Object.keys(distribuicao).forEach((tema: string) => {
        for (let i = 0; i < distribuicao[tema]; i++) {
            perguntas.push(pergunta(`${tema}-${i}`, tema));
        }
    });
    return perguntas;
}

class StorageFalso {
    respostas: RespostaRegisto[] = [];
    revisoes = new Map<string, RevisaoAgendada>();

    async registarResposta(registo: RespostaRegisto): Promise<void> {
        this.respostas.push(registo);
    }

    async listarRespostas(): Promise<RespostaRegisto[]> {
        return [...this.respostas].sort((a, b) => a.data - b.data);
    }

    async obterRevisao(perguntaId: string): Promise<RevisaoAgendada | undefined> {
        return this.revisoes.get(perguntaId);
    }

    async guardarRevisao(revisao: RevisaoAgendada): Promise<void> {
        this.revisoes.set(revisao.perguntaId, revisao);
    }

    async listarRevisoesPendentes(agora = Date.now()): Promise<RevisaoAgendada[]> {
        return [...this.revisoes.values()]
            .filter((revisao) => revisao.agendadaPara <= agora)
            .sort((a, b) => a.agendadaPara - b.agendadaPara);
    }
}

class ContentFalso {
    constructor(private readonly perguntas: Pergunta[]) {}

    async listarPerguntas(filtros: { tema?: string; categoria?: CategoriaCarta } = {}): Promise<Pergunta[]> {
        return this.perguntas.filter((p) => !filtros.tema || p.tema === filtros.tema);
    }
}

/** Sem biblioteca de sinais nestes testes: nenhuma revisão é de um sinal. */
class TreinoSinaisFalso {
    async perguntaDoSinal(): Promise<Pergunta | undefined> {
        return undefined;
    }
}

describe('SimuladoService', () => {
    let storage: StorageFalso;
    let progresso: ProgressoService;

    function montar(perguntas: Pergunta[]): SimuladoService {
        const content = new ContentFalso(perguntas) as unknown as ContentService;
        return new SimuladoService(
            content,
            progresso,
            storage as unknown as StorageService,
            new TreinoSinaisFalso() as unknown as TreinoSinaisService,
        );
    }

    beforeEach(() => {
        storage = new StorageFalso();
        progresso = new ProgressoService(storage as unknown as StorageService);
    });

    async function errarNoTema(tema: string, vezes: number): Promise<void> {
        for (let i = 0; i < vezes; i++) {
            await progresso.registarResposta(pergunta(`hist-${tema}-${i}`, tema), { escolhida: 1, duracaoMs: 6000 });
        }
    }

    async function acertarNoTema(tema: string, vezes: number): Promise<void> {
        for (let i = 0; i < vezes; i++) {
            await progresso.registarResposta(pergunta(`hist-${tema}-${i}`, tema), { escolhida: 0, duracaoMs: 6000 });
        }
    }

    describe('cobertura do exame (regressão do defeito C2)', () => {
        it('mantém cobertura dos temas não fracos em vez de encher só com fracos', async () => {
            // O tema fraco tem sozinho mais perguntas do que o simulado inteiro:
            // era exatamente o caso em que o motor antigo produzia uma prova
            // 100% do tema fraco, deixando de simular o exame real.
            const service = montar(banco({ sinais: 40, velocidade: 20, prioridade: 20 }));
            await errarNoTema('sinais', 10);

            const simulado = await service.montarSimulado('ligeiro', true);

            expect(simulado.length).toBe(TAMANHO_SIMULADO);

            const deSinais = simulado.filter((p) => p.tema === 'sinais').length;
            const limiteEsperado = Math.round(TAMANHO_SIMULADO * QUOTA_TEMAS_FRACOS);

            expect(deSinais).toBeLessThanOrEqual(limiteEsperado);
            // E os outros temas continuam representados.
            expect(simulado.some((p) => p.tema === 'velocidade' || p.tema === 'prioridade')).toBeTrue();
        });

        it('dá mais peso ao tema fraco do que ao tema dominado', async () => {
            const service = montar(banco({ sinais: 40, velocidade: 40 }));
            await errarNoTema('sinais', 10);
            await acertarNoTema('velocidade', 10);

            let deSinais = 0;
            let deVelocidade = 0;

            for (let i = 0; i < 20; i++) {
                const simulado = await service.montarSimulado('ligeiro', true);
                deSinais += simulado.filter((p) => p.tema === 'sinais').length;
                deVelocidade += simulado.filter((p) => p.tema === 'velocidade').length;
            }

            expect(deSinais).toBeGreaterThan(deVelocidade);
        });

        it('para um aluno novo o modo adaptativo não colapsa num só tema', async () => {
            // Sem histórico, nenhum tema é "fraco" — antes todos eram, e o modo
            // adaptativo ficava indistinguível do normal.
            const service = montar(banco({ sinais: 30, velocidade: 30, prioridade: 30 }));

            const simulado = await service.montarSimulado('ligeiro', true);
            const temas = new Set(simulado.map((p) => p.tema));

            expect(simulado.length).toBe(TAMANHO_SIMULADO);
            expect(temas.size).toBe(3);
        });

        it('modo normal distribui equilibradamente entre os temas', async () => {
            const service = montar(banco({ sinais: 100, velocidade: 5, prioridade: 5 }));

            const simulado = await service.montarSimulado('ligeiro', false);
            const deSinais = simulado.filter((p) => p.tema === 'sinais').length;

            // O banco maior não domina: 5 + 5 dos pequenos entram primeiro.
            expect(simulado.length).toBe(TAMANHO_SIMULADO);
            expect(deSinais).toBeLessThanOrEqual(15);
            expect(simulado.filter((p) => p.tema === 'velocidade').length).toBe(5);
        });

        it('não repete perguntas dentro do mesmo simulado', async () => {
            const service = montar(banco({ sinais: 30, velocidade: 30 }));
            await errarNoTema('sinais', 10);

            const simulado = await service.montarSimulado('ligeiro', true);

            expect(new Set(simulado.map((p) => p.id)).size).toBe(simulado.length);
        });

        it('devolve o banco todo quando é menor do que o simulado', async () => {
            const service = montar(banco({ sinais: 4 }));

            expect((await service.montarSimulado('ligeiro', true)).length).toBe(4);
            expect((await service.montarSimulado('ligeiro', false)).length).toBe(4);
        });
    });

    describe('recência (o documento pedia "evitar repetir de imediato")', () => {
        it('despriorizza perguntas respondidas há pouco', async () => {
            const perguntas = banco({ velocidade: 20 });
            const service = montar(perguntas);

            // Marca 10 como vistas agora mesmo.
            const vistas = perguntas.slice(0, 10);
            for (const p of vistas) {
                await progresso.registarResposta(p, { escolhida: 0, duracaoMs: 6000 });
            }
            const idsVistas = new Set(vistas.map((p) => p.id));

            let repetidas = 0;
            const execucoes = 30;
            for (let i = 0; i < execucoes; i++) {
                const sessao = await service.perguntasPorTema('velocidade', 'ligeiro', 5);
                repetidas += sessao.filter((p) => idsVistas.has(p.id)).length;
            }

            // Sem penalização esperar-se-iam ~50% de repetições (75 de 150).
            expect(repetidas).toBeLessThan(execucoes * 5 * 0.3);
        });
    });

    describe('fila de revisões', () => {
        it('devolve apenas perguntas com revisão vencida, de qualquer tema', async () => {
            const perguntas = banco({ sinais: 5, velocidade: 5 });
            const service = montar(perguntas);

            // Erro agenda para hoje (vencida); acerto agenda para amanhã.
            await progresso.registarResposta(perguntas[0], { escolhida: 1, duracaoMs: 6000 });
            await progresso.registarResposta(perguntas[5], { escolhida: 1, duracaoMs: 6000 });
            await progresso.registarResposta(perguntas[1], { escolhida: 0, duracaoMs: 6000 });

            const fila = await service.perguntasParaRevisao('ligeiro', 10);
            const ids = fila.map((p) => p.id);

            expect(ids).toContain(perguntas[0].id);
            expect(ids).toContain(perguntas[5].id);
            expect(ids).not.toContain(perguntas[1].id);
            // Atravessa temas — antes só se revia um tema de cada vez.
            expect(new Set(fila.map((p) => p.tema)).size).toBe(2);
        });
    });

    describe('sessões baseadas no histórico local', () => {
        it('Meus erros devolve cada pergunta falhada uma única vez, da mais recente para a mais antiga', async () => {
            const perguntas = banco({ velocidade: 5 });
            const service = montar(perguntas);

            await progresso.registarResposta(perguntas[0], { escolhida: 1, duracaoMs: 6000 });
            await progresso.registarResposta(perguntas[1], { escolhida: 0, duracaoMs: 6000 });
            await progresso.registarResposta(perguntas[2], { escolhida: 2, duracaoMs: 6000 });
            await progresso.registarResposta(perguntas[0], { escolhida: 2, duracaoMs: 6000 });

            const erradas = await service.perguntasErradas('ligeiro', 10);

            expect(erradas.map((p) => p.id)).toEqual([perguntas[0].id, perguntas[2].id]);
        });

        it('Nunca respondidas exclui qualquer pergunta que já esteja no histórico', async () => {
            const perguntas = banco({ prioridade: 6 });
            const service = montar(perguntas);

            await progresso.registarResposta(perguntas[0], { escolhida: 0, duracaoMs: 6000 });
            await progresso.registarResposta(perguntas[1], { escolhida: 2, duracaoMs: 6000 });

            const novas = await service.perguntasNuncaRespondidas('ligeiro', 10);
            const ids = new Set(novas.map((p) => p.id));

            expect(novas.length).toBe(4);
            expect(ids.has(perguntas[0].id)).toBeFalse();
            expect(ids.has(perguntas[1].id)).toBeFalse();
        });
    });
});
