import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    bookOutline, chevronForwardOutline, documentTextOutline, homeOutline, libraryOutline, lockClosedOutline,
    personOutline, refreshOutline, schoolOutline, statsChartOutline, textOutline, warningOutline,
} from 'ionicons/icons';
import { AcessoService } from '../../core/acesso.service';
import { ContentService } from '../../core/content.service';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { ProgressoService } from '../../core/progresso.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { TreinoSinaisService } from '../../core/treino-sinais.service';
import { LicaoEstudo, ProgressoEstudo } from '../../models/material-estudo.model';
import { CategoriaCarta } from '../../models/pergunta.model';
import { RecomendacaoEstudo } from '../../models/progresso.model';

/** Uma das quatro frentes do material de estudo. */
interface SecaoEstudo {
    id: string;
    titulo: string;
    descricao: string;
    icone: string;
    rota: string;
    total: number;
    progresso?: ProgressoEstudo;
}

/**
 * Hub de estudos.
 *
 * Antes este ecrã era só uma lista de categorias de artigos do Código com uma
 * recomendação em cima. Agora reúne as quatro frentes de estudo — sinalização,
 * fichas por tema, o Código por capítulos e o glossário — cada uma com o seu
 * progresso de leitura.
 */
@Component({
    standalone: true,
    selector: 'app-estudos',
    imports: [RouterLink, IonContent, IonIcon],
    templateUrl: './estudos.page.html',
    styleUrls: ['./estudos.page.scss'],
})
export class EstudosPage implements OnInit {
    secoes: SecaoEstudo[] = [];
    recomendacao: RecomendacaoEstudo | null = null;
    fichaRecomendada: LicaoEstudo | null = null;
    categoria: CategoriaCarta = 'ligeiro';
    revisoesPendentes = 0;
    sinaisPorReforcar = 0;
    bloqueados = 0;
    plano: 'gratis' | 'pago' = 'gratis';
    carregando = true;

    constructor(
        private readonly material: MaterialEstudoService,
        private readonly storage: StorageService,
        private readonly content: ContentService,
        private readonly progresso: ProgressoService,
        private readonly temasService: TemasService,
        private readonly treino: TreinoSinaisService,
        private readonly acesso: AcessoService,
    ) {
        addIcons({
            bookOutline, chevronForwardOutline, documentTextOutline, homeOutline, libraryOutline, lockClosedOutline,
            personOutline, refreshOutline, schoolOutline, statsChartOutline, textOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        await this.temasService.carregar();

        const [estudo, progressoLeitura, temas, categoriaGuardada, revisoes, reforco] = await Promise.all([
            this.material.carregar(),
            this.material.progresso(),
            this.content.listarTemas(),
            this.storage.obterCategoria(),
            this.storage.listarRevisoesPendentes(),
            this.treino.totalParaReforcar(),
        ]);

        this.categoria = (categoriaGuardada || 'ligeiro') as CategoriaCarta;
        this.revisoesPendentes = revisoes.length;
        this.sinaisPorReforcar = reforco;
        this.bloqueados = (estudo.sinaisBloqueados ?? 0) + (estudo.licoesBloqueadas ?? 0);
        this.plano = (await this.acesso.estaPago()) ? 'pago' : 'gratis';

        this.secoes = [
            {
                id: 'sinais',
                titulo: 'Sinais de trânsito',
                descricao: 'Reconhecer pela forma, cor e significado',
                icone: 'warning-outline',
                rota: '/sinais',
                total: estudo.sinais.length,
                progresso: progressoLeitura.sinais,
            },
            {
                id: 'licoes',
                titulo: 'Fichas por tema',
                descricao: 'Regras, condução, primeiros socorros e mecânica',
                icone: 'library-outline',
                rota: '/licoes',
                total: estudo.licoes.length,
                progresso: progressoLeitura.licoes,
            },
            {
                id: 'codigo',
                titulo: 'Código da Estrada',
                descricao: 'O texto legal, organizado por capítulos',
                icone: 'document-text-outline',
                rota: '/codigo',
                total: estudo.artigos.length,
                progresso: progressoLeitura.artigos,
            },
            {
                id: 'glossario',
                titulo: 'Glossário',
                descricao: 'O vocabulário que aparece nas perguntas',
                icone: 'text-outline',
                rota: '/glossario',
                total: estudo.glossario.length,
            },
        ];

        await this.prepararRecomendacao(temas, revisoes[0]?.tema);

        this.carregando = false;
    }

    /** Só mostra secções com conteúdo publicado — cartões vazios não ajudam. */
    get secoesDisponiveis(): SecaoEstudo[] {
        return this.secoes.filter((secao) => secao.total > 0);
    }

    get semMaterial(): boolean {
        return !this.carregando && this.secoesDisponiveis.length === 0;
    }

    nomeTema(tema: string): string {
        return this.temasService.nome(tema);
    }

    private async prepararRecomendacao(temas: string[], temaEmRevisao?: string): Promise<void> {
        const estatisticas = await this.progresso.estatisticasPorTema(temas);
        const recomendacao = this.progresso.recomendarEstudo(estatisticas, 5, temaEmRevisao);

        if (!recomendacao) {
            return;
        }

        const disponiveis = await this.content.listarPerguntas({
            tema: recomendacao.tema,
            categoria: this.categoria,
        });
        const totalPerguntas = Math.min(5, disponiveis.length);

        this.recomendacao = {
            ...recomendacao,
            totalPerguntas,
            minutosEstimados: Math.max(4, totalPerguntas * 2),
        };

        // A ficha do tema fraco é o material que explica o que ele está a errar.
        this.fichaRecomendada = (await this.material.licaoParaTema(recomendacao.tema)) ?? null;
    }
}
