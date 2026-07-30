import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonContent, IonIcon, IonSearchbar } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    arrowBackOutline, bookOutline, checkmarkCircle, checkmarkCircleOutline, chevronDownOutline,
    chevronForwardOutline, createOutline, documentTextOutline, home, layersOutline, personOutline,
    statsChartOutline,
} from 'ionicons/icons';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { ArtigoCodigoEstrada, BlocoTexto, CapituloCodigo, LicaoEstudo } from '../../models/material-estudo.model';

interface CapituloComArtigos extends CapituloCodigo {
    lista: ArtigoCodigoEstrada[];
    lidos: number;
}

/**
 * Código da Estrada por capítulos.
 *
 * O ecrã anterior mostrava todos os artigos numa lista única sob uma categoria
 * inventada chamada "Código da Estrada" — sem estrutura, o aluno não sabia onde
 * estava nem o que faltava. Os capítulos vêm agora do painel.
 */
@Component({
    standalone: true,
    selector: 'app-codigo',
    imports: [FormsModule, RouterLink, IonContent, IonIcon, IonSearchbar],
    templateUrl: './codigo.page.html',
    styleUrls: ['./codigo.page.scss'],
})
export class CodigoPage implements OnInit {
    capitulos: CapituloComArtigos[] = [];
    resultados: ArtigoCodigoEstrada[] = [];
    pesquisa = '';
    capituloAberto: number | null | undefined = undefined;
    artigoAberto: number | null = null;
    blocos: BlocoTexto[] = [];
    licoesDoArtigo: LicaoEstudo[] = [];
    lidos = new Set<string>();
    total = 0;
    totalLidos = 0;
    carregando = true;

    constructor(
        private readonly route: ActivatedRoute,
        private readonly material: MaterialEstudoService,
    ) {
        addIcons({
            arrowBackOutline, bookOutline, checkmarkCircle, checkmarkCircleOutline, chevronDownOutline,
            chevronForwardOutline, createOutline, documentTextOutline, home, layersOutline, personOutline,
            statsChartOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        const [capitulos, artigos, lidos] = await Promise.all([
            this.material.capitulos(),
            this.material.artigos(),
            this.material.conteudosLidos(),
        ]);

        this.lidos = lidos;
        this.total = artigos.length;
        this.totalLidos = artigos.filter((artigo) => this.lido(artigo.numero)).length;

        const porNumero = new Map(artigos.map((artigo) => [artigo.numero, artigo]));

        this.capitulos = capitulos.map((capitulo) => {
            const lista = capitulo.artigos
                .map((numero) => porNumero.get(numero))
                .filter((artigo): artigo is ArtigoCodigoEstrada => Boolean(artigo));

            return {
                ...capitulo,
                lista,
                lidos: lista.filter((artigo) => this.lido(artigo.numero)).length,
            };
        });

        this.carregando = false;

        // Ligação profunda a partir do glossário ou de uma ficha: /codigo?artigo=5
        const pedido = Number(this.route.snapshot.queryParamMap.get('artigo'));
        if (pedido) {
            await this.abrirArtigoPorNumero(pedido);
        }
    }

    get percentagem(): number {
        return this.total ? Math.round((this.totalLidos / this.total) * 100) : 0;
    }

    get pesquisando(): boolean {
        return this.pesquisa.trim().length >= 2;
    }

    async pesquisar(): Promise<void> {
        this.resultados = this.pesquisando ? await this.material.procurarArtigos(this.pesquisa) : [];
    }

    lido(numero: number): boolean {
        return this.lidos.has(`artigo:${numero}`);
    }

    alternarCapitulo(numero: number | null): void {
        this.capituloAberto = this.capituloAberto === numero ? undefined : numero;
        this.artigoAberto = null;
    }

    async alternarArtigo(artigo: ArtigoCodigoEstrada): Promise<void> {
        if (this.artigoAberto === artigo.numero) {
            this.artigoAberto = null;
            return;
        }

        this.artigoAberto = artigo.numero;
        this.blocos = this.material.formatar(artigo.texto);
        this.licoesDoArtigo = await this.material.licoesComArtigo(artigo.numero);

        // Abrir o artigo conta como leitura: é o gesto de estudo desta secção.
        if (!this.lido(artigo.numero)) {
            await this.material.marcarArtigoLido(artigo.numero);
            this.lidos.add(`artigo:${artigo.numero}`);
            this.totalLidos += 1;
            this.recontarCapitulos();
        }
    }

    private async abrirArtigoPorNumero(numero: number): Promise<void> {
        const capitulo = this.capitulos.find((item) => item.lista.some((artigo) => artigo.numero === numero));
        const artigo = capitulo?.lista.find((item) => item.numero === numero);

        if (!capitulo || !artigo) {
            return;
        }

        this.capituloAberto = capitulo.numero;
        await this.alternarArtigo(artigo);
    }

    private recontarCapitulos(): void {
        this.capitulos = this.capitulos.map((capitulo) => ({
            ...capitulo,
            lidos: capitulo.lista.filter((artigo) => this.lido(artigo.numero)).length,
        }));
    }
}
