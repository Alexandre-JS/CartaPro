import { Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowBackOutline, arrowForwardOutline, documentTextOutline, timeOutline } from 'ionicons/icons';
import { CategoriaCarta, Pergunta } from '../../models/pergunta.model';
import { DetalheResposta, OrigemResposta, ResultadoResumo, SimuladoEmCurso } from '../../models/progresso.model';
import { SimuladoService } from '../../core/simulado.service';
import { ProgressoService } from '../../core/progresso.service';
import { StorageService } from '../../core/storage.service';
import { ContentService } from '../../core/content.service';
import { RegrasService } from '../../core/regras.service';
import { PerguntaCardComponent } from '../../components/pergunta-card/pergunta-card.component';
import { TAMANHO_SIMULADO } from '../../config/simulado.config';
import { ExameApiService } from '../../core/exame-api.service';
import { mensagemErroApi } from '../../core/api-error';

@Component({
    standalone: true,
    selector: 'app-simulado',
    imports: [IonContent, IonIcon, PerguntaCardComponent],
    templateUrl: './simulado.page.html',
    styleUrls: ['./simulado.page.scss'],
})
export class SimuladoPage implements OnInit, OnDestroy {
    perguntas: Pergunta[] = [];
    indice = 0;
    escolhas: Array<number | null> = [];
    respondidas: boolean[] = [];
    acertos = 0;
    segundosDecorridos = 0;
    segundosRestantes = 0;
    duracaoTotalSegundos = 0;
    processandoResposta = false;
    totalQuestoes = TAMANHO_SIMULADO;
    numeroExame = 0;
    notaPassagem = 0;
    retomado = false;
    carregando = true;
    iniciada = false;
    mensagemErro = '';

    private temporizador?: ReturnType<typeof setInterval>;
    private finalizando = false;
    private categoria: CategoriaCarta = 'ligeiro';
    private origem: OrigemResposta = 'simulado';
    /** Instante em que a pergunta atual apareceu — base da telemetria. */
    private perguntaMostradaEm = Date.now();
    /**
     * Instante de arranque da prova.
     * O tempo é medido pelo relógio e não por ticks de setInterval: em Android,
     * com o app em segundo plano os timers são estrangulados e o contador
     * antigo simplesmente parava — bastava minimizar o app para ganhar tempo.
     */
    private iniciadoEm = Date.now();
    private chaveEstado = '';

    constructor(
        private readonly route: ActivatedRoute,
        private readonly router: Router,
        private readonly simulado: SimuladoService,
        private readonly progresso: ProgressoService,
        private readonly storage: StorageService,
        private readonly content: ContentService,
        private readonly regras: RegrasService,
        private readonly examesApi: ExameApiService,
    ) {
        addIcons({ arrowBackOutline, arrowForwardOutline, documentTextOutline, timeOutline });
    }

