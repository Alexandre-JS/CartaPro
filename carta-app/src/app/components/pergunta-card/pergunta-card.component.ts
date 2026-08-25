import { Component, EventEmitter, inject, Input, Output } from '@angular/core';
import { IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { checkmarkCircle, closeCircle } from 'ionicons/icons';
import { Pergunta } from '../../models/pergunta.model';
import { TemasService } from '../../core/temas.service';

@Component({
    selector: 'app-pergunta-card',
    standalone: true,
    imports: [IonIcon],
    templateUrl: './pergunta-card.component.html',
    styleUrls: ['./pergunta-card.component.scss'],
})
export class PerguntaCardComponent {
    readonly letras = ['A', 'B', 'C', 'D', 'E', 'F'];
    @Input({ required: true }) pergunta!: Pergunta;
    @Input() respondida = false;
    @Input() escolhida: number | null = null;
    /** Em exames a correção só aparece no resultado final. */
    @Input() mostrarFeedback = true;
    @Output() escolha = new EventEmitter<number>();

    private readonly temas = inject(TemasService);

    constructor() {
        addIcons({ checkmarkCircle, closeCircle });
    }

    get temaLegivel(): string {
        return this.temas.nome(this.pergunta.tema);
    }
}
