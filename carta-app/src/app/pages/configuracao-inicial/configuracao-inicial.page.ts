import { Component } from '@angular/core';
import { Router } from '@angular/router';
import { Preferences } from '@capacitor/preferences';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { carSportOutline, checkmarkCircle, helpCircleOutline, peopleOutline, speedometerOutline } from 'ionicons/icons';
import { CategoriaCarta } from '../../models/pergunta.model';
import { StorageService } from '../../core/storage.service';

type EscolhaCategoria = CategoriaCarta | 'nao_sei';

interface OpcaoCategoria {
    valor: EscolhaCategoria;
    titulo: string;
    descricao: string;
    icone: string;
}

@Component({
    standalone: true,
    selector: 'app-configuracao-inicial',
    imports: [IonContent, IonIcon],
    templateUrl: './configuracao-inicial.page.html',
    styleUrls: ['./configuracao-inicial.page.scss'],
})
export class ConfiguracaoInicialPage {
    escolha: EscolhaCategoria | null = null;
    guardando = false;

    readonly opcoes: OpcaoCategoria[] = [
        { valor: 'ligeiro', titulo: 'Veículos ligeiros', descricao: 'Categoria B e preparação geral.', icone: 'car-sport-outline' },
        { valor: 'pesado', titulo: 'Veículos pesados', descricao: 'Preparação para categorias de pesados.', icone: 'speedometer-outline' },
        { valor: 'profissional_publico', titulo: 'Profissional e público', descricao: 'Transporte profissional ou de passageiros.', icone: 'people-outline' },
        { valor: 'nao_sei', titulo: 'Ainda não sei', descricao: 'Pode escolher ou alterar a categoria mais tarde.', icone: 'help-circle-outline' },
    ];

    constructor(
        private readonly storage: StorageService,
        private readonly router: Router,
    ) {
        addIcons({ carSportOutline, checkmarkCircle, helpCircleOutline, peopleOutline, speedometerOutline });
    }

    selecionar(valor: EscolhaCategoria): void {
        this.escolha = valor;
    }

    async continuar(): Promise<void> {
        if (!this.escolha || this.guardando) return;

        this.guardando = true;
        try {
            if (this.escolha !== 'nao_sei') {
                await this.storage.guardarCategoria(this.escolha);
            }
            await Preferences.set({ key: 'setupInicialConcluido', value: '1' });
            await this.router.navigateByUrl('/inicio', { replaceUrl: true });
        } finally {
            this.guardando = false;
        }
    }
}
