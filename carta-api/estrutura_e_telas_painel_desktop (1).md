# Painel Desktop (Dashboard) — Estrutura e Telas
## Angular (web) + API · v1 (a cozinha)

> Documento de construção para o copiloto. O painel é a ferramenta *online* onde se cria e gere o conteúdo, se aprova e se publica, e onde as escolas aplicam provas às suas turmas. É o lado oposto ao app do aluno (que é offline). Aqui a internet é sempre pressuposta.

Stack: **Angular (web)**, partilhando os modelos de dados do app. Comunica com a API por HTTP. Acesso controlado por **papel** (admin ou escola).

---

## 1. Para que serve e quem usa

Duas funções grandes, dois tipos de utilizador:

**Área de conteúdo (autoria)** — inserir, categorizar, rever, aprovar e publicar perguntas. Usada por **ti (admin)** e pelas **escolas** (que propõem, mas não aprovam nem publicam).

**Área da escola (provas de multimédia)** — a escola monta provas a partir do banco aprovado e aplica-as às suas turmas, vendo os resultados. Usada pelas **escolas**; o admin tem visão global.

Papéis:
- **Admin (tu):** acesso total — cria, aprova, publica, gere escolas, utilizadores e desbloqueios.
- **Escola:** propõe perguntas (ficam "por aprovar"), gere as suas turmas e provas, vê os seus resultados. Não aprova nem publica.

---

## 2. Ligação à API

O painel não guarda dados localmente (ao contrário do app): lê e escreve tudo na API. O fluxo central é: a escola/admin **cria** perguntas → o admin **aprova** → o admin **publica** um pacote → o pacote fica no CDN → o app do aluno descarrega-o. Só perguntas aprovadas entram no pacote.

---

## 3. Estrutura de ficheiros (Angular web)

Componentes standalone, organizados por área.

```
painel-web/
├── angular.json
├── package.json
├── src/
│   ├── main.ts
│   ├── index.html
│   └── app/
│       ├── app.component.ts
│       ├── app.routes.ts
│       ├── models/                 # partilhados com o app (pergunta, pacote, etc.)
│       │   ├── pergunta.model.ts
│       │   ├── escola.model.ts
│       │   ├── prova.model.ts
│       │   └── utilizador.model.ts
│       ├── core/
│       │   ├── api.service.ts       # base HTTP para a API
│       │   ├── auth.service.ts      # login, papel, sessão (token)
│       │   ├── auth.guard.ts        # protege rotas por papel
│       │   ├── perguntas.service.ts
│       │   ├── sinais.service.ts
│       │   ├── artigos.service.ts
│       │   ├── publicacao.service.ts
│       │   ├── escolas.service.ts
│       │   ├── desbloqueios.service.ts
│       │   └── provas.service.ts
│       ├── layout/
│       │   ├── shell.component.ts   # moldura: menu lateral + topo
│       │   └── menu.component.ts    # itens do menu conforme o papel
│       └── pages/
│           ├── login/
│           ├── dashboard/
│           ├── perguntas/           # lista + form de criar/editar
│           ├── aprovacao/           # fila de revisão (admin)
│           ├── sinais/
│           ├── artigos/
│           ├── categorias/          # temas + categorias de carta
│           ├── publicacao/          # gerar/publicar pacotes (admin)
│           ├── escolas/             # gerir escolas (admin)
│           ├── utilizadores/        # contas do painel (admin)
│           ├── desbloqueios/        # pagamentos/desbloqueios (admin)
│           ├── turmas/              # turmas e alunos (escola)
│           ├── provas/              # criar e listar provas da escola
│           └── resultados/          # resultados por turma/aluno
```

---

## 4. Navegação / rotas (por papel)

```
'login'         → LoginPage                (público)
''              → DashboardPage            (admin + escola)
'perguntas'     → ListaPerguntasPage       (admin + escola)
'perguntas/nova'→ FormPerguntaPage         (admin + escola)
'perguntas/:id' → FormPerguntaPage         (admin + escola)
'aprovacao'     → AprovacaoPage            (só admin)
'sinais'        → SinaisPage               (admin; escola só leitura)
'artigos'       → ArtigosPage              (admin; escola só leitura)
'categorias'    → CategoriasPage           (só admin)
'publicacao'    → PublicacaoPage           (só admin)
'escolas'       → EscolasPage              (só admin)
'utilizadores'  → UtilizadoresPage         (só admin)
'desbloqueios'  → DesbloqueiosPage         (só admin)
'turmas'        → TurmasPage               (escola; admin global)
'provas'        → ProvasPage               (escola; admin global)
'resultados'    → ResultadosPage           (escola; admin global)
```

