import { inject, Injectable } from '@angular/core';
import {
    ArtigoCodigoEstrada,
    BlocoTexto,
    CapituloCodigo,
    LicaoEstudo,
    MaterialEstudo,
    ProgressoEstudo,
    SinalTransito,
    TaxonomiaItem,
    TermoGlossario,
} from '../models/material-estudo.model';
import { Pacote } from '../models/pacote.model';
import { ContentService } from './content.service';
import { normalizarTexto } from './texto';
import { StorageService } from './storage.service';

const MATERIAL_VAZIO: MaterialEstudo = {
    taxonomia: { categoriasSinais: [], gruposLicoes: [] },
    sinais: [],
    licoes: [],
    capitulos: [],
    artigos: [],
    glossario: [],
};

/**
 * Material de estudo: sinalização, fichas, artigos do Código e glossário.
 *
 * Antes este serviço pedia `/articles` em runtime, página a página (30 em 30,
 * em série), montava uma única categoria falsa chamada "Código da Estrada" com
 * todos os artigos, e guardava o resultado em Preferences — que no Android é
 * SharedPreferences e não se destina a blobs grandes. Agora o material vem
 * dentro do pacote offline, já organizado pelo painel, e vive no IndexedDB
 * junto com o resto do conteúdo.
 */
@Injectable({ providedIn: 'root' })
export class MaterialEstudoService {
    private readonly content = inject(ContentService);
    private readonly storage = inject(StorageService);

    private material?: MaterialEstudo;
    /**
     * Pacote de que a cache foi derivada.
     *
     * Guardado por identidade e não por versão: quando o plano muda, o
     * ContentService volta a sincronizar e devolve outro objeto. Sem esta
     * comparação, quem acabasse de desbloquear continuava a ver o material
     * reduzido do plano gratuito até reiniciar o app.
     */
    private origem?: Pacote;

    async carregar(): Promise<MaterialEstudo> {
        const pacote = await this.content.carregarPacote();

        if (!this.material || this.origem !== pacote) {
            this.material = { ...MATERIAL_VAZIO, ...(pacote.estudo ?? {}) };
            this.origem = pacote;
        }

        return this.material;
    }

    /** Descarta a cache em memória. */
    limparCache(): void {
        this.material = undefined;
        this.origem = undefined;
    }

    // ------------------------------------------------------------- sinalização

    async categoriasSinais(): Promise<Array<TaxonomiaItem & { total: number }>> {
        const material = await this.carregar();

        return material.taxonomia.categoriasSinais
            .map((categoria) => ({
                ...categoria,
                total: material.sinais.filter((sinal) => sinal.categoria === categoria.slug).length,
            }))
            // Categorias sem sinais não interessam ao aluno.
            .filter((categoria) => categoria.total > 0);
    }

    async sinais(categoria?: string): Promise<SinalTransito[]> {
        const material = await this.carregar();

        return categoria ? material.sinais.filter((sinal) => sinal.categoria === categoria) : material.sinais;
    }

    async sinal(slug: string): Promise<SinalTransito | undefined> {
        return (await this.carregar()).sinais.find((sinal) => sinal.slug === slug);
    }

    async procurarSinais(termo: string): Promise<SinalTransito[]> {
        const alvo = this.normalizar(termo);
        if (!alvo) {
            return this.sinais();
        }

        return (await this.carregar()).sinais.filter((sinal) =>
            this.normalizar(`${sinal.nome} ${sinal.significado} ${sinal.descricao ?? ''}`).includes(alvo),
        );
    }

    // ------------------------------------------------------------------ fichas

    async gruposLicoes(): Promise<Array<TaxonomiaItem & { total: number }>> {
        const material = await this.carregar();

        return material.taxonomia.gruposLicoes
            .map((grupo) => ({
                ...grupo,
                total: material.licoes.filter((licao) => licao.grupo === grupo.slug).length,
            }))
            .filter((grupo) => grupo.total > 0);
    }

    async licoes(grupo?: string): Promise<LicaoEstudo[]> {
        const material = await this.carregar();

        return grupo ? material.licoes.filter((licao) => licao.grupo === grupo) : material.licoes;
    }

    async licao(slug: string): Promise<LicaoEstudo | undefined> {
        return (await this.carregar()).licoes.find((licao) => licao.slug === slug);
    }

    /** Ficha sugerida para um tema em que o aluno está fraco. */
    async licaoParaTema(tema: string): Promise<LicaoEstudo | undefined> {
        return (await this.carregar()).licoes.find((licao) => licao.tema === tema);
    }

