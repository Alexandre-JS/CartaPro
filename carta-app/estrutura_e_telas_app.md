# App do Aluno — Estrutura do Projeto e Descrição das Telas
## Ionic + Angular + Capacitor · v1 (primeira fatia)

> Documento de construção para orientar o copiloto. Descreve a estrutura de ficheiros, os modelos de dados, os serviços e cada tela em detalhe. Não contém código — é o mapa a preencher.

Objetivo da primeira fatia: um app offline que corre um **simulado** de ponta a ponta (pergunta → resposta → explicação com artigo → resultado com temas fortes/fracos), alimentado por um **pacote de conteúdo de exemplo** feito à mão. Sem contas, sem servidor. O desbloqueio pode ficar como esboço.

---

## 1. Estrutura de ficheiros

Projeto Ionic Angular com **componentes standalone** (abordagem moderna).

```
carta-app/
├── capacitor.config.ts
├── ionic.config.json
├── angular.json
├── package.json
├── src/
│   ├── main.ts
│   ├── index.html
│   ├── manifest.webmanifest              # PWA
│   ├── assets/
│   │   ├── conteudo/
│   │   │   └── pacote-exemplo.json        # banco de perguntas de arranque
│   │   └── sinais/                        # SVGs dos sinais de trânsito
│   │       └── curva-perigosa.svg
│   └── app/
│       ├── app.component.ts
│       ├── app.routes.ts                  # rotas da aplicação
│       ├── models/
│       │   ├── pergunta.model.ts
│       │   ├── pacote.model.ts
│       │   └── progresso.model.ts
│       ├── core/                          # serviços (lógica, sem UI)
│       │   ├── content.service.ts         # carrega o pacote de conteúdo
│       │   ├── storage.service.ts         # IndexedDB via Dexie
│       │   ├── progresso.service.ts       # estatística por tema + maestria
│       │   ├── simulado.service.ts        # monta simulados + seleção adaptativa
│       │   └── desbloqueio.service.ts     # estado de acesso (esboço na v1)
│       ├── components/
│       │   └── pergunta-card/             # cartão reutilizável de uma pergunta
│       └── pages/
│           ├── inicio/
│           ├── simulado/
│           ├── resultado/
│           ├── estudo-tema/
│           └── desbloquear/
```

Dependências principais a instalar: `@ionic/angular`, `@capacitor/core`, `@capacitor/preferences` (armazenamento simples), `dexie` (IndexedDB).

---

## 2. Modelos de dados (interfaces TypeScript)

```ts
// models/pergunta.model.ts
export type TipoPergunta = 'teorico' | 'pratico';
export type CategoriaCarta = 'ligeiro' | 'pesado' | 'profissional_publico';

export interface Pergunta {
  id: string;
  tipo: TipoPergunta;
  tema: string;                 // ex.: 'velocidade', 'sinais_perigo'
  categoriaCarta: CategoriaCarta[];
  enunciado: string;
  imagem: string | null;        // nome do ficheiro SVG em assets/sinais, ou null
  opcoes: string[];
  correta: number;              // índice (0-based) da opção correta
  explicacao: string;
  artigoRef: number | null;     // nº do artigo do Código da Estrada
  bloqueado: boolean;
}

// models/pacote.model.ts
export interface Pacote {
  versao: string;               // ex.: '2026-07-28'
  temas: string[];
  perguntas: Pergunta[];
}

// models/progresso.model.ts
export interface ProgressoTema {
  tema: string;
  respondidas: number;
  acertos: number;
  taxaAcerto: number;           // 0..1
  graduado: boolean;            // true quando domina o tema
}

export interface RespostaRegisto {
  perguntaId: string;
  acertou: boolean;
  data: number;                 // timestamp
}

export interface EstadoAcesso {
  plano: 'gratis' | 'pago';
  telefone?: string;
}
```

