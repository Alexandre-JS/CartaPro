import { Component, inject, OnInit } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowBackOutline, checkmarkCircle, lockClosedOutline, refreshOutline, warningOutline } from 'ionicons/icons';
import { mensagemErroApi } from '../../core/api-error';
import { ContentService } from '../../core/content.service';
import { RegrasService } from '../../core/regras.service';
import { StorageService } from '../../core/storage.service';
import { CategoriaCarta } from '../../models/pergunta.model';

interface OpcaoCategoria { valor: CategoriaCarta; nome: string; descricao: string; }

@Component({
    standalone: true,
    selector: 'app-simular-configurar',
    imports: [RouterLink, IonContent, IonIcon],
    templateUrl: './simular-configurar.page.html',
    styleUrls: ['./simular-configurar.page.scss'],
})
export class SimularConfigurarPage implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly content = inject(ContentService);
    readonly regras = inject(RegrasService);
    private readonly storage = inject(StorageService);
    readonly categorias: OpcaoCategoria[] = [
        { valor: 'ligeiro', nome: 'Ligeiros', descricao: 'Categoria B' },
        { valor: 'pesado', nome: 'Pesados', descricao: 'Categorias C e D' },
        { valor: 'profissional_publico', nome: 'Transporte público', descricao: 'Preparação profissional' },
    ];
    categoria: CategoriaCarta = 'ligeiro';
    total = 25;
    duracao = 30;
    modo: 'normal' | 'adaptativo' = 'normal';
    plano: 'gratis' | 'pago' = 'gratis';
    totalDisponivel = 0;
    carregando = true;
    erroCarregamento = '';

    constructor() {
        addIcons({ arrowBackOutline, checkmarkCircle, lockClosedOutline, refreshOutline, warningOutline });
    }

    async ngOnInit(): Promise<void> {
        const parametros = this.route.snapshot.queryParamMap;
        const categoriaRota = parametros.get('categoria') as CategoriaCarta | null;
        this.categoria = categoriaRota || ((await this.storage.obterCategoria()) || 'ligeiro') as CategoriaCarta;
        this.modo = parametros.get('modo') === 'adaptativo' ? 'adaptativo' : 'normal';
        this.total = Math.max(1, Number(parametros.get('total')) || 25);
        await this.carregar();
        if (this.modo === 'adaptativo' && this.plano !== 'pago') this.modo = 'normal';
    }

    async carregar(): Promise<void> {
        this.carregando = true;
        this.erroCarregamento = '';
        try {
            await this.regras.carregar();
            this.duracao = Math.max(1, Number(this.route.snapshot.queryParamMap.get('duracao')) || this.regras.para(this.categoria).minutos);
            const [perguntas, acesso] = await Promise.all([
                this.content.listarPerguntas({ categoria: this.categoria }),
                this.storage.obterEstadoAcesso(),
            ]);
            this.totalDisponivel = perguntas.length;
            this.plano = acesso.plano;
            this.total = Math.min(this.total, this.totalDisponivel || this.total);
        } catch (erro) {
            this.erroCarregamento = mensagemErroApi(erro);
        } finally {
            this.carregando = false;
        }
    }

    async escolherCategoria(categoria: CategoriaCarta): Promise<void> {
        this.categoria = categoria;
        await this.storage.guardarCategoria(categoria);
        await this.carregar();
    }

    escolherModo(modo: 'normal' | 'adaptativo'): void {
        if (modo === 'adaptativo' && this.plano !== 'pago') return;
        this.modo = modo;
    }

    iniciar(): Promise<boolean> {
        return this.router.navigate(['/simular/sessao'], {
            queryParams: { categoria: this.categoria, total: this.total, duracao: this.duracao, modo: this.modo },
        });
    }

    get totais(): number[] {
        if (!this.totalDisponivel) return [];
        return [...new Set([Math.min(10, this.totalDisponivel), Math.min(25, this.totalDisponivel)])];
    }
}
