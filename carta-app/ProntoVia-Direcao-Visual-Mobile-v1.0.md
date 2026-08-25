# ProntoVia — Direção Visual da Aplicação Mobile

**Versão:** 1.0  
**Estado:** Direção visual proposta para implementação  
**Âmbito:** Aplicação ProntoVia para candidatos — Android, Web mobile e futura adaptação iOS  
**Stack atual:** Ionic Angular + Capacitor  
**Base visual recomendada:** Ionic components + Ionicons + princípios Material Design / Human Interface Guidelines + CSS próprio ProntoVia

## 1. Visão

A aplicação mobile do ProntoVia deve ser o principal ambiente de aprendizagem do candidato.

Ela deve ajudar o utilizador a:
- aprender;
- praticar;
- simular;
- rever erros;
- acompanhar progresso;
- acompanhar prontidão;
- estudar offline;
- ligar-se opcionalmente a uma escola.

A escola aparece apenas como uma extensão quando existe vínculo.

> **A aplicação deve ser útil desde o primeiro minuto, mesmo para um candidato que nunca ligue a sua conta a uma escola.**

## 2. Filosofia visual

A aplicação deve parecer leve, organizada, confiável, previsível, rápida, educativa e moderna sem excesso visual.

Evitar:
- glassmorphism;
- blur em todos os painéis;
- glow/neon;
- cartões sobre cartões;
- gradientes em cada botão;
- formas 3D;
- mascotes de IA em todas as páginas;
- ícones gigantes;
- dezenas de cores;
- grandes banners dentro de páginas operacionais;
- animações constantes;
- dashboards com demasiados números.

> **No mobile, cada elemento tem de justificar o espaço que ocupa.**

## 3. Base tecnológica da interface

Utilizar prioritariamente componentes Ionic:
`ion-header`, `ion-toolbar`, `ion-title`, `ion-content`, `ion-list`, `ion-item`, `ion-label`, `ion-button`, `ion-icon`, `ion-tabs`, `ion-tab-bar`, `ion-tab-button`, `ion-progress-bar`, `ion-segment`, `ion-modal`, `ion-alert`, `ion-toast`, `ion-searchbar`, `ion-refresher`, `ion-skeleton-text` e `ion-chip`.

A identidade ProntoVia deve ser aplicada através de CSS variables, tokens, tipografia, spacing, cores, iconografia e pequenas customizações.

## 4. Sistema de cores

```css
--pv-indigo: #1A1F5C;
--pv-cyan: #00B8F0;
--pv-orange: #FF8A00;

--pv-white: #FFFFFF;
--pv-bg: #F7F8FB;
--pv-surface: #FFFFFF;

--pv-text: #202534;
--pv-text-secondary: #667085;
--pv-muted: #98A2B3;

--pv-border: #E4E7EC;

--pv-success: #198754;
--pv-warning: #B76E00;
--pv-danger: #D92D20;
--pv-info: #087EA4;
```

Funções:
- **Indigo:** marca, ação principal, títulos fortes, navegação ativa.
- **Ciano:** progresso, links, seleção, foco.
- **Laranja:** atenção, checkpoint e temas a reforçar.
- **Verde:** correto/concluído/aprovado.
- **Vermelho:** erro/falha/destrutivo.

## 5. Tipografia

Poppins.

Escala sugerida:
- Page title: 22–24px / 600
- Section title: 18px / 600
- Card title: 15–16px / 600
- Body: 14–16px / 400
- Metadata: 12–13px / 400
- Button: 14–15px / 500–600
- Large metric: 28–32px / 600
- Question statement: 17–19px / 500
- Answer option: 15–16px / 400–500

## 6. Espaçamento e radius

Escala: 4, 8, 12, 16, 20, 24, 32px.

Padrão:
- margem horizontal: 16px
- entre secções: 24px
- título-conteúdo: 12px
- entre itens: 8–12px

Radius:
- buttons: 8px
- inputs: 8px
- cards: 10–12px
- bottom sheet: 16px no topo

