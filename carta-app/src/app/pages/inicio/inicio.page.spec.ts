import { InicioPage } from './inicio.page';

function criarPagina(opcoes: { token?: string | null; falha?: Error } = {}): {
    pagina: InicioPage;
    perfil: { obter: jasmine.Spy; obterLocal: jasmine.Spy };
} {
    const content = {
        listarTemas: jasmine.createSpy().and.callFake(async () => {
            if (opcoes.falha) throw opcoes.falha;
            return ['velocidade'];
        }),
    };
    const storage = {
        obterCategoria: jasmine.createSpy().and.resolveTo('ligeiro'),
        listarExames: jasmine.createSpy().and.resolveTo([]),
        listarRevisoesPendentes: jasmine.createSpy().and.resolveTo([]),
        obterEstadoAcesso: jasmine.createSpy().and.resolveTo({ plano: 'gratis' }),
    };
    const progresso = {
        estatisticasPorTema: jasmine.createSpy().and.resolveTo([]),
        recomendarEstudo: jasmine.createSpy().and.returnValue(null),
    };
    const perfil = {
        obter: jasmine.createSpy().and.resolveTo({ nome: 'Conta Remota', email: '', telefone: '' }),
        obterLocal: jasmine.createSpy().and.resolveTo({ nome: 'Estudante', email: '', telefone: '' }),
    };
    const temas = { carregar: jasmine.createSpy().and.resolveTo(undefined), nome: (slug: string) => slug };
    const regras = { carregar: jasmine.createSpy().and.resolveTo(undefined), aprovado: () => false };
    const acesso = {
        revalidarSeNecessario: jasmine.createSpy().and.resolveTo(undefined),
        conteudoBloqueado: jasmine.createSpy().and.resolveTo({ total: 0, porTema: {} }),
    };
    const treino = { totalParaReforcar: jasmine.createSpy().and.resolveTo(0) };
    const auth = { token: jasmine.createSpy().and.resolveTo(opcoes.token ?? null) };

    return {
        pagina: new InicioPage(content as any, storage as any, progresso as any, perfil as any, temas as any, regras as any, acesso as any, treino as any, auth as any),
        perfil,
    };
}

describe('InicioPage', () => {
    it('usa o perfil local quando o visitante não tem sessão', async () => {
        const { pagina, perfil } = criarPagina();

        await pagina.ngOnInit();

        expect(perfil.obterLocal).toHaveBeenCalled();
        expect(perfil.obter).not.toHaveBeenCalled();
        expect(pagina.carregando).toBeFalse();
        expect(pagina.erroCarregamento).toBe('');
    });

    it('termina o loading e expõe a falha real da API', async () => {
        const { pagina } = criarPagina({ falha: new Error('API indisponível') });

        await pagina.ngOnInit();

        expect(pagina.carregando).toBeFalse();
        expect(pagina.erroCarregamento).toBe('API indisponível');
    });
});
