# ProntoVia — Documento Mestre de Refatoração e Arquitetura do Produto

**Versão:** 1.1  
**Estado:** Direção estratégica aprovada para refatoração  
**Estado operacional:** Plataforma ainda não publicada (não live); alterações estruturais podem ser feitas antes do lançamento.
**Origem:** Evolução do projeto CartaPro  
**Marca principal:** ProntoVia  
**Produtos:** ProntoVia, ProntoVia+, ProntoVia Escolas  
**Princípio central:** o candidato pode usar o ProntoVia de forma independente; a escola é uma extensão opcional.

---

## 1. Objetivo deste documento

Este documento orienta a transformação do sistema atual CartaPro em **ProntoVia**, preservando o investimento técnico existente e refatorando apenas os elementos necessários para alinhar arquitetura, produto, marca, modelo de negócio e experiência do utilizador à nova estratégia.

A refatoração não é uma reconstrução do zero.

O objetivo é:

- preservar a base técnica que já funciona;
- remover dependências desnecessárias do conceito anterior;
- tornar o aluno independente da escola;
- transformar a escola numa camada complementar;
- reforçar aprendizagem, prática e prontidão;
- criar uma presença pública própria da marca;
- reduzir o risco de dependência de processos regulatórios;
- preparar o produto para crescimento B2C e B2B.

---

# 2. Visão do Produto

O **ProntoVia** é uma plataforma de aprendizagem, preparação e acompanhamento para candidatos à carta de condução.

O produto deve ajudar o utilizador a:

1. aprender;
2. praticar;
3. simular;
4. identificar dificuldades;
5. rever conteúdos;
6. acompanhar a sua evolução;
7. saber quão preparado está para o exame.

O ProntoVia não deve ser definido apenas como um simulador de exames.

A promessa central é:

> **Aprenda. Pratique. Esteja pronto.**

---

# 3. Princípio estratégico fundamental

## 3.1 Independência do candidato

Um utilizador deve conseguir:

- instalar o ProntoVia;
- criar conta;
- estudar;
- praticar;
- fazer simulados;
- acompanhar progresso;
- receber recomendações;
- utilizar revisão inteligente;
- calcular a sua prontidão;

sem qualquer ligação a uma escola.

## 3.2 Escola como extensão

A escola acrescenta:

- turmas;
- instrutores;
- testes privados;
- tarefas;
- acompanhamento;
- resultados;
- conteúdo próprio;
- sessões de prova;
- relatórios pedagógicos.

Mas nunca deve ser requisito para utilizar o produto.

Regra:

> **ProntoVia sem escola = produto completo.  
> ProntoVia + escola = produto ampliado.**

---

# 4. Posicionamento

O ProntoVia não deve comunicar:

> “Aplicação para fazer exames de condução.”

Deve comunicar:

> **“Plataforma que ajuda o candidato a aprender, praticar e saber quando está preparado para o exame de condução.”**

O simulador passa a ser uma funcionalidade importante, mas não a definição do produto.

---

# 5. Arquitetura de produto

O ecossistema terá quatro superfícies principais.

## 5.1 ProntoVia Website

Website institucional e comercial da marca.

Objetivos:

- apresentar o produto;
- explicar como funciona;
- apresentar funcionalidades;
- apresentar ProntoVia+;
- apresentar ProntoVia Escolas;
- esclarecer dúvidas;
- construir confiança;
- captar utilizadores;
- captar escolas;
- encaminhar candidatos para a aplicação;
- funcionar como canal próprio de aquisição.

## 5.2 ProntoVia App

Aplicação principal do candidato.

Disponível inicialmente em:

- Android;
- Web.

Posteriormente:

- iOS.

## 5.3 ProntoVia Escolas

Área profissional para:

- escolas;
- administradores escolares;
- instrutores;
- turmas;
- alunos;
- avaliações;
- acompanhamento pedagógico.

## 5.4 ProntoVia Admin

Backoffice interno da plataforma.

Responsável por:

- conteúdo;
- revisão;
- publicação;
- utilizadores;
- escolas;
- acessos;
- pagamentos;
- taxonomias;
- auditoria;
- operação da plataforma.

---

# 6. Estrutura pública de domínios

