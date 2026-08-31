import { inject, Injectable } from '@angular/core';
import { ApiService } from './api.service';

export interface SessaoEscolarResumo {
    codigo: string;
    estado: string;
    aberta: boolean;
    prova: { nome: string; perguntas: number; minutos: number };
    turma: { nome: string };
}

export interface QuestaoEscolar {
    id: string;
    tema: string;
    enunciado: string;
    imagem: string | null;
    opcoes: string[];
}

export interface ProvaEscolarQuestoes {
    codigo: string;
    aluno: { nome: string };
    prova: { nome: string; minutos: number; notaPassagem: number; percentagemPassagem: number };
    perguntas: QuestaoEscolar[];
}

export interface ResultadoProvaEscolar {
    pontuacao: number;
    total: number;
    percentagem: number;
    valores: number;
    notaPassagem: number;
    aprovado: boolean;
    contaParaAptidao: boolean;
    temasFracos: string[];
    detalhePorTema: Record<string, { total: number; acertos: number; taxa: number }>;
}

@Injectable({ providedIn: 'root' })
export class ProvaEscolarService {
    private readonly api = inject(ApiService);

    consultar(codigo: string): Promise<SessaoEscolarResumo> {
        return this.api.get<SessaoEscolarResumo>(`sessions/${this.codigo(codigo)}`);
    }

    async entrar(codigo: string, nome: string): Promise<{ bilhete: string; aluno: { nome: string } }> {
        return this.api.post(`sessions/${this.codigo(codigo)}/entrar`, { nome });
    }

    async perguntas(codigo: string, bilhete: string): Promise<ProvaEscolarQuestoes> {
        const prova = await this.api.get<ProvaEscolarQuestoes>(
            `sessions/${this.codigo(codigo)}/perguntas`, false, { 'X-Exam-Ticket': bilhete },
        );
        prova.perguntas = prova.perguntas.map((pergunta) => ({ ...pergunta, imagem: this.api.absoluteAssetUrl(pergunta.imagem) }));
        return prova;
    }

    submeter(codigo: string, bilhete: string, respostas: Record<string, number>, tempoSegundos: number): Promise<ResultadoProvaEscolar> {
        return this.api.post<ResultadoProvaEscolar>(
            `sessions/${this.codigo(codigo)}/submeter`,
            { answers: respostas, tempoSegundos },
            false,
            { 'X-Exam-Ticket': bilhete },
        );
    }

    private codigo(valor: string): string {
        return encodeURIComponent(valor.trim().toUpperCase());
    }
}
