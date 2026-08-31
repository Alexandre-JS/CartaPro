import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, inject, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    arrowForwardOutline, bookOutline, calendarOutline, chevronForwardOutline,
    documentTextOutline, refreshOutline, statsChartOutline, timeOutline,
    trendingDownOutline, trendingUpOutline, warningOutline,
} from 'ionicons/icons';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { mensagemErroApi } from '../../core/api-error';
import { AuthService } from '../../core/auth.service';
import { ContentService } from '../../core/content.service';
import { ProgressoService } from '../../core/progresso.service';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { CategoriaCarta } from '../../models/pergunta.model';
import { HistoricoExame, ProgressoTema, RespostaRegisto, RevisaoAgendada } from '../../models/progresso.model';

type FiltroHistorico = 'todos' | 'simulados' | 'pratica' | 'revisoes';
type TipoAtividade = Exclude<FiltroHistorico, 'todos'>;

interface AtividadeLocal {
    id: string;
    tipo: TipoAtividade;
    titulo: string;
    data: number;
    total: number;
    acertos: number;
    tempoSegundos?: number;
}

interface PontoEvolucao {
    id: string;
    rotulo: string;
    valor: number;
}

interface RecomendacaoApresentada {
    titulo: string;
    descricao: string;
    acao: string;
    rota: unknown[];
    query?: Record<string, unknown>;
}

@Component({
    standalone: true,
    selector: 'app-progresso',
    imports: [DatePipe, DecimalPipe, RouterLink, IonContent, IonIcon, BottomNavComponent, SkeletonComponent, AppHeaderComponent],
    templateUrl: './desempenho.page.html',
    styleUrls: ['./desempenho.page.scss'],
})
export class DesempenhoPage implements OnInit {
    private readonly storage = inject(StorageService);
    private readonly content = inject(ContentService);
    private readonly temasService = inject(TemasService);
    readonly regras = inject(RegrasService);
    private readonly progresso = inject(ProgressoService);
    private readonly auth = inject(AuthService);

    historico: HistoricoExame[] = [];
    atividades: AtividadeLocal[] = [];
    temas: ProgressoTema[] = [];
    pontosEvolucao: PontoEvolucao[] = [];
    recomendacao?: RecomendacaoApresentada;
    totalPerguntasBanco = 0;
    perguntasPraticadas = 0;
    revisoesPendentes = 0;
    cobertura = 0;
    prontidao = 0;
    evolucao = 0;
    fonteProntidao = 'Sem dados suficientes';
    categoria: CategoriaCarta = 'ligeiro';
    filtroHistorico: FiltroHistorico = 'todos';
    autenticado = false;
    carregando = true;
    erroCarregamento = '';
    private inicializado = false;

