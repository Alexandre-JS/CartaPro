import { TestBed } from '@angular/core/testing';
import { RespostaRegisto } from '../models/progresso.model';
import { SinalTransito } from '../models/material-estudo.model';
import { MaterialEstudoService } from './material-estudo.service';
import { StorageService } from './storage.service';
import { TreinoSinaisService } from './treino-sinais.service';

function sinal(slug: string, categoria: string, significado: string): SinalTransito {
    return {
        slug,
        nome: `Sinal ${slug}`,
        categoria,
        tema: 'sinalizacao',
        significado,
        descricao: null,
        artigoRef: null,
        imagem: `/images/signs/${slug}.svg`,
        bloqueado: false,
    };
}

class MaterialFalso {
    constructor(private readonly lista: SinalTransito[]) {}

    async sinais(categoria?: string): Promise<SinalTransito[]> {
        return categoria ? this.lista.filter((item) => item.categoria === categoria) : this.lista;
    }
}

class StorageFalso {
    respostas: RespostaRegisto[] = [];

    async listarRespostas(): Promise<RespostaRegisto[]> {
        return this.respostas;
    }
}

function resposta(perguntaId: string, acertou: boolean): RespostaRegisto {
    return { perguntaId, tema: 'sinalizacao', acertou, data: Date.now(), escolhida: 0, duracaoMs: 4000, origem: 'estudo' };
}

describe('TreinoSinaisService', () => {
    let storage: StorageFalso;

    function montar(lista: SinalTransito[]): TreinoSinaisService {
        storage = new StorageFalso();

        TestBed.resetTestingModule();
        TestBed.configureTestingModule({
            providers: [
                TreinoSinaisService,
                { provide: MaterialEstudoService, useValue: new MaterialFalso(lista) },
                { provide: StorageService, useValue: storage },
            ],
        });

        return TestBed.inject(TreinoSinaisService);
    }

    const biblioteca = [
        sinal('perigo-a', 'perigo', 'Curva perigosa à direita'),
        sinal('perigo-b', 'perigo', 'Descida acentuada'),
        sinal('perigo-c', 'perigo', 'Passagem de nível sem guarda'),
        sinal('perigo-d', 'perigo', 'Queda de pedras'),
        sinal('proibicao-a', 'proibicao', 'Proibido ultrapassar'),
        sinal('proibicao-b', 'proibicao', 'Proibido estacionar'),
    ];

    it('gera perguntas com a resposta certa no índice indicado', async () => {
        const service = montar(biblioteca);

        const sessao = await service.montarSessao(undefined, 6);

        expect(sessao.length).toBe(6);

        for (const pergunta of sessao) {
            const slug = TreinoSinaisService.slugDaPergunta(pergunta.id);
            const origem = biblioteca.find((item) => item.slug === slug);

            expect(origem).toBeDefined();
            // O índice `correta` tem de apontar para o significado do sinal
            // mostrado — é o defeito clássico ao embaralhar opções.
            expect(pergunta.opcoes[pergunta.correta]).toBe(origem!.significado);
            expect(pergunta.imagem).toBe(origem!.imagem);
        }
    });

    it('não repete opções e prefere distratores da mesma categoria', async () => {
        const service = montar(biblioteca);

        const sessao = await service.montarSessao('perigo', 4);

        for (const pergunta of sessao) {
            expect(new Set(pergunta.opcoes).size).toBe(pergunta.opcoes.length);

            // Há 4 sinais de perigo: as 4 opções devem sair todas de "perigo",
            // porque confundir um perigo com uma proibição não treina nada.
            const categorias = pergunta.opcoes.map((opcao) => {
                const dono = biblioteca.find((item) => item.significado === opcao);
                return dono?.categoria;
            });

            expect(categorias.every((categoria) => categoria === 'perigo')).toBe(true);
        }
    });

    it('não gera sessão quando não há sinais suficientes para opções', async () => {
        const service = montar([sinal('unico', 'perigo', 'Curva perigosa')]);

        expect((await service.montarSessao()).length).toBe(0);
    });

    it('ignora sinais sem imagem ou sem significado', async () => {
        const service = montar([
            ...biblioteca,
            { ...sinal('sem-imagem', 'perigo', 'Sem imagem'), imagem: null },
            { ...sinal('sem-significado', 'perigo', ''), significado: '' },
        ]);

        const sessao = await service.montarSessao(undefined, 20);
        const slugs = sessao.map((pergunta) => TreinoSinaisService.slugDaPergunta(pergunta.id));

        expect(slugs).not.toContain('sem-imagem');
        expect(slugs).not.toContain('sem-significado');
    });

    it('prioriza sinais nunca vistos antes dos já acertados', async () => {
        const service = montar(biblioteca);
        storage.respostas = [
            resposta(TreinoSinaisService.idPergunta('perigo-a'), true),
            resposta(TreinoSinaisService.idPergunta('perigo-b'), true),
            resposta(TreinoSinaisService.idPergunta('perigo-c'), true),
            resposta(TreinoSinaisService.idPergunta('perigo-d'), true),
        ];

        const sessao = await service.montarSessao(undefined, 2);
        const slugs = sessao.map((pergunta) => TreinoSinaisService.slugDaPergunta(pergunta.id));

        expect(slugs.sort()).toEqual(['proibicao-a', 'proibicao-b']);
    });

    it('conta e serve os sinais falhados no modo de reforço', async () => {
        const service = montar(biblioteca);
        storage.respostas = [
            resposta(TreinoSinaisService.idPergunta('perigo-a'), false),
            resposta(TreinoSinaisService.idPergunta('proibicao-b'), false),
            resposta(TreinoSinaisService.idPergunta('perigo-c'), true),
            // Respostas do banco de perguntas não são de sinais: não contam.
            resposta('pergunta-normal', false),
        ];

        expect(await service.totalParaReforcar()).toBe(2);

        const sessao = await service.montarSessaoDeReforco(10);
        const slugs = sessao.map((pergunta) => TreinoSinaisService.slugDaPergunta(pergunta.id)).sort();

        expect(slugs).toEqual(['perigo-a', 'proibicao-b']);
    });

    it('deixa de contar um sinal como falhado depois de o acertar mais vezes', async () => {
        const service = montar(biblioteca);
        storage.respostas = [
            resposta(TreinoSinaisService.idPergunta('perigo-a'), false),
            resposta(TreinoSinaisService.idPergunta('perigo-a'), true),
            resposta(TreinoSinaisService.idPergunta('perigo-a'), true),
        ];

        expect(await service.totalParaReforcar()).toBe(0);
    });

    it('reconstrói a pergunta de um sinal a partir do id, para a fila de revisões', async () => {
        const service = montar(biblioteca);

        const pergunta = await service.perguntaDoSinal(TreinoSinaisService.idPergunta('perigo-b'));

        expect(pergunta).toBeDefined();
        expect(pergunta!.opcoes[pergunta!.correta]).toBe('Descida acentuada');
        expect(await service.perguntaDoSinal('pergunta-do-banco')).toBeUndefined();
        expect(await service.perguntaDoSinal(TreinoSinaisService.idPergunta('inexistente'))).toBeUndefined();
    });
});
