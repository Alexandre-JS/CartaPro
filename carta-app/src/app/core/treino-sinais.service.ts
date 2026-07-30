import { inject, Injectable } from '@angular/core';
import { Pergunta } from '../models/pergunta.model';
import { SinalTransito } from '../models/material-estudo.model';
import { embaralhar } from './aleatorio';
import { MaterialEstudoService } from './material-estudo.service';
import { StorageService } from './storage.service';

/** Nº de opções apresentadas em cada pergunta de reconhecimento. */
const OPCOES_POR_PERGUNTA = 4;

/**
 * Treino de reconhecimento de sinais.
 *
 * O reconhecimento de sinalização é uma parte grande do exame e não havia
 * forma de o treinar: dependia inteiramente de alguém ter criado perguntas com
 * imagem no painel. Aqui as perguntas são geradas da biblioteca de sinais.
 *
 * As perguntas geradas usam ids com o prefixo `sinal:` para não colidirem com
 * as do banco, e alimentam o mesmo motor de progresso e de repetição espaçada.
 */
@Injectable({ providedIn: 'root' })
export class TreinoSinaisService {
    private readonly material = inject(MaterialEstudoService);
    private readonly storage = inject(StorageService);

    /** Identificador da pergunta gerada para um sinal. */
    static idPergunta(slug: string): string {
        return `sinal:${slug}`;
    }

    static ehPerguntaDeSinal(perguntaId: string): boolean {
        return perguntaId.startsWith('sinal:');
    }

    static slugDaPergunta(perguntaId: string): string {
        return perguntaId.replace(/^sinal:/, '');
    }

    /**
     * Monta uma sessão de treino.
     *
     * @param categoria  limita a uma categoria de sinalização (opcional)
     * @param tamanho    número de perguntas
     */
    async montarSessao(categoria?: string, tamanho = 10): Promise<Pergunta[]> {
        const todos = await this.material.sinais();
        const elegiveis = (categoria ? todos.filter((sinal) => sinal.categoria === categoria) : todos)
            .filter((sinal) => sinal.imagem && sinal.significado);

        // Precisa de pelo menos duas opções distintas para haver pergunta.
        if (elegiveis.length < 2) {
            return [];
        }

        const priorizados = await this.priorizarPorDesempenho(elegiveis);

        return priorizados
            .slice(0, Math.min(tamanho, priorizados.length))
            .map((sinal) => this.criarPergunta(sinal, todos));
    }

    /** Sessão focada nos sinais que o aluno ainda não acertou. */
    async montarSessaoDeReforco(tamanho = 10): Promise<Pergunta[]> {
        const todos = await this.material.sinais();
        const elegiveis = todos.filter((sinal) => sinal.imagem && sinal.significado);
        const falhados = await this.slugsFalhados();

        const alvo = elegiveis.filter((sinal) => falhados.has(sinal.slug));
        const base = alvo.length ? alvo : elegiveis;

        return embaralhar(base)
            .slice(0, Math.min(tamanho, base.length))
            .map((sinal) => this.criarPergunta(sinal, todos));
    }

    /** Quantos sinais têm erros registados — alimenta o aviso no ecrã. */
    async totalParaReforcar(): Promise<number> {
        return (await this.slugsFalhados()).size;
    }

    /**
     * Reconstrói a pergunta de um sinal a partir do seu id.
     *
     * As perguntas de sinais não existem no banco: são geradas. Sem isto, uma
     * revisão agendada para um sinal ficava agendada para sempre porque a fila
     * de revisões não conseguia resolver o id.
     */
    async perguntaDoSinal(perguntaId: string): Promise<Pergunta | undefined> {
        if (!TreinoSinaisService.ehPerguntaDeSinal(perguntaId)) {
            return undefined;
        }

        const todos = await this.material.sinais();
        const sinal = todos.find((item) => item.slug === TreinoSinaisService.slugDaPergunta(perguntaId));

        if (!sinal || !sinal.significado || todos.length < 2) {
            return undefined;
        }

        return this.criarPergunta(sinal, todos);
    }