Evitar cards com 24–32px de radius em toda a aplicação.

## 7. Navegação principal

Usar no máximo 5 destinos principais:

```text
Início
Aprender
Praticar
Simular
Progresso
```

Perfil deve ser acessível pelo avatar. `Minha Escola` deve ser contextual, não um sexto tab.

A tab bar deve ter ícone + label. Tabs são navegação, não ações.

## 8. Iconografia

Biblioteca principal: **Ionicons**.

Regra:
- outline em estado normal;
- fill no estado selecionado, quando apropriado;
- não misturar Ionicons, Bootstrap Icons, Font Awesome e outros packs.

Mapa sugerido:
- Início: `home-outline`
- Aprender: `book-outline`
- Praticar: `create-outline`
- Simular: `clipboard-outline`
- Progresso: `stats-chart-outline`
- Perfil: `person-circle-outline`
- Lições: `reader-outline`
- Código: `document-text-outline`
- Revisões: `refresh-outline`
- Correto: `checkmark-circle-outline`
- Errado: `close-circle-outline`
- Prontidão: `speedometer-outline`
- Escola: `business-outline`
- Turma: `people-outline`
- Tarefa: `checkbox-outline`
- Histórico: `time-outline`
- Pesquisa: `search-outline`
- Filtro: `filter-outline`
- Download: `cloud-download-outline`
- Offline: `cloud-offline-outline`
- Notificação: `notifications-outline`
- Configuração: `settings-outline`

## 9. Home

A Home deve responder: **O que devo fazer agora?**

Estrutura:

```text
Olá, Alexandre                         [avatar]
Continue a sua preparação

Prontidão
78% — Em preparação
[barra]
Tema a reforçar: Prioridade
[Ver progresso]

Continue de onde parou
Prioridade nas interseções
12 min estudados
[Continuar]

O que quer fazer?
[Aprender] [Praticar]
[Simular]  [Rever erros]

Recomendado para hoje
10 questões · Prioridade
[Começar treino]

Minha Escola
Escola XYZ
1 tarefa pendente
```

O bloco `Minha Escola` só aparece quando existir vínculo.

A Home não deve ter 8–10 cartões de estatísticas.

## 10. Prontidão

Representação:

```text
Prontidão
78%
Em preparação

████████████████░░░░

Sinais        91%
Prioridade    64%
Velocidade    84%

Tema a reforçar
Prioridade

[Ver análise]
```

Preferir barra, anel simples ou barras horizontais. Evitar gauges 3D.

## 11. Aprender

Orientar por currículo.

```text
Aprender
[Pesquisar conteúdo]

Continuar
Prioridade e cedência de passagem
43% concluído

Temas
Regras gerais               72%
Prioridade                  43%
Velocidade                  60%
Manobras                    28%
Sinais                      81%
Segurança rodoviária        35%
```

Preferir `ion-list`, `ion-item`, `ion-label`, `ion-progress-bar`.

Cada tema deve parecer uma linha de aprendizagem, não um grande card ilustrado.

## 12. Página de tema

```text
< Aprender

Prioridade
43% concluído

█████████░░░░░░

Lições
✓ Conceito de prioridade
✓ Sinais de cedência
• Cruzamentos
○ Entroncamentos
○ Rotundas
○ Exercício final
```

Checkpoint:
- ✓ concluído
- • atual
- ○ pendente

## 13. Lições

Priorizar leitura.

```text
Título
Texto
[imagem/sinal quando necessário]

Ponto importante
Resumo curto.

Exemplo
...

[Anterior]             [Continuar]
```

Não colocar cada parágrafo em card.

## 14. Praticar

```text
Praticar

Treino recomendado
Prioridade
10 perguntas
[Começar]

Treinar por
Tema
Erros recentes
Sinais
Questões difíceis
Nunca respondidas
Revisões pendentes
```

Configuração curta pode usar bottom sheet:

