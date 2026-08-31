import { Component, inject, OnDestroy } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    arrowBackOutline, arrowForwardOutline, checkmarkCircleOutline, closeCircleOutline,
    documentTextOutline, refreshOutline, schoolOutline, timeOutline, warningOutline,
} from 'ionicons/icons';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { mensagemErroApi } from '../../core/api-error';
import {
    ProvaEscolarQuestoes, ProvaEscolarService, ResultadoProvaEscolar, SessaoEscolarResumo,
} from '../../core/prova-escolar.service';

@Component({
    standalone: true,
    selector: 'app-prova-escolar',
    imports: [FormsModule, RouterLink, IonContent, IonIcon, BottomNavComponent],
    templateUrl: './prova-escolar.page.html',
    styleUrls: ['./prova-escolar.page.scss'],
})
export class ProvaEscolarPage implements OnDestroy {
    private readonly escola = inject(ProvaEscolarService);
    codigo = '';
    nome = '';
    sessao?: SessaoEscolarResumo;
    prova?: ProvaEscolarQuestoes;
    resultado?: ResultadoProvaEscolar;
    indice = 0;
    respostas: Record<string, number> = {};
    segundosRestantes = 0;
    segundosDecorridos = 0;
    carregando = false;
    submetendo = false;
    mensagemErro = '';
    private temporizador?: ReturnType<typeof setInterval>;

    constructor() {
        addIcons({
            arrowBackOutline, arrowForwardOutline, checkmarkCircleOutline, closeCircleOutline,
            documentTextOutline, refreshOutline, schoolOutline, timeOutline, warningOutline,
        });
    }

    ngOnDestroy(): void {
        this.pararTemporizador();
    }

    async consultar(): Promise<void> {
        if (!this.codigo.trim()) return;
        this.carregando = true;
        this.mensagemErro = '';
        try {
            this.sessao = await this.escola.consultar(this.codigo);
            this.codigo = this.sessao.codigo;
        } catch (erro) {
            this.sessao = undefined;
            this.mensagemErro = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }

    async identificar(): Promise<void> {
        if (!this.sessao || this.nome.trim().length < 3 || !this.sessao.aberta) return;
        this.carregando = true;
        this.mensagemErro = '';
        try {
            const acesso = await this.escola.entrar(this.codigo, this.nome.trim());
            this.prova = await this.escola.perguntas(this.codigo, acesso.bilhete);
            this.nome = acesso.aluno.nome;
            this.bilhete = acesso.bilhete;
            this.segundosRestantes = this.prova.prova.minutos * 60;
            this.arrancarTemporizador();
        } catch (erro) {
            this.mensagemErro = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }

    private bilhete = '';

    get perguntaAtual() {
        return this.prova?.perguntas[this.indice];
    }

    get progresso(): number {
        return this.prova?.perguntas.length ? Math.round(((this.indice + 1) / this.prova.perguntas.length) * 100) : 0;
    }

    get tempoFormatado(): string {
        const minutos = Math.floor(this.segundosRestantes / 60).toString().padStart(2, '0');
        return `${minutos}:${(this.segundosRestantes % 60).toString().padStart(2, '0')}`;
    }

    nomeTema(tema: string): string {
        return tema.replace(/_/g, ' ');
    }

    escolher(indice: number): void {
        if (this.perguntaAtual) this.respostas[this.perguntaAtual.id] = indice;
    }

    anterior(): void {
        if (this.indice > 0) this.indice--;
    }

    seguinte(): void {
        if (this.prova && this.indice < this.prova.perguntas.length - 1) this.indice++;
    }

    async finalizar(): Promise<void> {
        if (!this.prova || this.submetendo) return;
        this.submetendo = true;
        this.mensagemErro = '';
        this.pararTemporizador();
        try {
            this.resultado = await this.escola.submeter(this.codigo, this.bilhete, this.respostas, this.segundosDecorridos);
        } catch (erro) {
            this.mensagemErro = mensagemErroApi(erro);
            if (this.segundosRestantes > 0) this.arrancarTemporizador();
        } finally {
            this.submetendo = false;
        }
    }

    reiniciarEntrada(): void {
        this.pararTemporizador();
        this.sessao = undefined;
        this.prova = undefined;
        this.resultado = undefined;
        this.respostas = {};
        this.indice = 0;
        this.nome = '';
        this.bilhete = '';
        this.segundosRestantes = 0;
        this.segundosDecorridos = 0;
        this.mensagemErro = '';
    }

    private arrancarTemporizador(): void {
        this.pararTemporizador();
        this.temporizador = setInterval(() => {
            this.segundosDecorridos++;
            this.segundosRestantes = Math.max(0, this.segundosRestantes - 1);
            if (this.segundosRestantes === 0) void this.finalizar();
        }, 1000);
    }

    private pararTemporizador(): void {
        if (this.temporizador) clearInterval(this.temporizador);
        this.temporizador = undefined;
    }
}
