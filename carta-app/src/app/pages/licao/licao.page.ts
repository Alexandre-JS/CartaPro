import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    arrowBackOutline, bookOutline, checkmarkCircle, checkmarkCircleOutline, chevronBackOutline,
    chevronForwardOutline, documentTextOutline, libraryOutline, schoolOutline, timeOutline, warningOutline,
} from 'ionicons/icons';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { TemasService } from '../../core/temas.service';
import { ArtigoCodigoEstrada, BlocoTexto, LicaoEstudo, SinalTransito } from '../../models/material-estudo.model';

/**
 * Leitura de uma ficha de estudo.
 *
 * O corpo vem em texto simples do painel e é renderizado como blocos —
 * nunca por `innerHTML`, para conteúdo do painel não poder injetar marcação
 * no app.
 */
@Component({
    standalone: true,
    selector: 'app-licao',
    imports: [RouterLink, IonContent, IonIcon],
    templateUrl: './licao.page.html',
    styleUrls: ['./licao.page.scss'],
})
export class LicaoPage implements OnInit {
    licao?: LicaoEstudo;
    blocos: BlocoTexto[] = [];
    sinais: SinalTransito[] = [];
    artigos: ArtigoCodigoEstrada[] = [];
    grupoNome = '';
    temaNome = '';
    lida = false;
    anterior?: LicaoEstudo;
    proxima?: LicaoEstudo;
    artigoAberto: number | null = null;
    carregando = true;

    constructor(
        private readonly route: ActivatedRoute,
        private readonly material: MaterialEstudoService,
        private readonly temas: TemasService,
    ) {
        addIcons({
            arrowBackOutline, bookOutline, checkmarkCircle, checkmarkCircleOutline, chevronBackOutline,
            chevronForwardOutline, documentTextOutline, libraryOutline, schoolOutline, timeOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        // Navegar para a ficha seguinte reaproveita o componente: reage ao param.
        this.route.paramMap.subscribe((parametros) => {
            void this.mostrar(parametros.get('slug') || '');
        });
    }

    alternarArtigo(numero: number): void {
        this.artigoAberto = this.artigoAberto === numero ? null : numero;
    }

    blocosDoArtigo(artigo: ArtigoCodigoEstrada): BlocoTexto[] {
        return this.material.formatar(artigo.texto);
    }

    async marcarLida(): Promise<void> {
        if (!this.licao || this.lida) {
            return;
        }

        await this.material.marcarLicaoLida(this.licao.slug);
        this.lida = true;
    }

    private async mostrar(slug: string): Promise<void> {
        this.carregando = true;
        this.artigoAberto = null;

        const licao = await this.material.licao(slug);

        if (!licao) {
            this.licao = undefined;
            this.carregando = false;
            return;
        }

        await this.temas.carregar();

        const [grupos, irmas, lidos] = await Promise.all([
            this.material.gruposLicoes(),
            this.material.licoes(licao.grupo),
            this.material.conteudosLidos(),
        ]);

        this.licao = licao;
        this.blocos = this.material.formatar(licao.corpo);
        this.grupoNome = grupos.find((grupo) => grupo.slug === licao.grupo)?.nome ?? licao.grupo;
        this.temaNome = licao.tema ? this.temas.nome(licao.tema) : '';
        this.lida = lidos.has(`licao:${licao.slug}`);

        const indice = irmas.findIndex((item) => item.slug === licao.slug);
        this.anterior = indice > 0 ? irmas[indice - 1] : undefined;
        this.proxima = indice >= 0 && indice < irmas.length - 1 ? irmas[indice + 1] : undefined;

        this.sinais = await this.resolverSinais(licao.sinais);
        this.artigos = await this.resolverArtigos(licao.artigos);

        this.carregando = false;
    }

    private async resolverSinais(slugs: string[]): Promise<SinalTransito[]> {
        const encontrados: SinalTransito[] = [];

        for (const slug of slugs) {
            const sinal = await this.material.sinal(slug);
            // Um sinal bloqueado no plano gratuito não vem no pacote: ignora-se
            // em silêncio em vez de mostrar um espaço vazio na ficha.
            if (sinal) {
                encontrados.push(sinal);
            }
        }

        return encontrados;
    }

    private async resolverArtigos(numeros: number[]): Promise<ArtigoCodigoEstrada[]> {
        const encontrados: ArtigoCodigoEstrada[] = [];

        for (const numero of numeros) {
            const artigo = await this.material.artigo(numero);
            if (artigo) {
                encontrados.push(artigo);
            }
        }

        return encontrados;
    }
}