```text
Tema [Prioridade]
Questões  5 / 10 / 20
[Começar treino]
```

## 15. Tela de pergunta

```text
Prática — Prioridade        7 de 10
██████████████░░░░

[imagem]

O que deve fazer o condutor nesta situação?

A   Reduzir a velocidade
B   Parar obrigatoriamente
C   Ceder passagem
D   Continuar sem alteração

[Confirmar resposta]
```

A questão deve dominar a tela. Pouca decoração.

Cada opção deve ter touch target confortável (~44–48px ou mais).

## 16. Feedback de resposta

Correta:
```text
✓ Correto
[explicação]
```

Errada:
```text
✕ Não é essa a resposta

Resposta correta:
C — Ceder passagem

Explicação:
...
```

Feedback apenas após submissão, quando o modo permitir.

## 17. Simular

Entrada:
```text
Simulado
Categoria B
30 questões
25 minutos
[Iniciar simulado]
```

Em curso:
```text
Simulado                         18:42
Questão 7 de 30
██████░░░░░░░░

[enunciado]
[imagem]
A ...
B ...
C ...
D ...

[Anterior]                 [Próxima]
```

Timer visível, mas não dominante.

## 18. Resultado

```text
Resultado

22 / 30
73%

Aprovado / Não aprovado
(de acordo com a regra real)

Desempenho por tema
Sinais        90%
Velocidade    80%
Prioridade    50%
Manobras      60%

Recomendação
Reforce prioridade antes do próximo simulado.

[Rever respostas]
[Praticar pontos fracos]
```

Evitar confetti automático em todos os resultados.

## 19. Progresso

Três níveis:

```text
Resumo
Temas
Histórico
```

Resumo:
```text
Prontidão
78%

Evolução
[linha simples]

Esta semana
4 sessões
67 perguntas
82% corretas

Temas a reforçar
Prioridade
Manobras
```

No máximo 3–4 métricas principais.

## 20. Progresso por tema

Preferir barras horizontais:

```text
Sinais       91%
██████████████████░

Velocidade   84%
████████████████░░░

Prioridade   64%
████████████░░░░░░

Manobras     59%
███████████░░░░░░░
```

## 21. Histórico

Lista simples:

```text
Hoje
Simulado Categoria B
24/30 · 80%

Ontem
Treino — Prioridade
8/10 · 80%

22 Ago
Revisão de sinais
14/15 · 93%
```

## 22. Revisão inteligente

```text
Revisões

12 questões para rever hoje

Prioridade       5
Sinais           4
Velocidade       3

[Começar revisão]
```

## 23. Biblioteca de sinais

```text
Sinais
[Pesquisar]

Perigo
18 sinais

Cedência de passagem
6 sinais

Proibição
32 sinais
```

Para sinais, grid visual é apropriado.

Item:
```text
[imagem do sinal]
STOP
```

Detalhe:
```text
[imagem]
STOP

Paragem obrigatória.

Descrição
...

Referência
...
```

## 24. Minha Escola

Só aparece quando existe vínculo.

```text
Minha Escola
Escola XYZ
Turma B — Tarde

Tarefas
Treino de Prioridade    Pendente
Simulado 4              Sexta-feira

Resultados recentes
Teste 3                 16 valores
```

Não criar “uma app dentro da app”.

## 25. Perfil

Acesso pelo avatar.

```text
Perfil

Conta
Dados pessoais
Categoria
Segurança

Preferências
Tema
Downloads
Notificações

Escola
Minha Escola

Suporte
Ajuda
Termos
Privacidade

Sair
```

Preferir listas, não cards.

## 26. Offline

Banner discreto:

```text
Sem ligação
Está a estudar com conteúdo guardado no dispositivo.
```

Downloads:

```text
Conteúdo offline
Pacote atual: 2026.08

Sinais     Guardado
Lições     Guardado
Imagens    84%

[Atualizar conteúdo]
```

## 27. Loading, empty e error states

