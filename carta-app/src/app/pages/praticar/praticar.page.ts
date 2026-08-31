import { DecimalPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    alertCircleOutline, bulbOutline, chevronForwardOutline, createOutline, flashOutline,
    helpCircleOutline, lockClosedOutline, refreshOutline, timeOutline, warningOutline,
} from 'ionicons/icons';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { mensagemErroApi } from '../../core/api-error';
import { ContentService } from '../../core/content.service';
import { ProgressoService } from '../../core/progresso.service';
import { StorageService } from '../../core/storage.service';
import { TreinoSinaisService } from '../../core/treino-sinais.service';
import { CategoriaCarta } from '../../models/pergunta.model';
import { TemaDetalhe } from '../../models/pacote.model';
import { ProgressoTema } from '../../models/progresso.model';

interface TreinoRecomendado {
    titulo: string;
    descricao: string;
    acao: string;
    rota: unknown[];
    query?: Record<string, unknown>;
}

@Component({
    standalone: true,
    selector: 'app-praticar',
    imports: [DecimalPipe, RouterLink, IonContent, IonIcon, AppHeaderComponent, BottomNavComponent, SkeletonComponent],
    templateUrl: './praticar.page.html',
    styleUrls: ['./praticar.page.scss'],
})
export class PraticarPage implements OnInit {
    temas: TemaDetalhe[] = [];
    progressoTemas: ProgressoTema[] = [];
    recomendado: TreinoRecomendado | null = null;
    categoria: CategoriaCarta = 'ligeiro';
    totalErros = 0;
    totalRevisoes = 0;
    totalNovas = 0;
    sinaisPorReforcar = 0;
    plano: 'gratis' | 'pago' = 'gratis';
    carregando = true;
    erroCarregamento = '';

    constructor(
        private readonly content: ContentService,
        private readonly storage: StorageService,
        private readonly progresso: ProgressoService,
        private readonly treinoSinais: TreinoSinaisService,
    ) {
        addIcons({
            alertCircleOutline, bulbOutline, chevronForwardOutline, createOutline, flashOutline,
            helpCircleOutline, lockClosedOutline, refreshOutline, timeOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        await this.carregar();
    }

    async carregar(): Promise<void> {
        this.carregando = true;
        this.erroCarregamento = '';

        try {
            this.categoria = ((await this.storage.obterCategoria()) || 'ligeiro') as CategoriaCarta;
            const [temas, perguntas, respostas, revisoes, sinais, acesso] = await Promise.all([
                this.content.listarTemasDetalhe(),
                this.content.listarPerguntas({ categoria: this.categoria }),
                this.storage.listarRespostas(),
                this.storage.listarRevisoesPendentes(),
                this.treinoSinais.totalParaReforcar(),
                this.storage.obterEstadoAcesso(),
            ]);

            this.temas = temas;
            this.progressoTemas = await this.progresso.estatisticasPorTema(temas.map((tema) => tema.slug));
            this.totalRevisoes = revisoes.length;
            this.sinaisPorReforcar = sinais;
            this.plano = acesso.plano;

            const idsDisponiveis = new Set(perguntas.map((pergunta) => pergunta.id));
            const respondidas = new Set(respostas.map((resposta) => resposta.perguntaId));
            this.totalErros = new Set(respostas.filter((resposta) => !resposta.acertou && idsDisponiveis.has(resposta.perguntaId)).map((resposta) => resposta.perguntaId)).size;
            this.totalNovas = perguntas.filter((pergunta) => !respondidas.has(pergunta.id)).length;
            this.recomendado = this.montarRecomendacao(revisoes[0]?.tema);
        } catch (erro) {
            this.erroCarregamento = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }

    progressoDoTema(slug: string): ProgressoTema {
        return this.progressoTemas.find((item) => item.tema === slug) ?? {
            tema: slug, respondidas: 0, acertos: 0, taxaAcerto: 0, taxaRecente: 0,
            estado: 'nao_praticado', graduado: false, revisoesPendentes: 0, tempoMedioMs: 0,
        };
    }

    private montarRecomendacao(temaEmRevisao?: string): TreinoRecomendado {
        if (this.totalRevisoes) {
            return {
                titulo: 'Revisões pendentes',
                descricao: `${this.totalRevisoes} ${this.totalRevisoes === 1 ? 'pergunta está' : 'perguntas estão'} no momento certo para rever.`,
                acao: 'Revisar agora',
                rota: ['/praticar/revisoes'],
            };
        }

        const recomendacao = this.progresso.recomendarEstudo(this.progressoTemas, 5, temaEmRevisao);
        if (recomendacao) {
            const tema = this.temas.find((item) => item.slug === recomendacao.tema);
            return {
                titulo: tema?.nome || recomendacao.tema.replace(/_/g, ' '),
                descricao: recomendacao.motivo,
                acao: recomendacao.acao === 'reforcar' ? 'Reforçar tema' : 'Começar sessão',
                rota: ['/praticar/tema', recomendacao.tema],
                query: { categoria: this.categoria },
            };
        }

        return {
            titulo: 'Sessão rápida',
            descricao: 'Cinco perguntas para manter o ritmo de preparação.',
            acao: 'Começar',
            rota: ['/praticar/sessao'],
            query: { total: 5 },
        };
    }
}
