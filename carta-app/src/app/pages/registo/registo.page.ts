import { Component } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
    IonButton,
    IonCheckbox,
    IonContent,
    IonIcon,
    IonInput,
    IonInputPasswordToggle,
    IonItem,
    IonNote,
} from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowBackOutline, callOutline, lockClosedOutline, mailOutline, personOutline } from 'ionicons/icons';
import { contaJaExiste, mensagensDeErro } from '../../core/erros-api';
import { PerfilService } from '../../core/perfil.service';
import { AuthService } from '../../core/auth.service';

function palavrasPasseIguais(controle: AbstractControl): ValidationErrors | null {
    const palavraPasse = controle.get('palavraPasse')?.value;
    const confirmarPalavraPasse = controle.get('confirmarPalavraPasse')?.value;

    return palavraPasse === confirmarPalavraPasse ? null : { palavrasPasseDiferentes: true };
}

@Component({
    standalone: true,
    selector: 'app-registo',
    imports: [
        ReactiveFormsModule,
        RouterLink,
        IonButton,
        IonCheckbox,
        IonContent,
        IonIcon,
        IonInput,
        IonInputPasswordToggle,
        IonItem,
        IonNote,
    ],
    templateUrl: './registo.page.html',
    styleUrls: ['./registo.page.scss'],
})
export class RegistoPage {
    readonly formulario;
    submetido = false;
    processando = false;
    /** Uma mensagem por campo rejeitado pela API. */
    errosApi: string[] = [];
    /** true quando o email/telefone já tem conta: oferece-se o login. */
    sugerirEntrada = false;

    constructor(
        formBuilder: FormBuilder,
        private readonly router: Router,
        private readonly perfil: PerfilService,
        private readonly auth: AuthService,
    ) {
        addIcons({ arrowBackOutline, callOutline, lockClosedOutline, mailOutline, personOutline });
        this.formulario = formBuilder.nonNullable.group({
            nome: ['', [Validators.required, Validators.minLength(3)]],
            email: ['', [Validators.required, Validators.email]],
            telefone: ['', [Validators.required, Validators.pattern(/^[0-9+ ]{8,16}$/)]],
            palavraPasse: ['', [Validators.required, Validators.minLength(6)]],
            confirmarPalavraPasse: ['', [Validators.required]],
            termos: [false, [Validators.requiredTrue]],
        }, { validators: palavrasPasseIguais });
    }

    async criarConta(): Promise<void> {
        this.submetido = true;

        if (this.formulario.invalid) {
            this.formulario.markAllAsTouched();
            return;
        }

        const { nome, email, telefone, palavraPasse } = this.formulario.getRawValue();
        this.processando = true;
        this.errosApi = [];
        this.sugerirEntrada = false;

        try {
            const utilizador = await this.auth.registar({ nome, email, telefone, palavraPasse });
            await this.perfil.guardar(utilizador);
            await this.router.navigateByUrl('/inicio');
        } catch (error: any) {
            if (error?.status === 0 || error?.name === 'TimeoutError') {
                this.errosApi = ['Sem ligação à internet. Verifica a rede e tenta novamente.'];
            } else if (error?.status === 429) {
                this.errosApi = ['Demasiadas tentativas. Aguarda um momento e tenta novamente.'];
            } else if (error?.status >= 500) {
                this.errosApi = ['O servidor está com problemas. Tenta novamente dentro de alguns minutos.'];
            } else {
                // Todas as mensagens, não só a primeira com "(and 1 more error)".
                const mensagens = mensagensDeErro(error);
                this.errosApi = mensagens.length ? mensagens : ['Não foi possível criar a conta. Confirma os dados.'];
                this.sugerirEntrada = contaJaExiste(error);
            }
        } finally {
            this.processando = false;
        }
    }
}
