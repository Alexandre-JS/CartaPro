import { DecimalPipe } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowBackOutline, arrowForwardOutline, bookOutline, checkmarkCircle, closeCircle, createOutline, home, personOutline, schoolOutline, statsChartOutline, trendingUpOutline } from 'ionicons/icons';
import { ContentService } from '../../core/content.service';
import { ProgressoService } from '../../core/progresso.service';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { TemasService } from '../../core/temas.service';
import { Pergunta } from '../../models/pergunta.model';
import { ProgressoTema, ResultadoResumo } from '../../models/progresso.model';

interface DetalheApresentado {
    pergunta: Pergunta;
    escolhida: number | null;
}

@Component({
    standalone: true,
    selector: 'app-resultado',
    imports: [DecimalPipe, RouterLink, IonContent, IonIcon],
    templateUrl: './resultado.page.html',
    styleUrls: ['./resultado.page.scss'],
})
export class ResultadoPage implements OnInit {
    resumo: ResultadoResumo = { total: 0, acertos: 0 };
    fortes: ProgressoTema[] = [];
    fracos: ProgressoTema[] = [];
    naoPraticados: ProgressoTema[] = [];
    detalhes: DetalheApresentado[] = [];
    tempoSegundos = 0;
    mostrarCorrecoes = false;
    notaPassagem = 0;
    valores = 0;
    /** Acertos muito rápidos: provável adivinhação, não domínio. */
    acertosSuspeitos = 0;

    constructor(
        private readonly router: Router,
        private readonly content: ContentService,
        private readonly progresso: ProgressoService,
        private readonly storage: StorageService,
        private readonly regras: RegrasService,
        private readonly temasService: TemasService,
    ) {
        addIcons({ arrowBackOutline, arrowForwardOutline, bookOutline, checkmarkCircle, closeCircle, createOutline, home, personOutline, schoolOutline, statsChartOutline, trendingUpOutline });
    }

    async ngOnInit(): Promise<void> {
        await Promise.all([this.regras.carregar(), this.temasService.carregar()]);

        /*
         * Lido do armazenamento e não de `history.state`: recarregar a página
         * mostrava "0 de 0" porque o estado da navegação desaparece.
         */
        const guardado = await this.storage.obterUltimoResultado();
        if (guardado) {
            this.resumo = guardado.resumo;
            this.tempoSegundos = guardado.tempoSegundos;
            this.notaPassagem = guardado.notaPassagem || this.regras.notaPassagem(guardado.resumo.total);

            for (const detalhe of guardado.detalhes) {
                const pergunta = await this.content.obterPergunta(detalhe.perguntaId);
                if (pergunta) {
                    this.detalhes.push({ pergunta, escolhida: detalhe.escolhida });
                }
            }
        }

        this.valores = this.regras.valores(this.resumo.acertos, this.resumo.total);

        const estatisticas = await this.progresso.estatisticasPorTema(await this.content.listarTemas());
        this.fortes = this.progresso.temasFortes(estatisticas);
        /*
         * "Fracos" já não inclui os temas nunca praticados — antes o ecrã
         * listava dezenas de temas que o aluno nem tinha visto, o que tornava
         * o diagnóstico inútil. Ficam numa secção própria.
         */
        this.fracos = this.progresso.temasFracos(estatisticas);
        this.naoPraticados = this.progresso.temasNaoPraticados(estatisticas);

        const diagnostico = await this.progresso.diagnosticoAvancado();
        this.acertosSuspeitos = diagnostico.acertosSuspeitos;
    }

    get percentagem(): number {
        return this.resumo.total ? Math.round((this.resumo.acertos / this.resumo.total) * 100) : 0;
    }

    get aprovado(): boolean {
        return this.regras.aprovado(this.resumo.acertos, this.resumo.total);
    }

    get naoRespondidas(): number {
        return this.detalhes.filter((detalhe) => detalhe.escolhida === null).length;
    }

    get erros(): number {
        return Math.max(0, this.resumo.total - this.resumo.acertos - this.naoRespondidas);
    }

    get tempoFormatado(): string {
        const minutos = Math.floor(this.tempoSegundos / 60).toString().padStart(2, '0');
        return `${minutos}:${(this.tempoSegundos % 60).toString().padStart(2, '0')}`;
    }

    respostaEscolhida(detalhe: DetalheApresentado): string {
        return detalhe.escolhida === null ? 'Não respondida' : detalhe.pergunta.opcoes[detalhe.escolhida];
    }

    nomeTema(tema: string): string {
        return this.temasService.nome(tema);
    }

    percentagemTema(tema: ProgressoTema): number {
        return Math.round(tema.taxaRecente * 100);
    }

    repetirAdaptativo(): Promise<boolean> {
        return this.router.navigate(['/simulado'], { queryParams: { modo: 'adaptativo' } });
    }

    novoExame(): Promise<boolean> {
        return this.router.navigate(['/exames']);
    }

    voltarInicio(): Promise<boolean> {
        return this.router.navigateByUrl('/inicio');
    }
}
