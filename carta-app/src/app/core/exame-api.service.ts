import { inject, Injectable } from '@angular/core';
import { ExameApiDetalhe, ExameApiResumo } from '../models/exame-api.model';
import { ApiService } from './api.service';
import { AuthService } from './auth.service';

@Injectable({ providedIn: 'root' })
export class ExameApiService {
    private readonly api = inject(ApiService);
    private readonly auth = inject(AuthService);

    async listar(): Promise<ExameApiResumo[]> {
        const response = await this.api.get<{ data: ExameApiResumo[] }>('mobile/exams', !!(await this.auth.token()));
        return response.data;
    }

    async obter(id: number): Promise<ExameApiDetalhe> {
        const exam = await this.api.get<ExameApiDetalhe>(`mobile/exams/${id}`, !!(await this.auth.token()));
        exam.perguntas = exam.perguntas.map((question) => ({ ...question, imagem: this.api.absoluteAssetUrl(question.imagem) }));
        return exam;
    }
}
