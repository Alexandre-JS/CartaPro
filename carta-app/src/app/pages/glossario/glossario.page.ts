import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon, IonSearchbar } from '@ionic/angular/standalone';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { addIcons } from 'ionicons';
import { arrowBackOutline, bookOutline, chevronDownOutline, chevronForwardOutline, documentTextOutline, textOutline } from 'ionicons/icons';
import { MaterialEstudoService } from '../../core/material-estudo.service';
import { normalizarTexto } from '../../core/texto';
import { TermoGlossario } from '../../models/material-estudo.model';

interface LetraGlossario {
    letra: string;
    termos: TermoGlossario[];
}

/**
 * Glossário de termos.
 *
 * Muitas perguntas do exame falham não por desconhecimento das regras mas do
 * vocabulário legal ("cedência de passagem", "via reservada", "paragem"). Aqui
 * o aluno tem as definições sem ter de ler o artigo inteiro.
 */
@Component({
    standalone: true,
    selector: 'app-glossario',
    imports: [FormsModule, RouterLink, IonContent, IonIcon, IonSearchbar, SkeletonComponent],
    templateUrl: './glossario.page.html',
    styleUrls: ['./glossario.page.scss'],
})
export class GlossarioPage implements OnInit {
    termos: TermoGlossario[] = [];
    pesquisa = '';
    aberto: string | null = null;
    carregando = true;

    constructor(private readonly material: MaterialEstudoService) {
        addIcons({ arrowBackOutline, bookOutline, chevronDownOutline, chevronForwardOutline, documentTextOutline, textOutline });
    }

    async ngOnInit(): Promise<void> {
        this.termos = await this.material.glossario();
        this.carregando = false;
    }

    get filtrados(): TermoGlossario[] {
        const alvo = normalizarTexto(this.pesquisa);

        if (!alvo) {
            return this.termos;
        }

        return this.termos.filter((termo) => normalizarTexto(`${termo.termo} ${termo.definicao}`).includes(alvo));
    }

    /** Agrupado por inicial: é assim que se procura num glossário. */
    get porLetra(): LetraGlossario[] {
        const grupos = new Map<string, TermoGlossario[]>();

        for (const termo of this.filtrados) {
            const letra = (normalizarTexto(termo.termo)[0] || '#').toLocaleUpperCase('pt');
            const lista = grupos.get(letra) ?? [];
            lista.push(termo);
            grupos.set(letra, lista);
        }

        return Array.from(grupos.entries())
            .map(([letra, termos]) => ({ letra, termos }))
            .sort((a, b) => a.letra.localeCompare(b.letra, 'pt'));
    }

    alternar(slug: string): void {
        this.aberto = this.aberto === slug ? null : slug;
    }
}
