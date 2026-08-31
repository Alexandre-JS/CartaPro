import { retornoSeguro } from './auth.guard';

describe('retornoSeguro', () => {
    it('preserva uma rota interna', () => {
        expect(retornoSeguro('/simular/configurar?modo=rapido')).toBe('/simular/configurar?modo=rapido');
    });

    it('rejeita destinos externos e caminhos sem barra inicial', () => {
        expect(retornoSeguro('//site-malicioso.test')).toBe('/inicio');
        expect(retornoSeguro('https://site-malicioso.test')).toBe('/inicio');
    });

    it('evita ciclos entre as páginas de conta', () => {
        expect(retornoSeguro('/conta/entrar')).toBe('/inicio');
        expect(retornoSeguro('/conta/criar?retorno=/perfil')).toBe('/inicio');
        expect(retornoSeguro('/conta/guardar')).toBe('/inicio');
    });
});
