import { Component } from '@angular/core';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { chevronBackOutline, chevronForwardOutline, documentTextOutline, libraryOutline } from 'ionicons/icons';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';

@Component({
    standalone: true,
    selector: 'app-biblioteca',
    imports: [IonContent, IonIcon, AppHeaderComponent, BottomNavComponent],
    templateUrl: './biblioteca.page.html',
    styleUrls: ['./biblioteca.page.scss'],
})
export class BibliotecaPage {
    constructor() {
        addIcons({ chevronBackOutline, chevronForwardOutline, documentTextOutline, libraryOutline });
    }
}
