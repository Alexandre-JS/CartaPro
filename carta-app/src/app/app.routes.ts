import { Routes } from '@angular/router';
import { authGuard, guestGuard } from './core/auth.guard';
import { setupGuard } from './core/setup.guard';

export const routes: Routes = [
    {
        path: '',
        canActivate: [setupGuard],
        loadComponent: () => import('./pages/inicio/inicio.page').then((m) => m.InicioPage),
    },
    {
        path: 'configuracao-inicial',
        loadComponent: () => import('./pages/configuracao-inicial/configuracao-inicial.page').then((m) => m.ConfiguracaoInicialPage),
    },
    {
        path: 'conta/entrar',
        canActivate: [guestGuard],
        loadComponent: () => import('./pages/login/login.page').then((m) => m.LoginPage),
    },
    {
        path: 'conta/criar',
        canActivate: [guestGuard],
        loadComponent: () => import('./pages/registo/registo.page').then((m) => m.RegistoPage),
    },
    {
        path: 'conta/guardar',
        canActivate: [guestGuard],
        loadComponent: () => import('./pages/guardar-progresso/guardar-progresso.page').then((m) => m.GuardarProgressoPage),
    },
    // A Home e Aprender são públicas; o conteúdo Free não exige conta.
    {
        path: 'inicio',
        canActivate: [setupGuard],
        loadComponent: () => import('./pages/inicio/inicio.page').then((m) => m.InicioPage),
    },
    {
        path: 'escola',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/escola/escola.page').then((m) => m.EscolaPage),
    },
    { path: 'simular', pathMatch: 'full', loadComponent: () => import('./pages/simular/simular.page').then((m) => m.SimularPage) },
    { path: 'simular/configurar', loadComponent: () => import('./pages/simular-configurar/simular-configurar.page').then((m) => m.SimularConfigurarPage) },
    { path: 'simular/sessao', loadComponent: () => import('./pages/simulado/simulado.page').then((m) => m.SimuladoPage) },
    { path: 'simular/resultado', loadComponent: () => import('./pages/resultado/resultado.page').then((m) => m.ResultadoPage) },
    { path: 'simular/exames', loadComponent: () => import('./pages/exames/exames.page').then((m) => m.ExamesPage) },
    { path: 'simular/escola', loadComponent: () => import('./pages/prova-escolar/prova-escolar.page').then((m) => m.ProvaEscolarPage) },
    { path: 'progresso', pathMatch: 'full', loadComponent: () => import('./pages/desempenho/desempenho.page').then((m) => m.DesempenhoPage) },
    { path: 'progresso/tema/:slug', loadComponent: () => import('./pages/progresso-tema/progresso-tema.page').then((m) => m.ProgressoTemaPage) },
    {
        path: 'aprender',
        loadComponent: () => import('./pages/estudos/estudos.page').then((m) => m.EstudosPage),
    },
    {
        path: 'aprender/tema/:slug',
        loadComponent: () => import('./pages/tema-percurso/tema-percurso.page').then((m) => m.TemaPercursoPage),
    },
    { path: 'aprender/biblioteca', loadComponent: () => import('./pages/biblioteca/biblioteca.page').then((m) => m.BibliotecaPage) },
    { path: 'aprender/sinais', loadComponent: () => import('./pages/sinais/sinais.page').then((m) => m.SinaisPage) },
    { path: 'aprender/sinais/:slug', loadComponent: () => import('./pages/sinal-detalhe/sinal-detalhe.page').then((m) => m.SinalDetalhePage) },
    { path: 'aprender/licoes', loadComponent: () => import('./pages/licoes/licoes.page').then((m) => m.LicoesPage) },
    { path: 'aprender/licoes/:slug', loadComponent: () => import('./pages/licao/licao.page').then((m) => m.LicaoPage) },
    { path: 'aprender/codigo', loadComponent: () => import('./pages/codigo/codigo.page').then((m) => m.CodigoPage) },
    { path: 'aprender/glossario', loadComponent: () => import('./pages/glossario/glossario.page').then((m) => m.GlossarioPage) },
    { path: 'praticar', pathMatch: 'full', loadComponent: () => import('./pages/praticar/praticar.page').then((m) => m.PraticarPage) },
    { path: 'praticar/tema/:slug', data: { modoPratica: 'tema' }, loadComponent: () => import('./pages/estudo-tema/estudo-tema.page').then((m) => m.EstudoTemaPage) },
    { path: 'praticar/erros', data: { modoPratica: 'erros' }, loadComponent: () => import('./pages/estudo-tema/estudo-tema.page').then((m) => m.EstudoTemaPage) },
    { path: 'praticar/novas', data: { modoPratica: 'novas' }, loadComponent: () => import('./pages/estudo-tema/estudo-tema.page').then((m) => m.EstudoTemaPage) },
    { path: 'praticar/sessao', data: { modoPratica: 'rapida' }, loadComponent: () => import('./pages/estudo-tema/estudo-tema.page').then((m) => m.EstudoTemaPage) },
    { path: 'praticar/revisoes', loadComponent: () => import('./pages/revisoes/revisoes.page').then((m) => m.RevisoesPage) },
    { path: 'praticar/sinais', loadComponent: () => import('./pages/treino-sinais/treino-sinais.page').then((m) => m.TreinoSinaisPage) },
    // Compatibilidade: links e favoritos anteriores convergem para a árvore canónica.
    { path: 'estudos', redirectTo: 'aprender', pathMatch: 'full' },
    { path: 'biblioteca', redirectTo: 'aprender/biblioteca', pathMatch: 'full' },
    { path: 'sinais', redirectTo: 'aprender/sinais', pathMatch: 'full' },
    { path: 'sinal/:slug', redirectTo: 'aprender/sinais/:slug' },
    { path: 'licoes', redirectTo: 'aprender/licoes', pathMatch: 'full' },
    { path: 'licao/:slug', redirectTo: 'aprender/licoes/:slug' },
    { path: 'codigo', redirectTo: 'aprender/codigo', pathMatch: 'full' },
    { path: 'glossario', redirectTo: 'aprender/glossario', pathMatch: 'full' },
    { path: 'estudo/:tema', redirectTo: 'praticar/tema/:tema' },
    { path: 'revisoes', redirectTo: 'praticar/revisoes', pathMatch: 'full' },
    { path: 'treino-sinais', redirectTo: 'praticar/sinais', pathMatch: 'full' },
    { path: 'simulado', redirectTo: 'simular/configurar', pathMatch: 'full' },
    { path: 'exames', redirectTo: 'simular/exames', pathMatch: 'full' },
    { path: 'resultado', redirectTo: 'simular/resultado', pathMatch: 'full' },
    { path: 'desempenho', redirectTo: 'progresso', pathMatch: 'full' },
    { path: 'entrar', redirectTo: 'conta/entrar', pathMatch: 'full' },
    { path: 'registo', redirectTo: 'conta/criar', pathMatch: 'full' },
    // As antigas categorias falsas de artigos convergem no Código real.
    { path: 'estudos/:categoria', redirectTo: 'aprender/codigo' },
    {
        path: 'desbloquear',
        canActivate: [authGuard],
        loadComponent: () => import('./pages/desbloquear/desbloquear.page').then((m) => m.DesbloquearPage),
    },
    {
        path: 'perfil',
        loadComponent: () => import('./pages/perfil/perfil.page').then((m) => m.PerfilPage),
    },
    { path: '**', redirectTo: '' },
];
