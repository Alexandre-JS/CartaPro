import { inject, Injectable } from '@angular/core';
import Dexie, { Table } from 'dexie';
import { Preferences } from '@capacitor/preferences';
import { Pacote } from '../models/pacote.model';
import {
    EstadoAcesso,
    HistoricoExame,
    RespostaRegisto,
    ResultadoGuardado,
    RevisaoAgendada,
    SimuladoEmCurso,
} from '../models/progresso.model';
import { ApiService } from './api.service';

interface SyncSnapshot {
    serverTime: string;
    cursor: string;
    incremental: boolean;
    answers: RespostaRegisto[];
    exams: HistoricoExame[];
    revisions: RevisaoAgendada[];
    readContents: string[];
    access: EstadoAcesso;
}

const CHAVE_CURSOR = 'syncCursor';
const CHAVE_LIDOS = 'conteudosLidos';
const CHAVE_LIDOS_PENDENTES = 'conteudosLidosPendentes';
const ATRASO_SYNC_MS = 8000;

class CartaDb extends Dexie {
    pacote!: Table<Pacote & { id: string }, string>;
    respostas!: Table<RespostaRegisto, number>;
    exames!: Table<HistoricoExame, number>;
    revisoes!: Table<RevisaoAgendada, string>;
    simulado!: Table<SimuladoEmCurso, string>;
    resultados!: Table<ResultadoGuardado & { id: string }, string>;

    constructor() {
        super('carta-app-db');
        this.version(1).stores({ pacote: 'id', respostas: '++id, perguntaId, tema, data' });
        this.version(2).stores({ pacote: 'id', respostas: '++id, perguntaId, tema, data', exames: '++id, numero, data' });
        this.version(3).stores({ pacote: 'id', respostas: '++id, perguntaId, tema, data', exames: '++id, numero, data', revisoes: 'perguntaId, tema, agendadaPara' });
        this.version(4).stores({ pacote: 'id', respostas: '++id, clientId, perguntaId, tema, data', exames: '++id, clientId, numero, data', revisoes: 'perguntaId, tema, agendadaPara' });
        // v5: marca de pendente para sincronização incremental + retoma de prova
        // e resultado persistido (antes o resultado vivia só em history.state).
        this.version(5)
            .stores({
                pacote: 'id',
                respostas: '++id, clientId, perguntaId, tema, data, pendente',
                exames: '++id, clientId, numero, data, pendente',
                revisoes: 'perguntaId, tema, agendadaPara, pendente',
                simulado: 'chave',
                resultados: 'id',
            })
            .upgrade(async (transaction) => {
                // Registos anteriores nunca foram confirmados por um sync
                // incremental: marcam-se como pendentes para subirem uma vez.
                await transaction.table('respostas').toCollection().modify({ pendente: 1 });
                await transaction.table('exames').toCollection().modify({ pendente: 1 });
                await transaction.table('revisoes').toCollection().modify({ pendente: 1 });
            });
    }
}

@Injectable({ providedIn: 'root' })
export class StorageService {
    private readonly api = inject(ApiService);
    private readonly db = new CartaDb();
    private temporizadorSync?: ReturnType<typeof setTimeout>;
    private syncEmCurso?: Promise<void>;

    // ---------------------------------------------------------------- pacote

    async guardarPacote(pacote: Pacote): Promise<void> {
        await this.db.pacote.put({ ...pacote, id: 'ativo' });
    }

    async obterPacote(): Promise<Pacote | null> {
        const pacote = await this.db.pacote.get('ativo');
        if (!pacote) {
            return null;
        }
        const { id, ...dados } = pacote;
        return dados;
    }

    // --------------------------------------------------------------- registos

    async registarResposta(registo: RespostaRegisto): Promise<void> {
        await this.db.respostas.add({
            ...registo,
            clientId: registo.clientId || this.novoId(),
            pendente: 1,
        });
        this.agendarSync();
    }

    async listarRespostas(): Promise<RespostaRegisto[]> {
        return this.db.respostas.orderBy('data').toArray();
    }

    async obterRevisao(perguntaId: string): Promise<RevisaoAgendada | undefined> {
        return this.db.revisoes.get(perguntaId);
    }

    async guardarRevisao(revisao: RevisaoAgendada): Promise<void> {
        await this.db.revisoes.put({ ...revisao, pendente: 1 });
        this.agendarSync();
    }

    async listarRevisoesPendentes(agora = Date.now()): Promise<RevisaoAgendada[]> {
        return this.db.revisoes.where('agendadaPara').belowOrEqual(agora).sortBy('agendadaPara');
    }

    async contarRevisoesPendentes(agora = Date.now()): Promise<number> {
        return this.db.revisoes.where('agendadaPara').belowOrEqual(agora).count();
    }

    async registarExame(exame: HistoricoExame): Promise<void> {
        await this.db.exames.add({ ...exame, clientId: exame.clientId || this.novoId(), pendente: 1 });
        this.agendarSync();
    }

