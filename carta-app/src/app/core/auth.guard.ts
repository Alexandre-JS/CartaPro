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
export const authGuard: CanActivateFn = async () => {
    const router = inject(Router);
    const { value } = await Preferences.get({ key: 'mobileAuthToken' });

    if (value) {
        return true;
    }

    await router.navigateByUrl('/entrar');
    return false;
};

/** Impede que quem já tem sessão volte ao ecrã de entrada. */
export const guestGuard: CanActivateFn = async () => {
    const router = inject(Router);
    const { value } = await Preferences.get({ key: 'mobileAuthToken' });

    if (!value) {
        return true;
    }

    await router.navigateByUrl('/inicio');
    return false;
};
