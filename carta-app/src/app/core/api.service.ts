import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';
import { firstValueFrom, timeout } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ApiService {
    private readonly http = inject(HttpClient);
    readonly baseUrl = (Capacitor.getPlatform() === 'android' ? environment.androidApiBaseUrl : environment.apiBaseUrl).replace(/\/$/, '');

    async get<T>(path: string, authenticated = false): Promise<T> {
        return firstValueFrom(this.http.get<T>(this.url(path), { headers: await this.headers(authenticated) }).pipe(timeout(environment.apiTimeoutMs)));
    }

    async post<T>(path: string, body: unknown, authenticated = false): Promise<T> {
        return firstValueFrom(this.http.post<T>(this.url(path), body, { headers: await this.headers(authenticated) }).pipe(timeout(environment.apiTimeoutMs)));
    }

    async put<T>(path: string, body: unknown, authenticated = false): Promise<T> {
        return firstValueFrom(this.http.put<T>(this.url(path), body, { headers: await this.headers(authenticated) }).pipe(timeout(environment.apiTimeoutMs)));
    }

    absoluteAssetUrl(value: string | null): string | null {
        if (!value) {
            return null;
        }

        try {
            const path = new URL(value).pathname;
            return `${this.apiOrigin()}${path}`;
        } catch {
            return `${this.apiOrigin()}/${value.replace(/^\//, '')}`;
        }
    }

    private apiOrigin(): string {
        return new URL(this.baseUrl).origin;
    }

    private url(path: string): string { return `${this.baseUrl}/${path.replace(/^\//, '')}`; }

    private async headers(authenticated: boolean): Promise<Record<string, string>> {
        const headers: Record<string, string> = { Accept: 'application/json' };
        if (authenticated) {
            const { value } = await Preferences.get({ key: 'mobileAuthToken' });
            if (!value) throw new Error('Sessão não iniciada.');
            headers['Authorization'] = `Bearer ${value}`;
        }
        return headers;
    }
}
