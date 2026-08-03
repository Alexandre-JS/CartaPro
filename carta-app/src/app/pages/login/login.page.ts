import { Component } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
    IonButton,
    IonContent,
    IonIcon,
    IonInput,
    IonInputPasswordToggle,
    IonItem,
    IonNote,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { lockClosedOutline, mailOutline } from 'ionicons/icons';
import { AuthService } from '../../core/auth.service';
import { mensagemDeErro } from '../../core/erros-api';

@Component({
    standalone: true,
    selector: 'app-login',
    imports: [
        ReactiveFormsModule,
        RouterLink,
        IonButton,
        IonContent,
        IonIcon,
        IonInput,
        IonInputPasswordToggle,
        IonItem,
        IonNote,
    ],
    templateUrl: './login.page.html',
    styleUrls: ['./login.page.scss'],
})
export class LoginPage {
    readonly formulario;
    submetido = false;
    processando = false;
    mensagemErro = '';

    constructor(
        formBuilder: FormBuilder,
        private readonly router: Router,
        private readonly auth: AuthService,
    ) {
        addIcons({ lockClosedOutline, mailOutline });
        this.formulario = formBuilder.nonNullable.group({
            identificador: ['', [Validators.required]],
            palavraPasse: ['', [Validators.required, Validators.minLength(4)]],
        });
    }

    async entrar(): Promise<void> {
        this.submetido = true;

        if (this.formulario.invalid) {
            this.formulario.markAllAsTouched();
            return;
        }

        this.processando = true; this.mensagemErro = '';
        try {
            const { identificador, palavraPasse } = this.formulario.getRawValue();
            await this.auth.entrar(identificador, palavraPasse);
            await this.router.navigateByUrl('/inicio');
        } catch (error: any) {
            if (error?.status === 0 || error?.name === 'TimeoutError') {
                this.mensagemErro = 'Sem ligação à API. Verifica a internet e tenta novamente.';
            } else if (error?.status === 422) {
                // A API já devolve a mensagem traduzida (ex.: credenciais inválidas).
                this.mensagemErro = mensagemDeErro(error, 'Não foi possível entrar. Confirma os dados ou cria uma conta.');
            } else {
                this.mensagemErro = mensagemDeErro(error, 'Ocorreu um problema ao entrar. Tenta novamente dentro de instantes.');
            }
        } finally { this.processando = false; }
    }
}