Um `auth.guard` verifica o papel antes de abrir cada rota. O menu lateral mostra só os itens permitidos ao papel.

---

## 5. Telas — Área de conteúdo (autoria)

### 5.1 Login

**Objetivo:** autenticar e identificar o papel.

**Elementos:** campos de email e palavra-passe; botão "Entrar"; mensagem de erro. Ao autenticar, guarda o token e o papel, e redireciona para o Dashboard.

---

### 5.2 Dashboard (inicial)

**Objetivo:** visão rápida do estado, adaptada ao papel.

**Admin vê:** número de perguntas por estado (aprovadas / por aprovar), nº de escolas, fila de aprovação pendente, e a versão do último pacote publicado. Atalhos para "Rever pendentes" e "Publicar".

**Escola vê:** as suas perguntas propostas e o estado delas, as suas turmas e as últimas provas aplicadas.

---

### 5.3 Lista de perguntas

**Objetivo:** encontrar e gerir perguntas.

**Elementos:**
- Tabela com colunas: enunciado (resumido), tema, tipo (teórico/prático), categoria de carta, estado (etiqueta colorida: por aprovar / aprovada), autor.
- Filtros: por tema, tipo, categoria de carta, estado; e caixa de pesquisa por texto.
- Botão "Nova pergunta".
- Ação por linha: editar; (admin) aprovar/rejeitar rápido.

**Regra de papel:** o admin vê todas; a escola vê só as suas.

---

### 5.4 Criar / editar pergunta

**Objetivo:** o formulário central de autoria. É a tela mais importante do painel.

**Campos:**
- Enunciado (texto).
- Tipo: teórico | prático.
- Tema (seleção da lista de temas).
- Categoria de carta (seleção múltipla: ligeiro, pesado, profissional/público).
- Opções: lista dinâmica (adicionar/remover), com um marcador para indicar **qual é a correta**.
- Explicação (texto) — obrigatória; é o coração da qualidade.
- Artigo de referência (seleção da lista de artigos do Código).
- Imagem/sinal (opcional): escolher um SVG da biblioteca de sinais ou carregar um.
- Estado: a escola só pode gravar como "por aprovar"; o admin pode gravar como "aprovada".

**Comportamento:** validação (tem de haver uma opção correta, explicação preenchida). Ao gravar, envia para a API. Para a escola, entra na fila de aprovação do admin.

---

### 5.5 Fila de aprovação (só admin)

**Objetivo:** garantir a qualidade antes de qualquer pergunta chegar ao aluno.

**Elementos:**
- Lista das perguntas "por aprovar", com pré-visualização (enunciado, opções, correta, explicação, artigo).
- Ações: **Aprovar**; **Rejeitar** com nota de correção (devolve à escola); editar antes de aprovar.
- Filtro por escola.

Este passo é obrigatório e protege a promessa "estuda aqui e passas".

---

### 5.6 Biblioteca de sinais

**Objetivo:** gerir os SVGs dos sinais usados nas perguntas.

**Elementos:** grelha de sinais com miniatura, nome, categoria (perigo, proibição, indicação, etc.) e significado. Carregar novo SVG; editar dados; procurar. (Escola: só leitura, para escolher ao criar perguntas.)

---

### 5.7 Artigos do Código

**Objetivo:** ter os artigos do Código da Estrada disponíveis para ligar às perguntas.

**Elementos:** lista pesquisável de artigos (número, título, texto). Importados uma vez do PDF (ação de importação só do admin). Usados como referência no formulário de pergunta.

---

### 5.8 Temas e categorias (só admin)

**Objetivo:** gerir as etiquetas que estruturam tudo.

**Elementos:** duas listas geríveis — **temas** (velocidade, sinais_perigo, prioridade, primeiros_socorros, mecânica…) e **categorias de carta** (ligeiro, pesado, profissional/público…). Adicionar, renomear, desativar.

---

### 5.9 Publicação (só admin)

**Objetivo:** transformar o conteúdo aprovado no pacote que o app descarrega.

**Elementos:**
- Resumo do que entra no próximo pacote (quantas perguntas aprovadas, por tema/categoria).
- Botão **"Publicar novo pacote"** → gera o JSON versionado e coloca-o no CDN.
- Histórico de pacotes (versão, data, nº de perguntas), com opção de ver/repor.

---

## 6. Telas — Administração (só admin)

