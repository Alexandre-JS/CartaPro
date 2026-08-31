import { DatePipe } from '@angular/common';
import { Component, inject, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    arrowForwardOutline, chevronForwardOutline, documentTextOutline, flashOutline,
    refreshOutline, schoolOutline, timeOutline, trophyOutline, warningOutline,
} from 'ionicons/icons';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { mensagemErroApi } from '../../core/api-error';
import { ContentService } from '../../core/content.service';
import { ExameApiService } from '../../core/exame-api.service';
import { StorageService } from '../../core/storage.service';
import { CategoriaCarta } from '../../models/pergunta.model';
import { HistoricoExame, SimuladoEmCurso } from '../../models/progresso.model';

const NOMES_CATEGORIA: Record<CategoriaCarta, string> = {
    ligeiro: 'Veículos ligeiros',
    pesado: 'Veículos pesados',
    profissional_publico: 'Transporte público',
};

@Component({
    standalone: true,
    selector: 'app-simular-hub',
    imports: [DatePipe, RouterLink, IonContent, IonIcon, AppHeaderComponent, BottomNavComponent, SkeletonComponent],
    templateUrl: './simular.page.html',
    styleUrls: ['./simular.page.scss'],
})
export class SimularPage implements OnInit {
    private readonly storage = inject(StorageService);
    private readonly content = inject(ContentService);
    private readonly examesApi = inject(ExameApiService);
    categoria: CategoriaCarta = 'ligeiro';
    historico: HistoricoExame[] = [];
    retomavel?: SimuladoEmCurso;
    totalProvas = 0;
    carregando = true;
    erroCarregamento = '';

    constructor() {
        addIcons({
            arrowForwardOutline, chevronForwardOutline, documentTextOutline, flashOutline,
            refreshOutline, schoolOutline, timeOutline, trophyOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        await this.carregar();
    }

    async carregar(): Promise<void> {
        this.carregando = true;
        this.erroCarregamento = '';

        try {
            this.categoria = ((await this.storage.obterCategoria()) || 'ligeiro') as CategoriaCarta;
            const [historico, retomaveis, provas] = await Promise.all([
                this.storage.listarExames(),
                this.storage.listarSimuladosEmCurso(),
                this.examesApi.listar(),
                this.content.carregarPacote(),
            ]);
            this.historico = historico;
            this.retomavel = retomaveis[0];
            this.totalProvas = provas.length;
        } catch (erro) {
            this.erroCarregamento = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }

    get nomeCategoria(): string {
        return NOMES_CATEGORIA[this.categoria];
    }

    get ultimaTentativa(): HistoricoExame | undefined {
        return this.historico[0];
    }

    percentagem(tentativa: HistoricoExame): number {
        return tentativa.total ? Math.round((tentativa.acertos / tentativa.total) * 100) : 0;
    }

    tituloRetoma(): string {
        if (!this.retomavel) return '';
        return this.retomavel.numeroExame ? `Exame ${this.retomavel.numeroExame}` : 'Simulado individual';
    }

    respondidasNaRetoma(): number {
        return this.retomavel?.respondidas.filter((respondida) => respondida).length ?? 0;
    }
}
