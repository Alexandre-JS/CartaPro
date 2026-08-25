import { DatePipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { IconeProvaComponent } from '../../components/icone-prova/icone-prova.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import { bookOutline, checkmarkCircleOutline, lockClosed, chevronDownOutline, chevronForwardOutline, chevronUpOutline, cloudOfflineOutline, documentTextOutline, refreshOutline, schoolOutline, timeOutline } from 'ionicons/icons';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { HistoricoExame } from '../../models/progresso.model';
import { ExameApiService } from '../../core/exame-api.service';
import { ExameApiResumo } from '../../models/exame-api.model';

interface ExameDisponivel {
    bloqueado: boolean;
    id: number;
    nome: string;
    numero: number;
    perguntas: number;
    minutos: number;
    notaPassagem: number;
    historico: HistoricoExame[];
}

@Component({
    standalone: true,
    selector: 'app-exames',
    imports: [DatePipe, RouterLink, IonContent, IonIcon, BottomNavComponent, SkeletonComponent, IconeProvaComponent, AppHeaderComponent],
    templateUrl: './exames.page.html',
    styleUrls: ['./exames.page.scss'],
})
export class ExamesPage implements OnInit {
    exames: ExameDisponivel[] = [];
    mensagemErro = '';
    carregando = true;
    historicoAberto?: number;

    constructor(
        private readonly storage: StorageService,
        private readonly examesApi: ExameApiService,
        private readonly regras: RegrasService,
    ) {
        addIcons({ bookOutline, checkmarkCircleOutline, lockClosed, chevronDownOutline, chevronForwardOutline, chevronUpOutline, cloudOfflineOutline, documentTextOutline, refreshOutline, schoolOutline, timeOutline });
    }

    ngOnInit(): Promise<void> {
        return this.carregar();
    }

    /**
     * Falhar a carregar e não haver provas publicadas são coisas diferentes: o
     * ecrã dizia "Nenhuma prova disponível" nos dois casos — e também enquanto
     * ainda estava a carregar, antes de saber a resposta.
     */
    async carregar(): Promise<void> {
        this.carregando = true;
        this.mensagemErro = '';
        await this.regras.carregar();

        try {
            const [catalogo, historico] = await Promise.all([this.examesApi.listar(), this.storage.listarExames()]);
            this.exames = catalogo.map((exame: ExameApiResumo) => ({ id: exame.id, nome: exame.nome, numero: exame.id, perguntas: exame.perguntas, minutos: exame.minutos, notaPassagem: exame.notaPassagem, bloqueado: !!exame.bloqueado, historico: historico.filter((tentativa) => tentativa.numero === exame.id) }));
        } catch (error: any) {
            this.mensagemErro = error?.message || 'Não foi possível carregar as provas.';
        } finally {
            this.carregando = false;
        }
    }

    numeroFormatado(numero: number): string {
        return numero.toString().padStart(2, '0');
    }

    percentagem(exame: HistoricoExame): number {
        return exame.total ? Math.round((exame.acertos / exame.total) * 100) : 0;
    }

    /**
     * Estado face à regra de aprovação única.
     *
     * Antes o limiar de "attention" era 80% fixo enquanto a nota de passagem
     * era 96%, e o rótulo dizia "Abaixo de 80%": quem reprovava com 85% via
     * "Em progresso" e não percebia que tinha chumbado.
     */
    estadoExame(exame: ExameDisponivel): 'pending' | 'failed' | 'attention' | 'passed' {
        const ultima = exame.historico[0];
        if (!ultima) {
            return 'pending';
        }

        if (this.regras.aprovado(ultima.acertos, ultima.total)) {
            return 'passed';
        }

        const faltam = this.regras.notaPassagem(ultima.total) - ultima.acertos;
        return faltam <= 2 ? 'attention' : 'failed';
    }

    rotuloEstado(exame: ExameDisponivel): string {
        const estado = this.estadoExame(exame);
        const ultima = exame.historico[0];

        if (estado === 'pending') {
            return 'Não realizado';
        }
        if (estado === 'passed') {
            return 'Aprovado';
        }

        const faltam = this.regras.notaPassagem(ultima.total) - ultima.acertos;
        return estado === 'attention'
            ? `Faltou ${faltam} para passar`
            : `Reprovado (${this.percentagem(ultima)}%)`;
    }

    notaMinima(exame: ExameDisponivel): number {
        return exame.notaPassagem || this.regras.notaPassagem(exame.perguntas);
    }

    formatarTempo(segundos: number): string {
        return `${Math.floor(segundos / 60).toString().padStart(2, '0')}:${(segundos % 60).toString().padStart(2, '0')}`;
    }
}
