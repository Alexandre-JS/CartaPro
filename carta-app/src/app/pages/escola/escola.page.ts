import { Component, OnInit } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonButton, IonContent, IonIcon } from '@ionic/angular/standalone';
import { addIcons } from 'ionicons';
import { arrowBackOutline, bookOutline, calendarOutline, schoolOutline } from 'ionicons/icons';
import { AppHeaderComponent } from '../../components/app-header/app-header.component';
import { SkeletonComponent } from '../../components/skeleton/skeleton.component';
import { EscolaMembership, EscolaService, EscolaTarefa } from '../../core/escola.service';

@Component({
    standalone: true,
    selector: 'app-escola',
    imports: [RouterLink, IonButton, IonContent, IonIcon, AppHeaderComponent, SkeletonComponent],
    templateUrl: './escola.page.html',
    styleUrls: ['./escola.page.scss'],
})
export class EscolaPage implements OnInit {
    memberships: EscolaMembership[] = [];
    tarefas: EscolaTarefa[] = [];
    carregando = true;
    erro = '';

    constructor(private readonly escola: EscolaService) {
        addIcons({ arrowBackOutline, bookOutline, calendarOutline, schoolOutline });
    }

    async ngOnInit(): Promise<void> {
        try {
            [this.memberships, this.tarefas] = await Promise.all([this.escola.memberships(), this.escola.tarefas()]);
        } catch {
            this.erro = 'Não foi possível carregar os dados da escola. Tenta novamente quando tiveres ligação.';
        } finally {
            this.carregando = false;
        }
    }

    get ativa(): EscolaMembership | undefined {
        return this.memberships.find((item) => item.status === 'active') || this.memberships[0];
    }

    tituloTarefa(tarefa: EscolaTarefa): string {
        return tarefa.assignment?.title || tarefa.assignment?.name || 'Tarefa de estudo';
    }
}
