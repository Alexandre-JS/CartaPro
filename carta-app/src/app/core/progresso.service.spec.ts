import { ProgressoService } from './progresso.service';
import { StorageService } from './storage.service';
import { LIMITE_MAESTRIA, MINIMO_AMOSTRA_MAESTRIA } from '../config/simulado.config';
import { Pergunta } from '../models/pergunta.model';
import { RespostaRegisto, RevisaoAgendada } from '../models/progresso.model';

/** Armazenamento em memória: os testes não tocam no IndexedDB. */
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
        return [...this.revisoes.values()].filter((revisao) => revisao.agendadaPara <= agora);
    }
}

function pergunta(id: string, tema = 'velocidade', correta = 0): Pergunta {
    return {
        id,
        tipo: 'teorico',
        tema,
        categoriaCarta: ['ligeiro'],
        enunciado: `Pergunta ${id}`,
        imagem: null,
        opcoes: ['A', 'B', 'C'],
        correta,
        explicacao: 'Explicação.',
        artigoRef: null,
        bloqueado: false,
    };
}

describe('ProgressoService', () => {
    let storage: StorageFalso;
    let service: ProgressoService;

    beforeEach(() => {
        storage = new StorageFalso();
        service = new ProgressoService(storage as unknown as StorageService);
    });

    async function responder(vezes: number, acertar: boolean, tema = 'velocidade', idBase = 'q'): Promise<void> {
        for (let i = 0; i < vezes; i++) {
            const p = pergunta(`${idBase}-${i}`, tema);
            await service.registarResposta(p, { escolhida: acertar ? p.correta : p.correta + 1, duracaoMs: 6000 });
        }
    }

    describe('maestria (regressão do defeito C1)', () => {
        it('NÃO considera o tema dominado com uma única resposta certa', async () => {
            await responder(1, true);

            const [tema] = await service.estatisticasPorTema(['velocidade']);

            expect(tema.respondidas).toBe(1);
            expect(tema.taxaRecente).toBe(1);
            // Era exatamente aqui que o motor antigo dizia `graduado: true`.
            expect(tema.graduado).toBeFalse();
            expect(tema.estado).toBe('em_avaliacao');
        });

        it('exige a amostra mínima antes de graduar', async () => {
            await responder(MINIMO_AMOSTRA_MAESTRIA - 1, true);
            let [tema] = await service.estatisticasPorTema(['velocidade']);
            expect(tema.graduado).toBeFalse();

            await responder(1, true, 'velocidade', 'extra');
            [tema] = await service.estatisticasPorTema(['velocidade']);
            expect(tema.respondidas).toBe(MINIMO_AMOSTRA_MAESTRIA);
            expect(tema.graduado).toBeTrue();
            expect(tema.estado).toBe('dominado');
        });

        it('não gradua quem tem amostra mas está abaixo do limite', async () => {
            await responder(6, true, 'velocidade', 'certa');
            await responder(6, false, 'velocidade', 'errada');

            const [tema] = await service.estatisticasPorTema(['velocidade']);

            expect(tema.respondidas).toBe(12);
            expect(tema.taxaRecente).toBeLessThan(LIMITE_MAESTRIA);
            expect(tema.graduado).toBeFalse();
            expect(tema.estado).toBe('fraco');
        });
    });

    describe('diagnóstico (regressão do defeito C7)', () => {
        it('separa "nunca praticado" de "fraco"', async () => {
            await responder(6, false, 'sinais_perigo', 'mal');

            const estatisticas = await service.estatisticasPorTema(['sinais_perigo', 'prioridade', 'mecanica']);

            const fracos = service.temasFracos(estatisticas).map((tema) => tema.tema);
            const naoPraticados = service.temasNaoPraticados(estatisticas).map((tema) => tema.tema);

            // Antes os três apareciam como "fracos", inundando o diagnóstico.
            expect(fracos).toEqual(['sinais_perigo']);
            expect(naoPraticados).toEqual(['prioridade', 'mecanica']);
        });

        it('não classifica como forte um tema sem amostra suficiente', async () => {
            await responder(2, true, 'prioridade', 'p');

            const estatisticas = await service.estatisticasPorTema(['prioridade']);

            expect(service.temasFortes(estatisticas)).toEqual([]);
            expect(service.temasEmAvaliacao(estatisticas).length).toBe(1);
        });

        it('sinaliza acertos demasiado rápidos como possível adivinhação', async () => {
            const p = pergunta('rapida');
            await service.registarResposta(p, { escolhida: p.correta, duracaoMs: 400 });
            await service.registarResposta(pergunta('lenta'), { escolhida: 0, duracaoMs: 9000 });

            const diagnostico = await service.diagnosticoAvancado();

            expect(diagnostico.acertosSuspeitos).toBe(1);
            expect(diagnostico.totalComTelemetria).toBe(2);
        });

        it('conta qual distrator engana mais', async () => {
            const p = pergunta('d-1');
            for (let i = 0; i < 3; i++) {
                await service.registarResposta(p, { escolhida: 2, duracaoMs: 5000 });
            }
            await service.registarResposta(p, { escolhida: 1, duracaoMs: 5000 });

            const { distratoresFrequentes } = await service.diagnosticoAvancado();

            expect(distratoresFrequentes[0]).toEqual({ perguntaId: 'd-1', opcao: 2, vezes: 3 });
        });
    });

    describe('repetição espaçada SM-2 (regressão do defeito C4)', () => {
        it('aumenta o intervalo a cada acerto consecutivo', async () => {
            const p = pergunta('sm2');

            await service.registarResposta(p, { escolhida: p.correta, duracaoMs: 6000 });
            const primeira = await storage.obterRevisao('sm2');
            expect(primeira!.intervaloDias).toBe(1);
            expect(primeira!.repeticoes).toBe(1);

            await service.registarResposta(p, { escolhida: p.correta, duracaoMs: 6000 });
            const segunda = await storage.obterRevisao('sm2');
            expect(segunda!.intervaloDias).toBe(3);

            await service.registarResposta(p, { escolhida: p.correta, duracaoMs: 6000 });
            const terceira = await storage.obterRevisao('sm2');
            expect(terceira!.intervaloDias).toBeGreaterThan(3);
            // A facilidade sobe com acertos confiantes.
            expect(terceira!.facilidade).toBeGreaterThan(primeira!.facilidade);
        });

        it('retém a história do item ao errar, em vez de reiniciar tudo', async () => {
            const p = pergunta('lapso');

            for (let i = 0; i < 3; i++) {
                await service.registarResposta(p, { escolhida: p.correta, duracaoMs: 6000 });
            }
            const antesDoErro = await storage.obterRevisao('lapso');

            await service.registarResposta(p, { escolhida: p.correta + 1, duracaoMs: 6000 });
            const depoisDoErro = await storage.obterRevisao('lapso');

            // Volta à fila de hoje…
            expect(depoisDoErro!.intervaloDias).toBe(0);
            expect(depoisDoErro!.agendadaPara).toBeLessThanOrEqual(Date.now());
            // …mas a facilidade e o histórico de lapsos sobrevivem.
            expect(depoisDoErro!.lapsos).toBe(1);
            expect(depoisDoErro!.facilidade).toBeLessThan(antesDoErro!.facilidade);
            expect(depoisDoErro!.facilidade).toBeGreaterThanOrEqual(1.3);
        });

        it('não infla a facilidade em acertos instantâneos', async () => {
            const p = pergunta('adivinha');

            await service.registarResposta(p, { escolhida: p.correta, duracaoMs: 300 });
            const revisao = await storage.obterRevisao('adivinha');

            expect(revisao!.repeticoes).toBe(1);
            expect(revisao!.facilidade).toBe(2.5);
        });
    });

    describe('recomendação de estudo', () => {
        it('dá prioridade à revisão pendente', async () => {
            await responder(6, false, 'sinais_perigo', 'x');
            const estatisticas = await service.estatisticasPorTema(['sinais_perigo', 'prioridade']);

            const recomendacao = service.recomendarEstudo(estatisticas, 5, 'prioridade');

            expect(recomendacao!.tema).toBe('prioridade');
            expect(recomendacao!.acao).toBe('revisar');
        });

        it('escolhe o pior tema medido antes de temas nunca praticados', async () => {
            await responder(6, false, 'sinais_perigo', 'mal');
            await responder(6, true, 'velocidade', 'bem');

            const estatisticas = await service.estatisticasPorTema(['sinais_perigo', 'velocidade', 'mecanica']);
            const recomendacao = service.recomendarEstudo(estatisticas);

            expect(recomendacao!.tema).toBe('sinais_perigo');
            expect(recomendacao!.acao).toBe('reforcar');
        });

        it('sugere começar quando não há nada medido em falta', async () => {
            await responder(MINIMO_AMOSTRA_MAESTRIA, true, 'velocidade', 'ok');

            const estatisticas = await service.estatisticasPorTema(['velocidade', 'mecanica']);
            const recomendacao = service.recomendarEstudo(estatisticas);

            expect(recomendacao!.tema).toBe('mecanica');
            expect(recomendacao!.acao).toBe('comecar');
        });
    });
});