    /**
     * Transforma um sinal numa pergunta de escolha múltipla.
     *
     * Os distratores vêm preferencialmente da mesma categoria: dizer que um
     * triângulo de perigo pode ser confundido com uma proibição circular não
     * treina nada — o que engana é o sinal parecido.
     */
    private criarPergunta(sinal: SinalTransito, universo: SinalTransito[]): Pergunta {
        const distratores = this.escolherDistratores(sinal, universo);
        const opcoes = embaralhar([sinal.significado, ...distratores.map((item) => item.significado)]);

        return {
            id: TreinoSinaisService.idPergunta(sinal.slug),
            tipo: 'teorico',
            tema: sinal.tema || 'sinalizacao',
            categoriaCarta: ['ligeiro', 'pesado', 'profissional_publico'],
            enunciado: 'O que indica este sinal?',
            imagem: sinal.imagem,
            opcoes,
            correta: opcoes.indexOf(sinal.significado),
            explicacao: sinal.descricao
                ? `${sinal.nome}. ${sinal.descricao}`
                : `${sinal.nome}: ${sinal.significado}`,
            artigoRef: sinal.artigoRef ?? null,
            bloqueado: sinal.bloqueado,
        };
    }

    private escolherDistratores(sinal: SinalTransito, universo: SinalTransito[]): SinalTransito[] {
        const outros = universo.filter(
            (item) => item.slug !== sinal.slug && item.significado && item.significado !== sinal.significado,
        );

        const mesmaCategoria = embaralhar(outros.filter((item) => item.categoria === sinal.categoria));
        const restantes = embaralhar(outros.filter((item) => item.categoria !== sinal.categoria));

        const escolhidos: SinalTransito[] = [];
        const usados = new Set<string>();

        for (const candidato of [...mesmaCategoria, ...restantes]) {
            if (escolhidos.length >= OPCOES_POR_PERGUNTA - 1) {
                break;
            }
            // Dois sinais podem ter significados equivalentes: evita opções repetidas.
            if (usados.has(candidato.significado)) {
                continue;
            }
            usados.add(candidato.significado);
            escolhidos.push(candidato);
        }

        return escolhidos;
    }

    /**
     * Ordena os sinais dando prioridade aos nunca vistos e aos falhados.
     */
    private async priorizarPorDesempenho(sinais: SinalTransito[]): Promise<SinalTransito[]> {
        const respostas = await this.storage.listarRespostas();

        const acertos = new Map<string, number>();
        const erros = new Map<string, number>();

        for (const resposta of respostas) {
            if (!TreinoSinaisService.ehPerguntaDeSinal(resposta.perguntaId)) {
                continue;
            }
            const slug = TreinoSinaisService.slugDaPergunta(resposta.perguntaId);
            const mapa = resposta.acertou ? acertos : erros;
            mapa.set(slug, (mapa.get(slug) ?? 0) + 1);
        }

        // 0 = nunca visto, 1 = já falhou, 2 = já acertou. Ordena por prioridade
        // e embaralha dentro de cada grupo para não repetir a mesma sequência.
        const grupos: SinalTransito[][] = [[], [], []];

        for (const sinal of sinais) {
            const errou = erros.get(sinal.slug) ?? 0;
            const acertou = acertos.get(sinal.slug) ?? 0;

            if (!errou && !acertou) {
                grupos[0].push(sinal);
            } else if (errou >= acertou) {
                grupos[1].push(sinal);
            } else {
                grupos[2].push(sinal);
            }
        }

        return [...embaralhar(grupos[0]), ...embaralhar(grupos[1]), ...embaralhar(grupos[2])];
    }

    private async slugsFalhados(): Promise<Set<string>> {
        const respostas = await this.storage.listarRespostas();
        const acertos = new Map<string, number>();
        const erros = new Map<string, number>();

        for (const resposta of respostas) {
            if (!TreinoSinaisService.ehPerguntaDeSinal(resposta.perguntaId)) {
                continue;
            }
            const slug = TreinoSinaisService.slugDaPergunta(resposta.perguntaId);
            const mapa = resposta.acertou ? acertos : erros;
            mapa.set(slug, (mapa.get(slug) ?? 0) + 1);
        }

        const falhados = new Set<string>();
        for (const [slug, vezes] of erros) {
            if (vezes >= (acertos.get(slug) ?? 0)) {
                falhados.add(slug);
            }
        }

        return falhados;
    }
}
