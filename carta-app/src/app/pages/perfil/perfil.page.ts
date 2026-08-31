import { DatePipe } from '@angular/common';
import { Component, inject, OnInit } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { IonContent, IonIcon, IonInput, IonItem, IonNote } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    callOutline, checkmarkCircle, chevronForwardOutline, cloudDoneOutline, cloudOfflineOutline,
    cloudUploadOutline, closeOutline, downloadOutline, helpCircleOutline, lockOpenOutline,
    logInOutline, logOutOutline, mailOutline, notificationsOutline, pencilOutline, personOutline,
    phonePortraitOutline, schoolOutline, shieldCheckmarkOutline, warningOutline,
} from 'ionicons/icons';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { ApiService } from '../../core/api.service';
import { mensagemErroApi } from '../../core/api-error';
import { AuthService } from '../../core/auth.service';
import { NotificacoesService } from '../../core/notificacoes.service';
import { PerfilService } from '../../core/perfil.service';
import { EstadoSincronizacaoLocal, StorageService } from '../../core/storage.service';
import { CategoriaCarta } from '../../models/pergunta.model';

const NOMES_CATEGORIA: Record<CategoriaCarta, string> = {
    ligeiro: 'Veículos ligeiros',
    pesado: 'Veículos pesados',
    profissional_publico: 'Transporte profissional e público',
};

@Component({
    standalone: true,
    selector: 'app-perfil',
    imports: [DatePipe, ReactiveFormsModule, RouterLink, IonContent, IonIcon, IonInput, IonItem, IonNote, BottomNavComponent, SkeletonComponent, AppHeaderComponent],
    templateUrl: './perfil.page.html',
    styleUrls: ['./perfil.page.scss'],
})
export class PerfilPage implements OnInit {
    private readonly formBuilder = inject(FormBuilder);
    private readonly perfil = inject(PerfilService);
    private readonly router = inject(Router);
    private readonly auth = inject(AuthService);
    private readonly notificacoes = inject(NotificacoesService);
    private readonly storage = inject(StorageService);
    private readonly api = inject(ApiService);

    readonly formulario = this.formBuilder.nonNullable.group({
        nome: ['', [Validators.required, Validators.minLength(3)]],
        email: ['', [Validators.required, Validators.email]],
        telefone: ['', [Validators.required, Validators.pattern(/^[0-9+ ]{8,16}$/)]],
    });
    readonly categorias: Array<{ valor: CategoriaCarta; nome: string }> = Object.entries(NOMES_CATEGORIA)
        .map(([valor, nome]) => ({ valor: valor as CategoriaCarta, nome }));

    autenticado = false;
    plano: 'gratis' | 'pago' = 'gratis';
    categoria: CategoriaCarta = 'ligeiro';
    estadoSync: EstadoSincronizacaoLocal = { ultimoCursor: null, pendentes: 0 };
    carregando = true;
    processandoSync = false;
    guardado = false;
    submetido = false;
    editando = false;
    notificacoesAtivas = true;
    erroConta = '';
    erroSincronizacao = '';
    private inicializado = false;

    constructor() {
        addIcons({
            callOutline, checkmarkCircle, chevronForwardOutline, cloudDoneOutline, cloudOfflineOutline,
            cloudUploadOutline, closeOutline, downloadOutline, helpCircleOutline, lockOpenOutline,
            logInOutline, logOutOutline, mailOutline, notificationsOutline, pencilOutline, personOutline,
            phonePortraitOutline, schoolOutline, shieldCheckmarkOutline, warningOutline,
        });
    }

    async ngOnInit(): Promise<void> {
        await this.carregar();
        this.inicializado = true;
    }

    ionViewWillEnter(): void {
        if (this.inicializado) void this.carregar();
    }

    async carregar(): Promise<void> {
        this.carregando = true;
        this.erroConta = '';
        this.erroSincronizacao = '';
        this.autenticado = !!(await this.auth.token());

        const [categoria, notificacoes, perfilLocal, estadoSync, acessoLocal] = await Promise.all([
            this.storage.obterCategoria(),
            this.notificacoes.ativas(),
            this.perfil.obterLocal(),
            this.storage.obterEstadoSincronizacao(),
            this.storage.obterEstadoAcesso(),
        ]);
        this.categoria = (categoria || 'ligeiro') as CategoriaCarta;
        this.notificacoesAtivas = notificacoes;
        this.formulario.setValue(perfilLocal);
        this.estadoSync = estadoSync;
        this.plano = this.autenticado ? acessoLocal.plano : 'gratis';

        if (this.autenticado) {
            try {
                const perfilRemoto = await this.perfil.obter();
                this.formulario.setValue(perfilRemoto);
                this.plano = (await this.storage.obterEstadoAcesso()).plano;
            } catch (erro) {
                this.erroConta = mensagemErroApi(erro);
            }
        }

        this.carregando = false;
    }

    get iniciais(): string {
        return this.formulario.controls.nome.value.split(/\s+/).filter(Boolean).slice(0, 2).map((nome) => nome[0]).join('').toUpperCase() || 'PV';
    }

    get nomeCategoria(): string {
        return NOMES_CATEGORIA[this.categoria];
    }

    get suporteUrl(): string {
        return this.api.websiteUrl('faq');
    }

    get rotuloSincronizacao(): string {
        if (this.estadoSync.pendentes) return `${this.estadoSync.pendentes} ${this.estadoSync.pendentes === 1 ? 'alteração pendente' : 'alterações pendentes'}`;
        return this.estadoSync.ultimoCursor ? 'Tudo sincronizado' : 'Aguardando primeira sincronização';
    }

    async guardar(): Promise<void> {
        this.submetido = true;
        this.guardado = false;
        this.erroConta = '';
        if (this.formulario.invalid) {
            this.formulario.markAllAsTouched();
            return;
        }
        try {
            await this.perfil.guardar(this.formulario.getRawValue());
            this.guardado = true;
            this.editando = false;
        } catch (erro) {
            this.erroConta = mensagemErroApi(erro);
        }
    }

    async alterarCategoria(categoria: CategoriaCarta): Promise<void> {
        this.categoria = categoria;
        await this.storage.guardarCategoria(categoria);
        if (this.autenticado && this.formulario.valid) {
            try {
                await this.perfil.guardar(this.formulario.getRawValue());
            } catch (erro) {
                this.erroConta = mensagemErroApi(erro);
            }
        }
    }

    async sincronizarAgora(): Promise<void> {
        if (!this.autenticado || this.processandoSync) return;
        this.processandoSync = true;
        this.erroSincronizacao = '';
        try {
            await this.storage.sincronizarAgora();
            this.estadoSync = await this.storage.obterEstadoSincronizacao();
        } catch (erro) {
            this.erroSincronizacao = mensagemErroApi(erro);
        } finally {
            this.processandoSync = false;
        }
    }

    async sair(): Promise<void> {
        await this.auth.sair();
        await this.router.navigateByUrl('/perfil', { replaceUrl: true });
        await this.carregar();
    }

    async alternarNotificacoes(): Promise<void> {
        this.notificacoesAtivas = !this.notificacoesAtivas;
        await this.notificacoes.definir(this.notificacoesAtivas);
    }
}
