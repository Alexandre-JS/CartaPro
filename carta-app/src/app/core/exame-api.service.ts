import { inject, Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { ExameApiDetalhe, ExameApiResumo } from '../models/exame-api.model';
import { ApiService } from './api.service';
import { StorageService } from './storage.service';

@Injectable({ providedIn: 'root' })
export class ExameApiService {
    private readonly api = inject(ApiService);
    private readonly storage = inject(StorageService);

    async listar(): Promise<ExameApiResumo[]> {
        try {
            const response = await this.api.get<{ data: ExameApiResumo[] }>('mobile/exams', true);
            await Preferences.set({ key: 'mobileExamsCache', value: JSON.stringify(response.data) });
            return response.data;
        } catch {
            const { value } = await Preferences.get({ key: 'mobileExamsCache' });
            if (value) return JSON.parse(value);
            const packageExams = (await this.storage.obterPacote())?.provas || [];
            if (!packageExams.length) throw new Error('É necessária ligação para descarregar as provas.');
            return packageExams.map((exam) => ({ id: exam.id, nome: exam.nome, categoriasCarta: exam.categoriasCarta, tipo: exam.tipo, perguntas: exam.perguntas.length, notaPassagem: exam.notaPassagem, minutos: exam.minutos }));
        }
    }

    async obter(id: number): Promise<ExameApiDetalhe> {
        const key = `mobileExamCache:${id}`;
        try {
            const exam = await this.api.get<ExameApiDetalhe>(`mobile/exams/${id}`, true);
            exam.perguntas = exam.perguntas.map((question) => ({ ...question, imagem: this.api.absoluteAssetUrl(question.imagem) }));
            await Preferences.set({ key, value: JSON.stringify(exam) });
            return exam;
        } catch {
            const { value } = await Preferences.get({ key });
            if (value) return JSON.parse(value);
            const packageExam = (await this.storage.obterPacote())?.provas?.find((exam) => exam.id === id);
            if (!packageExam) throw new Error('Esta prova ainda não foi descarregada para uso offline.');
            return packageExam;
        }
    }
}
