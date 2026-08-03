import { DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import { alertCircleOutline, bulbOutline, calendarOutline, closeOutline, documentTextOutline, eyeOutline, refreshOutline, statsChartOutline } from 'ionicons/icons';
import { ContentService } from '../../core/content.service';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { CategoriaCarta } from '../../models/pergunta.model';
import { HistoricoExame, RespostaRegisto, RevisaoAgendada } from '../../models/progresso.model';

interface RetencaoTema {
    tema: string;
    total: number;
    praticadas: number;
    retidas: number;
    consolidadas: number;
    retencao: number;
}

@Component({
    standalone: true,
    selector: 'app-desempenho',
    imports: [DatePipe, RouterLink, IonContent, IonIcon, BottomNavComponent, SkeletonComponent],
    templateUrl: './desempenho.page.html',
    styleUrls: ['./desempenho.page.scss'],
})
export class DesempenhoPage implements OnInit {
    historico: HistoricoExame[] = [];
    temas: RetencaoTema[] = [];
    totalPerguntasBanco = 0;
    perguntasPraticadas = 0;
    perguntasRetidas = 0;
    perguntasConsolidadas = 0;
    perguntasARever = 0;
    retencao = 0;
    cobertura = 0;
    dominioGlobal = 0;
    consolidacaoAvaliada = false;
    categoria: CategoriaCarta = 'ligeiro';
    carregando = true;
    alertaVisivel = true;

    constructor(
        private readonly storage: StorageService,
        private readonly content: ContentService,
        private readonly temasService: TemasService,
        private readonly regras: RegrasService,
    ) {
        addIcons({ alertCircleOutline, bulbOutline, calendarOutline, closeOutline, documentTextOutline, eyeOutline, refreshOutline, statsChartOutline });
    }

    ionViewWillEnter(): void {
        this.alertaVisivel = true;
    }

    async ngOnInit(): Promise<void> {
        await Promise.all([this.temasService.carregar(), this.regras.carregar()]);

        const [historico, respostas, revisoes, nomesTemas, categoriaGuardada] = await Promise.all([
            this.storage.listarExames(),
            this.storage.listarRespostas(),
            this.storage.listarRevisoes(),
            this.content.listarTemas(),
            this.storage.obterCategoria(),
        ]);
        this.categoria = (categoriaGuardada || 'ligeiro') as CategoriaCarta;
        const perguntasDoBanco = await this.content.listarPerguntas({ categoria: this.categoria });
        const idsDoBanco = new Set(perguntasDoBanco.map((pergunta) => pergunta.id));
        const respostasAtuais = this.ultimasRespostasPorPergunta(
            respostas.filter((resposta) => idsDoBanco.has(resposta.perguntaId)),
        );
        const idsRevistosComAcerto = new Set(
            respostas
                .filter((resposta) => idsDoBanco.has(resposta.perguntaId) && resposta.origem === 'revisao' && resposta.acertou)
                .map((resposta) => resposta.perguntaId),
        );
        this.consolidacaoAvaliada = respostas.some(
            (resposta) => idsDoBanco.has(resposta.perguntaId) && resposta.origem === 'revisao',
        );
        const respostasPorId = new Map(respostasAtuais.map((resposta) => [resposta.perguntaId, resposta]));
        const revisoesPorId = new Map(revisoes.map((revisao) => [revisao.perguntaId, revisao]));

        this.historico = historico;
        this.totalPerguntasBanco = perguntasDoBanco.length;
        this.perguntasPraticadas = respostasAtuais.length;
        this.perguntasRetidas = respostasAtuais.filter((resposta) => resposta.acertou).length;
        this.perguntasConsolidadas = respostasAtuais.filter((resposta) =>
            this.consolidada(resposta, revisoesPorId.get(resposta.perguntaId), idsRevistosComAcerto.has(resposta.perguntaId)),
        ).length;
        this.perguntasARever = respostasAtuais.filter((resposta) => {
            const revisao = revisoesPorId.get(resposta.perguntaId);
            return !resposta.acertou || !!revisao && revisao.agendadaPara <= Date.now();
        }).length;
        this.retencao = this.perguntasPraticadas
            ? Math.round((this.perguntasRetidas / this.perguntasPraticadas) * 100)
            : 0;
        this.cobertura = this.totalPerguntasBanco
            ? Math.round((this.perguntasPraticadas / this.totalPerguntasBanco) * 100)
            : 0;
        this.dominioGlobal = this.totalPerguntasBanco
            ? Math.round((this.perguntasConsolidadas / this.totalPerguntasBanco) * 100)
            : 0;
        this.temas = nomesTemas.map((tema) => {
            const perguntas = perguntasDoBanco.filter((pergunta) => pergunta.tema === tema);
            const atuais = perguntas
                .map((pergunta) => respostasPorId.get(pergunta.id))
                .filter((resposta): resposta is RespostaRegisto => !!resposta);
            const retidas = atuais.filter((resposta) => resposta.acertou).length;
            const consolidadas = atuais.filter((resposta) =>
                this.consolidada(resposta, revisoesPorId.get(resposta.perguntaId), idsRevistosComAcerto.has(resposta.perguntaId)),
            ).length;

            return {
                tema,
                total: perguntas.length,
                praticadas: atuais.length,
                retidas,
                consolidadas,
                retencao: atuais.length ? Math.round((retidas / atuais.length) * 100) : 0,
            };
        });
        this.carregando = false;
    }

    /**
     * O desempenho principal mede conhecimento atual, não volume de cliques:
     * cada pergunta conta uma vez e vale o resultado da tentativa mais recente.
     */
    private ultimasRespostasPorPergunta(respostas: RespostaRegisto[]): RespostaRegisto[] {
        const ultimas = new Map<string, RespostaRegisto>();

        for (const resposta of respostas) {
            const anterior = ultimas.get(resposta.perguntaId);
            if (!anterior || resposta.data >= anterior.data) {
                ultimas.set(resposta.perguntaId, resposta);
            }
        }

        return [...ultimas.values()];
    }

    /** Consolidada = ciclo repetido, revisão correta e conhecimento ainda certo. */
    private consolidada(resposta: RespostaRegisto, revisao: RevisaoAgendada | undefined, revistaComAcerto: boolean): boolean {
        return resposta.acertou && revistaComAcerto && (revisao?.repeticoes ?? 0) >= 2;
    }

    get temasComDados(): RetencaoTema[] {
        return this.temas.filter((tema) => tema.praticadas > 0);
    }

    get temasPorPraticar(): RetencaoTema[] {
        return this.temas.filter((tema) => tema.praticadas === 0);
    }

    get nomesPorPraticar(): string {
        return this.temasPorPraticar.map((tema) => this.nomeTema(tema.tema)).join(', ');
    }

    percentagem(exame: HistoricoExame): number {
        return exame.total ? Math.round((exame.acertos / exame.total) * 100) : 0;
    }

    /** A nota de passagem vem das regras do painel, não de um 80% fixo. */
    aprovado(exame: HistoricoExame): boolean {
        return this.regras.aprovado(exame.acertos, exame.total, this.categoria);
    }

    formatarTempo(segundos: number): string {
        return `${Math.floor(segundos / 60).toString().padStart(2, '0')}:${(segundos % 60).toString().padStart(2, '0')}`;
    }

    numeroExame(numero: number): string {
        return numero.toString().padStart(2, '0');
    }

    /** Nome vindo do painel — o mapa hardcoded de 3 temas foi removido. */
    nomeTema(tema: string): string {
        return this.temasService.nome(tema);
    }
}