    async ngOnInit(): Promise<void> {
        const parametros = this.route.snapshot.queryParamMap;
        this.categoria = (parametros.get('categoria') || (await this.storage.obterCategoria()) || 'ligeiro') as CategoriaCarta;
        const adaptativo = parametros.get('modo') === 'adaptativo';
        this.numeroExame = Number(parametros.get('exame')) || 0;
        this.origem = this.numeroExame ? 'exame' : 'simulado';
        const totalPedido = Math.max(1, Math.min(100, Number(parametros.get('total')) || TAMANHO_SIMULADO));
        const duracaoMinutos = Math.max(1, Math.min(180, Number(parametros.get('duracao')) || 0));
        const chaveRetoma = parametros.get('retomar');
        this.chaveEstado = chaveRetoma || (this.numeroExame
            ? `exame:${this.numeroExame}`
            : `simulado:${this.categoria}:${adaptativo ? 'adaptativo' : 'normal'}:${totalPedido}:${duracaoMinutos || 'oficial'}`);

        try {
            await this.regras.carregar();
            const retomavel = await this.storage.obterSimuladoEmCurso(this.chaveEstado);
            if (retomavel) {
                this.numeroExame = retomavel.numeroExame;
                this.origem = this.numeroExame ? 'exame' : 'simulado';
            }
            if (retomavel && (await this.retomar(retomavel))) {
                this.retomado = true;
            } else {
                await this.iniciarNovo(adaptativo, totalPedido, duracaoMinutos);
            }

            this.iniciada = !!this.perguntas.length;
            if (this.iniciada) {
                this.perguntaMostradaEm = Date.now();
                this.atualizarTempo();
                this.arrancarTemporizador();
                await this.guardarEstado();
            }
        } catch (erro) {
            this.mensagemErro = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }

    ngOnDestroy(): void {
        this.pararTemporizador();
        // Guarda o estado ao sair: fechar o app já não perde a prova inteira.
        if (!this.finalizando && this.perguntas.length) {
            void this.guardarEstado();
        }
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

    get acertouAtual(): boolean {
        return this.respondida && this.escolhida === this.perguntaAtual?.correta;
    }

    get progressoPercentagem(): number {
        return this.totalQuestoes ? Math.round(((this.indice + 1) / this.totalQuestoes) * 100) : 0;
    }

    get tempoFormatado(): string {
        return this.formatar(this.segundosRestantes);
    }

    get tempoUrgente(): boolean {
        return this.segundosRestantes <= 5 * 60;
    }

    get tempoCritico(): boolean {
        return this.segundosRestantes <= 60;
    }

    get tempoTotalFormatado(): string {
        return this.formatar(this.duracaoTotalSegundos);
    }

    get ultimaPergunta(): boolean {
        return this.indice === this.perguntas.length - 1;
    }

    get textoBotao(): string {
        return this.ultimaPergunta ? 'Concluir' : 'Próxima';
    }

    /**
     * Registo da resposta.
     *
     * O avanço automático em caso de acerto foi removido: escondia a explicação
     * e o artigo exatamente nas perguntas certas — incluindo os acertos por
     * adivinhação, que são os que mais precisam de explicação. O aluno vê
     * sempre a correção e avança quando quiser.
     */
    async responder(indice: number): Promise<void> {
        if (this.respondida || !this.perguntaAtual || this.processandoResposta) {
            return;
        }

        this.processandoResposta = true;
        const duracaoMs = Date.now() - this.perguntaMostradaEm;

        this.escolhas[this.indice] = indice;
        this.respondidas[this.indice] = true;

        const acertou = await this.progresso.registarResposta(this.perguntaAtual, {
            escolhida: indice,
            duracaoMs,
            origem: this.origem,
        });

        this.acertos += acertou ? 1 : 0;
        this.processandoResposta = false;

        // O estado só é persistido quando o aluno inicia; a tela de preparação
        // não deve criar uma prova retomável nem começar o cronómetro.
    }

    async avancar(): Promise<void> {
        if (!this.perguntaAtual || !this.respondida) {
            return;
        }

        if (this.ultimaPergunta) {
            await this.finalizarExame();
            return;
        }

        this.indice += 1;
        this.perguntaMostradaEm = Date.now();
        await this.guardarEstado();
    }

    async voltar(): Promise<void> {
        if (this.indice === 0) {
            return;
        }
        this.indice -= 1;
        this.perguntaMostradaEm = Date.now();
    }

    sair(): Promise<boolean> {
        return this.router.navigateByUrl('/simular');
    }

    private async iniciarNovo(adaptativo: boolean, totalPedido: number, duracaoMinutos: number): Promise<void> {
        if (this.numeroExame) {
            const exame = await this.examesApi.obter(this.numeroExame);
            this.perguntas = exame.perguntas;
            this.duracaoTotalSegundos = (exame.minutos || this.regras.para(this.categoria).minutos) * 60;
            this.notaPassagem = exame.notaPassagem || this.regras.notaPassagem(exame.perguntas.length, this.categoria);
        } else {
            this.perguntas = await this.simulado.montarSimulado(this.categoria, adaptativo, totalPedido);
            this.duracaoTotalSegundos = duracaoMinutos ? duracaoMinutos * 60 : this.regras.duracaoSegundos(this.categoria);
            this.notaPassagem = this.regras.notaPassagem(this.perguntas.length, this.categoria);
        }

        this.totalQuestoes = this.perguntas.length;
        this.escolhas = this.perguntas.map(() => null);
        this.respondidas = this.perguntas.map(() => false);
        this.acertos = 0;
        this.indice = 0;
        this.iniciadoEm = Date.now();
    }

    /** Reconstrói uma prova interrompida a partir dos ids guardados. */
    private async retomar(estado: SimuladoEmCurso): Promise<boolean> {
        const disponiveis = this.numeroExame
            ? (await this.examesApi.obter(this.numeroExame)).perguntas
            : (await this.content.carregarPacote()).perguntas;
        const porId = new Map(disponiveis.map((pergunta) => [pergunta.id, pergunta]));
        const perguntas = estado.perguntaIds.map((id) => porId.get(id)).filter((pergunta): pergunta is Pergunta => !!pergunta);

        if (perguntas.length !== estado.perguntaIds.length) {
            // Conteúdo mudou desde a interrupção: recomeça limpo.
            await this.storage.limparSimuladoEmCurso(estado.chave);
            return false;
        }

        const decorridos = Math.floor((Date.now() - estado.iniciadoEm) / 1000);
        if (decorridos >= estado.duracaoTotalSegundos) {
            await this.storage.limparSimuladoEmCurso(estado.chave);
            return false;
        }

        this.perguntas = perguntas;
        this.escolhas = estado.escolhas;
        this.respondidas = estado.respondidas;
        this.acertos = estado.acertos;
        this.notaPassagem = estado.notaPassagem;
        this.duracaoTotalSegundos = estado.duracaoTotalSegundos;
        this.totalQuestoes = perguntas.length;
        this.iniciadoEm = estado.iniciadoEm;
        const primeiraPendente = estado.respondidas.findIndex((respondida) => !respondida);
        this.indice = primeiraPendente === -1 ? perguntas.length - 1 : primeiraPendente;

        return true;
    }

    private async guardarEstado(): Promise<void> {
        if (!this.perguntas.length) {
            return;
        }

        await this.storage.guardarSimuladoEmCurso({
            chave: this.chaveEstado,
            perguntaIds: this.perguntas.map((pergunta) => pergunta.id),
            escolhas: this.escolhas,
            respondidas: this.respondidas,
            acertos: this.acertos,
            numeroExame: this.numeroExame,
            notaPassagem: this.notaPassagem,
            duracaoTotalSegundos: this.duracaoTotalSegundos,
            iniciadoEm: this.iniciadoEm,
            guardadoEm: Date.now(),
        });
    }

    private arrancarTemporizador(): void {
        if (!this.perguntas.length) {
            return;
        }

        this.temporizador = setInterval(() => this.atualizarTempo(), 1000);
    }

    /** Recalcula pelo relógio, imune a ticks perdidos em segundo plano. */
    private atualizarTempo(): void {
        this.segundosDecorridos = Math.floor((Date.now() - this.iniciadoEm) / 1000);
        this.segundosRestantes = Math.max(0, this.duracaoTotalSegundos - this.segundosDecorridos);

        if (this.segundosRestantes === 0) {
            void this.finalizarExame();
        }
    }

    private pararTemporizador(): void {
        if (this.temporizador) {
            clearInterval(this.temporizador);
            this.temporizador = undefined;
        }
    }

    private async finalizarExame(): Promise<void> {
        if (this.finalizando || !this.perguntas.length) {
            return;
        }

        this.finalizando = true;
        this.pararTemporizador();

        const resumo: ResultadoResumo = { total: this.perguntas.length, acertos: this.acertos };
        const detalhes: DetalheResposta[] = this.perguntas.map((pergunta, indice) => ({
            perguntaId: pergunta.id,
            escolhida: this.escolhas[indice] ?? null,
        }));

        await this.storage.registarExame({
            numero: this.numeroExame,
            total: resumo.total,
            acertos: resumo.acertos,
            tempoSegundos: this.segundosDecorridos,
            data: Date.now(),
        });

        // Persistido: recarregar a página de resultado já não mostra 0 de 0.
        await this.storage.guardarUltimoResultado({
            resumo,
            tempoSegundos: this.segundosDecorridos,
            notaPassagem: this.notaPassagem,
            detalhes,
            data: Date.now(),
        });

        await this.storage.limparSimuladoEmCurso(this.chaveEstado);
        void this.storage.sincronizarAgora().catch(() => undefined);

        await this.router.navigate(['/simular/resultado']);
    }

    private formatar(segundos: number): string {
        const minutos = Math.floor(segundos / 60).toString().padStart(2, '0');
        return `${minutos}:${(segundos % 60).toString().padStart(2, '0')}`;
    }
}
