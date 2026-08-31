import { Component, inject, OnInit } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    alertCircleOutline, arrowBackOutline, arrowForwardOutline, checkmarkCircleOutline,
    closeCircleOutline, refreshOutline, timeOutline, warningOutline,
} from 'ionicons/icons';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { mensagemErroApi } from '../../core/api-error';
import { ContentService } from '../../core/content.service';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { CategoriaCarta, Pergunta } from '../../models/pergunta.model';
import { RespostaRegisto } from '../../models/progresso.model';

interface ErroFrequente {
    pergunta: Pergunta;
    vezes: number;
}

@Component({
    standalone: true,
    selector: 'app-progresso-tema',
    imports: [RouterLink, IonContent, IonIcon, BottomNavComponent],
    templateUrl: './progresso-tema.page.html',
    styleUrls: ['./progresso-tema.page.scss'],
})
export class ProgressoTemaPage implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly content = inject(ContentService);
    private readonly storage = inject(StorageService);
    private readonly temas = inject(TemasService);
    readonly regras = inject(RegrasService);

    slug = '';
    nome = '';
    descricao: string | null = null;
    categoria: CategoriaCarta = 'ligeiro';
    respostas: RespostaRegisto[] = [];
    errosFrequentes: ErroFrequente[] = [];
    totalPerguntas = 0;
    perguntasPraticadas = 0;
    totalPraticado = 0;
    taxaRecente = 0;
    taxaGlobal = 0;
    evolucao = 0;
    revisoesTotal = 0;
    revisoesPendentes = 0;
    carregando = true;
    erroCarregamento = '';

    constructor() {
        addIcons({
            alertCircleOutline, arrowBackOutline, arrowForwardOutline, checkmarkCircleOutline,
            closeCircleOutline, refreshOutline, timeOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        this.slug = this.route.snapshot.paramMap.get('slug') || '';
        await this.carregar();
    }

    async carregar(): Promise<void> {
        this.carregando = true;
        this.erroCarregamento = '';

        try {
            const [pacote, , , categoriaGuardada, todasRespostas, revisoes] = await Promise.all([
                this.content.carregarPacote(),
                this.temas.carregar(),
                this.regras.carregar(),
                this.storage.obterCategoria(),
                this.storage.listarRespostas(),
                this.storage.listarRevisoes(),
            ]);
            const detalhe = pacote.temasDetalhe?.find((tema) => tema.slug === this.slug);
            if (!pacote.temas.includes(this.slug) && !detalhe) throw new Error('Tema não encontrado no conteúdo atual.');

            this.nome = detalhe?.nome || this.temas.nome(this.slug);
            this.descricao = detalhe?.descricao || this.temas.descricao(this.slug);
            this.categoria = (categoriaGuardada || 'ligeiro') as CategoriaCarta;
            const perguntas = pacote.perguntas.filter((pergunta) => pergunta.tema === this.slug && pergunta.categoriaCarta.includes(this.categoria));
            const porId = new Map(perguntas.map((pergunta) => [pergunta.id, pergunta]));
            this.respostas = todasRespostas.filter((resposta) => resposta.tema === this.slug && porId.has(resposta.perguntaId)).sort((a, b) => a.data - b.data);
            const recentes = this.respostas.slice(-10);
            const anteriores = this.respostas.slice(-20, -10);

            this.totalPerguntas = perguntas.length;
            this.perguntasPraticadas = new Set(this.respostas.map((resposta) => resposta.perguntaId)).size;
            this.totalPraticado = this.respostas.length;
            this.taxaGlobal = this.percentagem(this.respostas);
            this.taxaRecente = this.percentagem(recentes);
            this.evolucao = anteriores.length ? this.taxaRecente - this.percentagem(anteriores) : 0;
            const revisoesDoTema = revisoes.filter((revisao) => revisao.tema === this.slug && porId.has(revisao.perguntaId));
            this.revisoesTotal = revisoesDoTema.length;
            this.revisoesPendentes = revisoesDoTema.filter((revisao) => revisao.agendadaPara <= Date.now()).length;
            this.errosFrequentes = this.montarErros(porId);
        } catch (erro) {
            this.erroCarregamento = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }

    get ultimasRespostas(): RespostaRegisto[] {
        return this.respostas.slice(-12);
    }

    get recomendacaoTitulo(): string {
        if (this.revisoesPendentes) return 'Faça as revisões pendentes';
        if (!this.totalPraticado) return 'Comece uma sessão curta';
        if (this.taxaRecente < this.regras.percentagemPassagem(this.categoria)) return 'Reforce este tema';
        return 'Mantenha o conhecimento ativo';
    }

    get recomendacaoTexto(): string {
        if (this.revisoesPendentes) return `${this.revisoesPendentes} ${this.revisoesPendentes === 1 ? 'pergunta está pronta' : 'perguntas estão prontas'} para rever.`;
        if (!this.totalPraticado) return 'Cinco perguntas são suficientes para obter a primeira leitura deste tema.';
        if (this.errosFrequentes.length) return `Comece pelas perguntas que já falhou, sobretudo “${this.errosFrequentes[0].pergunta.enunciado}”.`;
        return 'Uma prática curta ajuda a confirmar que o resultado se mantém.';
    }

    get rotaRecomendacao(): unknown[] {
        return this.revisoesPendentes ? ['/praticar/revisoes'] : ['/praticar/tema', this.slug];
    }

    private percentagem(respostas: RespostaRegisto[]): number {
        return respostas.length ? Math.round((respostas.filter((resposta) => resposta.acertou).length / respostas.length) * 100) : 0;
    }

    private montarErros(perguntas: Map<string, Pergunta>): ErroFrequente[] {
        const contagem = new Map<string, number>();
        for (const resposta of this.respostas) {
            if (!resposta.acertou) contagem.set(resposta.perguntaId, (contagem.get(resposta.perguntaId) || 0) + 1);
        }
        return [...contagem.entries()]
            .map(([id, vezes]) => ({ pergunta: perguntas.get(id), vezes }))
            .filter((item): item is ErroFrequente => !!item.pergunta)
            .sort((a, b) => b.vezes - a.vezes)
            .slice(0, 5);
    }
}