Estrutura alvo:

```text
prontovia.co.mz
    Website institucional

app.prontovia.co.mz
    Aplicação Web do candidato

escolas.prontovia.co.mz
    ProntoVia Escolas

admin.prontovia.co.mz
    Administração da plataforma

api.prontovia.co.mz
    API Laravel
```

Os subdomínios podem apontar para o mesmo backend Laravel quando tecnicamente apropriado.

---

# 7. Website institucional

## 7.1 Decisão arquitetural

O website institucional será implementado inicialmente **dentro do backend Laravel existente**.

Não será criado um projeto separado nesta fase.

## 7.2 Motivo

A plataforma atual já utiliza:

- Laravel;
- Blade;
- Vite;
- Tailwind CSS;
- controllers Web;
- sessões;
- acesso à mesma base de dados.

Isso permite criar o website com baixo custo adicional de manutenção.

## 7.3 Separação lógica

Mesmo estando no mesmo Laravel, o website será tratado como módulo independente.

Estrutura sugerida:

```text
app/Http/Controllers/
├── Website/
├── Admin/
├── School/
└── Api/V1/

resources/views/
├── website/
├── admin/
├── school/
├── exams/
└── layouts/
```

O website não deve reutilizar controllers administrativos.

## 7.4 Páginas principais

```text
/
├── candidatos
├── escolas
├── como-funciona
├── funcionalidades
├── precos
├── sinais
├── aprender
├── faq
├── contacto
├── privacidade
└── termos
```

## 7.5 Home

A Home deve apresentar dois caminhos principais.

### Para candidatos

CTA:

> **Baixar aplicação**

Mensagem:

> Prepare-se para conduzir com confiança.

### Para escolas

CTA:

> **Conhecer ProntoVia Escolas**

Mensagem:

> Acompanhe a preparação de cada aluno.

---

# 8. Website como canal de aquisição

O website não será apenas institucional.

Também deverá permitir descoberta através de pesquisa.

Exemplos:

```text
/sinais/stop
/sinais/perigo
/aprender/prioridade
/aprender/velocidade
```

Fluxo esperado:

```text
Google / Redes sociais / Indicação
                ↓
        prontovia.co.mz
                ↓
       Conteúdo gratuito
                ↓
      Criar conta / baixar app
                ↓
           ProntoVia
```

A estratégia reduz a dependência de escolas como fonte de utilizadores.

---

# 9. Independência de plataformas regulatórias

O ProntoVia não deve competir com funções regulatórias.

Caso o INATRO implemente futuramente uma plataforma obrigatória para escolas, o ProntoVia deve continuar relevante.

## Funções regulatórias

Podem incluir:

- inscrição;
- licenciamento;
- marcação de exames;
- exame oficial;
- certificação;
- fiscalização.

## Funções do ProntoVia

Devem permanecer:

- aprendizagem;
- estudo;
- prática;
- explicação;
- diagnóstico;
- revisão;
- prontidão;
- acompanhamento pedagógico;
- preparação individual.

Princípio:

> **O regulador administra o processo oficial.  
> O ProntoVia ajuda o candidato a aprender.**

---

# 10. Pilares funcionais do ProntoVia

## 10.1 Aprender

Conteúdo pedagógico estruturado por temas.

Fluxo:

```text
Tema
 ↓
Lição
 ↓
Exercício
 ↓
Verificação
```

## 10.2 Praticar

Treino direcionado.

Filtros:

- por tema;
- por categoria;
- apenas erros;
- perguntas difíceis;
- nunca respondidas;
- em revisão;
- sinais.

## 10.3 Simular

Ambiente semelhante ao exame.

Inclui:

- tempo;
- número de perguntas;
- regras de avaliação;
- resultado;
- revisão;
- histórico.

## 10.4 Rever

Repetição inteligente baseada em:

- erros;
- frequência;
- tempo de resposta;
- desempenho recente;
- repetição espaçada.

## 10.5 Prontidão

Indicador pedagógico que responde:

> **Quanto este candidato está preparado?**

Exemplo:

```text
Prontidão geral: 78%

Sinais:       91%
Prioridade:   62%
Velocidade:   84%
Manobras:     58%
```

