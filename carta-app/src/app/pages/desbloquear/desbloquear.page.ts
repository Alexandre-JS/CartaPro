import { CurrencyPipe, SlicePipe } from '@angular/common';
import { Component, inject, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon, IonInput, IonItem } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    alertCircleOutline, arrowBackOutline, checkmarkCircle, chevronForwardOutline, createOutline,
    lockOpenOutline, openOutline, phonePortraitOutline, refreshOutline, ribbonOutline, shieldCheckmarkOutline,
} from 'ionicons/icons';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { AcessoService } from '../../core/acesso.service';
import { ContentService } from '../../core/content.service';
import { DesbloqueioService } from '../../core/desbloqueio.service';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { mensagemDeErro } from '../../core/erros-api';
import { CatalogoPlanos, MetodoPagamento, Pagamento, PagamentoService, PlanoVenda } from '../../core/pagamento.service';
import { EstadoAcesso } from '../../models/progresso.model';

/**
 * `pagar` → escolhe carteira, confirma o número e decide;
 * `aguardar` → pedido de confirmação no telemóvel;
 * `codigo` → OTP, só para pagamentos registados à mão pelo apoio;
 * `ativo` → plano completo em vigor.
 */
type Etapa = 'pagar' | 'aguardar' | 'codigo' | 'ativo';

/**
 * Ativação do plano completo.
 *
 * Este ecrã pedia ao aluno que pagasse "para o número indicado pela CartaPro"
 * — sem mostrar número nem preço, e sem forma de comunicar que tinha pago.
 *
 * O número da carteira é confirmado antes de cobrar e pode ser corrigido:
 * muita gente tem a conta CartaPro num número e o dinheiro noutro, e cobrar
 * sempre o número da conta deixava essas pessoas sem forma de pagar.
 */
@Component({
    standalone: true,
    selector: 'app-desbloquear',
    imports: [CurrencyPipe, SlicePipe, FormsModule, RouterLink, IonContent, IonIcon, IonInput, IonItem, SkeletonComponent],
    templateUrl: './desbloquear.page.html',
    styleUrls: ['./desbloquear.page.scss'],
})
export class DesbloquearPage implements OnInit {
    private readonly pagamentos = inject(PagamentoService);
    private readonly desbloqueio = inject(DesbloqueioService);
    private readonly acesso = inject(AcessoService);
    private readonly content = inject(ContentService);
    private readonly storage = inject(StorageService);
    private readonly regras = inject(RegrasService);

    etapa: Etapa = 'pagar';
    carregando = true;
    ocupado = false;
    catalogo: CatalogoPlanos | null = null;
    plano: PlanoVenda | null = null;
    metodo: MetodoPagamento | null = null;
    estado: EstadoAcesso = { plano: 'gratis' };
    perguntasBloqueadas = 0;

    /* Prova construída com os números reais do aluno. Inventar "95% dos nossos
       alunos aprovam" seria mentir; o que ele próprio já fez convence mais. */
    respondidas = 0;
    taxaAcerto = 0;
    exigidoNoExame = 0;

    /** Número da carteira. Arranca no da conta e é editável. */
    carteira = '';
    editarNumero = false;

    codigo = '';
    mensagemErro = '';
    mensagemInfo = '';
    /** Sobe enquanto se aguarda a confirmação, para o ecrã não parecer parado. */
    segundosAEsperar = 0;
    /** Página do agregador, quando o método é e-Mola. */
    checkoutUrl = '';

    constructor() {
        addIcons({
            alertCircleOutline, arrowBackOutline, checkmarkCircle, chevronForwardOutline, createOutline,
            lockOpenOutline, openOutline, phonePortraitOutline, refreshOutline, ribbonOutline, shieldCheckmarkOutline,
        });
    }

    get moeda(): string {
        return this.catalogo?.moeda || 'MZN';
    }

    /** Custo diário: 500 MZN num ano lê-se melhor como "menos de 2 MZN por dia". */
    get porDia(): number {
        const dias = this.plano?.dias ?? 0;

        return dias > 0 ? Math.ceil(((this.plano?.preco ?? 0) / dias) * 100) / 100 : 0;
    }

    /**
     * Só se mostra a prova pessoal com respostas suficientes para significar
     * algo: 3 perguntas certas não são "100% de acerto".
     */
    get temProvaPessoal(): boolean {
        return this.respondidas >= 10 && this.exigidoNoExame > 0;
    }

    get faltamPontos(): number {
        return Math.max(0, this.exigidoNoExame - this.taxaAcerto);
    }

    get jaChegaria(): boolean {
        return this.temProvaPessoal && this.faltamPontos === 0;
    }

    get metodos(): MetodoPagamento[] {
        return this.catalogo?.metodos ?? [];
    }

    /** Carteira que o prefixo do número indica — `null` se for desconhecido. */
    get metodoDoNumero(): MetodoPagamento | null {
        return this.pagamentos.metodoParaNumero(this.carteira, this.metodos);
    }

    /**
     * O número escrito não serve a carteira escolhida.
     *
     * É um aviso, não um bloqueio do lado do app: o servidor decide, e um
     * prefixo que a nossa lista não conheça não pode impedir um pagamento.
     */
    get numeroNaoBate(): boolean {
        const detetado = this.metodoDoNumero;

        return !!detetado && !!this.metodo && detetado.chave !== this.metodo.chave;
    }

