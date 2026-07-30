import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    arrowBackOutline, arrowForwardOutline, checkmarkCircle, closeCircleOutline, refreshOutline,
    schoolOutline, trophyOutline, warningOutline,
} from 'ionicons/icons';
import { PerguntaCardComponent } from '../../components/pergunta-card/pergunta-card.component';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { ProgressoService } from '../../core/progresso.service';
import { TreinoSinaisService } from '../../core/treino-sinais.service';
import { StorageService } from '../../core/storage.service';
import { Pergunta } from '../../models/pergunta.model';
import { SinalTransito } from '../../models/material-estudo.model';

/** Sinais por sessão: curta o suficiente para se fazer numa pausa. */
const PERGUNTAS_POR_SESSAO = 10;

/**
 * Treino de reconhecimento de sinais.
 *
 * Não é um exame: não tem cronómetro nem nota de passagem. Cada resposta entra
 * no mesmo histórico do simulado (com origem `estudo`) e agenda revisão
 * espaçada, para os sinais falhados voltarem a aparecer.
 */
@Component({
    standalone: true,
    selector: 'app-treino-sinais',
    imports: [RouterLink, IonContent, IonIcon, PerguntaCardComponent],
    templateUrl: './treino-sinais.page.html',
    styleUrls: ['./treino-sinais.page.scss'],
})
export class TreinoSinaisPage implements OnInit {
    perguntas: Pergunta[] = [];
    indice = 0;
    escolhas: Array<number | null> = [];
    respondidas: boolean[] = [];
    acertos = 0;
    processando = false;
    concluido = false;
    carregando = true;
    tituloSessao = 'Treino de sinais';
    falhados: SinalTransito[] = [];

    private categoria: string | null = null;
    private reforco = false;
    /** Instante em que a pergunta atual apareceu — alimenta a telemetria. */
    private mostradaEm = Date.now();

    constructor(
        private readonly route: ActivatedRoute,
        private readonly router: Router,
        private readonly treino: TreinoSinaisService,
        private readonly material: MaterialEstudoService,
        private readonly progresso: ProgressoService,
        private readonly storage: StorageService,
    ) {
        addIcons({
            arrowBackOutline, arrowForwardOutline, checkmarkCircle, closeCircleOutline, refreshOutline,
            schoolOutline, trophyOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        const parametros = this.route.snapshot.queryParamMap;
        this.categoria = parametros.get('categoria') || null;
        this.reforco = parametros.get('modo') === 'reforco';

        await this.iniciar();
    }

    get perguntaAtual(): Pergunta | null {
        return this.perguntas[this.indice] || null;
    }

    get escolhida(): number | null {
        return this.escolhas[this.indice] ?? null;
    }

    get respondida(): boolean {
        return this.respondidas[this.indice] ?? false;
    }

    get ultima(): boolean {
        return this.indice === this.perguntas.length - 1;
    }

    get percentagem(): number {
        return this.perguntas.length ? Math.round((this.acertos / this.perguntas.length) * 100) : 0;
    }

    get progressoBarra(): number {
        return this.perguntas.length ? Math.round(((this.indice + 1) / this.perguntas.length) * 100) : 0;
    }

    get sinalAtual(): string | null {
        const pergunta = this.perguntaAtual;

        return pergunta && TreinoSinaisService.ehPerguntaDeSinal(pergunta.id)
            ? TreinoSinaisService.slugDaPergunta(pergunta.id)
            : null;
    }

    async responder(escolha: number): Promise<void> {
        if (this.respondida || !this.perguntaAtual || this.processando) {
            return;
        }

        this.processando = true;
        this.escolhas[this.indice] = escolha;
        this.respondidas[this.indice] = true;

        const acertou = await this.progresso.registarResposta(this.perguntaAtual, {
            escolhida: escolha,
            duracaoMs: Date.now() - this.mostradaEm,
            origem: 'estudo',
        });

        this.acertos += acertou ? 1 : 0;

        // Ver o sinal na correção também conta como estudado.
        const slug = this.sinalAtual;
        if (slug) {
            await this.material.marcarSinalVisto(slug);
        }

        this.processando = false;
    }

    async avancar(): Promise<void> {
        if (!this.respondida) {
            return;
        }

        if (this.ultima) {
            await this.concluir();
            return;
        }

        this.indice += 1;
        this.mostradaEm = Date.now();
    }

    /** Repete o treino — nova amostra, priorizando o que ainda falha. */
    async repetir(): Promise<void> {
        this.carregando = true;
        this.concluido = false;
        await this.iniciar();
    }

    /** Segunda volta apenas sobre os sinais desta sessão que falharam. */
    async repetirFalhados(): Promise<void> {
        this.carregando = true;
        this.concluido = false;
        this.reforco = true;
        this.categoria = null;
        await this.iniciar();
    }

    sair(): Promise<boolean> {
        return this.router.navigateByUrl('/sinais');
    }

    private async iniciar(): Promise<void> {
        this.perguntas = this.reforco
            ? await this.treino.montarSessaoDeReforco(PERGUNTAS_POR_SESSAO)
            : await this.treino.montarSessao(this.categoria ?? undefined, PERGUNTAS_POR_SESSAO);

        this.escolhas = this.perguntas.map(() => null);
        this.respondidas = this.perguntas.map(() => false);
        this.acertos = 0;
        this.indice = 0;
        this.falhados = [];
        this.tituloSessao = await this.montarTitulo();
        this.mostradaEm = Date.now();
        this.carregando = false;
    }

    private async montarTitulo(): Promise<string> {
        if (this.reforco) {
            return 'Reforço de sinais';
        }

        if (!this.categoria) {
            return 'Treino de sinais';
        }

        const categorias = await this.material.categoriasSinais();

        return categorias.find((item) => item.slug === this.categoria)?.nome ?? 'Treino de sinais';
    }

    private async concluir(): Promise<void> {
        this.concluido = true;
        this.falhados = await this.recolherFalhados();

        // O treino conta para o histórico sincronizado como qualquer resposta.
        void this.storage.sincronizarAgora().catch(() => undefined);
    }

    /** Os sinais errados nesta sessão, para revisão imediata no ecrã final. */
    private async recolherFalhados(): Promise<SinalTransito[]> {
        const errados: SinalTransito[] = [];

        for (const [posicao, pergunta] of this.perguntas.entries()) {
            if (this.escolhas[posicao] === pergunta.correta) {
                continue;
            }

            const sinal = await this.material.sinal(TreinoSinaisService.slugDaPergunta(pergunta.id));
            if (sinal) {
                errados.push(sinal);
            }
        }

        return errados;
    }
}
