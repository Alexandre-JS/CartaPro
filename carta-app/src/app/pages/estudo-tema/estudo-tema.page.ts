import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowBackOutline, arrowForwardOutline, bookOutline, readerOutline, ribbonOutline } from 'ionicons/icons';
import { PerguntaCardComponent } from '../../components/pergunta-card/pergunta-card.component';
import { ProgressoService } from '../../core/progresso.service';
import { SimuladoService } from '../../core/simulado.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { TAMANHO_SESSAO_ESTUDO } from '../../config/simulado.config';
import { CategoriaCarta, Pergunta } from '../../models/pergunta.model';
import { ProgressoTema } from '../../models/progresso.model';

@Component({
    standalone: true,
    selector: 'app-estudo-tema',
    imports: [RouterLink, IonContent, IonIcon, PerguntaCardComponent],
    templateUrl: './estudo-tema.page.html',
    styleUrls: ['./estudo-tema.page.scss'],
})
export class EstudoTemaPage implements OnInit {
    tema = '';
    perguntas: Pergunta[] = [];
    progressoTema: ProgressoTema | null = null;
    indice = 0;
    escolhida: number | null = null;
    respondida = false;
    iniciada = false;
    concluida = false;
    acertosSessao = 0;
    modoRevisao = false;
    /** Instante em que a pergunta atual apareceu — base da telemetria. */
    private mostradaEm = Date.now();

    constructor(
        private readonly route: ActivatedRoute,
        private readonly simulado: SimuladoService,
        private readonly progresso: ProgressoService,
        private readonly storage: StorageService,
        private readonly temasService: TemasService,
    ) {
        addIcons({ arrowBackOutline, arrowForwardOutline, bookOutline, readerOutline, ribbonOutline });
    }

    async ngOnInit(): Promise<void> {
        this.tema = this.route.snapshot.paramMap.get('tema') || '';
        const categoria = (this.route.snapshot.queryParamMap.get('categoria') || 'ligeiro') as CategoriaCarta;
        this.modoRevisao = this.route.snapshot.queryParamMap.get('modo') === 'revisao';
        await this.temasService.carregar();

        if (this.modoRevisao) {
            const pendentes = await this.storage.listarRevisoesPendentes();
            const idsPendentes = new Set(
                pendentes.filter((revisao) => revisao.tema === this.tema).map((revisao) => revisao.perguntaId),
            );
            const doTema = await this.simulado.perguntasPorTema(this.tema, categoria, Number.MAX_SAFE_INTEGER);
            this.perguntas = doTema.filter((pergunta) => idsPendentes.has(pergunta.id)).slice(0, TAMANHO_SESSAO_ESTUDO);
        } else {
            // A amostragem já vem ponderada por recência: deixa de ser sempre
            // o mesmo grupo de 5 perguntas do topo do banco.
            this.perguntas = await this.simulado.perguntasPorTema(this.tema, categoria, TAMANHO_SESSAO_ESTUDO);
        }

        await this.atualizarProgresso();
    }

    get perguntaAtual(): Pergunta | null {
        return this.perguntas[this.indice] || null;
    }

    get progressoPercentagem(): number {
        return Math.round((this.progressoTema?.taxaRecente || 0) * 100);
    }

    get nomeTema(): string {
        return this.temasService.nome(this.tema);
    }

    get percentagemSessao(): number {
        return this.perguntas.length ? Math.round((this.acertosSessao / this.perguntas.length) * 100) : 0;
    }

    iniciarSessao(): void {
        this.iniciada = true;
        this.mostradaEm = Date.now();
    }

    async confirmarOuAvancar(): Promise<void> {
        if (!this.perguntaAtual || this.escolhida === null) {
            return;
        }
        if (!this.respondida) {
            const acertou = await this.progresso.registarResposta(this.perguntaAtual, {
                escolhida: this.escolhida,
                duracaoMs: Date.now() - this.mostradaEm,
                origem: this.modoRevisao ? 'revisao' : 'estudo',
            });
            if (acertou) {
                this.acertosSessao++;
            }
            await this.atualizarProgresso();
            this.respondida = true;
            return;
        }
        if (this.indice === this.perguntas.length - 1) {
            this.concluida = true;
            return;
        }
        this.indice++;
        this.escolhida = null;
        this.respondida = false;
        this.mostradaEm = Date.now();
    }

    private async atualizarProgresso(): Promise<void> {
        this.progressoTema = (await this.progresso.estatisticasPorTema([this.tema]))[0];
    }
}
