import { Component, OnInit } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import {
    bookOutline, checkmarkCircleOutline, chevronForwardOutline, documentTextOutline, ellipseOutline, libraryOutline, searchOutline, textOutline, timeOutline, warningOutline,
} from 'ionicons/icons';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { ProgressoEstudo } from '../../models/material-estudo.model';
import { ContentService } from '../../core/content.service';
import { TemaDetalhe } from '../../models/pacote.model';
import { ProgressoService } from '../../core/progresso.service';
import { ProgressoTema } from '../../models/progresso.model';

/** Uma das quatro frentes do material de estudo. */
interface SecaoEstudo {
    id: string;
    titulo: string;
    descricao: string;
    /** O que se conta nesta secção: "sinais", "fichas", "artigos", "termos". */
    unidade: string;
    icone: string;
    rota: string;
    total: number;
    progresso?: ProgressoEstudo;
}

/**
 * Biblioteca de estudo.
 *
 * Já foi um segundo Início: trazia a recomendação do dia, as revisões
 * pendentes e o reforço de sinais, tudo repetido do Início — e a recomendação
 * chegava a apontar para a mesma tarefa por outra rota. O que só existe aqui é
 * o índice do material: as quatro frentes, cada uma com o seu progresso.
 */
@Component({
    standalone: true,
    selector: 'app-estudos',
    imports: [DecimalPipe, FormsModule, RouterLink, IonContent, IonIcon, BottomNavComponent, SkeletonComponent, AppHeaderComponent],
    templateUrl: './estudos.page.html',
    styleUrls: ['./estudos.page.scss'],
})
export class EstudosPage implements OnInit {
    secoes: SecaoEstudo[] = [];
    temas: TemaDetalhe[] = [];
    progressoTemas: ProgressoTema[] = [];
    carregando = true;
    pesquisa = '';

    constructor(private readonly material: MaterialEstudoService, private readonly content: ContentService, private readonly progresso: ProgressoService) {
        addIcons({
            bookOutline, checkmarkCircleOutline, chevronForwardOutline, documentTextOutline, ellipseOutline, libraryOutline, searchOutline, textOutline, timeOutline, warningOutline,
        });
    }

    get temasVisiveis(): TemaDetalhe[] {
        const termo = this.pesquisa.trim().toLocaleLowerCase();
        return termo ? this.temas.filter((tema) => `${tema.nome} ${tema.descricao || ''}`.toLocaleLowerCase().includes(termo)) : this.temas;
    }

    async ngOnInit(): Promise<void> {
        const [estudo, progressoLeitura, temas] = await Promise.all([
            this.material.carregar(),
            this.material.progresso(),
            this.content.listarTemasDetalhe(),
        ]);

        this.temas = temas;
        this.progressoTemas = await this.progresso.estatisticasPorTema(temas.map((tema) => tema.slug));

        this.secoes = [
            {
                id: 'sinais',
                titulo: 'Sinais de trânsito',
                descricao: 'Reconhecer pela forma, cor e significado',
                unidade: 'sinais',
                icone: 'warning-outline',
                rota: '/sinais',
                total: estudo.sinais.length,
                progresso: progressoLeitura.sinais,
            },
            {
                id: 'licoes',
                titulo: 'Fichas por tema',
                descricao: 'Regras, condução, primeiros socorros e mecânica',
                unidade: 'fichas',
                icone: 'library-outline',
                rota: '/licoes',
                total: estudo.licoes.length,
                progresso: progressoLeitura.licoes,
            },
            {
                id: 'codigo',
                titulo: 'Código da Estrada',
                descricao: 'O texto legal, organizado por capítulos',
                unidade: 'artigos',
                icone: 'document-text-outline',
                rota: '/codigo',
                total: estudo.artigos.length,
                progresso: progressoLeitura.artigos,
            },
            {
                id: 'glossario',
                titulo: 'Glossário',
                descricao: 'O vocabulário que aparece nas perguntas',
                unidade: 'termos',
                icone: 'text-outline',
                rota: '/glossario',
                total: estudo.glossario.length,
            },
        ];

        this.carregando = false;
    }

    /** Só mostra secções com conteúdo publicado — cartões vazios não ajudam. */
    get secoesDisponiveis(): SecaoEstudo[] {
        return this.secoes.filter((secao) => secao.total > 0);
    }

    get semMaterial(): boolean {
        return !this.carregando && this.secoesDisponiveis.length === 0;
    }

    get secoesPreview(): SecaoEstudo[] {
        return this.secoesDisponiveis.slice(0, 2);
    }

    progressoDoTema(slug: string): ProgressoTema {
        return this.progressoTemas.find((item) => item.tema === slug) ?? {
            tema: slug, respondidas: 0, acertos: 0, taxaAcerto: 0, taxaRecente: 0,
            estado: 'nao_praticado', graduado: false, revisoesPendentes: 0, tempoMedioMs: 0,
        };
    }

    estadoIcone(estado: ProgressoTema['estado']): string {
        if (estado === 'dominado' || estado === 'solido') return 'checkmark-circle-outline';
        if (estado === 'fraco' || estado === 'em_avaliacao') return 'time-outline';
        return 'ellipse-outline';
    }
}
