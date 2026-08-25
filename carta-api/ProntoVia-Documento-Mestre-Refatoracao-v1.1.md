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

## Regra de entrada Free e recursos pagos

O candidato pode instalar a aplicação e começar a utilizar o produto Free
sem criar conta. O conteúdo Free deve funcionar localmente e pode ser
explorado sem autenticação.

Uma conta ProntoVia passa a ser obrigatória para:

- guardar histórico e progresso na nuvem;
- sincronizar entre dispositivos;
- receber recomendações e prontidão persistentes;
- aceitar vínculos e tarefas escolares;
- iniciar pagamentos ou aceder ao ProntoVia+.

O servidor continua a ser a fonte de verdade do entitlement. Um visitante sem
sessão recebe apenas conteúdo Free; conteúdo Plus nunca deve ser entregue
anonimamente.

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

### Alinhamento com a Direção Visual Web v1.0 — Sidemenu

- [x] largura desktop definida em `244px` e modo recolhido em `72px`;
- [x] fundo índigo sólido `#1A1F5C`, sem gradiente;
- [x] módulos organizados em `Visão geral`, `Aprendizagem`, `Conteúdo`, `Gestão` e `Sistema`;
- [x] itens condicionados por perfil e permissões do backend;
- [x] Bootstrap Icons usado como mapa iconográfico único;
- [x] estado ativo discreto com barra lateral ciano, texto branco e fundo translúcido;
- [x] hover com branco de baixa opacidade, sem glow;
- [x] menu recolhível no desktop com preferência guardada localmente;
- [x] offcanvas responsivo para tablet e mobile até `900px`;
- [x] labels, `aria-current`, tooltips no modo recolhido e navegação por teclado preservados;
- [x] validar build visual e testes no fecho da etapa do sidemenu — build concluído e suíte integral aprovada (206 testes, 966 asserções).

### Alinhamento com a Direção Visual Web v1.0 — Topbar

- [x] componente reutilizável aplicado ao shell administrativo;
- [x] altura compacta e superfície branca com separador inferior discreto;
- [x] controlo de recolhimento do sidemenu movido para a topbar no desktop;
- [x] botão de abertura do menu preservado em tablet e mobile;
- [x] pesquisa contextual exibida apenas nos módulos que possuem pesquisa real;
- [x] acesso às notificações ligado à fila real de aprovação, condicionado por permissão;
- [x] perfil compacto com nome, papel, escola e término de sessão;
- [x] títulos e descrições mantidos no cabeçalho da página, sem competir com a topbar;
- [x] Bootstrap Icons, foco visível, labels acessíveis e comportamento responsivo aplicados;
- [x] validar build e testes no fecho da etapa do topbar — build concluído e suíte integral aprovada (207 testes, 977 asserções).

### Alinhamento com a Direção Visual Web v1.0 — Dashboard

- [x] cabeçalho de página com descrição curta e uma única ação primária;
- [x] quatro KPIs no máximo, sem gradientes ou círculos decorativos;
- [x] Bootstrap Icons com dimensão e função visual consistentes;
- [x] evolução principal em área ampla e atenção necessária em área secundária;
- [x] visão da escola orientada a desempenho, temas críticos e próxima ação;
- [x] visão da plataforma orientada a adoção, utilização e prioridades editoriais;
- [x] atividade escolar e editorial apresentada em superfícies densas e simples;
- [x] hierarquia responsiva para desktop, tablet e mobile;
- [x] validar build e testes no fecho da etapa do dashboard — build concluído e suíte integral aprovada (208 testes, 988 asserções).

### Alinhamento com a Direção Visual Web v1.0 — Tabelas