Loading: `ion-skeleton-text` para conteúdo estruturado.

Empty:
```text
Ainda não realizou simulados.
Faça o primeiro simulado para começar a acompanhar o seu desempenho.
[Iniciar simulado]
```

Erro:
```text
Não conseguimos carregar este conteúdo.
[Voltar a tentar]
```

## 28. Bottom sheets, modais e search

Bottom sheet:
- filtros;
- treino;
- categoria;
- ações rápidas.

Modal fullscreen:
- fluxos complexos.

Search apenas onde o volume justifica:
- sinais;
- código;
- glossário;
- lições.

## 29. Chips

Usar apenas para filtros/estados selecionáveis:

```text
[Todos] [Erros] [Revisão] [Novas]
```

## 30. Botões

Primary: uma ação principal por tela.

Exemplos:
- Continuar
- Começar treino
- Iniciar simulado
- Confirmar resposta
- Guardar

Secondary:
- Ver análise
- Rever respostas
- Cancelar

Danger:
- Eliminar conta
- Desvincular

## 31. Acessibilidade

Touch targets: 44–48px ou mais para ações principais.

Garantir:
- contraste;
- labels;
- screen reader;
- não depender apenas da cor;
- texto legível;
- foco;
- feedback visual.

Ícones decorativos com `aria-hidden="true"` quando o label já comunica a ação.

## 32. Gamificação

Moderada.

Pode existir:
- streak;
- metas;
- checkpoints;
- progresso;
- marcos.

Evitar:
- moedas;
- gemas;
- roletas;
- caixas surpresa;
- recompensas que desviem da aprendizagem.

## 33. Checkpoints

Metáfora adequada para ProntoVia:

```text
Aprender
   ✓
Praticar
   ✓
Rever
   •
Simular
   ○
```

Usar em planos de estudo e temas, não como decoração.

## 34. Gráficos mobile

Preferir:
1. barras horizontais;
2. linha simples;
3. progress bar;
4. anel simples.

Evitar:
- pie com muitas fatias;
- radar;
- gauge complexo;
- gráficos 3D.

## 35. Prontidão detalhada

```text
Prontidão
78%
Em preparação

A sua evolução
[linha]

Por tema
Sinais           91%
Velocidade       84%
Prioridade       64%
Manobras         59%

O que reforçar
1. Prioridade
2. Manobras

Próxima ação
Treino de prioridade · 10 questões

[Começar treino]
```

## 36. Arquitetura visual por nível

```text
Nível 1
Início / Aprender / Praticar / Simular / Progresso

Nível 2
Aprender → Prioridade

Nível 3
Prioridade → Cruzamentos

Nível 4
Cruzamentos → Exercício
```

Evitar hierarquia muito profunda.

## 37. Regra de uma decisão por tela

Home: O que devo fazer agora?  
Aprender: O que quero estudar?  
Praticar: O que quero treinar?  
Simular: Quero iniciar o simulado?  
Resultado: O que devo melhorar?  
Progresso: Como estou a evoluir?

## 38. Componentes custom reutilizáveis

Criar sobre Ionic:

```text
pv-page-header
pv-readiness-summary
pv-topic-progress
pv-learning-item
pv-question-option
pv-exam-progress
pv-recommendation
pv-empty-state
pv-school-summary
pv-offline-banner
```

## 39. Theme variables

Centralizar em `src/theme/variables.scss` ou equivalente.

Mapear:

```css
--ion-color-primary: #1A1F5C;
--ion-color-secondary: #00B8F0;
--ion-color-tertiary: #FF8A00;
--ion-background-color: #F7F8FB;
--ion-text-color: #202534;
```

Evitar cores hardcoded em muitas páginas.

## 40. Android primeiro, sem bloquear iOS

Como Android é a plataforma nativa atual:
- validar principalmente comportamento Material;
- preservar back;
- respeitar safe areas;
- evitar UI exclusivamente iOS.

Ionic deve continuar responsável por boa parte da adaptação entre plataformas.

