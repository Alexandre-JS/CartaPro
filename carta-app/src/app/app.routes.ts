import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';

export const routes: Routes = [
    {
        path: '',
        loadComponent: () => import('./pages/inicio/inicio.page').then((m) => m.InicioPage),
    },
    {
        path: 'entrar',
        canActivate: [guestGuard],
        loadComponent: () => import('./pages/login/login.page').then((m) => m.LoginPage),
    },
    {
        path: 'registo',
        canActivate: [guestGuard],
        loadComponent: () => import('./pages/registo/registo.page').then((m) => m.RegistoPage),
    },
    // A Home e Aprender são públicas; o conteúdo Free não exige conta.
    {
        path: 'inicio',
        loadComponent: () => import('./pages/inicio/inicio.page').then((m) => m.InicioPage),
    },
    {
        path: 'escola',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/escola/escola.page').then((m) => m.EscolaPage),
    },
    {
        path: 'simulado',
        loadComponent: () => import('./pages/simulado/simulado.page').then((m) => m.SimuladoPage),
    },
    {
        path: 'exames',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/exames/exames.page').then((m) => m.ExamesPage),
    },
    {
        path: 'resultado',
        loadComponent: () => import('./pages/resultado/resultado.page').then((m) => m.ResultadoPage),
    },
    {
        path: 'revisoes',
        loadComponent: () => import('./pages/revisoes/revisoes.page').then((m) => m.RevisoesPage),
    },
    {
        path: 'estudos',
        loadComponent: () => import('./pages/estudos/estudos.page').then((m) => m.EstudosPage),
    },
    {
        path: 'biblioteca',
        loadComponent: () => import('./pages/biblioteca/biblioteca.page').then((m) => m.BibliotecaPage),
    },
    {
        path: 'sinais',
        loadComponent: () => import('./pages/sinais/sinais.page').then((m) => m.SinaisPage),
    },
    {
        path: 'sinal/:slug',
        loadComponent: () => import('./pages/sinal-detalhe/sinal-detalhe.page').then((m) => m.SinalDetalhePage),
    },
    {
        path: 'treino-sinais',
        loadComponent: () => import('./pages/treino-sinais/treino-sinais.page').then((m) => m.TreinoSinaisPage),
    },
    {
        path: 'licoes',
        loadComponent: () => import('./pages/licoes/licoes.page').then((m) => m.LicoesPage),
    },
    {
        path: 'licao/:slug',
        loadComponent: () => import('./pages/licao/licao.page').then((m) => m.LicaoPage),
    },
    {
        path: 'codigo',
        loadComponent: () => import('./pages/codigo/codigo.page').then((m) => m.CodigoPage),
    },
    {
        path: 'glossario',
        loadComponent: () => import('./pages/glossario/glossario.page').then((m) => m.GlossarioPage),
    },
    // As categorias falsas de artigos deram lugar aos capítulos reais do Código.
    { path: 'estudos/:categoria', redirectTo: 'codigo' },
    {
        path: 'estudo/:tema',
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
