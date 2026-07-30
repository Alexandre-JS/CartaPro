import { Pergunta } from './pergunta.model';
import type { ExameApiDetalhe } from './exame-api.model';
import type { MaterialEstudo } from './material-estudo.model';

/** Nome legível de um tema, vindo do painel. */
export interface TemaDetalhe {
    slug: string;
    nome: string;
    descricao?: string | null;
}

export interface RegraCategoria {
    totalPerguntas: number;
    percentagemPassagem: number;
    notaPassagem: number;
    valoresPassagem: number;
    minutos: number;
}

/** Regras de classificação — fonte única, definida na API. */
export interface RegrasPacote {
    valoresMaximos: number;
    aptidao: { valoresMinimos: number; notasNecessarias: number };
    porCategoria: Record<string, RegraCategoria>;
    omissao: RegraCategoria;
}

export interface Pacote {
    versao: string;
    temas: string[];
    /** Nomes e descrições dos temas: elimina os mapas hardcoded no app. */
    temasDetalhe?: TemaDetalhe[];
    regras?: RegrasPacote;
    perguntas: Pergunta[];
    provas: ExameApiDetalhe[];
    /** Sinalização, fichas de estudo, artigos e glossário — tudo offline. */
    estudo?: MaterialEstudo;
    /** Plano com que este pacote foi entregue pelo servidor. */
    plano?: 'gratis' | 'pago';
    /** Quantas perguntas bloqueadas existem por tema (alimenta os cadeados). */
    bloqueadasPorTema?: Record<string, number>;
    totalBloqueadas?: number;
}
