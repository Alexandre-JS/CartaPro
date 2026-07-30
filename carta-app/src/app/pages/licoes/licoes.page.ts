import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon, IonSearchbar } from '@ionic/angular/standalone';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import { arrowBackOutline, bookOutline, carOutline, checkmarkCircle, lockClosed, chevronForwardOutline, constructOutline, documentTextOutline, libraryOutline, lockClosedOutline, medkitOutline, timeOutline, warningOutline } from 'ionicons/icons';
import { AcessoService } from '../../core/acesso.service';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { normalizarTexto } from '../../core/texto';
import { LicaoEstudo, TaxonomiaItem } from '../../models/material-estudo.model';

interface GrupoComLicoes extends TaxonomiaItem {
    licoes: LicaoEstudo[];
    lidas: number;
}

/**
 * Fichas de estudo por área.
 *
 * O material antes resumia-se a artigos do Código, que são texto legal e não
 * material de estudo. As fichas explicam o mesmo em linguagem de aluno e ligam
 * cada tema aos sinais e artigos correspondentes.
 */
@Component({
    standalone: true,
    selector: 'app-licoes',
    imports: [FormsModule, RouterLink, IonContent, IonIcon, IonSearchbar, SkeletonComponent],
    templateUrl: './licoes.page.html',
    styleUrls: ['./licoes.page.scss'],
})
export class LicoesPage implements OnInit {
    grupos: GrupoComLicoes[] = [];
    pesquisa = '';
    lidas = new Set<string>();
    total = 0;
    totalLidas = 0;
    bloqueadas = 0;
    plano: 'gratis' | 'pago' = 'gratis';
    carregando = true;

    constructor(
        private readonly material: MaterialEstudoService,
        private readonly acesso: AcessoService,
    ) {
        addIcons({ arrowBackOutline, bookOutline, carOutline, checkmarkCircle, lockClosed, chevronForwardOutline, constructOutline, documentTextOutline, libraryOutline, lockClosedOutline, medkitOutline, timeOutline, warningOutline });
    }

    async ngOnInit(): Promise<void> {
        const [grupos, licoes, lidos, material] = await Promise.all([
            this.material.gruposLicoes(),
            this.material.licoes(),
            this.material.conteudosLidos(),
            this.material.carregar(),
        ]);

        this.lidas = lidos;
        this.total = licoes.length;
        this.totalLidas = licoes.filter((licao) => this.lida(licao)).length;
        this.bloqueadas = material.licoesBloqueadas ?? 0;
        this.plano = (await this.acesso.estaPago()) ? 'pago' : 'gratis';

        this.grupos = grupos.map((grupo) => {
            const doGrupo = licoes.filter((licao) => licao.grupo === grupo.slug);

            return {
                ...grupo,
                licoes: doGrupo,
                lidas: doGrupo.filter((licao) => this.lida(licao)).length,
            };
        });

        this.carregando = false;
    }

    get gruposVisiveis(): GrupoComLicoes[] {
        const termo = this.normalizar(this.pesquisa);

        if (!termo) {
            return this.grupos;
        }

        return this.grupos
            .map((grupo) => ({
                ...grupo,
                licoes: grupo.licoes.filter((licao) =>
                    this.normalizar(`${licao.titulo} ${licao.resumo ?? ''}`).includes(termo),
                ),
            }))
            .filter((grupo) => grupo.licoes.length > 0);
    }

    get percentagem(): number {
        return this.total ? Math.round((this.totalLidas / this.total) * 100) : 0;
    }

    lida(licao: LicaoEstudo): boolean {
        return this.lidas.has(`licao:${licao.slug}`);
    }

    private normalizar(texto: string): string {
        return normalizarTexto(texto);
    }
}
