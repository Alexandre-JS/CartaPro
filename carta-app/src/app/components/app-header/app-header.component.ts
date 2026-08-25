import { Component, Input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { chevronBackOutline, personCircleOutline } from 'ionicons/icons';

@Component({
    standalone: true,
    selector: 'app-app-header',
    imports: [RouterLink, IonIcon],
    templateUrl: './app-header.component.html',
    styleUrls: ['./app-header.component.scss'],
})
export class AppHeaderComponent {
    @Input() titulo = '';
    @Input() subtitulo = '';
    @Input() voltarPara?: string;
    @Input() mostrarPerfil = true;

    constructor() {
        addIcons({ chevronBackOutline, personCircleOutline });
    }
}
