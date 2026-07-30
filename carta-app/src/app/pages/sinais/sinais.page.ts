import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon, IonSearchbar } from '@ionic/angular/standalone';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import { albumsOutline, arrowBackOutline, arrowForwardCircleOutline, bookOutline, checkmarkCircle, lockClosed, closeCircleOutline, handLeftOutline, informationCircleOutline, lockClosedOutline, removeOutline, schoolOutline, stopCircleOutline, swapHorizontalOutline, warningOutline } from 'ionicons/icons';
import { AcessoService } from '../../core/acesso.service';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { normalizarTexto } from '../../core/texto';
import { SinalTransito, TaxonomiaItem } from '../../models/material-estudo.model';

/**
 * Biblioteca de sinalização.
 *
 * A API já tinha os sinais desde o início; o app nunca teve ecrã nenhum para
 * os estudar, apesar de o reconhecimento de sinais ser grande parte do exame.
 */
@Component({
    standalone: true,
    selector: 'app-sinais',
    imports: [FormsModule, RouterLink, IonContent, IonIcon, IonSearchbar, SkeletonComponent],
    templateUrl: './sinais.page.html',
    styleUrls: ['./sinais.page.scss'],
})
export class SinaisPage implements OnInit {
    categorias: Array<TaxonomiaItem & { total: number }> = [];
    sinais: SinalTransito[] = [];
    categoriaAtiva = '';
    pesquisa = '';
    vistos = new Set<string>();
    carregando = true;
    bloqueados = 0;
    plano: 'gratis' | 'pago' = 'gratis';

    constructor(
        private readonly material: MaterialEstudoService,
        private readonly acesso: AcessoService,
    ) {
        addIcons({ albumsOutline, arrowBackOutline, arrowForwardCircleOutline, bookOutline, checkmarkCircle, lockClosed, closeCircleOutline, handLeftOutline, informationCircleOutline, lockClosedOutline, removeOutline, schoolOutline, stopCircleOutline, swapHorizontalOutline, warningOutline });
    }

    async ngOnInit(): Promise<void> {
        const [categorias, sinais, vistos, material] = await Promise.all([
            this.material.categoriasSinais(),
            this.material.sinais(),
            this.material.conteudosLidos(),
            this.material.carregar(),
        ]);

        this.categorias = categorias;
        this.sinais = sinais;
        this.vistos = vistos;
        this.bloqueados = material.sinaisBloqueados ?? 0;
        this.plano = (await this.acesso.estaPago()) ? 'pago' : 'gratis';
        this.carregando = false;
    }

    get sinaisVisiveis(): SinalTransito[] {
        const termo = this.normalizar(this.pesquisa);

        return this.sinais.filter((sinal) => {
            const categoriaOk = !this.categoriaAtiva || sinal.categoria === this.categoriaAtiva;
            const termoOk = !termo || this.normalizar(`${sinal.nome} ${sinal.significado ?? ''}`).includes(termo);
            return categoriaOk && termoOk;
        });
    }

    get totalVistos(): number {
        return this.sinais.filter((sinal) => this.vistos.has(`sinal:${sinal.slug}`)).length;
    }

    get percentagemVista(): number {
        return this.sinais.length ? Math.round((this.totalVistos / this.sinais.length) * 100) : 0;
    }

    /** Descrição da categoria filtrada — antes varria a lista no template. */
    get descricaoCategoriaAtiva(): string {
        if (!this.categoriaAtiva) {
            return '';
        }
        return this.categorias.find((categoria) => categoria.slug === this.categoriaAtiva)?.descricao || '';
    }

    nomeCategoria(slug: string): string {
        return this.categorias.find((categoria) => categoria.slug === slug)?.nome || slug.replace(/_/g, ' ');
    }

    iconeCategoria(slug: string): string {
        return this.categorias.find((categoria) => categoria.slug === slug)?.icone || 'albums-outline';
    }

    filtrar(categoria: string): void {
        this.categoriaAtiva = this.categoriaAtiva === categoria ? '' : categoria;
    }

    visto(sinal: SinalTransito): boolean {
        return this.vistos.has(`sinal:${sinal.slug}`);
    }

    private normalizar(texto: string): string {
        return normalizarTexto(texto);
    }
}
