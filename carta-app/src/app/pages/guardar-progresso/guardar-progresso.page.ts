import { Component, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import {
    arrowBackOutline,
    arrowForwardOutline,
    cloudDoneOutline,
    phonePortraitOutline,
    schoolOutline,
    shieldCheckmarkOutline,
} from 'ionicons/icons';
import { retornoSeguro } from '../../core/auth.guard';

@Component({
    standalone: true,
    selector: 'app-guardar-progresso',
    imports: [RouterLink, IonContent, IonIcon],
    templateUrl: './guardar-progresso.page.html',
    styleUrls: ['./guardar-progresso.page.scss'],
})
export class GuardarProgressoPage {
    private readonly route = inject(ActivatedRoute);
    readonly retorno = retornoSeguro(this.route.snapshot.queryParamMap.get('retorno'), '/perfil');

    constructor() {
        addIcons({
            arrowBackOutline,
            arrowForwardOutline,
            cloudDoneOutline,
            phonePortraitOutline,
            schoolOutline,
            shieldCheckmarkOutline,
        });
    }
}