Recomendação:

> Reforce prioridade e manobras antes do próximo simulado.

---

# 11. Motor de aprendizagem

Criar um domínio explícito:

```text
Learning
```

Responsabilidades:

- progresso;
- domínio por tema;
- recomendações;
- revisão;
- prontidão;
- histórico;
- sessões de estudo.

Entidades conceituais:

```text
LearningProfile
LearningEvent
TopicMastery
StudyRecommendation
ReadinessScore
```

---

# 12. Eventos de aprendizagem

Estrutura recomendada:

```text
learning_events

id
mobile_user_id
type
entity_type
entity_id
topic_id
result
duration_ms
metadata
occurred_at
```

Tipos:

```text
question_answered
simulation_completed
lesson_read
sign_practiced
revision_completed
exam_completed
```

Isso permite evolução futura de métricas e modelos de recomendação sem alterar continuamente a estrutura central.

---

# 13. Conta do candidato

A conta do candidato permanecerá independente da escola.

A entidade atual `mobile_users` deverá continuar como base da identidade individual.

A conta deve manter:

- perfil;
- progresso;
- histórico;
- revisões;
- simulados;
- conteúdos estudados;
- pagamentos;
- prontidão.

---

# 14. Relação com escolas

Hoje existem identidades distintas:

```text
mobile_users
students
```

Elas não devem ser fundidas diretamente.

## Solução

Criar:

```text
school_memberships
```

Estrutura proposta:

```text
school_memberships

id
school_id
mobile_user_id
student_id nullable
classroom_id nullable
status
joined_at
left_at
created_at
updated_at
```

Estados:

```text
invited
active
suspended
left
completed
```

Relação:

```text
MobileUser
    |
    +── SchoolMembership
            |
            +── School
            +── Student
            +── Classroom
```

---

# 15. Regra de propriedade dos dados

A escola não é proprietária do progresso pessoal do candidato.

O candidato deve manter os seus dados de aprendizagem quando:

- muda de escola;
- termina o curso;
- sai de uma turma;
- deixa de estar vinculado.

Dados especificamente produzidos dentro de uma atividade escolar poderão continuar associados à escola para fins administrativos, respeitando as regras de privacidade.

---

# 16. ProntoVia Escolas

## 16.1 Escolas

- perfil;
- operadores;
- instrutores;
- turmas;
- alunos;
- convites;
- vínculos ProntoVia.

## 16.2 Testes

- criar;
- duplicar;
- selecionar perguntas;
- criar blueprint;
- configurar tempo;
- configurar classificação;
- publicar internamente;
- aplicar à turma;
- aplicar individualmente.

## 16.3 Sessões

- código de sessão;
- controlo de acesso;
- início/fim;
- acompanhamento;
- resultados.

## 16.4 Tarefas

Uma escola pode enviar:

- treino;
- leitura;
- simulado;
- teste;
- revisão.

## 16.5 Analytics

- por aluno;
- por turma;
- por tema;
- evolução;
- dificuldades;
- prontidão;
- taxa de conclusão.

---

# 17. Instrutores

Criar conceito próprio de instrutor.

Papéis alvo:

```text
platform_admin
school_owner
school_admin
instructor
content_author
content_reviewer
```

Permissões:

```text
question.create
question.submit
question.review
exam.create
exam.publish
classroom.manage
student.view
analytics.view
```

Migrar gradualmente de perfis simples para RBAC granular.

---

# 18. Conteúdo

Manter:

- temas;
- perguntas;
- sinais;
- artigos;
- lições;
- glossário;
- categorias;
- provas públicas;
- pacotes versionados.

Workflow editorial:

```text
draft
  ↓
review
  ↓
approved
  ↓
published
```

Origem do conteúdo:

```text
platform
school
instructor
```

Visibilidade:

```text
private
school
platform
```

---

# 19. Conteúdo público e conteúdo autenticado

Nem todo o conteúdo deverá exigir login.

## Público

Pode incluir:

- sinais;
- artigos;
- algumas lições;
- demonstrações;
- conteúdo educativo;
- mini-treinos.

## Autenticado

Necessário para:

