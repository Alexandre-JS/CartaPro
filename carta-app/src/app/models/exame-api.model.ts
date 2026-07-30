import { Pergunta } from './pergunta.model';

export interface ExameApiResumo {
    id: number;
    nome: string;
    categoriasCarta: string[];
    tipo: string;
    perguntas: number;
    notaPassagem: number;
    minutos: number;
    /** Prova fechada para este plano — o servidor não a entrega. */
    bloqueado?: boolean;
}

export interface ExameApiDetalhe {
    id: number;
    nome: string;
    categoriasCarta: string[];
    tipo: string;
    notaPassagem: number;
    minutos: number;
    perguntas: Pergunta[];
}
