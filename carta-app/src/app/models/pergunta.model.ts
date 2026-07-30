export type TipoPergunta = 'teorico' | 'pratico';
export type CategoriaCarta = 'ligeiro' | 'pesado' | 'profissional_publico';

export interface Pergunta {
    id: string;
    tipo: TipoPergunta;
    tema: string;
    categoriaCarta: CategoriaCarta[];
    enunciado: string;
    imagem: string | null;
    opcoes: string[];
    correta: number;
    explicacao: string;
    artigoRef: number | null;
    bloqueado: boolean;
}