- histórico;
- progresso;
- sincronização;
- revisão;
- prontidão;
- recomendações;
- pagamentos;
- ligação a escolas.

---

# 20. Offline

Preservar a estratégia offline-first.

Melhorias necessárias:

- cache completo de imagens;
- pacotes menores;
- atualização incremental;
- resolução de conflitos;
- background sync quando possível;
- revisão de segurança dos dados locais.

Objetivo:

> O candidato deve conseguir estudar com conectividade limitada.

---

# 21. API

Manter:

```text
/api/v1
```

Adicionar gradualmente:

```text
/api/v1/learning
/api/v1/readiness
/api/v1/recommendations
/api/v1/school-memberships
/api/v1/instructors
```

Não criar API v2 sem necessidade real de breaking changes.

---

# 22. Monólito modular

Manter Laravel como monólito modular nesta fase.

Estrutura conceitual:

```text
Domain/
├── Identity
├── Learning
├── Content
├── Assessment
├── Schools
├── Billing
├── Publishing
└── Website
```

Não introduzir microserviços sem necessidade comprovada.

---

# 23. Fonte de verdade das regras

O backend será a fonte principal das regras de negócio.

Centralizar:

- aprovação;
- nota mínima;
- elegibilidade;
- regras de prova;
- entitlement;
- prontidão;
- políticas de acesso;
- categorias.

O frontend pode repetir algumas regras apenas para melhorar a experiência imediata.

---

# 24. Taxonomias

Migrar todos os agrupamentos importantes para dados administráveis.

Evitar valores hardcoded para:

- temas;
- grupos;
- categorias;
- planos;
- tipos de treino;
- regras pedagógicas.

Objetivo:

> Alterar uma taxonomia não deve exigir novo deploy.

---

# 25. Modelo comercial

## 25.1 ProntoVia Free

- conteúdo essencial;
- sinais;
- treino limitado;
- simulados limitados;
- progresso básico.

## 25.2 ProntoVia+

- simulados ilimitados;
- revisão inteligente;
- prontidão;
- treino personalizado;
- histórico completo;
- recursos premium.

## 25.3 ProntoVia Escolas

- painel;
- turmas;
- instrutores;
- testes;
- tarefas;
- resultados;
- analytics;
- banco privado de perguntas.

---

# 26. Branding

Marca aprovada para continuar o projeto:

# ProntoVia

Família:

```text
ProntoVia
ProntoVia+
ProntoVia Escolas
```

Mensagem principal:

> **Aprenda. Pratique. Esteja pronto.**

A identidade visual deverá ser própria e não depender visualmente de entidades reguladoras.

---

# 27. Rebranding técnico

Identificar e substituir gradualmente:

```text
CartaPro
mz.cartapro.app
carta-app-db
cartapro-*
api.cartapro.co.mz
logos CartaPro
assets CartaPro
```

A mudança do `appId` deve ser avaliada com cuidado conforme o estado da publicação na Google Play.

---

# 28. O que deve ser preservado

Preservar sempre que possível:

- Laravel;
- Angular;
- Ionic;
- Capacitor;
- autenticação;
- motor de perguntas;
- sinais;
- simulados;
- exames;
- workflow editorial;
- publicação versionada;
- pagamentos;
- IndexedDB;
- sincronização;
- repetição espaçada;
- histórico;
- progresso;
- analytics;
- testes automatizados.

---

# 29. O que deve ser refatorado

Prioridades:

1. branding;
2. identidade do aluno;
3. vínculo opcional à escola;
4. instrutores;
5. RBAC;
6. domínio Learning;
7. prontidão;
8. regras duplicadas;
9. taxonomias;
10. conteúdo offline;
11. website institucional;
12. monetização por planos.

---

# 30. Plano de refatoração

## 30.0 Acompanhamento da execução

Este plano é atualizado no fecho de cada fase. A validação automatizada é
executada uma vez no final da fase, evitando repetir a suite completa após cada
funcionalidade.

Legenda: `[x]` concluído, `[ ]` pendente.

## Fase 0 — Proteção do estado atual

