import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { chevronBackOutline, chevronForwardOutline, documentTextOutline, libraryOutline, textOutline, warningOutline } from 'ionicons/icons';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { BottomNavComponent } from '../../components/bottom-nav/bottom-nav.component';
import { MaterialEstudoService } from '../../core/material-estudo.service';

interface MaterialLink { titulo: string; descricao: string; total: number; rota: string; icone: string; }

@Component({
    standalone: true,
    selector: 'app-biblioteca',
    imports: [RouterLink, IonContent, IonIcon, AppHeaderComponent, BottomNavComponent],
    templateUrl: './biblioteca.page.html',
    styleUrls: ['./biblioteca.page.scss'],
})
export class BibliotecaPage implements OnInit {
    materiais: MaterialLink[] = [];

    constructor(private readonly material: MaterialEstudoService) {
        addIcons({ chevronBackOutline, chevronForwardOutline, documentTextOutline, libraryOutline, textOutline, warningOutline });
    }

    async ngOnInit(): Promise<void> {
        const [estudo] = await Promise.all([this.material.carregar()]);
        this.materiais = [
            { titulo: 'Sinais de trânsito', descricao: 'Reconhecer pela forma, cor e significado', total: estudo.sinais.length, rota: '/sinais', icone: 'warning-outline' },
            { titulo: 'Fichas por tema', descricao: 'Regras, condução, primeiros socorros e mecânica', total: estudo.licoes.length, rota: '/licoes', icone: 'library-outline' },
            { titulo: 'Código da Estrada', descricao: 'Texto legal organizado por capítulos', total: estudo.artigos.length, rota: '/codigo', icone: 'document-text-outline' },
            { titulo: 'Glossário', descricao: 'Vocabulário usado nas perguntas', total: estudo.glossario.length, rota: '/glossario', icone: 'text-outline' },
        ].filter((item) => item.total > 0);
    }
}
