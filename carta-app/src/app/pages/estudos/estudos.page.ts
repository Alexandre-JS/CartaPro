import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import {
    bookOutline, chevronForwardOutline, documentTextOutline, libraryOutline, textOutline, warningOutline,
} from 'ionicons/icons';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { ProgressoEstudo } from '../../models/material-estudo.model';

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
    imports: [RouterLink, IonContent, IonIcon, BottomNavComponent, SkeletonComponent],
    templateUrl: './estudos.page.html',
    styleUrls: ['./estudos.page.scss'],
})
export class EstudosPage implements OnInit {
    secoes: SecaoEstudo[] = [];
    carregando = true;

    constructor(private readonly material: MaterialEstudoService) {
        addIcons({
            bookOutline, chevronForwardOutline, documentTextOutline, libraryOutline, textOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        const [estudo, progressoLeitura] = await Promise.all([
            this.material.carregar(),
            this.material.progresso(),
        ]);

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
}
