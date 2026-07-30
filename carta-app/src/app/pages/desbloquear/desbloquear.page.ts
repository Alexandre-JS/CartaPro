import { NgIf, SlicePipe } from '@angular/common';
import { Component, inject, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { IonButton, IonContent, IonHeader, IonInput, IonItem, IonLabel, IonText, IonTitle, IonToolbar } from '@ionic/angular/standalone';
import { AcessoService } from '../../core/acesso.service';
import { mensagemDeErro } from '../../core/erros-api';
import { ContentService } from '../../core/content.service';
import { DesbloqueioService } from '../../core/desbloqueio.service';
import { PerfilService } from '../../core/perfil.service';
import { EstadoAcesso } from '../../models/progresso.model';

type Etapa = 'estado' | 'codigo' | 'ativo';

/**
 * Ativação do plano completo.
 *
 * O aluno já não escreve um número: usa-se o da conta e confirma-se por SMS.
 * Antes qualquer pessoa podia introduzir o número de quem pagou e obter acesso.
 */
@Component({
    standalone: true,
    selector: 'app-desbloquear',
    imports: [FormsModule, NgIf, SlicePipe, RouterLink, IonButton, IonContent, IonHeader, IonInput, IonItem, IonLabel, IonText, IonTitle, IonToolbar],
    templateUrl: './desbloquear.page.html',
    styleUrls: ['./desbloquear.page.scss'],
})
export class DesbloquearPage implements OnInit {
    private readonly desbloqueio = inject(DesbloqueioService);
    private readonly acesso = inject(AcessoService);
    private readonly perfil = inject(PerfilService);
    private readonly content = inject(ContentService);

    etapa: Etapa = 'estado';
    telefone = '';
    codigo = '';
    ocupado = false;
    mensagemErro = '';
    mensagemInfo = '';
    estado: EstadoAcesso = { plano: 'gratis' };
    perguntasBloqueadas = 0;

    async ngOnInit(): Promise<void> {
        const [perfilAtual, bloqueado] = await Promise.all([
            this.perfil.obter(),
            this.acesso.conteudoBloqueado(),
        ]);

        this.telefone = perfilAtual.telefone || '';
        this.perguntasBloqueadas = bloqueado.total;

        this.estado = await this.desbloqueio.revalidar();
        this.etapa = this.estado.plano === 'pago' ? 'ativo' : 'estado';

        if (this.estado.pagamentoPorReclamar) {
            this.mensagemInfo = 'Encontrámos um pagamento associado ao teu número. Confirma o código para ativar.';
        }
    }

    async pedirCodigo(): Promise<void> {
        this.ocupado = true;
        this.mensagemErro = '';
        this.mensagemInfo = '';

        try {
            const resposta = await this.desbloqueio.pedirCodigo();

            if (resposta.estado === 'ja_ativo') {
                await this.concluir();
                return;
            }

            this.etapa = 'codigo';
            this.mensagemInfo = `Enviámos um código para ${resposta.telefone}. É válido ${resposta.expiraEmMinutos ?? 10} minutos.`;
        } catch (erro: any) {
            this.mensagemErro = this.mensagemDeErro(erro);
        } finally {
            this.ocupado = false;
        }
    }

    async confirmar(): Promise<void> {
        this.ocupado = true;
        this.mensagemErro = '';

        try {
            this.estado = await this.desbloqueio.confirmarCodigo(this.codigo);
            await this.concluir();
        } catch (erro: any) {
            this.mensagemErro = this.mensagemDeErro(erro);
        } finally {
            this.ocupado = false;
        }
    }

    private async concluir(): Promise<void> {
        // Novo plano = novo pacote: é o servidor que decide o que enviar.
        await this.content.atualizarPacote();
        this.estado = await this.desbloqueio.obterEstado();
        this.etapa = 'ativo';
        this.mensagemInfo = 'Plano completo ativado neste número.';
    }

    private mensagemDeErro(erro: any): string {
        return mensagemDeErro(erro, 'Não foi possível concluir a operação. Tenta novamente.');
    }
}
