import { Component, Input } from '@angular/core';

/**
 * Ícone de prova: volante, estrada e visto.
 *
 * Desenhado em SVG e não recortado do PNG da identidade visual para poder ser
 * tingido pelo estado da prova — verde aprovada, amarelo perto de passar,
 * vermelho reprovada, cinzento por fazer — sem precisar de uma imagem por
 * variante. O volante e o visto seguem `currentColor`; a estrada fica escura,
 * como na folha de identidade.
 *
 * Significado, da mesma folha: volante = condução, estrada = caminho de
 * aprendizagem, visto = aprovação.
 */
@Component({
    standalone: true,
    selector: 'app-icone-prova',
    templateUrl: './icone-prova.component.html',
    styleUrls: ['./icone-prova.component.scss'],
})
export class IconeProvaComponent {
    /** Esconde o visto — usado quando a prova ainda não foi feita. */
    @Input() comVisto = true;
}
