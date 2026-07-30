/*
 * Constantes do motor de estudo.
 *
 * NOTA/PASSAGEM: a regra de aprovação já **não** vive aqui. Antes existiam
 * cinco definições em conflito (24/25 = 96% neste ficheiro, 72% na API, 14
 * valores no modelo, 3 notas válidas no painel). A fonte única é
 * config/grading.php na API e chega ao app dentro do pacote, na chave `regras`
 * — ver RegrasService. Os valores abaixo são apenas o recurso de emergência
 * usado quando ainda não há pacote descarregado.
 */

export const TAMANHO_SIMULADO = 25;
export const PERCENTAGEM_PASSAGEM_PADRAO = 72;
export const DURACAO_SIMULADO_SEGUNDOS = 30 * 60;

/*
 * Maestria
 * --------
 * MINIMO_AMOSTRA_MAESTRIA corrige o defeito em que um tema era considerado
 * dominado com **uma** resposta certa: a condição antiga
 * `recentes.length >= Math.min(JANELA, respostas.length)` era uma tautologia.
 */
export const LIMITE_MAESTRIA = 0.8;
export const JANELA_MAESTRIA = 10;
export const MINIMO_AMOSTRA_MAESTRIA = 8;

/** Abaixo desta amostra o tema é "em avaliação", nem forte nem fraco. */
export const MINIMO_AMOSTRA_DIAGNOSTICO = 4;

/*
 * Seleção adaptativa
 * ------------------
 * PESO_* controlam a ponderação. O simulado mantém uma quota mínima de
 * cobertura para continuar a parecer o exame real, em vez de ser 100%
 * perguntas de temas fracos como acontecia antes.
 */
export const QUOTA_TEMAS_FRACOS = 0.6;
export const PESO_TEMA_NAO_PRATICADO = 1.4;
export const PESO_MINIMO_TEMA = 0.15;

/** Perguntas respondidas nas últimas N respostas são despriorizadas. */
export const JANELA_RECENCIA = 40;
export const PENALIZACAO_RECENCIA = 0.15;

/** Perguntas por sessão de estudo dirigido. */
export const TAMANHO_SESSAO_ESTUDO = 5;

/*
 * Repetição espaçada (SM-2 simplificado)
 * --------------------------------------
 * A escada fixa [1,3,7,14,30] perdia toda a informação a cada erro. Agora
 * cada pergunta tem fator de facilidade próprio.
 */
export const FACILIDADE_INICIAL = 2.5;
export const FACILIDADE_MINIMA = 1.3;
export const FACILIDADE_MAXIMA = 3.0;
export const INTERVALO_PRIMEIRA_REVISAO = 1;
export const INTERVALO_SEGUNDA_REVISAO = 3;
export const INTERVALO_MAXIMO_DIAS = 180;

/** Acima deste tempo por pergunta assume-se leitura atenta, não adivinhação. */
export const MS_MINIMO_RESPOSTA_CONSIDERADA = 2500;
