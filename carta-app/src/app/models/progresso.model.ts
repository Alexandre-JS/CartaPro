/** Estado de diagnóstico de um tema. Separa "nunca praticado" de "fraco". */
export type EstadoTema = 'nao_praticado' | 'em_avaliacao' | 'fraco' | 'solido' | 'dominado';

export interface ProgressoTema {
    tema: string;
    respondidas: number;
    acertos: number;
    /** Taxa de toda a história do aluno no tema. */
    taxaAcerto: number;
    /** Taxa nas últimas JANELA_MAESTRIA respostas — reflete o nível atual. */
    taxaRecente: number;
    estado: EstadoTema;
    /** true só com amostra suficiente (MINIMO_AMOSTRA_MAESTRIA). */
    graduado: boolean;
    /** Revisões pendentes neste tema. */
    revisoesPendentes: number;
    /** Tempo médio por resposta, em ms (0 quando não há telemetria). */
    tempoMedioMs: number;
}

export type AcaoEstudo = 'comecar' | 'reforcar' | 'revisar' | 'continuar';

export interface RecomendacaoEstudo {
    tema: string;
    acao: AcaoEstudo;
    motivo: string;
    totalPerguntas: number;
    minutosEstimados: number;
}

export interface RevisaoAgendada {
    perguntaId: string;
    tema: string;
    agendadaPara: number;
    intervaloDias: number;
    /** Fator de facilidade SM-2. */
    facilidade: number;
    repeticoes: number;
    lapsos: number;
    ultimaRevisaoEm?: number;
    pendente?: 0 | 1;
}

export type OrigemResposta = 'simulado' | 'exame' | 'estudo' | 'revisao';

export interface RespostaRegisto {
    id?: number;
    clientId?: string;
    perguntaId: string;
    tema: string;
    acertou: boolean;
    data: number;
    /** Opção escolhida — permite medir qual distrator engana mais. */
    escolhida?: number | null;
    /** Tempo até responder, em ms. */
    duracaoMs?: number | null;
    origem?: OrigemResposta;
    /** Ainda não confirmada pelo servidor (sincronização incremental). */
    pendente?: 0 | 1;
}

export interface EstadoAcesso {
    plano: 'gratis' | 'pago';
    telefone?: string;
    expiraEm?: string | null;
    verificadoEm?: number;
    diasRestantes?: number | null;
    pagamentoPorReclamar?: boolean;
}

export interface ResultadoResumo {
    total: number;
    acertos: number;
}

export interface HistoricoExame extends ResultadoResumo {
    id?: number;
    clientId?: string;
    numero: number;
    tempoSegundos: number;
    data: number;
    pendente?: 0 | 1;
}

/** Prova em curso, para retomar depois de fechar o app. */
export interface SimuladoEmCurso {
    chave: string;
    perguntaIds: string[];
    escolhas: Array<number | null>;
    respondidas: boolean[];
    acertos: number;
    numeroExame: number;
    notaPassagem: number;
    duracaoTotalSegundos: number;
    /** Timestamp de arranque: o tempo é medido pelo relógio, não por ticks. */
    iniciadoEm: number;
    guardadoEm: number;
}

/** Detalhe por pergunta guardado com o resultado. */
export interface DetalheResposta {
    perguntaId: string;
    escolhida: number | null;
}

export interface ResultadoGuardado {
    resumo: ResultadoResumo;
    tempoSegundos: number;
    notaPassagem: number;
    detalhes: DetalheResposta[];
    data: number;
}
