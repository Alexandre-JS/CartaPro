import { Component, Input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { bookOutline, clipboardOutline, createOutline, homeOutline, statsChartOutline } from 'ionicons/icons';

export type SeccaoNav = 'inicio' | 'exames' | 'estudos' | 'desempenho' | 'perfil' | 'praticar';

/**
 * Barra de navegação principal.
 *
 * Estava copiada em dez templates, e as cópias divergiram: umas usavam
 * `home`/`create-outline`, outras `home-outline`/`document-text-outline`, pelo
 * que os ícones mudavam de forma ao navegar entre ecrãs. Uma só definição.
 */
@Component({
    standalone: true,
    selector: 'app-bottom-nav',
    imports: [RouterLink, IonIcon],
    templateUrl: './bottom-nav.component.html',
    styleUrls: ['./bottom-nav.component.scss'],
})
export class BottomNavComponent {
    /** Secção destacada. As páginas de detalhe herdam a secção do seu ramo. */
    @Input({ required: true }) ativo!: SeccaoNav;

    constructor() {
        addIcons({ bookOutline, clipboardOutline, createOutline, homeOutline, statsChartOutline });
    }
}