    async listarExames(): Promise<HistoricoExame[]> {
        return this.db.exames.orderBy('data').reverse().toArray();
    }

    // ------------------------------------------------- prova em curso/resultado

    async guardarSimuladoEmCurso(estado: SimuladoEmCurso): Promise<void> {
        await this.db.simulado.put({ ...estado, guardadoEm: Date.now() });
    }

    async obterSimuladoEmCurso(chave: string): Promise<SimuladoEmCurso | undefined> {
        return this.db.simulado.get(chave);
    }

    async limparSimuladoEmCurso(chave: string): Promise<void> {
        await this.db.simulado.delete(chave);
    }

    /** Resultado do último exame — sobrevive a recarregar a página. */
    async guardarUltimoResultado(resultado: ResultadoGuardado): Promise<void> {
        await this.db.resultados.put({ ...resultado, id: 'ultimo' });
    }

    async obterUltimoResultado(): Promise<ResultadoGuardado | undefined> {
        const guardado = await this.db.resultados.get('ultimo');
        if (!guardado) {
            return undefined;
        }
        const { id, ...dados } = guardado;
        return dados;
    }

    // ------------------------------------------------------------ preferências

    async guardarCategoria(categoria: string): Promise<void> {
        await Preferences.set({ key: 'categoriaCarta', value: categoria });
    }

    async obterCategoria(): Promise<string | null> {
        return (await Preferences.get({ key: 'categoriaCarta' })).value;
    }

    async marcarConteudoLido(conteudoId: string): Promise<void> {
        const lidos = await this.listarConteudosLidos();
        if (lidos.includes(conteudoId)) {
            return;
        }

        await Preferences.set({ key: CHAVE_LIDOS, value: JSON.stringify([...lidos, conteudoId]) });

        const pendentes = await this.listarConteudosLidosPendentes();
        await Preferences.set({ key: CHAVE_LIDOS_PENDENTES, value: JSON.stringify([...pendentes, conteudoId]) });

        this.agendarSync();
    }

    async listarConteudosLidos(): Promise<string[]> {
        const { value } = await Preferences.get({ key: CHAVE_LIDOS });
        return value ? JSON.parse(value) : [];
    }

    async guardarEstadoAcesso(estado: EstadoAcesso): Promise<void> {
        await Preferences.set({ key: 'estadoAcesso', value: JSON.stringify(estado) });
    }

    async obterEstadoAcesso(): Promise<EstadoAcesso> {
        const { value } = await Preferences.get({ key: 'estadoAcesso' });
        return value ? JSON.parse(value) : { plano: 'gratis' };
    }

    // ------------------------------------------------------------ sincronização

    /**
     * Agenda um envio com atraso.
     *
     * Antes cada resposta gravada disparava imediatamente um sync que enviava o
     * histórico **completo** — tráfego quadrático, pago pelo aluno em dados
     * móveis. Agora agrupa-se num único envio por sessão de estudo.
     */
    private agendarSync(): void {
        if (this.temporizadorSync) {
            clearTimeout(this.temporizadorSync);
        }
        this.temporizadorSync = setTimeout(() => {
            void this.sincronizar().catch(() => undefined);
        }, ATRASO_SYNC_MS);
    }

    /** Força o envio imediato do que está pendente (ex.: ao fim de um exame). */
    async sincronizarAgora(): Promise<void> {
        if (this.temporizadorSync) {
            clearTimeout(this.temporizadorSync);
            this.temporizadorSync = undefined;
        }
        await this.sincronizar();
    }

    /**
     * Sincronização incremental: envia apenas registos pendentes e pede ao
     * servidor apenas o que mudou desde o cursor. Nunca apaga dados locais.
     */
    async sincronizar(): Promise<void> {
        // Serializa: dois syncs concorrentes podiam sobrepor-se e, no modelo
        // antigo de apagar-e-repor, perder respostas gravadas entretanto.
        if (this.syncEmCurso) {
            return this.syncEmCurso;
        }

        this.syncEmCurso = this.executarSync().finally(() => {
            this.syncEmCurso = undefined;
        });

        return this.syncEmCurso;
    }

