import { inject, Injectable } from '@angular/core';
import { StorageService } from './storage.service';

export interface ResumoGamificacao { pontos: number; diasAtivos: number; conquistas: string[]; }

/** Gamificação local: não bloqueia o estudo nem inventa dados no servidor. */
@Injectable({ providedIn: 'root' })
export class GamificacaoService {
    private readonly storage = inject(StorageService);

    async resumo(): Promise<ResumoGamificacao> {
        const [respostas, exames] = await Promise.all([this.storage.listarRespostas(), this.storage.listarExames()]);
        const dias = new Set(respostas.map((item) => new Date(item.data).toISOString().slice(0, 10)));
        const conquistas: string[] = [];
        if (respostas.length >= 1) conquistas.push('Primeira resposta');
        if (respostas.length >= 25) conquistas.push('25 perguntas praticadas');
        if (exames.length >= 1) conquistas.push('Primeiro simulado concluído');
        return { pontos: respostas.filter((item) => item.acertou).length * 10 + respostas.filter((item) => !item.acertou).length * 3 + exames.length * 25, diasAtivos: dias.size, conquistas };
    }
}
