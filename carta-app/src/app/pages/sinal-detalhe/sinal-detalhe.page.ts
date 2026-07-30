import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import {
    albumsOutline, arrowBackOutline, arrowForwardCircleOutline, bookOutline, checkmarkCircle, chevronBackOutline,
    chevronForwardOutline, closeCircleOutline, documentTextOutline, handLeftOutline, informationCircleOutline,
    lockClosedOutline, removeOutline, schoolOutline, stopCircleOutline, swapHorizontalOutline, warningOutline,
} from 'ionicons/icons';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { ArtigoCodigoEstrada, BlocoTexto, LicaoEstudo, SinalTransito, TaxonomiaItem } from '../../models/material-estudo.model';

/**
 * Detalhe de um sinal.
 *
 * Junta num só ecrã o que o aluno precisa para o fixar: a imagem, o
 * significado curto (que é a resposta certa no treino), o texto de estudo, o
 * artigo do Código que o sustenta e as fichas que o referem.
 */
@Component({
    standalone: true,
    selector: 'app-sinal-detalhe',
    imports: [RouterLink, IonContent, IonIcon, SkeletonComponent],
    templateUrl: './sinal-detalhe.page.html',
    styleUrls: ['./sinal-detalhe.page.scss'],
})
export class SinalDetalhePage implements OnInit {
    sinal?: SinalTransito;
    categoria?: TaxonomiaItem;
    artigo?: ArtigoCodigoEstrada;
    artigoBlocos: BlocoTexto[] = [];
    licoes: LicaoEstudo[] = [];
    descricaoBlocos: BlocoTexto[] = [];
    anterior?: SinalTransito;
    proximo?: SinalTransito;
    posicao = 0;
    totalNaCategoria = 0;
    artigoAberto = false;
    carregando = true;

    constructor(
        private readonly route: ActivatedRoute,
        private readonly material: MaterialEstudoService,
    ) {
        addIcons({
            albumsOutline, arrowBackOutline, arrowForwardCircleOutline, bookOutline, checkmarkCircle, chevronBackOutline,
            chevronForwardOutline, closeCircleOutline, documentTextOutline, handLeftOutline, informationCircleOutline,
            lockClosedOutline, removeOutline, schoolOutline, stopCircleOutline, swapHorizontalOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        // paramMap como observable: navegar entre sinais irmãos reaproveita o
        // componente e o ngOnInit não voltaria a correr.
        this.route.paramMap.subscribe((parametros) => {
            void this.mostrar(parametros.get('slug') || '');
        });
    }

    get temConteudoDeEstudo(): boolean {
        return this.descricaoBlocos.length > 0 || Boolean(this.artigo) || this.licoes.length > 0;
    }

    alternarArtigo(): void {
        this.artigoAberto = !this.artigoAberto;
    }

    private async mostrar(slug: string): Promise<void> {
        this.carregando = true;
        this.artigoAberto = false;

        const sinal = await this.material.sinal(slug);

        if (!sinal) {
            this.sinal = undefined;
            this.carregando = false;
            return;
        }

        const [categorias, irmaos, licoes] = await Promise.all([
            this.material.categoriasSinais(),
            this.material.sinais(sinal.categoria),
            this.material.licoesComSinal(sinal.slug),
        ]);

        this.sinal = sinal;
        this.categoria = categorias.find((item) => item.slug === sinal.categoria);
        this.descricaoBlocos = sinal.descricao ? this.material.formatar(sinal.descricao) : [];
        this.licoes = licoes;

        const indice = irmaos.findIndex((item) => item.slug === sinal.slug);
        this.posicao = indice + 1;
        this.totalNaCategoria = irmaos.length;
        this.anterior = indice > 0 ? irmaos[indice - 1] : undefined;
        this.proximo = indice >= 0 && indice < irmaos.length - 1 ? irmaos[indice + 1] : undefined;

        this.artigo = sinal.artigoRef ? await this.material.artigo(sinal.artigoRef) : undefined;
        this.artigoBlocos = this.artigo ? this.material.formatar(this.artigo.texto) : [];

        // Abrir o sinal conta como estudado: é o que o ecrã de sinais promete.
        await this.material.marcarSinalVisto(sinal.slug);

        this.carregando = false;
    }
}