- [x] backup lógico através do histórico Git existente;
- [x] tag/release da versão atual (`prontovia-admin-shell-baseline-20260824`);
- [x] remover o endpoint temporário de finalização do deploy; a finalização é feita por SSH;
- [x] rever secrets: credenciais reais permanecem fora do Git e o ambiente de teste contém apenas valores descartáveis;
- [x] isolar os testes em SQLite na memória e impedir execução acidental noutra base de dados;
- [x] validar os testes atuais no fecho da fase (174 testes/781 asserções na API e 44 testes na app).

**Estado da Fase 0:** concluída em 25 de agosto de 2026.

## Fase 1 — Fundação ProntoVia

- [x] aplicar o branding ProntoVia no backend Laravel;
- [x] consolidar a identidade visual existente no website, autenticação, painel e provas web;
- [x] alinhar textos e mensagens públicas, administrativas, API, SMS e pagamentos;
- [x] centralizar os assets ProntoVia usados pelo backend e retirar referências aos assets CartaPro;
- [x] preparar os domínios ProntoVia na configuração de exemplo;
- [x] alinhar a nomenclatura operacional do backend, preservando aliases temporários dos comandos antigos;
- [x] manter a estrutura inicial independente do website em `Website/` e `resources/views/website/`;
- [ ] aplicar branding, assets, `appId` e armazenamento ProntoVia na aplicação móvel — adiado por decisão de escopo.
- [x] validar os testes do backend no fecho da fase (174 testes/781 asserções).

**Escopo desta execução:** backend Laravel. A aplicação móvel será tratada numa
etapa própria para evitar misturar superfícies e aumentar o custo de validação.

**Estado da Fase 1 no backend:** concluída em 25 de agosto de 2026. A parcela
mobile permanece pendente e não integra o escopo desta execução.

## Fase 2 — Website institucional

Criar:

- Home;
- Candidatos;
- Escolas;
- Como funciona;
- Funcionalidades;
- FAQ;
- Contacto;
- páginas legais;
- CTAs para app e escola.

## Fase 3 — Identidade e vínculo escolar

- [x] criar:

```text
school_memberships
```

Implementar:

- [x] convite iniciado por uma escola para uma conta ProntoVia existente;
- [x] associação aceite exclusivamente pelo candidato;
- [x] desvinculação pelo candidato e gestão de suspensão/conclusão pela escola;
- [x] mudança de escola com encerramento do vínculo anterior;
- [x] preservação do histórico pessoal, que continua ligado apenas a `mobile_users`;
- [x] validação de pertença entre escola, turma e registo de aluno;
- [x] isolamento de acesso entre escolas;
- [x] endpoints em `/api/v1/school-memberships` e endpoints de gestão escolar;
- [x] validar os testes do backend no fecho da fase (180 testes/806 asserções).

**Estado da Fase 3 no backend:** concluída em 25 de agosto de 2026.

## Fase 4 — Instrutores e permissões

- [x] criar entidade própria de instrutor ligada a uma conta do painel e a uma escola;
- [x] implementar vínculo muitos-para-muitos entre instrutores e turmas;
- [x] criar permissões administráveis e atribuição por papel ou diretamente ao utilizador;
- [x] suportar `platform_admin`, `school_owner`, `school_admin`, `instructor`, `content_author` e `content_reviewer`;
- [x] preservar `admin` e `school` como aliases legados durante a migração;
- [x] proteger operações de perguntas, provas, turmas, alunos, analítica e instrutores por permissão;
- [x] limitar instrutores às turmas que lhes foram atribuídas;
- [x] devolver papel, rótulo e permissões efetivas na autenticação da API;
- [x] validar os testes do backend no fecho da fase (186 testes/836 asserções).

**Estado da Fase 4 no backend:** concluída em 25 de agosto de 2026.

## Fase 5 — Learning Core

- [x] criar/refatorar:

```text
LearningProfile
LearningEvent
TopicMastery
StudyRecommendation
ReadinessScore
```

- [x] derivar o domínio Learning da telemetria móvel existente, sem duplicar o histórico pessoal;
- [x] registar eventos deduplicados para respostas, simulados e conteúdos lidos;
- [x] calcular domínio por tema a partir de precisão, volume e atividade recente;
- [x] calcular prontidão com decomposição por tema e desempenho recente em simulados;
- [x] gerar recomendações para temas fracos, revisões vencidas e início de aprendizagem;
- [x] expor perfil, eventos, prontidão e recomendações em endpoints autenticados;
- [x] manter todos os dados Learning privados e pertencentes à conta do candidato;
- [x] validar os testes do backend no fecho da fase (189 testes/862 asserções).

