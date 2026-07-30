import { TestBed } from '@angular/core/testing';
import { MaterialEstudo } from '../models/material-estudo.model';
import { Pacote } from '../models/pacote.model';
import { ContentService } from './content.service';
import { MaterialEstudoService } from './material-estudo.service';
import { StorageService } from './storage.service';

const ESTUDO: MaterialEstudo = {
    taxonomia: {
        categoriasSinais: [
            { slug: 'perigo', nome: 'Sinais de perigo', descricao: null, icone: 'warning-outline', ordem: 1 },
            { slug: 'semaforos', nome: 'Semaforização', descricao: null, icone: 'stop-circle-outline', ordem: 2 },
        ],
        gruposLicoes: [
            { slug: 'codigo', nome: 'Regras do Código', descricao: null, icone: 'book-outline', ordem: 1 },
            { slug: 'mecanica', nome: 'Mecânica básica', descricao: null, icone: 'construct-outline', ordem: 2 },
        ],
    },
    sinais: [
        {
            slug: 'curva-direita',
            nome: 'Curva à direita',
            categoria: 'perigo',
            tema: 'sinalizacao',
            significado: 'Curva perigosa à direita',
            descricao: 'Reduz a velocidade antes de entrar na curva.',
            artigoRef: 12,
            imagem: '/images/signs/curva-direita.svg',
            bloqueado: false,
        },
    ],
    licoes: [
        {
            slug: 'distancia-de-seguranca',
            titulo: 'Distância de segurança',
            resumo: 'Quanto espaço deixar para o veículo à frente.',
            corpo: 'Primeiro parágrafo.\n\n- Item um\n- Item dois\n\nSegundo parágrafo.',
            grupo: 'codigo',
            tema: 'circulacao',
            categoriasCarta: ['ligeiro'],
            sinais: ['curva-direita'],
            artigos: [12],
            minutosLeitura: 4,
            bloqueado: false,
        },
    ],
    capitulos: [
        { numero: 2, titulo: 'Da circulação', artigos: [12] },
        { numero: null, titulo: 'Outras disposições', artigos: [99] },
    ],
    artigos: [
        { numero: 12, capitulo: 2, capituloTitulo: 'Da circulação', titulo: 'Cedência de passagem', texto: 'Texto do artigo.' },
        { numero: 99, capitulo: null, capituloTitulo: null, titulo: 'Disposições finais', texto: 'Texto final.' },
    ],
    glossario: [
        { slug: 'cedencia-de-passagem', termo: 'Cedência de passagem', definicao: 'Deixar passar outro veículo.', artigoRef: 12 },
    ],
    licoesBloqueadas: 3,
    sinaisBloqueados: 7,
};

class ContentFalso {
    pacote: Pacote = { versao: '1', temas: [], perguntas: [], provas: [], estudo: ESTUDO };

    /** Devolve sempre a mesma referência, como o ContentService real em cache. */
    async carregarPacote(): Promise<Pacote> {
        return this.pacote;
    }
}

class StorageFalso {
    lidos: string[] = [];

    async listarConteudosLidos(): Promise<string[]> {
        return this.lidos;
    }

    async marcarConteudoLido(chave: string): Promise<void> {
        if (!this.lidos.includes(chave)) {
            this.lidos.push(chave);
        }
    }
}

describe('MaterialEstudoService', () => {
    let service: MaterialEstudoService;
    let storage: StorageFalso;
    let content: ContentFalso;

    beforeEach(() => {
        storage = new StorageFalso();
        content = new ContentFalso();

        TestBed.resetTestingModule();
        TestBed.configureTestingModule({
            providers: [
                MaterialEstudoService,
                { provide: ContentService, useValue: content },
                { provide: StorageService, useValue: storage },
            ],
        });

        service = TestBed.inject(MaterialEstudoService);
    });

    it('esconde categorias e grupos sem conteúdo publicado', async () => {
        // A taxonomia vem completa da API; o app só mostra o que tem material.
        expect((await service.categoriasSinais()).map((item) => item.slug)).toEqual(['perigo']);
        expect((await service.gruposLicoes()).map((item) => item.slug)).toEqual(['codigo']);
    });

    it('pesquisa sem acentos e sem distinguir maiúsculas', async () => {
        expect((await service.procurarSinais('CURVA PERIGOSA A DIREITA')).length).toBe(1);
        expect((await service.procurarArtigos('cedencia')).length).toBe(1);
        expect((await service.glossario('CEDENCIA')).length).toBe(1);
        expect((await service.procurarArtigos('inexistente')).length).toBe(0);
    });

    it('filtra artigos por capítulo, incluindo os que não têm capítulo', async () => {
        expect((await service.artigos(2)).map((artigo) => artigo.numero)).toEqual([12]);
        expect((await service.artigos(null)).map((artigo) => artigo.numero)).toEqual([99]);
        expect((await service.artigos()).length).toBe(2);
    });

    it('liga fichas a sinais, artigos e temas', async () => {
        expect((await service.licoesComSinal('curva-direita')).length).toBe(1);
        expect((await service.licoesComArtigo(12)).length).toBe(1);
        expect((await service.licoesComArtigo(99)).length).toBe(0);
        expect((await service.licaoParaTema('circulacao'))?.slug).toBe('distancia-de-seguranca');
    });

    it('converte o texto das fichas em parágrafos e listas', async () => {
        const blocos = service.formatar(ESTUDO.licoes[0].corpo);

        expect(blocos.length).toBe(3);
        expect(blocos[0]).toEqual({ tipo: 'paragrafo', texto: 'Primeiro parágrafo.' });
        expect(blocos[1]).toEqual({ tipo: 'lista', itens: ['Item um', 'Item dois'] });
        expect(blocos[2]).toEqual({ tipo: 'paragrafo', texto: 'Segundo parágrafo.' });
    });

    it('conta o progresso de leitura por secção', async () => {
        let progresso = await service.progresso();
        expect(progresso.licoes).toEqual({ lidos: 0, total: 1, percentagem: 0 });

        await service.marcarLicaoLida('distancia-de-seguranca');
        await service.marcarSinalVisto('curva-direita');
        await service.marcarArtigoLido(12);

        progresso = await service.progresso();

        expect(progresso.licoes.percentagem).toBe(100);
        expect(progresso.sinais.percentagem).toBe(100);
        expect(progresso.artigos).toEqual({ lidos: 1, total: 2, percentagem: 50 });
    });

    it('expõe as contagens de conteúdo bloqueado enviadas pelo servidor', async () => {
        const material = await service.carregar();

        expect(material.sinaisBloqueados).toBe(7);
        expect(material.licoesBloqueadas).toBe(3);
    });

    it('reconstrói o material quando o pacote é substituído (desbloqueio)', async () => {
        expect((await service.carregar()).sinaisBloqueados).toBe(7);

        // Depois de desbloquear, o ContentService devolve outro pacote — sem
        // plano gratuito e com todo o material.
        content.pacote = {
            versao: '2',
            temas: [],
            perguntas: [],
            provas: [],
            estudo: { ...ESTUDO, sinaisBloqueados: 0, licoesBloqueadas: 0 },
        };

        const material = await service.carregar();

        expect(material.sinaisBloqueados).toBe(0);
        expect(material.licoesBloqueadas).toBe(0);
    });
});
