import { ApplicationConfig } from '@angular/core';
import { provideRouter, withPreloading, PreloadAllModules, RouteReuseStrategy } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideIonicAngular, IonicRouteStrategy } from '@ionic/angular/standalone';
import { routes } from './app.routes';

export const appConfig: ApplicationConfig = {
    providers: [
        provideIonicAngular(),
        provideHttpClient(),
        provideRouter(routes, withPreloading(PreloadAllModules)),
        { provide: RouteReuseStrategy, useClass: IonicRouteStrategy },
    ],
};