**Estado da Fase 5 no backend:** concluída em 25 de agosto de 2026.

## Fase 6 — Nova experiência do candidato

**Estado:** adiada por decisão de escopo. Esta fase pertence à aplicação móvel
e será retomada depois da refatoração prioritária do backend.

Navegação:

```text
Início
Aprender
Praticar
Simular
Progresso
```

Área opcional:

```text
Minha Escola
```

## Fase 7 — ProntoVia Escolas

Evoluir:

- [x] tarefas para turmas ou candidatos com treino, leitura, simulado, teste e revisão;
- [x] distribuição apenas a candidatos com vínculo escolar ativo;
- [x] acompanhamento de estado atribuído, em curso e concluído;
- [x] preservação do histórico concluído depois da saída da escola, sem manter acesso a tarefas abertas;
- [x] instrutores e atribuição a turmas, implementados na Fase 4;
- [x] testes, sessões e resultados escolares preservados e protegidos por permissão;
- [x] turmas, alunos e vínculos escolares integrados;
- [x] analytics por turma e progresso de tarefas;
- [x] isolamento de instrutores às turmas atribuídas;
- [x] validar os testes do backend no fecho da fase (193 testes/882 asserções).

**Estado da Fase 7 no backend:** concluída em 25 de agosto de 2026.

## Fase 8 — Monetização

Migrar de plano único para:

```text
Free
Plus
School
```

- [x] catálogo de planos migrado para dados administráveis (`plans`), sem exigir deploy para alterar preço, duração, recursos ou disponibilidade;
- [x] `Free` definido como produto base do candidato;
- [x] `Plus` definido como único produto individual comprável, com pagamento e desbloqueio normalizados para `plus`;
- [x] `School` concedido pelo backend apenas a candidatos com vínculo escolar ativo e escola ativa;
- [x] prioridade de entitlement centralizada em `School > Plus > Free`;
- [x] alias legado `completo` aceito na entrada e normalizado para `plus` durante a transição;
- [x] contrato legado `plano: gratis|pago` preservado até à futura fase mobile, acompanhado pelos novos campos `produto` e `nomePlano`;
- [x] endpoints administrativos para consultar e atualizar o catálogo de planos;
- [x] `Free` e `School` impedidos de entrar no fluxo de cobrança individual;
- [x] testes direcionados da fase (43 testes/165 asserções) e suíte completa do backend (197 testes/910 asserções).

**Estado da Fase 8 no backend:** concluída em 25 de agosto de 2026. A migration `000029` foi validada em SQLite descartável e ainda não foi aplicada à base local, por decisão de segurança.

## Fase 9 — Rebranding técnico do backend

- [x] identidade do pacote Composer alterada de `laravel/laravel` para `prontovia/backend`;
- [x] descrição e palavras-chave técnicas alinhadas à plataforma ProntoVia;
- [x] ambiente de referência já usa `APP_NAME="ProntoVia API"` e base `prontovia`;
- [x] referências do backend ao projeto móvel legado retiradas da documentação operacional;
- [x] logotipos CartaPro antigos, sem utilização, removidos dos assets distribuídos;
- [x] comandos operacionais usam o namespace `prontovia:*`;
- [x] aliases `cartapro:*` mantidos temporariamente para não quebrar cron jobs já configurados;
- [x] identificadores e mudanças do aplicativo móvel continuam adiados com a Fase 6;
- [x] validar o manifesto Composer e os testes do backend no fecho da fase (200 testes/924 asserções).

**Estado da Fase 9 no backend:** concluída em 25 de agosto de 2026. Não exigiu migration de base de dados.

## Fase 10 — Segurança e preparação operacional

