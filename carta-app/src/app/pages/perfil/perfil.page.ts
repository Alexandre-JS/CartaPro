import { Component, OnInit } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { IonContent, IonIcon, IonInput, IonItem, IonNote, IonToggle } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { bookOutline, callOutline, checkmarkCircle, chevronForwardOutline, documentTextOutline, helpCircleOutline, homeOutline, logOutOutline, mailOutline, notificationsOutline, personOutline, shieldCheckmarkOutline, statsChartOutline } from 'ionicons/icons';
import { DesbloqueioService } from '../../core/desbloqueio.service';
import { PerfilService } from '../../core/perfil.service';
import { StorageService } from '../../core/storage.service';
import { AuthService } from '../../core/auth.service';

@Component({
    standalone: true,
    selector: 'app-perfil',
    imports: [ReactiveFormsModule, RouterLink, IonContent, IonIcon, IonInput, IonItem, IonNote, IonToggle],
    templateUrl: './perfil.page.html',
    styleUrls: ['./perfil.page.scss'],
})
export class PerfilPage implements OnInit {
    readonly formulario;
    plano: 'gratis' | 'pago' = 'gratis';
    totalRespondidas = 0;
    taxaAcerto = 0;
    guardado = false;
    submetido = false;

    constructor(
        formBuilder: FormBuilder,
        private readonly perfil: PerfilService,
        private readonly storage: StorageService,
        private readonly desbloqueio: DesbloqueioService,
        private readonly router: Router,
        private readonly auth: AuthService,
    ) {
        addIcons({ bookOutline, callOutline, checkmarkCircle, chevronForwardOutline, documentTextOutline, helpCircleOutline, homeOutline, logOutOutline, mailOutline, notificationsOutline, personOutline, shieldCheckmarkOutline, statsChartOutline });
        this.formulario = formBuilder.nonNullable.group({
            nome: ['', [Validators.required, Validators.minLength(3)]],
            email: ['', [Validators.required, Validators.email]],
            telefone: ['', [Validators.pattern(/^[0-9+ ]{0,16}$/)]],
        });
    }

    async ngOnInit(): Promise<void> {
        const [perfil, respostas, acesso] = await Promise.all([
            this.perfil.obter(),
            this.storage.listarRespostas(),
            this.desbloqueio.revalidar(),
        ]);
        this.formulario.setValue(perfil);
        this.plano = acesso.plano;
        this.totalRespondidas = respostas.length;
        const acertos = respostas.filter((resposta) => resposta.acertou).length;
        this.taxaAcerto = respostas.length ? Math.round((acertos / respostas.length) * 100) : 0;
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
    }

    async sair(): Promise<boolean> {
        await this.auth.sair();
        return this.router.navigateByUrl('/');
    }
}
