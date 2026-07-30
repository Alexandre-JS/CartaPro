import { inject, Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { PerfilUtilizador } from '../models/perfil.model';
import { ApiService } from './api.service';
import { StorageService } from './storage.service';

interface AuthResponse { token: string; user: PerfilUtilizador & { id: number; categoriaCarta: string }; }

@Injectable({ providedIn: 'root' })
export class AuthService {
    private readonly api = inject(ApiService);
    private readonly storage = inject(StorageService);

    async registar(data: { nome: string; email: string; telefone: string; palavraPasse: string }): Promise<PerfilUtilizador> {
        const response = await this.api.post<AuthResponse>('mobile/register', { name: data.nome, email: data.email, phone: data.telefone, password: data.palavraPasse });
        await this.saveSession(response);
        return response.user;
    }

    async entrar(identifier: string, password: string): Promise<PerfilUtilizador> {
        const response = await this.api.post<AuthResponse>('mobile/login', { identifier, password });
        await this.saveSession(response);
        return response.user;
    }

    async sair(): Promise<void> {
        try { await this.api.post('mobile/logout', {}, true); } catch { /* remove a sessão local mesmo sem rede */ }
        await Preferences.remove({ key: 'mobileAuthToken' });
        await Preferences.remove({ key: 'perfilUtilizador' });
    }

    async token(): Promise<string | null> { return (await Preferences.get({ key: 'mobileAuthToken' })).value; }

    private async saveSession(response: AuthResponse): Promise<void> {
        await Preferences.set({ key: 'mobileAuthToken', value: response.token });
        await Preferences.set({ key: 'perfilUtilizador', value: JSON.stringify(response.user) });
        await this.storage.prepararDadosDaConta(response.user.id);
        await Preferences.set({ key: 'categoriaCarta', value: response.user.categoriaCarta || 'ligeiro' });
        await this.storage.baixarSnapshot();
    }
}
