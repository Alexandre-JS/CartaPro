import { amostrarPonderado, embaralhar } from './aleatorio';

describe('embaralhar (Fisher-Yates)', () => {
    it('preserva todos os elementos sem duplicar', () => {
        const original = Array.from({ length: 50 }, (_, i) => i);
        const misturado = embaralhar(original);

        expect(misturado.length).toBe(original.length);
        expect([...misturado].sort((a, b) => a - b)).toEqual(original);
    });

    it('não altera o array de entrada', () => {
        const original = [1, 2, 3, 4, 5];
        embaralhar(original);
        expect(original).toEqual([1, 2, 3, 4, 5]);
    });

    /*
     * Regressão do defeito C3: `sort(() => Math.random() - 0.5)` não é uniforme.
     * Medido com 9 itens, o primeiro aparecia na 1.ª posição 20,8% das vezes e
     * o último 6,6% (ideal 11,1%). Aqui exige-se que cada item apareça na 1.ª
     * posição dentro de ±3 pontos percentuais do ideal.
     */
    it('distribui as posições uniformemente', () => {
        const n = 9;
        const execucoes = 30000;
        const ideal = 100 / n;
        const primeiraPosicao = new Array(n).fill(0);

        for (let i = 0; i < execucoes; i++) {
            const resultado = embaralhar(Array.from({ length: n }, (_, k) => k));
            primeiraPosicao[resultado[0]]++;
        }

        for (let item = 0; item < n; item++) {
            const percentagem = (primeiraPosicao[item] / execucoes) * 100;
            expect(Math.abs(percentagem - ideal)).toBeLessThan(3);
        }
    });
});

describe('amostrarPonderado', () => {
    it('devolve a quantidade pedida sem repetições', () => {
        const itens = Array.from({ length: 30 }, (_, i) => ({ id: i }));
        const amostra = amostrarPonderado(itens, () => 1, 10);

        expect(amostra.length).toBe(10);
        expect(new Set(amostra.map((item) => item.id)).size).toBe(10);
    });

    it('favorece itens com peso maior sem excluir os de peso menor', () => {
        const itens = [
            { id: 'pesado', peso: 10 },
            { id: 'leve-1', peso: 1 },
            { id: 'leve-2', peso: 1 },
            { id: 'leve-3', peso: 1 },
        ];

        let pesadoEscolhido = 0;
        let levesEscolhidos = 0;
        const execucoes = 2000;

        for (let i = 0; i < execucoes; i++) {
            const amostra = amostrarPonderado(itens, (item) => item.peso, 1);
            if (amostra[0].id === 'pesado') {
                pesadoEscolhido++;
            } else {
                levesEscolhidos++;
            }
        }

        expect(pesadoEscolhido).toBeGreaterThan(levesEscolhidos);
        // Mas os leves continuam a sair: a ponderação não é um filtro.
        expect(levesEscolhidos).toBeGreaterThan(0);
    });

    it('devolve tudo embaralhado quando se pede mais do que existe', () => {
        const itens = [{ id: 1 }, { id: 2 }, { id: 3 }];
        expect(amostrarPonderado(itens, () => 1, 10).length).toBe(3);
    });
});
