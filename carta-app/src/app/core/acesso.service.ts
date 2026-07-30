import { inject, Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { ContentService } from './content.service';
import { DesbloqueioService } from './desbloqueio.service';

/**
 * Ponto único que responde "este aluno tem plano completo?".
 *
 * Antes o campo `plano` só servia para pintar uma etiqueta "Premium" no perfil:
 * nenhum ecrã o consultava para dar ou negar acesso, e o filtro
 * `incluirBloqueadas` do ContentService nunca era chamado. Resultado: o
 * utilizador gratuito e o pago tinham exatamente o mesmo produto.
 */
@Injectable({ providedIn: 'root' })
export class AcessoService {
    private readonly desbloqueio = inject(DesbloqueioService);
    private readonly content = inject(ContentService);

    async estaPago(): Promise<boolean> {
        return (await this.desbloqueio.obterEstado()).plano === 'pago';
    }

    /** Conteúdo por trás do cadeado, total e por tema. */
    async conteudoBloqueado(): Promise<{ total: number; porTema: Record<string, number> }> {
        const [total, porTema] = await Promise.all([
            this.content.totalBloqueadas(),
            this.content.bloqueadasPorTema(),
        ]);

        return { total, porTema };
    }

    async temaBloqueado(tema: string): Promise<number> {
        return (await this.content.bloqueadasPorTema())[tema] ?? 0;
    }

    /**
     * Revalida o plano no arranque, no máximo uma vez por dia, e recarrega o
     * pacote se o plano tiver mudado (ex.: acabou de pagar, ou expirou).
     */
    async revalidarSeNecessario(): Promise<void> {
        const anterior = await this.desbloqueio.obterEstado();
        const ultima = Number((await Preferences.get({ key: 'ultimaRevalidacaoPlano' })).value || 0);

        if (Date.now() - ultima < 24 * 60 * 60 * 1000) {
            return;
        }

        const atual = await this.desbloqueio.revalidar();
        await Preferences.set({ key: 'ultimaRevalidacaoPlano', value: String(Date.now()) });

        if (atual.plano !== anterior.plano) {
            await this.content.atualizarPacote();
        }
    }
}
