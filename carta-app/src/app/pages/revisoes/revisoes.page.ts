import { Component, OnDestroy, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowForwardOutline, bookOutline, checkmarkCircleOutline, createOutline, home, personOutline, refreshOutline, statsChartOutline, timeOutline } from 'ionicons/icons';
import { PerguntaCardComponent } from '../../components/pergunta-card/pergunta-card.component';
import { ContentService } from '../../core/content.service';
import { ProgressoService } from '../../core/progresso.service';
import { SimuladoService } from '../../core/simulado.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { CategoriaCarta, Pergunta } from '../../models/pergunta.model';

/**
 * Fila "Revisões de hoje" — transversal a todos os temas.
 *
 * A repetição espaçada já agendava revisões, mas nenhum ecrã as servia: só
 * entravam se a recomendação da página inicial calhasse escolher aquele tema, e
 * mesmo assim uma de cada vez. Era conhecimento agendado para ser esquecido.
 */
@Component({
    standalone: true,
    selector: 'app-revisoes',
    imports: [RouterLink, IonContent, IonIcon, PerguntaCardComponent],
    templateUrl: './revisoes.page.html',
    styleUrls: ['./revisoes.page.scss'],
})
export class RevisoesPage implements OnInit, OnDestroy {
    perguntas: Pergunta[] = [];
    indice = 0;
    escolhida: number | null = null;
    respondida = false;
    acertos = 0;
    concluida = false;
    carregando = true;
    totalPendentes = 0;

    private categoria: CategoriaCarta = 'ligeiro';
    private mostradaEm = Date.now();

    constructor(
        private readonly storage: StorageService,
        private readonly simulado: SimuladoService,
        private readonly progresso: ProgressoService,
        private readonly content: ContentService,
        private readonly temasService: TemasService,
    ) {
        addIcons({ arrowForwardOutline, bookOutline, checkmarkCircleOutline, createOutline, home, personOutline, refreshOutline, statsChartOutline, timeOutline });
    }

    async ngOnInit(): Promise<void> {
        this.categoria = ((await this.storage.obterCategoria()) || 'ligeiro') as CategoriaCarta;
        await this.temasService.carregar();

        this.totalPendentes = await this.storage.contarRevisoesPendentes();
        this.perguntas = await this.simulado.perguntasParaRevisao(this.categoria, 10);
        this.carregando = false;
        this.mostradaEm = Date.now();
    }

    ngOnDestroy(): void {
        // Sobe o progresso desta sessão de uma só vez.
        void this.storage.sincronizarAgora().catch(() => undefined);
    }

    get perguntaAtual(): Pergunta | null {
        return this.perguntas[this.indice] || null;
    }

    get total(): number {
        return this.perguntas.length;
    }

    get restantes(): number {
        return Math.max(0, this.total - this.indice - (this.respondida ? 1 : 0));
    }

    nomeTema(tema: string): string {
        return this.temasService.nome(tema);
    }

    async responder(indice: number): Promise<void> {
        if (this.respondida || !this.perguntaAtual) {
            return;
        }

        this.escolhida = indice;
        this.respondida = true;

        const acertou = await this.progresso.registarResposta(this.perguntaAtual, {
            escolhida: indice,
            duracaoMs: Date.now() - this.mostradaEm,
            origem: 'revisao',
        });

        if (acertou) {
            this.acertos++;
        }
    }

    avancar(): void {
        if (!this.respondida) {
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

    async recarregar(): Promise<void> {
        this.carregando = true;
        this.concluida = false;
        this.indice = 0;
        this.acertos = 0;
        this.escolhida = null;
        this.respondida = false;
        this.totalPendentes = await this.storage.contarRevisoesPendentes();
        this.perguntas = await this.simulado.perguntasParaRevisao(this.categoria, 10);
        this.carregando = false;
        this.mostradaEm = Date.now();
    }
}