## 41. Segurança visual durante provas

Durante provas/simulados:
- esconder distrações;
- limitar banners;
- remover promoções;
- não mostrar escola quando não é relevante;
- manter estado da sessão visível.

Modo prova deve ser focal.

## 42. Premium

Não colocar cadeados em toda parte.

Usar rótulo discreto `ProntoVia+`.

Paywall claro, sem urgência artificial.

## 43. O que não fazer

Não:
- transformar cada bloco em card;
- colocar hero dentro da app;
- usar banners institucionais após login;
- usar imagens de pessoas em todas as telas;
- usar fundo de estrada atrás do conteúdo;
- usar ilustrações 3D como componente operacional;
- mostrar todos os números na Home;
- misturar bibliotecas de ícones;
- usar cor sem significado;
- exagerar no radius;
- diminuir fontes para caber mais;
- copiar literalmente Material ou iOS;
- usar estética de IA como identidade do produto.

## 44. Ordem de refatoração visual

### Fase M1 — Foundations
1. tokens
2. cores
3. tipografia
4. spacing
5. radius
6. Ionicons
7. buttons
8. list items
9. headers
10. tabs

### Fase M2 — Navegação
1. bottom tabs
2. headers
3. perfil
4. rotas principais

### Fase M3 — Core Learning
1. Home
2. Aprender
3. Tema
4. Lição
5. Praticar
6. Pergunta
7. Feedback

### Fase M4 — Assessment
1. Simular
2. prova em curso
3. resultado
4. histórico

### Fase M5 — Intelligence
1. Progresso
2. Prontidão
3. Revisão
4. Recomendações

### Fase M6 — Extensões
1. Minha Escola
2. tarefas
3. offline/downloads
4. ProntoVia+

## 45. Critério de qualidade da Home

O candidato deve conseguir responder imediatamente:
1. Como estou?
2. O que devo fazer agora?
3. Onde parei?
4. O que preciso melhorar?
5. Onde inicio treino/simulado?

## 46. Critério de qualidade da pergunta

- enunciado fácil de ler;
- imagem visível;
- opções confortáveis;
- botão principal acessível;
- progresso compreensível;
- nenhuma decoração compete com a questão.

## 47. Critério de qualidade do Progresso

O utilizador percebe:
- nível geral;
- evolução;
- pontos fortes;
- pontos fracos;
- próxima recomendação.

Não mostrar todos os dados recolhidos.

## 48. Checklist por tela

- [ ] objetivo principal claro;
- [ ] título identifica a tela;
- [ ] no máximo uma ação principal dominante;
- [ ] utilizador sabe como voltar;
- [ ] touch targets confortáveis;
- [ ] texto principal legível;
- [ ] não há cards desnecessários;
- [ ] ícones são consistentes;
- [ ] cores têm significado;
- [ ] estados não dependem apenas da cor;
- [ ] loading tratado;
- [ ] empty state tratado;
- [ ] erro tratado;
- [ ] offline tratado quando relevante;
- [ ] funciona a 360px;
- [ ] safe areas respeitadas;
- [ ] acessibilidade considerada.

## 49. Resumo executivo

A direção visual mobile do ProntoVia deve ser:

> **Ionic nativo, educativo, simples e orientado à tarefa.**

```text
Ionic components
+ Ionicons
+ Poppins
+ Indigo / Ciano / Laranja
+ listas em vez de cards excessivos
+ 5 tabs principais
+ gráficos simples
+ barras de progresso
+ checkpoints
+ uma ação principal por tela
+ pouca sombra
+ radius moderado
+ animação discreta
+ forte acessibilidade
+ offline visível quando necessário
```

## 50. Princípio final

> **No ProntoVia Mobile, o candidato nunca deve sentir que está a navegar num dashboard. Deve sentir que está a avançar num percurso de aprendizagem.**

> **Quanto mais conteúdo o ProntoVia tiver, mais simples deve parecer o próximo passo.**
