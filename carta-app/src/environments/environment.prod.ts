/*
 * Configuração de produção.
 *
 * Antes este ficheiro apontava para `http://127.0.0.1:8000` — o APK publicado
 * tentava falar com o próprio telefone — e em HTTP simples, o que enviava os
 * Bearer tokens (válidos 90 dias) em texto claro.
 *
 * Substitua o domínio pelo da API em produção. Tem de ser HTTPS: o app envia o
 * token de sessão em cada pedido. O timeout subiu de 6s para 15s porque o
 * pacote completo é grande e as ligações móveis em Moçambique são lentas.
 */
export const environment = {
  production: true,
  apiBaseUrl: 'https://api.cartapro.co.mz/api/v1',
  androidApiBaseUrl: 'https://api.cartapro.co.mz/api/v1',
  apiTimeoutMs: 15000,
};