- [x] auditar dependências bloqueadas com o Composer;
- [x] eliminar 6 alertas de segurança de `league/commonmark`, incluindo vulnerabilidades de negação de serviço e filtragem de links;
- [x] atualizar apenas `league/commonmark` (2.8.3 → 2.10.0) e a dependência necessária `nette/schema` (1.3.5 → 1.3.6);
- [x] adicionar cabeçalhos globais contra MIME sniffing, framing indevido e exposição desnecessária de capacidades do navegador;
- [x] ativar HSTS apenas em pedidos HTTPS, evitando forçar comportamento incorreto no desenvolvimento HTTP;
- [x] explicitar cookies de sessão cifrados, Secure, HttpOnly e SameSite=Lax no ambiente de referência;
- [x] adiar CSP rígida até existir inventário dos recursos externos usados pelo website;
- [x] validar auditoria sem alertas, testes específicos (6 testes/31 asserções) e suíte completa (203 testes/941 asserções) no fecho da fase.

**Estado da Fase 10 no backend:** concluída em 25 de agosto de 2026. Não exigiu migration de base de dados.

## Fase 11 — Design system e UI operacional

Escopo: painel web administrativo e escolar. A experiência mobile permanece adiada com a Fase 6.

- [x] pesquisar padrões oficiais para shell de aplicações modulares, navegação lateral, tabelas densas e acessibilidade;
- [x] manter a paleta ProntoVia com índigo e ciano para marca, laranja para destaque e cores semânticas separadas;
- [x] organizar os módulos em grupos recolhíveis de dois níveis, preservando o grupo da página atual aberto;
- [x] guardar localmente a preferência de grupos abertos sem transformar o frontend em fonte de autorização;
- [x] reduzir cards e sombras, usando superfícies contínuas, divisores e espaço para definir hierarquia;
- [x] criar componentes Blade reutilizáveis para cabeçalhos de página, toolbars de dados, tabelas, estados, formulários, paginação, diálogos e estados vazios;
- [x] alinhar os módulos de maior densidade inicial (`Perguntas` e `Escolas`) ao novo padrão;
- [x] padronizar tabelas para largura útil, cabeçalhos semânticos, hover/focus de linha, paginação e ações consistentes;
- [x] garantir foco visível, navegação por teclado, skip link e adaptação do shell para ecrãs menores;
- [x] validar testes direcionados (28 testes/137 asserções) e suíte completa (206 testes/962 asserções) no fecho da etapa.

Referências adotadas: Carbon UI Shell e Data Table, GOV.UK Design System e WCAG 2.2. O padrão limita a navegação a dois níveis e usa tabs dentro da página quando um terceiro nível seria necessário.

**Estado da Fase 11 no painel web:** concluída em 25 de agosto de 2026. O shell e os estilos transversais cobrem todos os módulos; `Perguntas` e `Escolas` são as referências para a migração progressiva das views específicas.

---

# 31. Critério de sucesso arquitetural

A refatoração estará correta quando um utilizador conseguir:

1. conhecer o ProntoVia através do website;
2. instalar a aplicação;
3. criar conta sem escola;
4. estudar;
5. praticar;
6. realizar simulados;
7. receber recomendações;
8. acompanhar prontidão;
9. ligar-se opcionalmente a uma escola;
10. receber tarefas e testes dessa escola;
11. mudar ou sair da escola;
12. manter o seu histórico e progresso.

---

# 32. Princípios que não devem ser quebrados

1. **O candidato existe independentemente da escola.**
2. **A escola é uma extensão do produto, não a sua fundação.**
3. **O simulador é uma funcionalidade, não o produto inteiro.**
4. **O ProntoVia deve continuar útil mesmo que processos oficiais sejam digitalizados por terceiros.**
5. **O website é uma superfície oficial do produto e um canal de aquisição próprio.**
6. **O backend Laravel pode servir Website, Escolas, Admin e API desde que os módulos permaneçam separados.**
7. **Não reconstruir componentes sólidos sem necessidade.**
8. **O backend deve ser a fonte de verdade das regras de negócio.**
9. **A experiência offline continua estratégica.**
10. **O progresso pessoal acompanha o candidato.**

---

# 33. Princípio final

> **A escola pertence ao ecossistema do utilizador. O utilizador não pertence à escola.**

E:

> **ProntoVia existe para ajudar cada candidato a chegar preparado à via — independentemente de onde estuda.**
