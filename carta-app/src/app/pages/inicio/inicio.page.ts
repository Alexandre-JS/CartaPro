import { DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import { bookOutline, bulbOutline, checkmarkCircleOutline, chevronForwardOutline, documentTextOutline, lockClosedOutline, notificationsOutline, refreshOutline, timeOutline, warningOutline } from 'ionicons/icons';
import { AcessoService } from '../../core/acesso.service';
import { ContentService } from '../../core/content.service';
import { ProgressoService } from '../../core/progresso.service';
import { PerfilService } from '../../core/perfil.service';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { TreinoSinaisService } from '../../core/treino-sinais.service';
import { AuthService } from '../../core/auth.service';
import { CategoriaCarta } from '../../models/pergunta.model';
import { HistoricoExame, ProgressoTema } from '../../models/progresso.model';

/** Uma tarefa concreta que o aluno pode abrir agora. */
interface PassoEstudo {
    titulo: string;
    motivo: string;
    icone: string;
    accao: string;
    rota: unknown[];
    query?: Record<string, unknown>;
}

@Component({
    standalone: true,
    selector: 'app-inicio',
    imports: [DatePipe, RouterLink, IonContent, IonIcon, BottomNavComponent, SkeletonComponent],
    templateUrl: './inicio.page.html',
    styleUrls: ['./inicio.page.scss'],
})
export class InicioPage implements OnInit {
    categoria: CategoriaCarta = 'ligeiro';
    temas: ProgressoTema[] = [];
    historico: HistoricoExame[] = [];
    primeiroNome = 'Estudante';
    carregando = true;
    /** Fila de tarefas por ordem de urgência; só a cabeça vai a destaque. */
    passos: PassoEstudo[] = [];
    /** Perguntas por trás do cadeado (0 quando o plano é completo). */
    bloqueadas = 0;
    plano: 'gratis' | 'pago' = 'gratis';
    autenticado = false;

    constructor(
        private readonly content: ContentService,
        private readonly storage: StorageService,
        private readonly progresso: ProgressoService,
        private readonly perfil: PerfilService,
        private readonly temasService: TemasService,
        private readonly regras: RegrasService,
        private readonly acesso: AcessoService,
        private readonly treino: TreinoSinaisService,
        private readonly auth: AuthService,
    ) {
        addIcons({ bookOutline, bulbOutline, checkmarkCircleOutline, chevronForwardOutline, documentTextOutline, lockClosedOutline, notificationsOutline, refreshOutline, timeOutline, warningOutline });
    }

    async ngOnInit(): Promise<void> {
        this.autenticado = !!(await this.auth.token());
        const categoriaGuardada = await this.storage.obterCategoria();
        if (categoriaGuardada) {
            this.categoria = categoriaGuardada as CategoriaCarta;
        }

        // Revalida o plano no máximo uma vez por dia e recarrega o pacote se
        // o acesso mudou (pagou ou expirou).
        await this.acesso.revalidarSeNecessario();
        await Promise.all([this.temasService.carregar(), this.regras.carregar()]);

        const [nomesTemas, historico, revisoes, perfil, bloqueado, sinaisPorReforcar] = await Promise.all([
            this.content.listarTemas(),
            this.storage.listarExames(),
            this.storage.listarRevisoesPendentes(),
            this.perfil.obter(),
            this.acesso.conteudoBloqueado(),
            this.treino.totalParaReforcar(),
        ]);

        this.primeiroNome = perfil.nome.trim().split(/\s+/)[0] || 'Estudante';
        this.bloqueadas = bloqueado.total;
        this.plano = (await this.storage.obterEstadoAcesso()).plano;
        this.historico = historico;
        this.temas = await this.progresso.estatisticasPorTema(nomesTemas);

        await this.montarFila(revisoes.length, revisoes[0]?.tema, sinaisPorReforcar);

        this.carregando = false;
    }

    get passoPrincipal(): PassoEstudo | null {
        return this.passos[0] || null;
    }

    get outrosPassos(): PassoEstudo[] {
        return this.passos.slice(1);
    }

    get prontidao(): number {
        if (!this.autenticado || !this.historico.length) return 0;
        const ultimo = this.historico[0];
        return ultimo.total ? Math.round((ultimo.acertos / ultimo.total) * 100) : 0;
    }

    get tituloProntidao(): string {
        return this.autenticado ? (this.historico.length ? 'Em preparação' : 'A começar') : 'Cria conta para acompanhar';
    }

    /**
     * Ordena as tarefas por urgência real: primeiro o que a memória está prestes
     * a perder (revisões), depois o tema mais fraco, depois os sinais falhados.
     */
    private async montarFila(revisoesPendentes: number, temaEmRevisao: string | undefined, sinaisPorReforcar: number): Promise<void> {
        const fila: PassoEstudo[] = [];

        if (revisoesPendentes) {
            fila.push({
                titulo: `${revisoesPendentes} ${revisoesPendentes === 1 ? 'pergunta a rever' : 'perguntas a rever'}`,
                motivo: 'Revê agora, enquanto a memória ainda está fresca.',
                icone: 'time-outline',
                accao: 'Revisar',
                rota: ['/revisoes'],
            });
        }

        const recomendacao = this.progresso.recomendarEstudo(this.temas, 5, temaEmRevisao);
        // Com revisões pendentes, `recomendarEstudo` devolve o mesmo tema com
        // acção "revisar" — seria a mesma tarefa por outra rota.
        if (recomendacao && !(revisoesPendentes && recomendacao.acao === 'revisar')) {
            fila.push({
                titulo: this.nomeTema(recomendacao.tema),
                motivo: `${recomendacao.motivo} · ${recomendacao.totalPerguntas} perguntas · ${recomendacao.minutosEstimados} min`,
                icone: 'bulb-outline',
                accao: recomendacao.acao === 'reforcar' ? 'Reforçar' : 'Estudar',
                rota: ['/estudo', recomendacao.tema],
                query: { categoria: this.categoria },
            });
        }

        if (sinaisPorReforcar) {
            fila.push({
                titulo: `${sinaisPorReforcar} ${sinaisPorReforcar === 1 ? 'sinal a reforçar' : 'sinais a reforçar'}`,
                motivo: 'Sinais que já falhaste no treino de reconhecimento.',
                icone: 'warning-outline',
                accao: 'Treinar',
                rota: ['/treino-sinais'],
                query: { modo: 'reforco' },
            });
        }

        if (!this.historico.length) {
            fila.push({
                titulo: 'Fazer o primeiro exame',
                motivo: 'Um exame completo mostra onde estás antes do INATRO.',
                icone: 'document-text-outline',
                accao: 'Começar',
                rota: ['/exames'],
            });
        }

        this.passos = fila;
    }

    get ultimoExame(): HistoricoExame | null {
        return this.historico[0] || null;
    }

    percentagemExame(exame: HistoricoExame): number {
        return exame.total ? Math.round((exame.acertos / exame.total) * 100) : 0;
    }

    exameAprovado(exame: HistoricoExame): boolean {
        return this.regras.aprovado(exame.acertos, exame.total, this.categoria);
    }

    numeroExame(numero: number): string {
        return numero.toString().padStart(2, '0');
    }

    /** Nome vindo do painel — já não há mapa de temas hardcoded aqui. */
    nomeTema(tema: string): string {
        return this.temasService.nome(tema);
    }
}
