import { inject, Injectable } from '@angular/core';
import { CategoriaCarta, Pergunta, TipoPergunta } from '../models/pergunta.model';
import { Pacote, RegrasPacote, TemaDetalhe } from '../models/pacote.model';
import { StorageService } from './storage.service';
import { ApiService } from './api.service';

@Injectable({ providedIn: 'root' })
export class ContentService {
    private readonly api = inject(ApiService);
    private readonly storage = inject(StorageService);
    private pacote: Pacote | null = null;
    private sincronizacao?: Promise<Pacote>;

    async carregarPacote(): Promise<Pacote> {
        if (this.pacote) {
            return this.pacote;
        }

        this.sincronizacao ??= this.sincronizar();
        return this.sincronizacao;
    }

    async atualizarPacote(): Promise<Pacote> {
        this.sincronizacao = this.sincronizar(true);
        return this.sincronizacao;
    }

    async listarTemas(): Promise<string[]> {
        return (await this.carregarPacote()).temas;
    }

    async listarTemasDetalhe(): Promise<TemaDetalhe[]> {
        const pacote = await this.carregarPacote();

        if (pacote.temasDetalhe?.length) {
            return pacote.temasDetalhe;
        }

        // Pacote anterior à inclusão dos nomes: devolve os slugs conhecidos.
        return pacote.temas.map((slug) => ({ slug, nome: slug.replace(/_/g, ' ') }));
    }

    async obterRegras(): Promise<RegrasPacote | undefined> {
        return (await this.carregarPacote()).regras;
    }

    /** Plano com que o servidor entregou este pacote. */
    async planoDoPacote(): Promise<'gratis' | 'pago'> {
        return (await this.carregarPacote()).plano ?? 'gratis';
    }

    /**
     * Quantas perguntas ficam por trás do cadeado, por tema.
     *
     * O servidor não envia o conteúdo bloqueado a quem não pagou — envia só a
     * contagem, que é o que o app precisa para mostrar os cadeados.
     */
    async bloqueadasPorTema(): Promise<Record<string, number>> {
        return (await this.carregarPacote()).bloqueadasPorTema ?? {};
    }

    async totalBloqueadas(): Promise<number> {
        return (await this.carregarPacote()).totalBloqueadas ?? 0;
    }

    /**
     * Perguntas disponíveis para esta conta.
     *
     * Já não existe `incluirBloqueadas`: era um filtro que nunca chegou a ser
     * usado por nenhum chamador, o que tornava as perguntas bloqueadas
     * invisíveis para todos — inclusive para quem tinha pago. Hoje quem decide
     * o que entra no pacote é o servidor, em função do plano.
     */
    async listarPerguntas(filtros: { tema?: string; tipo?: TipoPergunta; categoria?: CategoriaCarta } = {}): Promise<Pergunta[]> {
        const pacote = await this.carregarPacote();

        return pacote.perguntas.filter((pergunta) => {
            const temaOk = !filtros.tema || pergunta.tema === filtros.tema;
            const tipoOk = !filtros.tipo || pergunta.tipo === filtros.tipo;
            const categoriaOk = !filtros.categoria || pergunta.categoriaCarta.includes(filtros.categoria);
            return temaOk && tipoOk && categoriaOk;
        });
    }

    async obterPergunta(id: string): Promise<Pergunta | undefined> {
        return (await this.carregarPacote()).perguntas.find((pergunta) => pergunta.id === id);
    }

    private async sincronizar(forcar = false): Promise<Pacote> {
        const guardado = await this.storage.obterPacote();

        try {
            // O pedido é autenticado: o pacote transporta a resposta correta e
            // a explicação de cada pergunta, e o seu conteúdo depende do plano.
            const remoto = this.normalizarPacote(await this.api.get<Pacote>('content-package', true));
            this.validarPacote(remoto);

            const mudou = forcar || !guardado || remoto.versao !== guardado.versao || remoto.plano !== guardado.plano;
            if (mudou) {
                await this.storage.guardarPacote(remoto);
            }

            this.pacote = remoto;
            return remoto;
        } catch (erro) {
            console.warn('CartaPro: API indisponível ou pacote inválido; a usar conteúdo offline.', erro);
            if (!guardado) {
                throw new Error('É necessária ligação à API para o primeiro carregamento.');
            }
            this.pacote = guardado;
            return guardado;
        }
    }

    private normalizarPacote(pacote: Pacote): Pacote {
        return {
            ...pacote,
            temas: [...new Set(pacote.temas || [])],
            temasDetalhe: pacote.temasDetalhe || [],
            perguntas: (pacote.perguntas || []).map((pergunta) => ({
                ...pergunta,
                imagem: this.api.absoluteAssetUrl(pergunta.imagem),
            })),
            provas: (pacote.provas || []).map((prova) => ({
                ...prova,
                perguntas: (prova.perguntas || []).map((pergunta) => ({ ...pergunta, imagem: this.api.absoluteAssetUrl(pergunta.imagem) })),
            })),
            /*
             * As imagens dos sinais escapavam a esta reescrita e chegavam com o
             * host do APP_URL do servidor — que em desenvolvimento não é o
             * mesmo por onde a API é servida, e no emulador Android nunca é.
             * Resultado: nenhuma imagem de sinal carregava.
             */
            estudo: pacote.estudo && {
                ...pacote.estudo,
                sinais: (pacote.estudo.sinais || []).map((sinal) => ({
                    ...sinal,
                    imagem: this.api.absoluteAssetUrl(sinal.imagem ?? null),
                })),
            },
        };
    }

    private validarPacote(pacote: Pacote): void {
        if (!pacote?.versao || !Array.isArray(pacote.temas) || !Array.isArray(pacote.perguntas)) {
            throw new Error('Contrato do pacote incompatível com o aplicativo.');
        }
        for (const pergunta of pacote.perguntas) {
            if (!pergunta.id || !pergunta.tema || !Array.isArray(pergunta.opcoes) || pergunta.correta < 0 || pergunta.correta >= pergunta.opcoes.length) {
                throw new Error(`Pergunta inválida no pacote: ${pergunta.id || 'sem identificador'}.`);
            }
        }
        if (!Array.isArray(pacote.provas)) {
            throw new Error('Catálogo de provas incompatível com o aplicativo.');
        }
    }
}
