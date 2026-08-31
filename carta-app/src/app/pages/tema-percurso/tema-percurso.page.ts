import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowBackOutline, bookOutline, checkmarkCircle, chevronForwardOutline, ellipseOutline, flagOutline, lockClosedOutline, playCircleOutline } from 'ionicons/icons';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { ContentService } from '../../core/content.service';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { ProgressoService } from '../../core/progresso.service';
import { LicaoEstudo } from '../../models/material-estudo.model';
import { ProgressoTema } from '../../models/progresso.model';

interface Checkpoint {
    id: string;
    titulo: string;
    descricao: string;
    estado: 'concluido' | 'atual' | 'pendente' | 'bloqueado';
    rota?: unknown[];
    query?: Record<string, string>;
    tipo: 'licao' | 'pratica' | 'final';
}

@Component({
    standalone: true,
    selector: 'app-tema-percurso',
    imports: [RouterLink, IonContent, IonIcon, BottomNavComponent, SkeletonComponent],
    templateUrl: './tema-percurso.page.html',
    styleUrls: ['./tema-percurso.page.scss'],
})
export class TemaPercursoPage implements OnInit {
    slug = '';
    nome = '';
    descricao = '';
    progresso?: ProgressoTema;
    checkpoints: Checkpoint[] = [];
    carregando = true;
    naoEncontrado = false;

    constructor(
        private readonly route: ActivatedRoute,
        private readonly content: ContentService,
        private readonly material: MaterialEstudoService,
        private readonly progressoService: ProgressoService,
    ) {
        addIcons({ arrowBackOutline, bookOutline, checkmarkCircle, chevronForwardOutline, ellipseOutline, flagOutline, lockClosedOutline, playCircleOutline });
    }

    async ngOnInit(): Promise<void> {
        this.slug = this.route.snapshot.paramMap.get('slug') || '';
        const [temas, licoes, lidos] = await Promise.all([
            this.content.listarTemasDetalhe(),
            this.material.licoes(),
            this.material.conteudosLidos(),
        ]);
        const tema = temas.find((item) => item.slug === this.slug);
        if (!tema) {
            this.naoEncontrado = true;
            this.carregando = false;
            return;
        }

        this.nome = tema.nome;
        this.descricao = tema.descricao || 'Aprenda os conceitos, pratique e confirme o domínio do tema.';
        [this.progresso] = await this.progressoService.estatisticasPorTema([this.slug]);
        this.checkpoints = this.montarCheckpoints(licoes.filter((licao) => licao.tema === this.slug), lidos);
        this.carregando = false;
    }

    get percentagem(): number {
        if (!this.checkpoints.length) return 0;
        const concluidos = this.checkpoints.filter((item) => item.estado === 'concluido').length;
        return Math.round((concluidos / this.checkpoints.length) * 100);
    }

    get atual(): Checkpoint | undefined {
        return this.checkpoints.find((item) => item.estado === 'atual');
    }

    icone(checkpoint: Checkpoint): string {
        if (checkpoint.estado === 'concluido') return 'checkmark-circle';
        if (checkpoint.estado === 'bloqueado') return 'lock-closed-outline';
        if (checkpoint.tipo === 'final') return 'flag-outline';
        if (checkpoint.tipo === 'pratica') return 'play-circle-outline';
        return 'ellipse-outline';
    }

    private montarCheckpoints(licoes: LicaoEstudo[], lidos: Set<string>): Checkpoint[] {
        const itens: Checkpoint[] = licoes.map((licao) => ({
            id: `licao:${licao.slug}`,
            titulo: licao.titulo,
            descricao: licao.resumo || `${licao.minutosLeitura} min de leitura`,
            estado: licao.bloqueado ? 'bloqueado' : lidos.has(`licao:${licao.slug}`) ? 'concluido' : 'pendente',
            rota: licao.bloqueado ? ['/desbloquear'] : ['/aprender/licoes', licao.slug],
            tipo: 'licao',
        }));

        const praticou = (this.progresso?.respondidas ?? 0) > 0;
        itens.push({
            id: 'pratica', titulo: `Praticar ${this.nome}`, descricao: praticou ? `${this.progresso!.respondidas} perguntas já respondidas` : 'Aplique o que aprendeu numa sessão curta',
            estado: praticou ? 'concluido' : 'pendente', rota: ['/praticar/tema', this.slug], query: { modo: 'pratica' }, tipo: 'pratica',
        });

        const dominado = this.progresso?.estado === 'dominado' || this.progresso?.estado === 'solido';
        itens.push({
            id: 'checkpoint-final', titulo: 'Checkpoint do tema', descricao: dominado ? 'Tema consolidado' : 'Confirme que os conceitos ficaram claros',
            estado: dominado ? 'concluido' : 'pendente', rota: ['/praticar/tema', this.slug], query: { modo: 'checkpoint' }, tipo: 'final',
        });

        const primeiroDisponivel = itens.find((item) => item.estado === 'pendente');
        if (primeiroDisponivel) primeiroDisponivel.estado = 'atual';
        return itens;
    }
}
