import { DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowForwardOutline, bookOutline, bulbOutline, chevronForwardOutline, createOutline, documentTextOutline, home, lockClosedOutline, notificationsOutline, personOutline, statsChartOutline, timeOutline } from 'ionicons/icons';
import { AcessoService } from '../../core/acesso.service';
import { ContentService } from '../../core/content.service';
import { ProgressoService } from '../../core/progresso.service';
import { PerfilService } from '../../core/perfil.service';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { CategoriaCarta } from '../../models/pergunta.model';
import { HistoricoExame, ProgressoTema, RecomendacaoEstudo } from '../../models/progresso.model';

@Component({
    standalone: true,
    selector: 'app-inicio',
    imports: [DatePipe, RouterLink, IonContent, IonIcon],
    templateUrl: './inicio.page.html',
    styleUrls: ['./inicio.page.scss'],
})
export class InicioPage implements OnInit {
    categoria: CategoriaCarta = 'ligeiro';
    temas: ProgressoTema[] = [];
    historico: HistoricoExame[] = [];
    totalRespondidas = 0;
    taxaAcerto = 0;
    recomendacao: RecomendacaoEstudo | null = null;
    primeiroNome = 'Estudante';
    revisoesPendentes = 0;
    /** Perguntas por trás do cadeado (0 quando o plano é completo). */
    bloqueadas = 0;
    plano: 'gratis' | 'pago' = 'gratis';

    constructor(
        private readonly content: ContentService,
        private readonly storage: StorageService,
        private readonly progresso: ProgressoService,
        private readonly perfil: PerfilService,
        private readonly temasService: TemasService,
        private readonly regras: RegrasService,
        private readonly acesso: AcessoService,
    ) {
        addIcons({ arrowForwardOutline, bookOutline, bulbOutline, chevronForwardOutline, createOutline, documentTextOutline, home, lockClosedOutline, notificationsOutline, personOutline, statsChartOutline, timeOutline });
    }

    async ngOnInit(): Promise<void> {
        const categoriaGuardada = await this.storage.obterCategoria();
        if (categoriaGuardada) {
            this.categoria = categoriaGuardada as CategoriaCarta;
        }

        // Revalida o plano no máximo uma vez por dia e recarrega o pacote se
        // o acesso mudou (pagou ou expirou).
        await this.acesso.revalidarSeNecessario();
        await Promise.all([this.temasService.carregar(), this.regras.carregar()]);

        const [nomesTemas, respostas, historico, revisoes, perfil, bloqueado] = await Promise.all([
            this.content.listarTemas(),
            this.storage.listarRespostas(),
            this.storage.listarExames(),
            this.storage.listarRevisoesPendentes(),
            this.perfil.obter(),
            this.acesso.conteudoBloqueado(),
        ]);

        this.primeiroNome = perfil.nome.trim().split(/\s+/)[0] || 'Estudante';
        this.revisoesPendentes = revisoes.length;
        this.bloqueadas = bloqueado.total;
        this.plano = (await this.storage.obterEstadoAcesso()).plano;

        this.temas = await this.progresso.estatisticasPorTema(nomesTemas);
        this.recomendacao = this.progresso.recomendarEstudo(this.temas, 5, revisoes[0]?.tema);

        if (this.recomendacao) {
            const perguntas = await this.content.listarPerguntas({
                tema: this.recomendacao.tema,
                categoria: this.categoria,
            });
            this.recomendacao = { ...this.recomendacao, totalPerguntas: Math.min(5, perguntas.length) };
        }

        this.historico = historico;
        this.totalRespondidas = respostas.length;
        const acertos = respostas.filter((resposta) => resposta.acertou).length;
        this.taxaAcerto = respostas.length ? Math.round((acertos / respostas.length) * 100) : 0;
    }

    get examesRealizados(): number {
        return this.historico.length;
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