- [x] componente de tabela reutilizável consolidado;
- [x] densidades confortável (`48px`) e compacta (`40px`) suportadas;
- [x] cabeçalho simples e fixo, hover discreto e largura máxima disponível;
- [x] toolbar previsível com pesquisa, filtros, limpeza e ação primária;
- [x] componente reutilizável de ações de linha com `Ver` visível e menu de opções;
- [x] paginação própria com intervalo apresentado, total e navegação acessível;
- [x] estados vazios, captions e associação acessível ao título preservados;
- [x] texto longo com truncamento visual e conteúdo integral em tooltip nativo;
- [x] comportamento responsivo com rolagem horizontal controlada;
- [x] padrão aplicado ao banco de `Perguntas` como referência;
- [x] paginação padrão das listagens administrativas uniformizada em 10 registos por página;
- [x] tabela de `Perguntas` reduzida às colunas essenciais: pergunta, tema, estado, atualização e ações;
- [x] identificador técnico da pergunta removido da listagem e das ações visíveis;

### Aplicação da Direção Visual — Utilizadores Mobile

- [x] página alinhada ao cabeçalho, toolbar, tabela e paginação reutilizáveis;
- [x] cinco colunas operacionais: utilizador, plano, atividade, estado e ações;
- [x] contacto e último acesso apresentados como informação secundária;
- [x] ação de detalhe visível e ativação/desativação no menu de linha;
- [x] dez registos por página preservados;
- [x] validar build e testes no fecho da etapa de Utilizadores Mobile — build concluído e suíte integral aprovada (209 testes, 1002 asserções).

### Aplicação da Direção Visual — Turmas

- [x] cabeçalho com ação primária de criação e contador de turmas;
- [x] tabela com turma, escola, alunos, sessões, estado e ações;
- [x] gestão de alunos preservada num painel expansível por turma;
- [x] ações de detalhe, gestão de alunos e remoção agrupadas no menu de linha;
- [x] paginação reutilizável de 10 registos e filtros por escola;
- [x] formulário de criação mantido acessível sem dominar a listagem;
- [x] validar build e testes no fecho da etapa de Turmas — build concluído e suíte integral aprovada (210 testes, 1007 asserções).

### Aplicação da Direção Visual — Provas

- [x] cabeçalho com descrição curta, contador e uma única ação primária;
- [x] tabela reduzida a prova, acesso, publicação, perguntas, aprovação e ações;
- [x] publicação, arquivamento, plano e cópia movidos para o menu de linha;
- [x] estados semânticos preservados para acesso, publicação e plano;
- [x] paginação reutilizável de 10 registos;
- [x] diálogos de cópia e remoção preservados;
- [x] validar build e testes no fecho da etapa de Provas — build concluído e suíte integral aprovada (211 testes, 1012 asserções).

### Aplicação da Direção Visual — Detalhe da Prova

- [x] detalhe específico de prova separado do detalhe genérico;
- [x] cabeçalho com retorno, título, contexto e estado de acesso;
- [x] resumo operacional com acesso, publicação, perguntas, nota, sessões e tentativas;
- [x] configuração apresentada em definition list, sem excesso de cards;
- [x] perguntas selecionadas apresentadas numa tabela própria e legível;
- [x] informação editorial e publicação destacadas numa secção dedicada;
- [x] validar build e testes no fecho da etapa do detalhe da Prova — build concluído e suíte integral aprovada (212 testes, 1017 asserções).

### Aplicação da Direção Visual — Edição da Prova

- [x] cabeçalho de página próprio com retorno às provas;
- [x] informação geral separada da seleção de perguntas;
- [x] campos relacionados mantidos em grelha de duas colunas;
- [x] prova selada mantém aviso e perguntas não editáveis;
- [x] códigos técnicos removidos da lista visual de perguntas;
- [x] ações de cancelar e guardar numa barra inferior consistente;
- [x] validar build e testes no fecho da etapa de edição da Prova — build concluído e suíte integral aprovada (213 testes, 1023 asserções).

### Aplicação da Direção Visual — Sessões

- [x] cabeçalho com contexto, contador e ação primária de criação numa área expansível;
- [x] tabela operacional com código/link, prova, turma, submissões, estado e ações;
- [x] copiar link, abrir link, iniciar, terminar e remover preservados;
- [x] estados de sessão apresentados com etiquetas semânticas e leitura rápida;
- [x] formulário de criação filtra turmas pela escola da prova escolhida;
- [x] paginação reutilizável de 10 registos e estado vazio orientado à próxima ação;
- [x] validar build e testes no fecho da etapa de Sessões — build concluído e suíte integral aprovada (214 testes, 1028 asserções).

