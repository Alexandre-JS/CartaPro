import { DecimalPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowBackOutline, arrowForwardOutline, checkmarkCircleOutline, readerOutline, refreshOutline, warningOutline } from 'ionicons/icons';
import { PerguntaCardComponent } from '../../components/pergunta-card/pergunta-card.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { mensagemErroApi } from '../../core/api-error';
import { ProgressoService } from '../../core/progresso.service';
import { SimuladoService } from '../../core/simulado.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { CategoriaCarta, Pergunta } from '../../models/pergunta.model';
import { ProgressoTema } from '../../models/progresso.model';

type ModoPratica = 'tema' | 'erros' | 'novas' | 'rapida';

@Component({
    standalone: true,
    selector: 'app-estudo-tema',
    imports: [DecimalPipe, RouterLink, IonContent, IonIcon, PerguntaCardComponent, SkeletonComponent],
    templateUrl: './estudo-tema.page.html',
    styleUrls: ['./estudo-tema.page.scss'],
})
export class EstudoTemaPage implements OnInit {
    tema = '';
    modo: ModoPratica = 'tema';
    perguntas: Pergunta[] = [];
    progressoTema: ProgressoTema | null = null;
    indice = 0;
    escolhida: number | null = null;
    respondida = false;
    iniciada = false;
    concluida = false;
    acertosSessao = 0;
    carregando = true;
    erroCarregamento = '';
    tamanhoSessao = 5;

    private bancoPerguntas: Pergunta[] = [];
    private categoria: CategoriaCarta = 'ligeiro';
    private mostradaEm = Date.now();

    constructor(
        private readonly route: ActivatedRoute,
        private readonly simulado: SimuladoService,
        private readonly progresso: ProgressoService,
        private readonly storage: StorageService,
        private readonly temasService: TemasService,
    ) {
        addIcons({ arrowBackOutline, arrowForwardOutline, checkmarkCircleOutline, readerOutline, refreshOutline, warningOutline });
    }

    async ngOnInit(): Promise<void> {
        this.modo = (this.route.snapshot.data['modoPratica'] || 'tema') as ModoPratica;
        this.tema = this.route.snapshot.paramMap.get('slug') || this.route.snapshot.paramMap.get('tema') || '';
        this.categoria = (this.route.snapshot.queryParamMap.get('categoria') || (await this.storage.obterCategoria()) || 'ligeiro') as CategoriaCarta;
        const totalPedido = Number(this.route.snapshot.queryParamMap.get('total'));
        this.tamanhoSessao = totalPedido === 10 ? 10 : 5;
        await this.carregar();
    }

    async carregar(): Promise<void> {
        this.carregando = true;
        this.erroCarregamento = '';
        this.reiniciarEstado();

        try {
            const [, perguntas] = await Promise.all([
                this.temasService.carregar(),
                this.selecionarPerguntas(),
            ]);
            this.bancoPerguntas = perguntas;
            this.configurarTamanho(this.tamanhoSessao);
            if (this.tema) await this.atualizarProgresso();
        } catch (erro) {
            this.erroCarregamento = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }

    get perguntaAtual(): Pergunta | null { return this.perguntas[this.indice] || null; }

    get nomeSessao(): string {
        if (this.modo === 'erros') return 'Meus erros';
        if (this.modo === 'novas') return 'Nunca respondidas';
        if (this.modo === 'rapida') return 'Sessão rápida';
        return this.temasService.nome(this.tema);
    }

    get etiquetaSessao(): string {
        if (this.modo === 'erros') return 'Reforço dirigido';
        if (this.modo === 'novas') return 'Primeiro contacto';
        if (this.modo === 'rapida') return 'Prática curta';
        return 'Prática por tema';
    }

    get descricaoSessao(): string {
        if (this.modo === 'erros') return 'Repita perguntas que falhou anteriormente e leia o feedback antes de continuar.';
        if (this.modo === 'novas') return 'Trabalhe apenas perguntas que ainda não aparecem no histórico deste dispositivo.';
        if (this.modo === 'rapida') return 'Uma sessão curta, misturada por temas, para manter o ritmo diário.';
        return 'Pratique este tema sem consultar o material e leia a explicação depois de cada resposta.';
    }

    get textoVazio(): string {
        if (this.modo === 'erros') return 'Não existem perguntas erradas no histórico local.';
        if (this.modo === 'novas') return 'Já respondeu a todas as perguntas disponíveis nesta categoria.';
        return 'Não existem perguntas disponíveis para esta sessão.';
    }

    get totalDisponivel(): number { return this.bancoPerguntas.length; }
    get percentagemSessao(): number { return this.perguntas.length ? Math.round((this.acertosSessao / this.perguntas.length) * 100) : 0; }

    configurarTamanho(total: number): void {
        if (this.iniciada) return;
        this.tamanhoSessao = total;
        this.perguntas = this.bancoPerguntas.slice(0, total);
        this.indice = 0;
        this.escolhida = null;
        this.respondida = false;
    }

    iniciarSessao(): void {
        this.iniciada = true;
        this.mostradaEm = Date.now();
    }

    async confirmarOuAvancar(): Promise<void> {
        if (!this.perguntaAtual || this.escolhida === null) return;

        if (!this.respondida) {
            const acertou = await this.progresso.registarResposta(this.perguntaAtual, {
                escolhida: this.escolhida,
                duracaoMs: Date.now() - this.mostradaEm,
                origem: 'estudo',
            });
            if (acertou) this.acertosSessao++;
            if (this.tema) await this.atualizarProgresso();
            this.respondida = true;
            return;
        }

        if (this.indice === this.perguntas.length - 1) {
            this.concluida = true;
            void this.storage.sincronizarAgora().catch(() => undefined);
            return;
        }

        this.indice++;
        this.escolhida = null;
        this.respondida = false;
        this.mostradaEm = Date.now();
    }

    private async selecionarPerguntas(): Promise<Pergunta[]> {
        const limite = Number.MAX_SAFE_INTEGER;
        if (this.modo === 'erros') return this.simulado.perguntasErradas(this.categoria, limite);
        if (this.modo === 'novas') return this.simulado.perguntasNuncaRespondidas(this.categoria, limite);
        if (this.modo === 'rapida') return this.simulado.montarSimulado(this.categoria, false, limite);
        return this.simulado.perguntasPorTema(this.tema, this.categoria, limite);
    }

    private reiniciarEstado(): void {
        this.perguntas = [];
        this.bancoPerguntas = [];
        this.indice = 0;
        this.escolhida = null;
        this.respondida = false;
        this.iniciada = false;
        this.concluida = false;
        this.acertosSessao = 0;
    }

    private async atualizarProgresso(): Promise<void> {
        this.progressoTema = (await this.progresso.estatisticasPorTema([this.tema]))[0];
    }
}