Constantes de configuração do motor adaptativo (num ficheiro de config):
- `LIMITE_MAESTRIA = 0.8` — taxa de acerto para um tema "graduar-se".
- `JANELA_MAESTRIA = 10` — nº de perguntas recentes consideradas.
- `TAMANHO_SIMULADO = 25` — perguntas por simulado.
- `NOTA_PASSAGEM = 24` — acertos mínimos (a confirmar por categoria).

---

## 3. Serviços (core)

**content.service.ts** — Carrega o `pacote-exemplo.json` de `assets/conteudo/` no arranque e guarda-o (via storage.service) na IndexedDB. Expõe métodos para obter todas as perguntas, filtrar por tema, por tipo e por categoria de carta, e distinguir bloqueadas/livres.

**storage.service.ts** — Camada fina sobre o Dexie (IndexedDB). Guarda e lê: o pacote de conteúdo, os registos de resposta, a estatística por tema e o estado de acesso. Tudo local, offline.

**progresso.service.ts** — Regista cada resposta, atualiza a estatística por tema, e calcula quais temas são fracos (taxa abaixo do limite) e quais já se "graduaram" (taxa ≥ `LIMITE_MAESTRIA` na `JANELA_MAESTRIA`).

**simulado.service.ts** — Monta um simulado: em modo normal, seleção equilibrada por temas; em modo adaptativo, **pondera a seleção para as áreas fracas** do aluno, puxando perguntas do banco e evitando repetir as mesmas de imediato. Respeita o `TAMANHO_SIMULADO` e o filtro de categoria de carta.

**desbloqueio.service.ts** — Guarda o `EstadoAcesso`. Na v1 pode apenas ler/gravar localmente (esboço). Mais tarde ligará ao servidor para confirmar pagamento por número.

---

## 4. Rotas

```
''            → InicioPage
'simulado'    → SimuladoPage
'resultado'   → ResultadoPage
'estudo/:tema'→ EstudoTemaPage
'desbloquear' → DesbloquearPage
```

---

## 5. Descrição das telas

### 5.1 Início (`pages/inicio`)

**Objetivo:** ponto de entrada. O aluno escolhe a categoria de carta e o que quer fazer.

**Elementos:**
- Cabeçalho (`ion-header` / `ion-toolbar`) com o nome do app.
- Seletor de **categoria de carta** (`ion-segment` ou `ion-select`): ligeiro, pesado, profissional/público. Guarda a escolha (para filtrar o conteúdo).
- Botão grande **"Iniciar simulado"** (`ion-button`) → navega para `SimuladoPage` em modo normal.
- Botão **"Treinar por tema"** → abre uma lista de temas (`ion-list`); cada tema mostra a taxa de acerto atual (de progresso.service) e um cadeado se estiver bloqueado. Tocar num tema → `EstudoTemaPage`.
- Botão/atalho **"Desbloquear tudo"** → `DesbloquearPage` (visível se o plano for 'gratis').

**Dados:** lê a lista de temas e a estatística por tema. Guarda a categoria de carta escolhida.

**Comportamento:** se o aluno for 'gratis', os simulados/temas além dos livres mostram cadeado e, ao tocar, encaminham para `DesbloquearPage`.

---

### 5.2 Simulado (`pages/simulado`)

**Objetivo:** correr um simulado de `TAMANHO_SIMULADO` perguntas, uma a uma.

**Elementos:**
- Barra de progresso (`ion-progress-bar`) e contador "Pergunta X de 25".
- Área da pergunta (usa o componente **pergunta-card**, ver 5.6).
- Botão **"Confirmar"** (desativado até haver opção escolhida). Depois de confirmar, revela o resultado da pergunta (ver pergunta-card) e o botão muda para **"Próxima"**.

**Comportamento:**
1. Ao entrar, chama `simulado.service` para obter a lista de perguntas (normal ou adaptativo, conforme veio da tela anterior).
2. Mostra uma pergunta de cada vez.
3. Ao confirmar: regista a resposta em `progresso.service`, revela certo/errado + explicação + artigo.
4. Ao tocar "Próxima": avança; na última, navega para `ResultadoPage` passando o resumo.

**Dados:** perguntas do simulado; grava cada resposta.