    constructor() {
        addIcons({
            arrowForwardOutline, bookOutline, calendarOutline, chevronForwardOutline,
            documentTextOutline, refreshOutline, statsChartOutline, timeOutline,
            trendingDownOutline, trendingUpOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        await this.carregar();
        this.inicializado = true;
    }

    ionViewWillEnter(): void {
        if (this.inicializado) void this.carregar();
    }

    async carregar(): Promise<void> {
        this.carregando = true;
        this.erroCarregamento = '';

        try {
            this.autenticado = !!(await this.auth.token());
            const [pacote, , , historico, respostas, revisoes, categoriaGuardada] = await Promise.all([
                this.content.carregarPacote(),
                this.temasService.carregar(),
                this.regras.carregar(),
                this.storage.listarExames(),
                this.storage.listarRespostas(),
                this.storage.listarRevisoes(),
                this.storage.obterCategoria(),
            ]);

            this.categoria = (categoriaGuardada || 'ligeiro') as CategoriaCarta;
            const perguntas = pacote.perguntas.filter((pergunta) => pergunta.categoriaCarta.includes(this.categoria));
            const idsDisponiveis = new Set(perguntas.map((pergunta) => pergunta.id));
            const respostasAtuais = respostas.filter((resposta) => idsDisponiveis.has(resposta.perguntaId));
            const idsPraticados = new Set(respostasAtuais.map((resposta) => resposta.perguntaId));
            const agora = Date.now();

            this.historico = historico;
            this.totalPerguntasBanco = perguntas.length;
            this.perguntasPraticadas = idsPraticados.size;
            this.cobertura = perguntas.length ? Math.round((idsPraticados.size / perguntas.length) * 100) : 0;
            this.revisoesPendentes = revisoes.filter((revisao) => idsDisponiveis.has(revisao.perguntaId) && revisao.agendadaPara <= agora).length;
            this.temas = await this.progresso.estatisticasPorTema(pacote.temas);
            this.atividades = this.montarHistorico(historico, respostasAtuais);
            this.calcularProntidao(historico, respostasAtuais);
            this.pontosEvolucao = this.montarEvolucao(historico, respostasAtuais);
            this.recomendacao = this.montarRecomendacao(revisoes, idsDisponiveis);
        } catch (erro) {
            this.erroCarregamento = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }

    get temasComDados(): ProgressoTema[] {
        return this.temas.filter((tema) => tema.respondidas > 0).sort((a, b) => a.taxaRecente - b.taxaRecente);
    }

    get temasAReforcar(): ProgressoTema[] {
        return this.temas.filter((tema) => tema.estado === 'fraco').sort((a, b) => a.taxaRecente - b.taxaRecente);
    }

    get atividadesFiltradas(): AtividadeLocal[] {
        return this.filtroHistorico === 'todos'
            ? this.atividades
            : this.atividades.filter((atividade) => atividade.tipo === this.filtroHistorico);
    }

    get prontidaoEstado(): string {
        const minimo = this.regras.percentagemPassagem(this.categoria);
        if (!this.atividades.length) return 'Comece a praticar';
        if (this.prontidao >= minimo) return 'No nível de aprovação';
        if (this.prontidao >= minimo - 10) return 'Perto do objetivo';
        return 'Ainda em preparação';
    }

    nomeTema(tema: string): string {
        return this.temasService.nome(tema);
    }

    percentagemAtividade(atividade: AtividadeLocal): number {
        return atividade.total ? Math.round((atividade.acertos / atividade.total) * 100) : 0;
    }

    formatarTempo(segundos?: number): string {
        if (segundos === undefined) return '';
        return `${Math.floor(segundos / 60).toString().padStart(2, '0')}:${(segundos % 60).toString().padStart(2, '0')}`;
    }

    rotuloTipo(tipo: TipoAtividade): string {
        if (tipo === 'simulados') return 'Simulado';
        if (tipo === 'revisoes') return 'Revisão';
        return 'Prática';
    }

    private calcularProntidao(historico: HistoricoExame[], respostas: RespostaRegisto[]): void {
        if (historico[0]) {
            this.prontidao = this.percentagemExame(historico[0]);
            this.fonteProntidao = 'Baseada no último simulado';
            this.evolucao = historico[1] ? this.prontidao - this.percentagemExame(historico[1]) : 0;
            return;
        }

        const recentes = [...respostas].sort((a, b) => b.data - a.data).slice(0, 20);
        this.prontidao = recentes.length ? Math.round((recentes.filter((resposta) => resposta.acertou).length / recentes.length) * 100) : 0;
        this.fonteProntidao = recentes.length ? `Baseada nas últimas ${recentes.length} respostas` : 'Sem atividade local';
        const anteriores = [...respostas].sort((a, b) => b.data - a.data).slice(20, 40);
        if (recentes.length && anteriores.length) {
            const taxaAnterior = Math.round((anteriores.filter((resposta) => resposta.acertou).length / anteriores.length) * 100);
            this.evolucao = this.prontidao - taxaAnterior;
        }
    }

    private montarEvolucao(historico: HistoricoExame[], respostas: RespostaRegisto[]): PontoEvolucao[] {
        if (historico.length) {
            return [...historico].slice(0, 6).reverse().map((exame) => ({
                id: `exame-${exame.id || exame.data}`,
                rotulo: new Date(exame.data).toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' }),
                valor: this.percentagemExame(exame),
            }));
        }

        const porDia = new Map<string, RespostaRegisto[]>();
        for (const resposta of respostas) {
            const dia = new Date(resposta.data).toISOString().slice(0, 10);
            porDia.set(dia, [...(porDia.get(dia) || []), resposta]);
        }
        return [...porDia.entries()].slice(-6).map(([dia, registos]) => ({
            id: dia,
            rotulo: new Date(`${dia}T12:00:00`).toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit' }),
            valor: Math.round((registos.filter((resposta) => resposta.acertou).length / registos.length) * 100),
        }));
    }

    private montarHistorico(historico: HistoricoExame[], respostas: RespostaRegisto[]): AtividadeLocal[] {
        const simulados: AtividadeLocal[] = historico.map((exame) => ({
            id: `simulado-${exame.id || exame.data}`,
            tipo: 'simulados',
            titulo: exame.numero ? `Exame ${exame.numero.toString().padStart(2, '0')}` : 'Simulado individual',
            data: exame.data,
            total: exame.total,
            acertos: exame.acertos,
            tempoSegundos: exame.tempoSegundos,
        }));

        const praticas = respostas
            .filter((resposta) => resposta.origem === 'estudo' || resposta.origem === 'revisao')
            .sort((a, b) => a.data - b.data);
        const sessoes: Array<{ tipo: 'pratica' | 'revisoes'; inicio: number; fim: number; respostas: RespostaRegisto[] }> = [];

        for (const resposta of praticas) {
            const tipo = resposta.origem === 'revisao' ? 'revisoes' : 'pratica';
            const atual = sessoes[sessoes.length - 1];
            if (!atual || atual.tipo !== tipo || resposta.data - atual.fim > 30 * 60 * 1000) {
                sessoes.push({ tipo, inicio: resposta.data, fim: resposta.data, respostas: [resposta] });
            } else {
                atual.fim = resposta.data;
                atual.respostas.push(resposta);
            }
        }

        return [...simulados, ...sessoes.map((sessao, indice): AtividadeLocal => ({
            id: `${sessao.tipo}-${sessao.inicio}-${indice}`,
            tipo: sessao.tipo,
            titulo: sessao.tipo === 'revisoes' ? 'Sessão de revisões' : `Prática · ${this.nomeTema(sessao.respostas[0].tema)}`,
            data: sessao.inicio,
            total: sessao.respostas.length,
            acertos: sessao.respostas.filter((resposta) => resposta.acertou).length,
            tempoSegundos: Math.max(0, Math.round((sessao.fim - sessao.inicio) / 1000)),
        }))].sort((a, b) => b.data - a.data);
    }

    private montarRecomendacao(revisoes: RevisaoAgendada[], idsDisponiveis: Set<string>): RecomendacaoApresentada | undefined {
        const primeiraRevisao = revisoes
            .filter((revisao) => idsDisponiveis.has(revisao.perguntaId) && revisao.agendadaPara <= Date.now())
            .sort((a, b) => a.agendadaPara - b.agendadaPara)[0];
        const recomendacao = this.progresso.recomendarEstudo(this.temas, 5, primeiraRevisao?.tema);
        if (!recomendacao) return undefined;
        if (recomendacao.acao === 'revisar') {
            return { titulo: 'Revisões pendentes', descricao: recomendacao.motivo, acao: 'Rever agora', rota: ['/praticar/revisoes'] };
        }
        return {
            titulo: this.nomeTema(recomendacao.tema), descricao: recomendacao.motivo,
            acao: recomendacao.acao === 'comecar' ? 'Começar' : 'Praticar tema',
            rota: ['/praticar/tema', recomendacao.tema], query: { categoria: this.categoria },
        };
    }

    private percentagemExame(exame: HistoricoExame): number {
        return exame.total ? Math.round((exame.acertos / exame.total) * 100) : 0;
    }
}