### Aplicação da Direção Visual — Resultados

- [x] cabeçalho com contexto, contador e exportação CSV como ação secundária;
- [x] resumo com média, notas válidas e provas submetidas sem excesso de cards;
- [x] filtro de turma compacto e acesso direto ao painel analítico da turma;
- [x] tabela reduzida às informações essenciais: aluno, prova/sessão, turma, resultado, aptidão e temas;
- [x] data apresentada como informação secundária junto do aluno;
- [x] detalhe e histórico agrupados no menu de ações da linha;
- [x] paginação reutilizável de 10 registos e estado vazio orientado;
- [x] validar build e testes no fecho da etapa de Resultados — build concluído e suíte integral aprovada (215 testes, 1034 asserções).

### Aplicação da Direção Visual — Aprovações

- [x] cabeçalho com contexto, contador e retorno rápido ao banco de perguntas;
- [x] estados de revisão organizados em tabs com contagens visíveis;
- [x] filtro de autoria/escola compacto e preservado para cada estado;
- [x] fila de revisão com pergunta, opções, resposta correta e explicação na mesma superfície;
- [x] códigos técnicos removidos da leitura principal da pergunta;
- [x] ações de aprovar e rejeitar com campo de motivo claramente separadas;
- [x] paginação reutilizável de 10 registos e estado vazio orientado;
- [x] validar build e testes no fecho da etapa de Aprovações — build concluído e suíte integral aprovada (216 testes, 1039 asserções).

### Aplicação da Direção Visual — Biblioteca de Sinais

- [x] cabeçalho com contador e criação de sinal como ação primária;
- [x] pesquisa por nome/significado e filtro por categoria numa toolbar compacta;
- [x] grelha visual responsiva com pré-visualização, categoria, significado e estado;
- [x] indicação discreta de conteúdo sem texto e plano completo;
- [x] detalhe visível e edição/remoção agrupadas no menu de ações;
- [x] paginação reutilizável de 10 registos e estado vazio orientado;
- [x] validar build e testes no fecho da etapa da Biblioteca de Sinais — build concluído e suíte integral aprovada (217 testes, 1044 asserções).

### Aplicação da Direção Visual — Fichas de Estudo

- [x] cabeçalho com contador e criação de ficha como ação primária;
- [x] resumo por área de estudo numa superfície contínua e sem excesso de cards;
- [x] pesquisa por título/resumo e filtro por área numa toolbar previsível;
- [x] tabela com ficha, área/tema, referências, leitura e estado;
- [x] resumo da ficha truncado, sem expor identificadores técnicos na listagem;
- [x] detalhe visível e edição/remoção agrupadas no menu de ações;
- [x] paginação reutilizável de 10 registos e estado vazio orientado;
- [x] validar build e testes no fecho da etapa das Fichas de Estudo — build concluído e suíte integral aprovada (218 testes, 1050 asserções).

### Aplicação da Direção Visual — Biblioteca Legal

- [x] cabeçalho com contador e criação de artigo como ação primária;
- [x] alerta contextual para artigos sem capítulo, sem interromper a leitura da lista;
- [x] pesquisa por número/título/texto e filtro por capítulo numa toolbar previsível;
- [x] tabela com número, capítulo, título/conteúdo e acesso por plano;
- [x] conteúdo legal truncado para leitura rápida, mantendo o detalhe disponível;
- [x] detalhe visível e edição/remoção agrupadas no menu de ações;
- [x] paginação reutilizável de 10 registos e estado vazio orientado;
- [x] validar build e testes no fecho da etapa da Biblioteca Legal — build concluído e suíte integral aprovada (219 testes, 1055 asserções).

### Aplicação da Direção Visual — Glossário de Termos

- [x] cabeçalho com contador e criação de termo numa área expansível;
- [x] formulário de criação mantido acessível sem dominar a listagem;
- [x] pesquisa por termo ou definição numa toolbar compacta;
- [x] tabela com termo, definição, base legal e acesso por plano;
- [x] identificadores técnicos removidos da leitura principal;
- [x] detalhe visível e remoção agrupada no menu de ações;
- [x] paginação reutilizável de 10 registos e estado vazio orientado;
- [x] validar build e testes no fecho da etapa do Glossário de Termos — build concluído e suíte integral aprovada (220 testes, 1061 asserções).

