import { inject, Injectable } from '@angular/core';
import { ApiService } from './api.service';

export interface EscolaMembership {
    id: number;
    status: string;
    school?: { id: number; name: string; code: string; is_active?: boolean };
    classroom?: { id: number; name: string; code: string } | null;
    student?: { name: string; identifier?: string } | null;
}

export interface EscolaTarefa {
    id: number;
    status: string;
    progress?: number;
    assignment?: { title?: string; name?: string; description?: string; due_at?: string | null; school?: { name: string } };
}

@Injectable({ providedIn: 'root' })
export class EscolaService {
    private readonly api = inject(ApiService);

    async memberships(): Promise<EscolaMembership[]> {
        const response = await this.api.get<{ data?: EscolaMembership[] } | EscolaMembership[]>('school-memberships', true);
        return Array.isArray(response) ? response : response.data ?? [];
    }

    async tarefas(): Promise<EscolaTarefa[]> {
        const response = await this.api.get<{ data?: EscolaTarefa[] } | EscolaTarefa[]>('school-assignments', true);
        return Array.isArray(response) ? response : response.data ?? [];
    }
}
