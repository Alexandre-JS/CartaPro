import { Component, OnInit } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { IonContent, IonIcon, IonInput, IonItem, IonNote } from '@ionic/angular/standalone';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import { callOutline, checkmarkCircle, chevronForwardOutline, closeOutline, lockOpenOutline, logOutOutline, mailOutline, notificationsOutline, pencilOutline, personOutline, schoolOutline } from 'ionicons/icons';
import { DesbloqueioService } from '../../core/desbloqueio.service';
import { PerfilService } from '../../core/perfil.service';
import { AuthService } from '../../core/auth.service';
import { NotificacoesService } from '../../core/notificacoes.service';

@Component({
    standalone: true,
    selector: 'app-perfil',
    imports: [ReactiveFormsModule, RouterLink, IonContent, IonIcon, IonInput, IonItem, IonNote, BottomNavComponent, SkeletonComponent, AppHeaderComponent],
    templateUrl: './perfil.page.html',
    styleUrls: ['./perfil.page.scss'],
})
export class PerfilPage implements OnInit {
    readonly formulario;
    plano: 'gratis' | 'pago' = 'gratis';
    carregando = true;
    guardado = false;
    submetido = false;
    editando = false;
    notificacoesAtivas = true;

    constructor(
        formBuilder: FormBuilder,
        private readonly perfil: PerfilService,
        private readonly desbloqueio: DesbloqueioService,
        private readonly router: Router,
        private readonly auth: AuthService,
        private readonly notificacoes: NotificacoesService,
    ) {
        addIcons({ callOutline, checkmarkCircle, chevronForwardOutline, closeOutline, lockOpenOutline, logOutOutline, mailOutline, notificationsOutline, pencilOutline, personOutline, schoolOutline });
        this.formulario = formBuilder.nonNullable.group({
            nome: ['', [Validators.required, Validators.minLength(3)]],
            email: ['', [Validators.required, Validators.email]],
            telefone: ['', [Validators.pattern(/^[0-9+ ]{0,16}$/)]],
        });
    }

    async ngOnInit(): Promise<void> {
        const [perfil, acesso] = await Promise.all([
            this.perfil.obter(),
            this.desbloqueio.revalidar(),
        ]);
        this.formulario.setValue(perfil);
        this.notificacoesAtivas = await this.notificacoes.ativas();
        this.plano = acesso.plano;
        this.carregando = false;
    }

    get iniciais(): string {
        return this.formulario.controls.nome.value.split(/\s+/).filter(Boolean).slice(0, 2).map((nome) => nome[0]).join('').toUpperCase() || 'CP';
    }

    async guardar(): Promise<void> {
        this.submetido = true;
        this.guardado = false;
        if (this.formulario.invalid) {
            this.formulario.markAllAsTouched();
            return;
        }
        await this.perfil.guardar(this.formulario.getRawValue());
        this.guardado = true;
        this.editando = false;
    }

    async sair(): Promise<boolean> {
        await this.auth.sair();
        return this.router.navigateByUrl('/entrar');
    }

    async alternarNotificacoes(): Promise<void> {
        this.notificacoesAtivas = !this.notificacoesAtivas;
        await this.notificacoes.definir(this.notificacoesAtivas);
    }
}
