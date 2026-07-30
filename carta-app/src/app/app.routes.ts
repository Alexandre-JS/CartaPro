import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';

export const routes: Routes = [
    {
        path: '',
        canActivate: [guestGuard],
        loadComponent: () => import('./pages/login/login.page').then((m) => m.LoginPage),
    },
    {
        path: 'registo',
        canActivate: [guestGuard],
        loadComponent: () => import('./pages/registo/registo.page').then((m) => m.RegistoPage),
    },
    // Tudo o que mostra conteúdo exige sessão: o pacote é servido apenas a
    // contas autenticadas e o plano é decidido no servidor.
    {
        path: 'inicio',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/inicio/inicio.page').then((m) => m.InicioPage),
    },
    {
        path: 'simulado',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/simulado/simulado.page').then((m) => m.SimuladoPage),
    },
    {
        path: 'exames',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/exames/exames.page').then((m) => m.ExamesPage),
    },
    {
        path: 'resultado',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/resultado/resultado.page').then((m) => m.ResultadoPage),
    },
    {
        path: 'revisoes',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/revisoes/revisoes.page').then((m) => m.RevisoesPage),
    },
    {
        path: 'estudos',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/estudos/estudos.page').then((m) => m.EstudosPage),
    },
    {
        path: 'sinais',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/sinais/sinais.page').then((m) => m.SinaisPage),
    },
    {
        path: 'sinal/:slug',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/sinal-detalhe/sinal-detalhe.page').then((m) => m.SinalDetalhePage),
    },
    {
        path: 'treino-sinais',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/treino-sinais/treino-sinais.page').then((m) => m.TreinoSinaisPage),
    },
    {
        path: 'licoes',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/licoes/licoes.page').then((m) => m.LicoesPage),
    },
    {
        path: 'licao/:slug',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/licao/licao.page').then((m) => m.LicaoPage),
    },
    {
        path: 'codigo',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/codigo/codigo.page').then((m) => m.CodigoPage),
    },
    {
        path: 'glossario',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/glossario/glossario.page').then((m) => m.GlossarioPage),
    },
    // As categorias falsas de artigos deram lugar aos capítulos reais do Código.
    { path: 'estudos/:categoria', redirectTo: 'codigo' },
    {
        path: 'estudo/:tema',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/estudo-tema/estudo-tema.page').then((m) => m.EstudoTemaPage),
    },
    {
        path: 'desbloquear',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/desbloquear/desbloquear.page').then((m) => m.DesbloquearPage),
    },
    {
        path: 'perfil',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/perfil/perfil.page').then((m) => m.PerfilPage),
    },
    {
        path: 'desempenho',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/desempenho/desempenho.page').then((m) => m.DesempenhoPage),
    },
    { path: '**', redirectTo: '' },
];