    private async executarSync(): Promise<void> {
        const [respostas, exames, revisoes, lidosPendentes, cursor] = await Promise.all([
            this.db.respostas.where('pendente').equals(1).toArray(),
            this.db.exames.where('pendente').equals(1).toArray(),
            this.db.revisoes.where('pendente').equals(1).toArray(),
            this.listarConteudosLidosPendentes(),
            this.obterCursor(),
        ]);

        const semAlteracoes = !respostas.length && !exames.length && !revisoes.length && !lidosPendentes.length;
        if (semAlteracoes && cursor) {
            return;
        }

        const snapshot = await this.api.post<SyncSnapshot>(
            'mobile/sync',
            {
                since: cursor,
                answers: respostas.map(({ id, pendente, ...resto }) => resto),
                exams: exames.map(({ id, pendente, ...resto }) => resto),
                revisions: revisoes.map(({ pendente, ...resto }) => resto),
                readContents: lidosPendentes,
            },
            true,
        );

        // Confirmado pelo servidor: deixa de estar pendente.
        await this.db.transaction('rw', this.db.respostas, this.db.exames, this.db.revisoes, async () => {
            if (respostas.length) {
                await this.db.respostas.bulkPut(respostas.map((registo) => ({ ...registo, pendente: 0 as const })));
            }
            if (exames.length) {
                await this.db.exames.bulkPut(exames.map((registo) => ({ ...registo, pendente: 0 as const })));
            }
            if (revisoes.length) {
                await this.db.revisoes.bulkPut(revisoes.map((registo) => ({ ...registo, pendente: 0 as const })));
            }
        });

        await Preferences.remove({ key: CHAVE_LIDOS_PENDENTES });
        await this.fundirSnapshot(snapshot);
        await Preferences.set({ key: CHAVE_CURSOR, value: snapshot.cursor });
    }

    /** Descarga completa — usada ao entrar na conta num dispositivo novo. */
    async baixarSnapshot(): Promise<void> {
        const snapshot = await this.api.get<SyncSnapshot>('mobile/snapshot', true);
        await this.fundirSnapshot(snapshot);
        await Preferences.set({ key: CHAVE_CURSOR, value: snapshot.cursor });
    }

    async prepararDadosDaConta(userId: number): Promise<void> {
        const { value } = await Preferences.get({ key: 'mobileCacheUserId' });
        if (value === String(userId)) {
            return;
        }

        // Conta diferente neste dispositivo: aqui limpar é correto.
        await this.db.transaction('rw', this.db.respostas, this.db.exames, this.db.revisoes, this.db.simulado, this.db.resultados, async () => {
            await Promise.all([
                this.db.respostas.clear(),
                this.db.exames.clear(),
                this.db.revisoes.clear(),
                this.db.simulado.clear(),
                this.db.resultados.clear(),
            ]);
        });

        for (const chave of [CHAVE_LIDOS, CHAVE_LIDOS_PENDENTES, 'estadoAcesso', 'categoriaCarta', CHAVE_CURSOR]) {
            await Preferences.remove({ key: chave });
        }

        await Preferences.set({ key: 'mobileCacheUserId', value: String(userId) });
    }

    /**
     * Funde as alterações do servidor com o que existe localmente.
     *
     * A versão anterior fazia `clear()` seguido de `bulkAdd()`: qualquer
     * resposta gravada entre as duas operações desaparecia.
     */
    private async fundirSnapshot(snapshot: SyncSnapshot): Promise<void> {
        await this.db.transaction('rw', this.db.respostas, this.db.exames, this.db.revisoes, async () => {
            for (const resposta of snapshot.answers ?? []) {
                const existente = resposta.clientId
                    ? await this.db.respostas.where('clientId').equals(resposta.clientId).first()
                    : undefined;

                // Não sobrepõe um registo ainda pendente de envio.
                if (existente?.pendente === 1) {
                    continue;
                }

                if (existente) {
                    await this.db.respostas.update(existente.id!, { ...resposta, pendente: 0 });
                } else {
                    await this.db.respostas.add({ ...resposta, pendente: 0 });
                }
            }

            for (const exame of snapshot.exams ?? []) {
                const existente = exame.clientId
                    ? await this.db.exames.where('clientId').equals(exame.clientId).first()
                    : undefined;

                if (existente?.pendente === 1) {
                    continue;
                }

                if (existente) {
                    await this.db.exames.update(existente.id!, { ...exame, pendente: 0 });
                } else {
                    await this.db.exames.add({ ...exame, pendente: 0 });
                }
            }

            for (const revisao of snapshot.revisions ?? []) {
                const existente = await this.db.revisoes.get(revisao.perguntaId);
                if (existente?.pendente === 1) {
                    continue;
                }
                await this.db.revisoes.put({ ...revisao, pendente: 0 });
            }
        });

        if (snapshot.readContents?.length) {
            const locais = await this.listarConteudosLidos();
            const juntos = [...new Set([...locais, ...snapshot.readContents])];
            await Preferences.set({ key: CHAVE_LIDOS, value: JSON.stringify(juntos) });
        }

        if (snapshot.access) {
            // O plano vem sempre do servidor — nunca é decidido localmente.
            await this.guardarEstadoAcesso({ ...snapshot.access, verificadoEm: Date.now() });
        }
    }

    private async obterCursor(): Promise<string | null> {
        return (await Preferences.get({ key: CHAVE_CURSOR })).value;
    }

    private async listarConteudosLidosPendentes(): Promise<string[]> {
        const { value } = await Preferences.get({ key: CHAVE_LIDOS_PENDENTES });
        return value ? JSON.parse(value) : [];
    }

    private novoId(): string {
        return typeof crypto !== 'undefined' && 'randomUUID' in crypto
            ? crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
    }
}
