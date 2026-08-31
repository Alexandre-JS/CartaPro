import { inject, Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { PerfilUtilizador } from '../models/perfil.model';
import { ApiService } from './api.service';
import { EstadoAcesso } from '../models/progresso.model';
import { StorageService } from './storage.service';

const PERFIL_PADRAO: PerfilUtilizador = {
    nome: 'Estudante',
    email: '',
    telefone: '',
};

@Injectable({ providedIn: 'root' })
export class PerfilService {
    private readonly api = inject(ApiService);
    private readonly storage = inject(StorageService);

    async obter(): Promise<PerfilUtilizador> {
        const response = await this.api.get<{ user: PerfilUtilizador; access?: EstadoAcesso }>('mobile/me', true);
        await Preferences.set({ key: 'perfilUtilizador', value: JSON.stringify(response.user) });
        if (response.access) await this.storage.guardarEstadoAcesso({ ...response.access, verificadoEm: Date.now() });
        return this.normalize(response.user);
    }

    async obterLocal(): Promise<PerfilUtilizador> {
        const { value } = await Preferences.get({ key: 'perfilUtilizador' });
        return value ? this.normalize(JSON.parse(value)) : PERFIL_PADRAO;
    }

    async guardar(perfil: PerfilUtilizador): Promise<void> {
        const categoria = (await Preferences.get({ key: 'categoriaCarta' })).value || 'ligeiro';
        const response = await this.api.put<{ user: PerfilUtilizador; access?: EstadoAcesso }>('mobile/me', { name: perfil.nome, email: perfil.email, phone: perfil.telefone, license_category: categoria }, true);
        await Preferences.set({ key: 'perfilUtilizador', value: JSON.stringify(response.user) });
        if (response.access) await this.storage.guardarEstadoAcesso({ ...response.access, verificadoEm: Date.now() });
    }

    private normalize(perfil: PerfilUtilizador): PerfilUtilizador {
        return { nome: perfil.nome || PERFIL_PADRAO.nome, email: perfil.email || PERFIL_PADRAO.email, telefone: perfil.telefone || '' };
    }
}
