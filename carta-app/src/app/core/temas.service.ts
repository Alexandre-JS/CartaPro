import { inject, Injectable } from '@angular/core';
import { TemaDetalhe } from '../models/pacote.model';
import { ContentService } from './content.service';

/**
 * Nomes legíveis dos temas.
 *
 * Antes cada página repetia um mapa hardcoded de 3 temas
 * (`{ velocidade: 'Velocidade', sinais_perigo: ... }`) e qualquer tema novo
 * criado no painel aparecia ao aluno como `primeiros_socorros` em texto cru —
 * o que impedia o conteúdo de crescer sem alterar o código do app.
 * Agora o nome viaja no pacote (`temasDetalhe`).
 */
@Injectable({ providedIn: 'root' })
export class TemasService {
    private readonly content = inject(ContentService);
    private mapa = new Map<string, TemaDetalhe>();

    async carregar(): Promise<void> {
        const detalhes = await this.content.listarTemasDetalhe();
        this.mapa = new Map(detalhes.map((tema) => [tema.slug, tema]));
    }

    /** Nome do tema; recorre ao slug legível se o pacote for antigo. */
    nome(slug: string): string {
        return this.mapa.get(slug)?.nome || this.humanizar(slug);
    }

    descricao(slug: string): string | null {
        return this.mapa.get(slug)?.descricao ?? null;
    }

    private humanizar(slug: string): string {
        const texto = (slug || '').replace(/_/g, ' ').trim();
        return texto ? texto.charAt(0).toUpperCase() + texto.slice(1) : '';
    }
}
