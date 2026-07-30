import { Component, Input } from '@angular/core';

/** Forma do conteúdo que está a ser esperado. */
export type VarianteSkeleton = 'cartao' | 'lista' | 'grelha' | 'texto';

/**
 * Espaço reservado para conteúdo em carregamento.
 *
 * Substitui duas coisas piores: nas páginas que tinham uma linha "A carregar…"
 * o conteúdo entrava de repente e empurrava tudo para baixo; nas que não
 * tinham indicador nenhum — Início, Exames, Desempenho, Perfil, Resultado e
 * Estudo — o ecrã afirmava durante o carregamento coisas que ainda não sabia:
 * "Estás em dia", "Nenhuma prova disponível", "0%", "Reprovado".
 *
 * O desenho imita a forma do conteúdo real para que nada salte quando chega.
 */
@Component({
    standalone: true,
    selector: 'app-skeleton',
    templateUrl: './skeleton.component.html',
    styleUrls: ['./skeleton.component.scss'],
})
export class SkeletonComponent {
    @Input() variante: VarianteSkeleton = 'lista';

    /** Quantos blocos desenhar. Aproxima-se do que a página costuma mostrar. */
    @Input() quantidade = 3;

    /** Texto para leitores de ecrã: diz o que está a chegar. */
    @Input() etiqueta = 'A carregar conteúdo';

    get blocos(): number[] {
        return Array.from({ length: Math.max(1, this.quantidade) }, (_, indice) => indice);
    }
}
