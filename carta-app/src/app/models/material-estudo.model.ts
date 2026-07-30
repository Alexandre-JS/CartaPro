/** Rótulo de uma categoria de sinais ou grupo de fichas, vindo da API. */
export interface TaxonomiaItem {
    slug: string;
    nome: string;
    descricao?: string | null;
    icone?: string | null;
    ordem: number;
}

export interface SinalTransito {
    slug: string;
    nome: string;
    categoria: string;
    tema?: string | null;
    /** Frase curta — é a resposta certa no treino. */
    /** Ausente nos bloqueados: não sai do servidor. */
    significado?: string;
    /** Texto de estudo mais longo. */
    descricao?: string | null;
    artigoRef?: number | null;
    imagem: string | null;
    bloqueado: boolean;
}

export type GrupoLicao = 'codigo' | 'sinalizacao' | 'conducao' | 'primeiros_socorros' | 'mecanica' | string;

export interface LicaoEstudo {
    slug: string;
    titulo: string;
    resumo?: string | null;
    /** Ausente nos bloqueados: não sai do servidor. */
    corpo?: string;
    grupo: GrupoLicao;
    tema?: string | null;
    categoriasCarta: string[];
    /** Slugs dos sinais referenciados pela ficha. */
    sinais: string[];
    /** Números dos artigos referenciados. */
    artigos: number[];
    minutosLeitura: number;
    bloqueado: boolean;
}

export interface ArtigoCodigoEstrada {
    numero: number;
    /** Verdadeiro nos que só chegam como montra, sem texto. */
    bloqueado?: boolean;
    capitulo?: number | null;
    capituloTitulo?: string | null;
    titulo: string;
    /** Ausente nos bloqueados: não sai do servidor. */
    texto?: string;
}

export interface CapituloCodigo {
    numero: number | null;
    titulo: string;
    artigos: number[];
}

export interface TermoGlossario {
    slug: string;
    termo: string;
    /** Verdadeiro nos que só chegam como montra, sem definição. */
    bloqueado?: boolean;
    /** Ausente nos bloqueados: não sai do servidor. */
    definicao?: string;
    artigoRef?: number | null;
}

/** Bloco `estudo` do pacote offline. */
export interface MaterialEstudo {
    taxonomia: {
        categoriasSinais: TaxonomiaItem[];
        gruposLicoes: TaxonomiaItem[];
    };
    sinais: SinalTransito[];
    licoes: LicaoEstudo[];
    capitulos: CapituloCodigo[];
    artigos: ArtigoCodigoEstrada[];
    glossario: TermoGlossario[];
    /** Contagens do que fica por trás do cadeado no plano gratuito. */
    licoesBloqueadas?: number;
    sinaisBloqueados?: number;
}

/** Progresso de leitura por secção, para o hub de estudos. */
export interface ProgressoEstudo {
    lidos: number;
    total: number;
    percentagem: number;
}

/**
 * Um parágrafo já preparado para renderizar.
 * O corpo das fichas é texto simples: linhas em branco separam parágrafos e
 * linhas começadas por "- " formam listas.
 */
export interface BlocoTexto {
    tipo: 'paragrafo' | 'lista';
    texto?: string;
    itens?: string[];
}