    /** Fichas que referem um sinal — mostra-se no detalhe do sinal. */
    async licoesComSinal(slug: string): Promise<LicaoEstudo[]> {
        return (await this.carregar()).licoes.filter((licao) => licao.sinais.includes(slug));
    }

    /** Fichas que citam um artigo do Código. */
    async licoesComArtigo(numero: number): Promise<LicaoEstudo[]> {
        return (await this.carregar()).licoes.filter((licao) => licao.artigos.includes(numero));
    }

    // ----------------------------------------------------------------- artigos

    async capitulos(): Promise<Array<CapituloCodigo & { titulo: string }>> {
        return (await this.carregar()).capitulos;
    }

    async artigos(capitulo?: number | null): Promise<ArtigoCodigoEstrada[]> {
        const material = await this.carregar();

        if (capitulo === undefined) {
            return material.artigos;
        }

        return material.artigos.filter((artigo) => (artigo.capitulo ?? null) === (capitulo ?? null));
    }

    async artigo(numero: number): Promise<ArtigoCodigoEstrada | undefined> {
        return (await this.carregar()).artigos.find((artigo) => artigo.numero === numero);
    }

    async procurarArtigos(termo: string): Promise<ArtigoCodigoEstrada[]> {
        const alvo = this.normalizar(termo);
        if (!alvo) {
            return [];
        }

        return (await this.carregar()).artigos.filter((artigo) =>
            this.normalizar(`artigo ${artigo.numero} ${artigo.titulo} ${artigo.texto}`).includes(alvo),
        );
    }

    // --------------------------------------------------------------- glossário

    async glossario(termo = ''): Promise<TermoGlossario[]> {
        const material = await this.carregar();
        const alvo = this.normalizar(termo);

        if (!alvo) {
            return material.glossario;
        }

        return material.glossario.filter((item) => this.normalizar(`${item.termo} ${item.definicao}`).includes(alvo));
    }

    // --------------------------------------------------------------- progresso

    /**
     * Progresso de leitura por secção.
     * A chave de leitura é `tipo:id`, gravada por marcarConteudoLido.
     */
    async progresso(): Promise<Record<'licoes' | 'artigos' | 'sinais', ProgressoEstudo>> {
        const [material, lidos] = await Promise.all([this.carregar(), this.storage.listarConteudosLidos()]);
        const conjunto = new Set(lidos);

        const contar = (prefixo: string, ids: Array<string | number>): ProgressoEstudo => {
            const total = ids.length;
            const lidosNaSecao = ids.filter((id) => conjunto.has(`${prefixo}:${id}`)).length;

            return {
                lidos: lidosNaSecao,
                total,
                percentagem: total ? Math.round((lidosNaSecao / total) * 100) : 0,
            };
        };

        return {
            licoes: contar('licao', material.licoes.map((licao) => licao.slug)),
            artigos: contar('artigo', material.artigos.map((artigo) => artigo.numero)),
            sinais: contar('sinal', material.sinais.map((sinal) => sinal.slug)),
        };
    }

    marcarLicaoLida(slug: string): Promise<void> {
        return this.storage.marcarConteudoLido(`licao:${slug}`);
    }

    marcarArtigoLido(numero: number): Promise<void> {
        return this.storage.marcarConteudoLido(`artigo:${numero}`);
    }

    marcarSinalVisto(slug: string): Promise<void> {
        return this.storage.marcarConteudoLido(`sinal:${slug}`);
    }

    async conteudosLidos(): Promise<Set<string>> {
        return new Set(await this.storage.listarConteudosLidos());
    }

    // ----------------------------------------------------------------- formato

    /**
     * Converte o texto simples das fichas em blocos renderizáveis.
     * Evita `innerHTML` — o conteúdo é escrito no painel e nunca é injetado
     * como HTML no app.
     */
    formatar(texto: string): BlocoTexto[] {
        const blocos: BlocoTexto[] = [];
        let listaAtual: string[] = [];

        const fecharLista = (): void => {
            if (listaAtual.length) {
                blocos.push({ tipo: 'lista', itens: listaAtual });
                listaAtual = [];
            }
        };

        for (const linha of (texto || '').split('\n')) {
            const limpa = linha.trim();

            if (!limpa) {
                fecharLista();
                continue;
            }

            if (limpa.startsWith('- ') || limpa.startsWith('• ')) {
                listaAtual.push(limpa.slice(2).trim());
                continue;
            }

            fecharLista();
            blocos.push({ tipo: 'paragrafo', texto: limpa });
        }

        fecharLista();

        return blocos;
    }

    /** Comparação sem acentos nem maiúsculas, para a pesquisa em português. */
    private normalizar(texto: string): string {
        return normalizarTexto(texto);
    }
}
