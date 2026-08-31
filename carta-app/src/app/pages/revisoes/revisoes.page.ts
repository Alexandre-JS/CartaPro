import { Component, OnDestroy, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import { arrowBackOutline, arrowForwardOutline, checkmarkCircleOutline, refreshOutline, timeOutline, warningOutline } from 'ionicons/icons';
import { PerguntaCardComponent } from '../../components/pergunta-card/pergunta-card.component';
import { ProgressoService } from '../../core/progresso.service';
import { SimuladoService } from '../../core/simulado.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { CategoriaCarta, Pergunta } from '../../models/pergunta.model';
import { mensagemErroApi } from '../../core/api-error';

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
    imports: [RouterLink, IonContent, IonIcon, PerguntaCardComponent, SkeletonComponent],
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
    erroCarregamento = '';
    proximaRevisao = '';

    private categoria: CategoriaCarta = 'ligeiro';
    private mostradaEm = Date.now();

    constructor(
        private readonly storage: StorageService,
        private readonly simulado: SimuladoService,
        private readonly progresso: ProgressoService,
        private readonly temasService: TemasService,
    ) {
        addIcons({ arrowBackOutline, arrowForwardOutline, checkmarkCircleOutline, refreshOutline, timeOutline, warningOutline });
    }

    async ngOnInit(): Promise<void> {
        await this.carregar();
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

        const revisao = await this.storage.obterRevisao(this.perguntaAtual.id);
        if (revisao) {
            this.proximaRevisao = revisao.intervaloDias === 0
                ? 'Esta pergunta volta ainda hoje.'
                : revisao.intervaloDias === 1
                    ? 'Próxima revisão amanhã.'
                    : `Próxima revisão daqui a ${revisao.intervaloDias} dias.`;
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
        this.proximaRevisao = '';
        this.mostradaEm = Date.now();
    }

    async recarregar(): Promise<void> {
        await this.carregar();
    }

    async carregar(): Promise<void> {
        this.carregando = true;
        this.erroCarregamento = '';
        this.concluida = false;
        this.indice = 0;
        this.acertos = 0;
        this.escolhida = null;
        this.respondida = false;
        this.proximaRevisao = '';

        try {
            this.categoria = ((await this.storage.obterCategoria()) || 'ligeiro') as CategoriaCarta;
            const [, totalPendentes, perguntas] = await Promise.all([
                this.temasService.carregar(),
                this.storage.contarRevisoesPendentes(),
                this.simulado.perguntasParaRevisao(this.categoria, 10),
            ]);
            this.totalPendentes = totalPendentes;
            this.perguntas = perguntas;
            this.mostradaEm = Date.now();
        } catch (erro) {
            this.erroCarregamento = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }
}