    get numeroValido(): boolean {
        return this.carteira.replace(/\D+/g, '').replace(/^258/, '').length === 9;
    }

    async ngOnInit(): Promise<void> {
        try {
            await this.regras.carregar();

            const [catalogo, bloqueado, respostas] = await Promise.all([
                this.pagamentos.catalogo(),
                this.acesso.conteudoBloqueado(),
                this.storage.listarRespostas(),
            ]);

            this.respondidas = respostas.length;
            const certas = respostas.filter((resposta) => resposta.acertou).length;
            this.taxaAcerto = respostas.length ? Math.round((certas / respostas.length) * 100) : 0;
            this.exigidoNoExame = Math.round(this.regras.percentagemPassagem());

            this.catalogo = catalogo;
            this.plano = catalogo.planos[0] ?? null;
            this.estado = catalogo.acesso;
            this.perguntasBloqueadas = bloqueado.total;
            this.carteira = catalogo.telefoneSugerido || '';

            // O número da conta já diz qual é a carteira provável.
            this.metodo = this.metodos.find((m) => m.chave === catalogo.metodoSugerido) ?? this.metodos[0] ?? null;

            if (this.estado.plano === 'pago') {
                this.etapa = 'ativo';
            } else if (this.estado.pagamentoPorReclamar) {
                // Pagamento registado pelo apoio: aqui o OTP ainda é preciso.
                this.etapa = 'codigo';
                this.mensagemInfo = 'Encontrámos uma ativação para o teu número. Pede o código para a concluir.';
            }
        } catch (erro: any) {
            this.mensagemErro = this.erro(erro);
        } finally {
            this.carregando = false;
        }
    }

    /** Trocar de carteira alinha o número, se ele ainda for o sugerido. */
    escolherMetodo(metodo: MetodoPagamento): void {
        this.metodo = metodo;
        this.mensagemErro = '';
    }

    async pagar(): Promise<void> {
        if (!this.plano || !this.metodo || this.ocupado || !this.numeroValido) {
            return;
        }

        this.ocupado = true;
        this.mensagemErro = '';
        this.mensagemInfo = '';
        this.checkoutUrl = '';
        this.segundosAEsperar = 0;

        try {
            let pagamento = await this.pagamentos.iniciar(this.plano.chave, this.metodo.chave, this.carteira);

            if (pagamento.estado === 'pendente') {
                this.etapa = 'aguardar';
                // A e-Mola conclui-se numa página do agregador; o M-Pesa não.
                this.checkoutUrl = pagamento.checkoutUrl || '';
                pagamento = await this.pagamentos.aguardar(pagamento, (tentativa) => {
                    this.segundosAEsperar = tentativa * 3;
                });
            }

            await this.concluir(pagamento);
        } catch (erro: any) {
            this.etapa = 'pagar';
            this.mensagemErro = this.erro(erro);
        } finally {
            this.ocupado = false;
        }
    }

    abrirCheckout(): void {
        if (this.checkoutUrl) {
            window.open(this.checkoutUrl, '_blank', 'noopener');
        }
    }

    private async concluir(pagamento: Pagamento): Promise<void> {
        this.estado = pagamento.acesso ?? this.estado;

        if (pagamento.estado === 'pago') {
            // Novo plano = novo pacote: é o servidor que decide o que enviar.
            await this.content.atualizarPacote();
            this.etapa = 'ativo';
            this.mensagemInfo = 'Tudo desbloqueado. Bons estudos!';
            return;
        }

        this.etapa = 'pagar';
        // Um pendente que esgotou o tempo não é uma falha: o valor pode ter
        // sido debitado e a confirmação chegar depois.
        this.mensagemErro = pagamento.estado === 'pendente'
            ? 'Ainda não recebemos a confirmação. Se o valor já saiu, o acesso abre sozinho — volta aqui dentro de alguns minutos.'
            : pagamento.mensagem || 'Não conseguimos concluir. Tenta de novo — o teu acesso continua à espera.';
    }

    // ---- Via manual: pagamentos registados pelo apoio ao cliente ----

    async pedirCodigo(): Promise<void> {
        this.ocupado = true;
        this.mensagemErro = '';
        this.mensagemInfo = '';

        try {
            const resposta = await this.desbloqueio.pedirCodigo();

            if (resposta.estado === 'ja_ativo') {
                await this.concluir({ estado: 'pago', acesso: await this.desbloqueio.obterEstado() } as Pagamento);
                return;
            }

            this.etapa = 'codigo';
            this.mensagemInfo = `Enviámos um código para ${resposta.telefone}. É válido ${resposta.expiraEmMinutos ?? 10} minutos.`;
        } catch (erro: any) {
            this.mensagemErro = this.erro(erro);
        } finally {
            this.ocupado = false;
        }
    }

    async confirmarCodigo(): Promise<void> {
        this.ocupado = true;
        this.mensagemErro = '';

        try {
            const estado = await this.desbloqueio.confirmarCodigo(this.codigo);
            await this.concluir({ estado: 'pago', acesso: estado } as Pagamento);
        } catch (erro: any) {
            this.mensagemErro = this.erro(erro);
        } finally {
            this.ocupado = false;
        }
    }

    private erro(erro: any): string {
        return mensagemDeErro(erro, 'Não foi possível concluir a operação. Tenta novamente.');
    }
}
