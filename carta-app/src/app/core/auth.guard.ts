import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { Preferences } from '@capacitor/preferences';

/**
 * Protege as páginas internas.
 *
 * As rotas não tinham guarda nenhuma: bastava navegar para /inicio para entrar
 * sem sessão — e, como o conteúdo passou a exigir autenticação na API, as
 * páginas ficariam vazias com erros por token ausente.
 */
export const authGuard: CanActivateFn = async (_route, state) => {
    const router = inject(Router);
    const { value } = await Preferences.get({ key: 'mobileAuthToken' });

    if (value) {
        return true;
    }

    return router.createUrlTree(['/conta/entrar'], { queryParams: { retorno: state.url } });
};

/** Impede que quem já tem sessão volte ao ecrã de entrada. */
export const guestGuard: CanActivateFn = async (route) => {
    const router = inject(Router);
    const { value } = await Preferences.get({ key: 'mobileAuthToken' });

    if (!value) {
        return true;
    }

    return router.parseUrl(retornoSeguro(route.queryParamMap.get('retorno')));
};

/** Só aceita destinos internos; impede redirecionamentos para outro domínio. */
export function retornoSeguro(valor: string | null | undefined, omissao = '/inicio'): string {
    if (!valor || !valor.startsWith('/') || valor.startsWith('//')) return omissao;
    if (valor.startsWith('/conta/entrar') || valor.startsWith('/conta/criar') || valor.startsWith('/conta/guardar')) return omissao;
    return valor;
}
