import { inject, Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { PerfilUtilizador } from '../models/perfil.model';
import { ApiService } from './api.service';

const PERFIL_PADRAO: PerfilUtilizador = {
    nome: 'Estudante',
    email: '',
    telefone: '',
};

@Injectable({ providedIn: 'root' })
export class PerfilService {
    private readonly api = inject(ApiService);

    async obter(): Promise<PerfilUtilizador> {
        const { value } = await Preferences.get({ key: 'perfilUtilizador' });
        try {
            const response = await this.api.get<{ user: PerfilUtilizador }>('mobile/me', true);
            await Preferences.set({ key: 'perfilUtilizador', value: JSON.stringify(response.user) });
            return this.normalize(response.user);
        } catch {
            return value ? this.normalize(JSON.parse(value)) : PERFIL_PADRAO;
        }
    }

    async guardar(perfil: PerfilUtilizador): Promise<void> {
        const categoria = (await Preferences.get({ key: 'categoriaCarta' })).value || 'ligeiro';
        const response = await this.api.put<{ user: PerfilUtilizador }>('mobile/me', { name: perfil.nome, email: perfil.email, phone: perfil.telefone, license_category: categoria }, true);
        await Preferences.set({ key: 'perfilUtilizador', value: JSON.stringify(response.user) });
    }

    private normalize(perfil: PerfilUtilizador): PerfilUtilizador {
        return { nome: perfil.nome || PERFIL_PADRAO.nome, email: perfil.email || PERFIL_PADRAO.email, telefone: perfil.telefone || '' };
    }
}
