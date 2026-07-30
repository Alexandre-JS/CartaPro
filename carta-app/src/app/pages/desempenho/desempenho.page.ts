import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import { calendarOutline, documentTextOutline, ribbonOutline, statsChartOutline, timeOutline, trendingUpOutline } from 'ionicons/icons';
import { ContentService } from '../../core/content.service';
import { ProgressoService } from '../../core/progresso.service';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { CategoriaCarta } from '../../models/pergunta.model';
import { HistoricoExame, ProgressoTema } from '../../models/progresso.model';

@Component({
    standalone: true,
    selector: 'app-desempenho',
    imports: [DatePipe, DecimalPipe, RouterLink, IonContent, IonIcon, BottomNavComponent, SkeletonComponent],
    templateUrl: './desempenho.page.html',
    styleUrls: ['./desempenho.page.scss'],
})
export class DesempenhoPage implements OnInit {
    historico: HistoricoExame[] = [];
    temas: ProgressoTema[] = [];
    totalQuestoes = 0;
    taxaGeral = 0;
    categoria: CategoriaCarta = 'ligeiro';
    carregando = true;

    constructor(
        private readonly storage: StorageService,
        private readonly content: ContentService,
        private readonly progresso: ProgressoService,
        private readonly temasService: TemasService,
        private readonly regras: RegrasService,
    ) {
        addIcons({ calendarOutline, documentTextOutline, ribbonOutline, statsChartOutline, timeOutline, trendingUpOutline });
    }

    async ngOnInit(): Promise<void> {
        await Promise.all([this.temasService.carregar(), this.regras.carregar()]);

        const [historico, respostas, nomesTemas, categoriaGuardada] = await Promise.all([
            this.storage.listarExames(),
            this.storage.listarRespostas(),
            this.content.listarTemas(),
            this.storage.obterCategoria(),
        ]);
        this.categoria = (categoriaGuardada || 'ligeiro') as CategoriaCarta;
        this.historico = historico;
        this.temas = await this.progresso.estatisticasPorTema(nomesTemas);
        this.totalQuestoes = respostas.length;
        const acertos = respostas.filter((resposta) => resposta.acertou).length;
        this.taxaGeral = respostas.length ? Math.round((acertos / respostas.length) * 100) : 0;
        this.carregando = false;
    }

    get mediaExames(): number {
        if (!this.historico.length) {
            return 0;
        }
        return Math.round(this.historico.reduce((total, exame) => total + this.percentagem(exame), 0) / this.historico.length);
    }

    get melhorResultado(): number {
        return this.historico.length ? Math.max(...this.historico.map((exame) => this.percentagem(exame))) : 0;
    }

    get tempoMedio(): string {
        if (!this.historico.length) {
            return '00:00';
        }
        const total = Math.round(this.historico.reduce((soma, exame) => soma + exame.tempoSegundos, 0) / this.historico.length);
        return this.formatarTempo(total);
    }

    get temasComDados(): ProgressoTema[] {
        return this.temas.filter((tema) => tema.respondidas > 0);
    }

    get temasPorPraticar(): ProgressoTema[] {
        return this.progresso.temasNaoPraticados(this.temas);
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