**Nota offline:** tudo corre em memória/local; nenhuma chamada de rede.

---

### 5.3 Resultado (`pages/resultado`)

**Objetivo:** mostrar o desempenho no simulado e orientar o próximo passo.

**Elementos:**
- Pontuação: "Acertaste X de 25" e indicação **Aprovado/Reprovado** face à `NOTA_PASSAGEM`.
- Lista de **temas fortes** e **temas fracos** (de progresso.service), com a taxa de cada.
- Botão **"Repetir focado nas fraquezas"** → `SimuladoPage` em **modo adaptativo** (o simulado.service pondera as áreas fracas).
- Botão **"Voltar ao início"** → `InicioPage`.

**Dados:** resumo recebido do simulado + estatística por tema atualizada.

---

### 5.4 Estudo por tema (`pages/estudo-tema`)

**Objetivo:** praticar um único tema à escolha, sem a pressão do simulado completo.

**Elementos:**
- Título com o nome do tema e a taxa de acerto atual.
- Mesmo fluxo pergunta-a-pergunta do simulado (reutiliza **pergunta-card**), mas só com perguntas daquele tema.
- Indicação de "tema graduado" quando a maestria é atingida.

**Dados:** perguntas filtradas pelo `:tema` da rota; grava respostas em progresso.service.

---

### 5.5 Desbloquear (`pages/desbloquear`)

**Objetivo:** explicar como pagar e (v1) registar o número. Na v1 pode ser esboço.

**Elementos:**
- Texto com instruções de pagamento por **M-Pesa** e **e-Mola** (número/entidade a preencher depois).
- Campo (`ion-input`) para o aluno introduzir o **número de telefone**.
- Botão **"Verificar desbloqueio"** → na v1, apenas grava localmente o estado como 'pago' (esboço); na fase 2, perguntará ao servidor.
- Mensagem de sucesso/erro.

**Dados:** escreve o `EstadoAcesso` via desbloqueio.service.

---

### 5.6 Componente `pergunta-card` (`components/pergunta-card`)

**Objetivo:** mostrar uma pergunta e, após resposta, revelar o resultado. Reutilizado no simulado e no estudo por tema.

**Entradas (`@Input`):** a `Pergunta`; se já foi respondida; a opção escolhida.

**Saídas (`@Output`):** evento quando o aluno escolhe uma opção; evento ao confirmar.

**Elementos:**
- Enunciado.
- Imagem: se `imagem` não for null, mostra o SVG de `assets/sinais/{imagem}`.
- Opções como lista selecionável (`ion-radio-group` + `ion-radio`).
- **Após confirmar:** destaca a opção correta a verde e a errada (se escolhida) a vermelho; mostra um bloco de **explicação** e, se `artigoRef` existir, a menção "ver Artigo {n}".

**Regra:** a explicação e o artigo aparecem **sempre**, mesmo no nível gratuito — é a montra de qualidade.

---

## 6. Pacote de conteúdo de exemplo

Criar `assets/conteudo/pacote-exemplo.json` à mão, seguindo o formato do documento principal (secção 7). Para a primeira fatia, incluir **2 a 3 temas** com poucas perguntas cada (ex.: `velocidade`, `sinais_perigo`, `prioridade`), com pelo menos uma pergunta com imagem (SVG) e uma marcada como `bloqueado: true` para testar o cadeado. Isto basta para exercitar todo o fluxo antes de haver API.

---

## 7. Ordem sugerida de construção

1. Modelos + `content.service` + `storage.service` a carregar o pacote de exemplo.
2. `pergunta-card` (o coração visual) a mostrar uma pergunta e revelar a resposta.
3. `SimuladoPage` a encadear perguntas e gravar respostas.
4. `progresso.service` + `ResultadoPage` com temas fortes/fracos.
5. `simulado.service` modo adaptativo + botão "repetir focado nas fraquezas".
6. `InicioPage` a amarrar tudo; `EstudoTemaPage`.
7. `DesbloquearPage` (esboço) e cadeados.
