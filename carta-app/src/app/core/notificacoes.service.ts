import { Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';

@Injectable({ providedIn: 'root' })
export class NotificacoesService {
    async ativas(): Promise<boolean> { return (await Preferences.get({ key: 'notificacoesAtivas' })).value !== '0'; }
    async definir(ativas: boolean): Promise<void> { await Preferences.set({ key: 'notificacoesAtivas', value: ativas ? '1' : '0' }); }
}