### Aplicação da Direção Visual — Criação de Escolas e Utilizadores

- [x] criação de escola transferida para diálogo reutilizável, mantendo a listagem como superfície principal;
- [x] criação de utilizador transferida para diálogo reutilizável com papel, escola, palavra-passe e estado;
- [x] edição permanece em página própria para preservar espaço e reduzir complexidade do modal;
- [x] campos condicionais de escola mantidos conforme o papel escolhido;
- [x] tabelas de escolas e utilizadores mantidas com paginação, filtros e ações agrupadas;
- [x] validar build e testes no fecho da etapa de criação por diálogos — build concluído e suíte integral aprovada (221 testes, 1067 asserções).

### Aplicação da Direção Visual — Pagamentos e Acessos

- [x] página reposicionada como acompanhamento de pagamentos e acessos, sem confundir desbloqueio com conta;
- [x] resumo operacional com total, ativos, associados e ações pendentes;
- [x] alerta contextual para pagamentos ativos sem conta associada;
- [x] registo manual de pagamento transferido para diálogo reutilizável;
- [x] tabela com telefone, plano/método, datas, associação e estado;
- [x] associação de conta e remoção agrupadas nas ações da linha;
- [x] pesquisa por telefone/referência e paginação de 10 registos;
- [x] validar build e testes no fecho da etapa de Pagamentos e Acessos — build concluído e suíte integral aprovada (222 testes, 1074 asserções).

### Aplicação da Direção Visual — Publicações

- [x] cabeçalho orientado à publicação com contador de versões e ação primária;
- [x] prontidão separada do histórico: perguntas aprovadas, provas públicas e cobertura por tema;
- [x] publicação de novo pacote transferida para diálogo reutilizável com notas da versão;
- [x] histórico com versão, data, autor, conteúdo e estado sem excesso de cards;
- [x] download autenticado, detalhe e restauração agrupados nas ações da linha;
- [x] paginação reutilizável de 10 registos e estado vazio orientado;
- [x] validar build e testes no fecho da etapa de Publicações — build concluído e suíte integral aprovada (223 testes, 1080 asserções).

### Aplicação da Direção Visual — Temas e Categorias

- [x] Temas com tabela operacional, contagem de perguntas e criação em diálogo;
- [x] Categorias de sinais com hierarquia pai/subcategoria preservada e ações agrupadas;
- [x] criação de categoria de sinais em diálogo e criação de subcategoria mantida no contexto da linha;
- [x] Categorias de carta com criação e edição em diálogos reutilizáveis;
- [x] identificadores técnicos removidos da leitura principal das listagens;
- [x] estados, ordem, descrições e contagens apresentados de forma compacta;
- [x] paginação e estados vazios mantidos nos módulos aplicáveis;
- [x] validar build e testes no fecho da etapa de Temas e Categorias — build concluído e suíte integral aprovada (224 testes, 1092 asserções).

### Aplicação da Direção Visual — Mensagens e Diálogos do Sistema

- [x] mensagens de sucesso, atenção e erro consolidadas num componente reutilizável;
- [x] toasts de sucesso com ícone semântico, título curto, texto, fecho manual e desaparecimento automático;
- [x] toast de sucesso alinhado à identidade: superfície branca, título índigo, acento ciano e verde reservado ao ícone semântico;
- [x] avisos e erros persistentes apresentados como alerts acessíveis quando exigem atenção;
- [x] erros de validação apresentados numa lista única e navegável;
- [x] região de mensagens aplicada globalmente ao layout administrativo;
- [x] diálogos nativos existentes mantidos como padrão para confirmação e criação;
- [x] validar build e testes no fecho da etapa de Mensagens e Diálogos — build concluído e suíte integral aprovada (225 testes, 1097 asserções).

- [x] validar build e testes no fecho da etapa das tabelas — build concluído e suíte integral aprovada (208 testes, 994 asserções).

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