### 6.1 Escolas

Gerir as escolas parceiras: nome, contacto, estado (ativa/inativa), e as contas de acesso associadas. Criar nova escola gera as credenciais de acesso dela.

### 6.2 Utilizadores do painel

Gerir as contas que entram no painel: nome, email, papel (admin/escola), escola associada (se aplicável). Criar, desativar, repor palavra-passe.

### 6.3 Desbloqueios / Pagamentos

**Objetivo:** ligar o pagamento (M-Pesa/e-Mola) ao desbloqueio do app.

**Elementos:**
- Lista de números desbloqueados (telefone, plano, data, referência do pagamento).
- Registar um desbloqueio manualmente (colar o número que pagou) — o fluxo semi-manual da fase 1.
- Pesquisa por número, para apoio ao cliente.

---

## 7. Telas — Área da escola (provas de multimédia)

> Onde a escola aplica testes às turmas, imitando o exame multimédia do INATRO. Puxa sempre de perguntas **aprovadas**.

### 7.1 Turmas e alunos

**Objetivo:** a escola organiza os seus alunos para lhes aplicar provas e acompanhar o progresso.

**Elementos:** lista de turmas; dentro de cada, a lista de alunos (nome e, se útil, número de telefone/identificador). Criar turma, adicionar/remover alunos.

### 7.2 Criar prova da escola

**Objetivo:** montar uma prova de treino no formato do exame.

**Elementos:**
- Nome da prova.
- Critérios: categoria de carta, tipo (teórico), temas a incluir, número de perguntas (ex.: 25) e nota de passagem (ex.: 24 — a confirmar).
- Modo de seleção: aleatória dentro dos critérios, do banco aprovado.
- Guardar a prova para reutilizar.

### 7.3 Aplicar prova (sessão)

**Objetivo:** dar a prova a uma turma e recolher os resultados.

**Elementos:**
- Escolher a prova e a turma.
- Gerar uma **sessão** com um **código** de acesso.
- Os alunos entram no código (no app, ou num computador da escola em modo exame) e realizam a prova.
- Estado da sessão: em curso / terminada; quem já submeteu.

### 7.4 Resultados da turma

**Objetivo:** o valor que a escola paga — ver quem está pronto e onde a turma falha.

**Elementos:**
- Por aluno: pontuação, aprovado/reprovado, temas fracos.
- Por turma: médias, temas onde a turma erra mais (para o instrutor reforçar na aula).
- Exportar (CSV) para relatório.

---

## 8. Permissões (resumo por papel)

| Área | Admin | Escola |
|---|---|---|
| Criar/editar perguntas | Sim | Sim (fica por aprovar) |
| Aprovar/publicar | Sim | Não |
| Sinais, artigos, temas | Gerir | Ler |
| Escolas, utilizadores, desbloqueios | Sim | Não |
| Turmas, provas, resultados | Ver tudo | Só os seus |

---

## 9. Faseamento

**Fase 2a — Autoria (o mais urgente):** login, lista e formulário de perguntas, fila de aprovação, sinais, artigos, temas, publicação, escolas e utilizadores. É o que permite encher o app de conteúdo com as escolas desde o início.

**Fase 2b — Desbloqueios:** tela de pagamentos/desbloqueios (pode começar manual).

**Fase 3 — Provas nas escolas:** turmas, criar/aplicar prova e resultados. Entra quando houver escolas parceiras a bordo.

---

## 10. Ligação à API (endpoints por área)

**Autenticação:** `POST /auth/login`

**Perguntas:** `GET /perguntas`, `POST /perguntas`, `PUT /perguntas/{id}`, `POST /perguntas/{id}/aprovar`, `POST /perguntas/{id}/rejeitar`

**Sinais / Artigos / Categorias:** `GET|POST|PUT /sinais`, `GET /artigos`, `POST /artigos/importar`, `GET|POST /temas`, `GET|POST /categorias`

**Publicação:** `POST /publicar`, `GET /pacotes`

**Escolas / Utilizadores:** `GET|POST|PUT /escolas`, `GET|POST|PUT /utilizadores`

**Desbloqueios:** `GET /desbloqueios`, `POST /desbloqueios`, `GET /desbloqueios/{telefone}`

**Turmas / Provas / Resultados:** `GET|POST /escolas/{id}/turmas`, `GET|POST /turmas/{id}/alunos`, `GET|POST /provas`, `POST /provas/{id}/aplicar`, `GET /provas/{id}/resultados`, `POST /sessoes/{codigo}/submeter`
