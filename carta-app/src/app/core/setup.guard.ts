import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { Preferences } from '@capacitor/preferences';

/** Mostra o setup apenas numa instalação que ainda não escolheu uma categoria. */
export const setupGuard: CanActivateFn = async () => {
    const router = inject(Router);
    const [setup, categoria] = await Promise.all([
        Preferences.get({ key: 'setupInicialConcluido' }),
        Preferences.get({ key: 'categoriaCarta' }),
    ]);

    if (setup.value === '1' || categoria.value) {
        return true;
    }

    return router.createUrlTree(['/configuracao-inicial']);
};
