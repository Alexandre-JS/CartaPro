# ProntoVia — Direção Visual Web e Sistema de Interface Operacional

**Versão:** 1.0  
**Estado:** Direção visual proposta para implementação  
**Âmbito:** ProntoVia Admin, ProntoVia Escolas, Portal Web e páginas autenticadas com grande volume de informação  
**Referência técnica preferencial:** HTML5 semântico + Bootstrap 5.3 + Bootstrap Icons + CSS próprio ProntoVia  
**Objetivo:** criar uma interface profissional, legível, estável e escalável, evitando o aspeto excessivamente “AI-generated”, glassmorphism ou dashboards saturados de cartões.

---

## 1. Contexto

O ProntoVia está a evoluir de um simples simulador para uma plataforma com várias superfícies e volumes crescentes de informação:

- candidatos;
- escolas;
- instrutores;
- turmas;
- perguntas;
- sinais;
- conteúdos;
- provas;
- sessões;
- resultados;
- progresso;
- prontidão;
- pagamentos;
- relatórios;
- administração.

À medida que o produto cresce, a direção visual deve privilegiar **clareza operacional** em vez de decoração.

A interface Web não deve tentar parecer uma landing page dentro do sistema.

O princípio é:

> **Informação primeiro. Decoração depois.**

---

# 2. Filosofia visual

A referência estética do sistema deve aproximar-se de software empresarial maduro:

- Bootstrap;
- sistemas administrativos tradicionais bem organizados;
- Carbon Design System;
- Atlassian Design System;
- ferramentas SaaS operacionais;
- backoffices bancários, escolares e logísticos modernos.

Não queremos:

- glassmorphism;
- fundos com blur;
- cartões “flutuantes” por todo lado;
- gradientes em todos os componentes;
- sombras grandes;
- glow/neon;
- botões excessivamente arredondados;
- enormes blocos coloridos;
- excesso de ilustrações dentro de áreas operacionais;
- “sparkles” ou ícones que façam tudo parecer IA;
- dashboards com 10 ou 15 KPIs simultaneamente;
- uma cor diferente para cada módulo.

O ProntoVia deve parecer:

> **um sistema profissional de educação e gestão, não uma demonstração de UI.**

---

# 3. Princípios de design

## 3.1 Hierarquia forte

Cada página deve deixar claro, por ordem:

1. onde o utilizador está;
2. o que a página mostra;
3. qual é a ação principal;
4. quais filtros estão ativos;
5. onde está a informação principal.

A informação mais importante deve aparecer no topo e receber maior contraste visual.

## 3.2 Menos cartões

Cards devem ser usados quando existe um agrupamento lógico real.

Não criar um card apenas porque existe um número.

Preferir uma estrutura de resumo + análise + tabela, em vez de transformar cada métrica em um bloco visual isolado.

## 3.3 Tabelas para informação operacional

Sempre que a informação representa uma coleção de entidades comparáveis, preferir **tabela** em vez de cards.

Exemplos:

- alunos;
- escolas;
- turmas;
- perguntas;
- sinais;
- provas;
- pagamentos;
- sessões;
- utilizadores;
- resultados.

Cards são melhores para:

- resumo;
- estados;
- KPIs;
- atalhos importantes;
- explicações;
- objetos muito visuais.

## 3.4 Progressive disclosure

Não mostrar tudo ao mesmo tempo.

A página deve apresentar primeiro a informação necessária à decisão atual.

Detalhes secundários podem ficar em:

- expansão de linha;
- modal;
- offcanvas;
- tab;
- página de detalhe;
- menu “mais ações”.

## 3.5 Cor com função

A cor deve indicar:

- identidade;
- estado;
- prioridade;
- seleção;
- feedback.

Não deve ser usada apenas para tornar a interface “mais moderna”.

---

# 4. Linguagem visual ProntoVia

## 4.1 Paleta principal

```css
:root {
    --pv-indigo: #1A1F5C;
    --pv-cyan: #00B8F0;
    --pv-orange: #FF8A00;

    --pv-bg: #F6F7FA;
    --pv-surface: #FFFFFF;
    --pv-border: #DEE2E6;

    --pv-text: #202534;
    --pv-text-secondary: #667085;
    --pv-text-muted: #8A94A6;

    --pv-success: #198754;
    --pv-danger: #DC3545;
    --pv-warning: #B76E00;
    --pv-info: #087EA4;
}
```

### Função das cores

**Indigo `#1A1F5C`**
- sidebar;
- títulos fortes;
- botão primário;
- elementos institucionais;
- navegação ativa em contexto escuro.

**Ciano `#00B8F0`**
- links;
- progresso;
- seleção;
- foco;
- indicadores secundários;
- detalhe da marca.

**Laranja `#FF8A00`**
- CTA pontual;
- atenção;
- checkpoint;
- elemento de marca;
- nunca como cor dominante da interface.

**Verde**
- sucesso/aprovado/concluído.

**Vermelho**
- erro/reprovado/perigo/remover.

Nunca usar ciano ou laranja para significar erro ou sucesso.

---

# 5. Fundos e superfícies

## Fundo principal

```css
background: #F6F7FA;
```

## Superfície

```css
background: #FFFFFF;
border: 1px solid #DEE2E6;
```

## Regra

A maioria dos painéis deve parecer plana.

Usar sombra apenas quando há necessidade de elevação:

- dropdown;
- modal;
- offcanvas;
- menu contextual;
- elemento temporariamente sobreposto.

Cards normais não precisam de sombras fortes.

---

# 6. Border radius

Evitar a estética de cartões muito arredondados.

Recomendação:

```text
inputs             6px
buttons            6px
cards              8px
modals             10px
badges              4px ou pill apenas quando semanticamente adequado
```

Evitar `20px`, `24px`, `32px` e `border-radius: 999px` em componentes normais.

---

# 7. Tipografia

Continuar com **Poppins** para preservar a identidade.

Porém, no sistema operacional usar pesos moderados.

## Escala sugerida

```text
Page title          24px / 600
Section title       18px / 600
Card/KPI title      14px / 500
Body                14px / 400
Table               13px–14px
Metadata            12px–13px
Large KPI           28px–32px / 600
```

Evitar:

- títulos de 40px dentro do dashboard;
- texto bold em toda parte;
- usar uppercase em frases completas.

---

# 8. Grid e largura

Para Admin e Escolas utilizar uma **interface de alta densidade controlada**.

O conteúdo deve aproveitar a largura disponível.

Estrutura:

```text
┌──────────────┬─────────────────────────────────────────────┐
│              │ Header                                      │
│   Sidebar    ├─────────────────────────────────────────────┤
│              │ Breadcrumb + título + ação                  │
│   240px      │                                             │
│              │ Conteúdo                                    │
│              │                                             │
└──────────────┴─────────────────────────────────────────────┘
```

## Medidas propostas

```text
Sidebar desktop       240px–248px
Sidebar collapsed      68px–72px
Header                  56px–64px
Page horizontal gap     24px
Page vertical gap       20px–24px
Section gap             24px–32px
```

Em telas grandes o conteúdo operacional pode usar praticamente toda a largura disponível.

---

# 9. Sidebar

A sidebar deve ser simples, previsível e pouco decorativa.

## Fundo

`#1A1F5C`

## Estrutura

```text
Logo

VISÃO GERAL
  Resumo

APRENDIZAGEM
  Candidatos
  Turmas
  Instrutores
  Provas
  Resultados

CONTEÚDO
  Perguntas
  Sinais
  Lições
  Código da Estrada
  Glossário

GESTÃO
  Escolas
  Utilizadores
  Pagamentos
  Publicações

SISTEMA
  Relatórios
  Configurações
```

Não mostrar necessariamente todos estes itens para todos os perfis.

O menu deve respeitar permissões.

## Item

```text
[ícone 18px] Label
```

Estado normal:
- texto branco com menor intensidade.

Estado hover:
- fundo branco com 6–10% de opacidade.

Estado ativo:
- pequena barra lateral ciano;
- texto branco;
- sem glow.

---

# 10. Topbar

A topbar não deve competir com o conteúdo.

Conteúdo sugerido:

```text
[botão recolher menu]                   [pesquisa] [notificações] [perfil]
```

A pesquisa global só deve ocupar grande espaço se for realmente utilizada.

Evitar mensagens motivacionais, clima ou muitos ícones sem função essencial.

---

# 11. Cabeçalho de página

Todas as páginas operacionais devem seguir a mesma anatomia.

```text
Breadcrumb
Escolas / Turmas

Turmas                                      [+ Nova turma]
Gerir as turmas e os candidatos associados.

----------------------------------------------------------
```

Ordem:

1. breadcrumb;
2. título;
3. descrição curta opcional;
4. ação principal à direita.

Nunca colocar duas ou três ações primárias competindo.

Apenas **uma primary action** por página.

As outras:

- secondary;
- outline;
- dropdown “Mais”.

---

# 12. Dashboard

O dashboard não deve tentar mostrar todo o sistema.

Um dashboard deve responder:

> “O que preciso saber ou fazer agora?”

## Estrutura recomendada

### Linha 1 — 4 KPIs no máximo

```text
Candidatos ativos
248
+12 este mês

Taxa de aprovação
78%
+4 pp

Provas realizadas
132
últimos 30 dias

Precisam de atenção
18
7%
```

KPIs:
- fundo branco;
- border;
- sem gradiente;
- ícone pequeno;
- número dominante;
- comparação discreta.

### Linha 2

```text
[ Evolução de desempenho — 8 colunas ] [ Atenção necessária — 4 colunas ]
```

### Linha 3

```text
[ Desempenho por turma — tabela ]
```

### Linha 4

```text
[ Atividade recente ] [ Próximas provas ]
```

Evitar mais de 4 KPIs no topo.

---

# 13. KPI card

Anatomia:

```text
┌─────────────────────────┐
│ Candidatos ativos   👥  │
│ 248                     │
│ +12 no último mês       │
└─────────────────────────┘
```

Regras:

- ícone 18px;
- título 13px–14px;
- número 28px;
- uma única informação comparativa;
- sem ilustração;
- sem gradiente;
- sem grande círculo decorativo atrás do ícone.

---

# 14. Tabelas

As tabelas serão o componente mais importante do sistema.

Bootstrap oferece uma base adequada:

```html
<div class="table-responsive">
    <table class="table table-hover align-middle">
        ...
    </table>
</div>
```

## Estrutura recomendada

```text
Título da lista                          [+ Criar]
Descrição

[Pesquisar________________] [Estado ▼] [Turma ▼] [Mais filtros]

----------------------------------------------------------
Nome          Turma      Progresso     Estado        ⋮
----------------------------------------------------------
Ana Paula     A          72%           Ativo         ⋮
Domingos J.   A          64%           Atenção       ⋮
Ivone S.      B          58%           Ativo         ⋮
----------------------------------------------------------

1–25 de 248                           ‹ 1 2 3 ... 10 ›
```

## Regras

- header simples;
- `table-hover`;
- zebra stripe apenas quando a densidade justificar;
- 44–48px de altura de linha como padrão;
- 36–40px para modo compacto;
- ações no fim;
- filtros acima;
- paginação em baixo;
- sorting apenas onde faz sentido;
- cabeçalhos curtos;
- tooltip para texto truncado.

Não colocar uma tabela dentro de um card estreito.

Dar à tabela o máximo de largura possível.

---

# 15. Densidade da tabela

Criar dois modos futuros:

```text
Confortável
Compacto
```

## Confortável

- 48px linha;
- para utilização padrão.

## Compacto

- 36–40px;
- para utilizadores que trabalham muitas horas no painel.

A densidade deve ser consistente entre `thead` e `tbody`.

---

# 16. Toolbar de tabelas

A toolbar deve seguir uma ordem previsível:

```text
[Pesquisar] [Filtro 1] [Filtro 2] [Filtros]       [Exportar] [+ Criar]
```

Até 4–5 ações visíveis.

Outras ações:

```text
⋮
```

Evitar uma fila com 8 botões.

---

# 17. Ações de linha

Não usar vários botões coloridos:

```text
[Ver] [Editar] [Aprovar] [Duplicar] [Apagar]
```

Preferir:

```text
Nome ... Estado       [⋮]
```

Menu:

```text
Ver detalhe
Editar
Duplicar
----------------
Arquivar
Eliminar
```

Uma ação muito comum pode ficar visível.

Exemplo:

```text
[Ver] [⋮]
```

---

# 18. Página de detalhe

Exemplo: candidato.

```text
← Candidatos

Ana Paula M.                          Ativo
Turma A · Categoria B

[Resumo] [Progresso] [Provas] [Revisões] [Escola]

----------------------------------------------------------

Informações principais
Nome
Telefone
Email
Turma
Categoria

----------------------------------------------------------

Desempenho
[ gráfico simples ]

----------------------------------------------------------

Últimas provas
[ tabela ]
```

Não colocar todos os campos em 15 cards.

Utilizar:

- tabs;
- definition lists;
- tabelas;
- secções.

---

# 19. Forms

Forms administrativos devem ser convencionais.

Preferir labels acima dos inputs.

```html
<div class="mb-3">
    <label for="title" class="form-label">Título</label>
    <input id="title" class="form-control">
    <div class="form-text">...</div>
</div>
```

## Largura

Form simples:

```text
max-width: 760px–900px
```

Form complexo:

```text
grid de 2 colunas apenas onde os campos têm relação.
```

Não criar um form com 4 colunas.

---

# 20. Formulários longos

Dividir em secções.

Exemplo para pergunta:

```text
Informação principal
--------------------
Enunciado
Tema
Tipo
Categorias

Respostas
---------
A
B
C
D
Resposta correta

Explicação e referência
-----------------------
Explicação
Artigo
Sinal

Publicação
----------
Estado
Premium
Ativo
```

Cada secção separada por:

- título;
- pequena descrição;
- border-top;
- spacing.

Não necessariamente por um card.

---

# 21. Tabs

Usar tabs para separar visões do mesmo objeto.

Bootstrap `nav-tabs` ou `nav-underline` são adequados.

Exemplo:

```text
Resumo | Progresso | Provas | Atividade | Configuração
```

Não usar tabs para navegação principal do sistema.

---

# 22. Offcanvas e modais

## Offcanvas

Bom para:

- filtros avançados;
- detalhe rápido;
- ajuda contextual.

## Modal

Bom para:

- confirmação;
- pequenas edições;
- criação rápida com poucos campos.

Não criar um formulário de 20 campos dentro de modal.

Para isso usar página própria.

---

# 23. Badges e estados

Badges são para estados curtos.

Exemplos:

```text
Ativo
Inativo
Rascunho
Em revisão
Aprovado
Rejeitado
Publicado
Pago
Pendente
Expirado
```

Estilo:

- pouco saturado;
- fundo muito claro;
- texto forte;
- borda opcional.

Não utilizar pills gigantes.

Não depender apenas da cor.

Sempre incluir texto.

---

# 24. Ícones

## Biblioteca recomendada

**Bootstrap Icons**

Motivos:

- alinhada ao Bootstrap;
- mais de 2.000 ícones;
- SVG;
- leve;
- simples;
- consistente;
- pouco “decorativa”;
- boa para software operacional.

## Tamanho

```text
Sidebar                18px
Botões                  16px
Table actions           16px
Page actions            16px
KPI                     18px–20px
Empty state             32px–40px
```

Não usar ícones de 48–64px dentro de cards comuns.

---

# 25. Regra de iconografia

Cada conceito deve possuir um ícone principal consistente.

Não utilizar o mesmo ícone para significados diferentes.

Não alternar aluno entre ícones de pessoa, chapéu académico e avatar sem razão.

Escolher uma metáfora e manter.

---

# 26. Mapa de ícones recomendado

Usando nomes do Bootstrap Icons.

| Conceito | Ícone sugerido |
|---|---|
| Dashboard / Resumo | `bi-speedometer2` |
| Candidatos / Alunos | `bi-people` |
| Utilizador individual | `bi-person` |
| Turmas | `bi-collection` |
| Instrutores | `bi-person-badge` |
| Escolas | `bi-building` |
| Perguntas | `bi-question-square` |
| Sinais | `bi-signpost-2` |
| Lições | `bi-book` |
| Código da Estrada | `bi-journal-text` |
| Glossário | `bi-bookmark` |
| Provas | `bi-clipboard-check` |
| Simulados | `bi-ui-checks-grid` |
| Resultados | `bi-bar-chart-line` |
| Progresso | `bi-graph-up-arrow` |
| Prontidão | `bi-speedometer` |
| Revisões | `bi-arrow-repeat` |
| Publicação | `bi-cloud-arrow-up` |
| Pagamentos | `bi-credit-card` |
| Relatórios | `bi-file-earmark-bar-graph` |
| Configurações | `bi-gear` |
| Notificações | `bi-bell` |
| Pesquisa | `bi-search` |
| Filtro | `bi-funnel` |
| Exportar | `bi-download` |
| Ver | `bi-eye` |
| Editar | `bi-pencil` |
| Duplicar | `bi-copy` |
| Arquivar | `bi-archive` |
| Eliminar | `bi-trash` |
| Aprovar | `bi-check-circle` |
| Rejeitar | `bi-x-circle` |
| Mais ações | `bi-three-dots-vertical` |
| Ajuda | `bi-question-circle` |

Antes da implementação confirmar que o ícone existe na versão instalada de Bootstrap Icons.

---

# 27. Ícones que devemos evitar como linguagem dominante

Evitar excesso de:

- estrelas;
- sparkles;
- robôs;
- magic wand;
- cérebro com circuito;
- ícones 3D;
- gradientes dentro do ícone;
- “AI assistant” em áreas que não usam IA.

Se no futuro houver uma funcionalidade realmente baseada em IA, ela deve ser marcada apenas nesse contexto.

Não usar estética de IA como linguagem global do produto.

---

# 28. Botões

Hierarquia:

## Primary

Uma ação principal.

Exemplo:

```text
+ Nova turma
Publicar
Guardar
Criar prova
```

## Secondary

Ações importantes mas não principais.

```text
Exportar
Pré-visualizar
Duplicar
```

## Tertiary / link

```text
Cancelar
Voltar
Ver detalhes
```

## Danger

Apenas:

```text
Eliminar
Revogar
```

Nunca usar 4 botões preenchidos com cores diferentes lado a lado.

---

# 29. Tamanho de botões

Sistema operacional:

```text
height normal       38px–40px
height small        32px–34px
border-radius       6px
```

Ícone + label:

```text
[+] Nova turma
```

Apenas ícone:

usar somente quando a função é universal e possuir tooltip/aria-label.

---

# 30. Charts

O ProntoVia terá dados suficientes para utilizar gráficos, mas gráficos devem responder perguntas.

Usar principalmente:

- line chart;
- bar chart;
- horizontal bar;
- progress bar;
- stacked bar quando necessário.

Usar donut com moderação.

Evitar:

- pie chart com muitas fatias;
- gauges 3D;
- gráficos decorativos;
- animações exageradas;
- 6 cores sem significado.

---

# 31. Regras para gráficos

## Linha

Usar para:

- evolução temporal;
- taxa de aprovação ao longo de semanas;
- prontidão ao longo do tempo.

## Barras

Usar para:

- comparar turmas;
- comparar temas;
- comparar resultados.

## Barra horizontal

Boa para:

```text
Sinais        91%
Velocidade    84%
Prioridade    64%
Manobras      59%
```

## Donut

Usar apenas para uma composição simples.

Exemplo:

```text
Aprovados / Reprovados
```

Não usar 5 donuts numa página.

---

# 32. Cores nos gráficos

Manter consistência.

Exemplo:

```text
Dado principal             Ciano
Dado comparativo           Indigo
Atenção                    Laranja
Sucesso                    Verde
Erro                       Vermelho
Referência histórica       Cinza
```

Se “Aprovação” for verde num gráfico, deve continuar verde nos restantes gráficos.

---

# 33. Legendas

Quando possível, escrever o nome diretamente junto ao dado.

Evitar legendas se existe apenas uma série.

Se existirem várias séries:

- manter posição consistente;
- máximo de duas linhas;
- usar nomes claros;
- evitar abreviações incompreensíveis.

---

# 34. Estados vazios

Uma página sem dados não deve parecer quebrada.

Exemplo:

```text
Nenhuma turma criada

Crie a primeira turma para começar a organizar os seus candidatos.

[+ Criar turma]
```

Usar um ícone simples de 32–40px.

Não usar uma grande ilustração de IA.

---

# 35. Loading

Preferir:

- spinner pequeno;
- skeleton discreto em listas/tabelas.

Não usar animações complexas, blur ou formas brilhantes.

---

# 36. Feedback

## Success

```text
Turma criada com sucesso.
```

## Error

```text
Não foi possível guardar a turma.
Verifique os campos assinalados.
```

## Warning

```text
Esta prova ainda possui questões em revisão.
```

Mensagens devem explicar o que aconteceu e, quando possível, o próximo passo.

---

# 37. Responsividade

O painel Web é orientado principalmente a desktop/tablet, mas deve continuar utilizável em mobile.

## Desktop

- sidebar fixa;
- tabelas completas;
- múltiplas colunas.

## Tablet

- sidebar recolhida/offcanvas;
- grid reduzido;
- tabelas ainda prioritárias.

## Mobile

Não transformar automaticamente cada linha de tabela num enorme card.

Preferir:

1. mostrar as colunas essenciais;
2. esconder colunas secundárias;
3. permitir scroll horizontal;
4. abrir detalhe da linha para restantes dados.

Bootstrap `.table-responsive` é apropriado.

---

# 38. Página de listagem padrão

```text
┌─────────────────────────────────────────────────────────────┐
│ Breadcrumb                                                  │
│                                                             │
│ Título                                    [+ Nova entidade] │
│ Descrição                                                    │
│                                                             │
│ [Pesquisar____________] [Estado ▼] [Filtro ▼] [Mais filtros]│
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Tabela                                                  │ │
│ │                                                         │ │
│ │                                                         │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ Resultados / paginação                                      │
└─────────────────────────────────────────────────────────────┘
```

Este padrão deve cobrir a maioria das áreas administrativas.

---

# 39. Página de dashboard padrão

```text
┌─────────────────────────────────────────────────────────────┐
│ Dashboard                                                   │
│ Visão geral da operação                                     │
│                                                             │
│ [ KPI ] [ KPI ] [ KPI ] [ KPI ]                             │
│                                                             │
│ [ Evolução principal                  ] [ Atenção          ] │
│                                                             │
│ [ Tabela / análise principal                              ] │
│                                                             │
│ [ Atividade recente                  ] [ Próximos eventos ] │
└─────────────────────────────────────────────────────────────┘
```

---

# 40. Página de formulário padrão

```text
Título                                           [Guardar]

Informação principal
------------------------------------------------------------
Label
[ input                                              ]

Label
[ select                                             ]

Configuração
------------------------------------------------------------
...

Publicação
------------------------------------------------------------
...

                                   [Cancelar] [Guardar]
```

---

# 41. CSS visual base

```css
.pv-panel {
    background: var(--pv-surface);
    border: 1px solid var(--pv-border);
    border-radius: 8px;
}

.pv-page-title {
    color: var(--pv-text);
    font-size: 1.5rem;
    font-weight: 600;
}

.pv-kpi-value {
    color: var(--pv-indigo);
    font-size: 1.875rem;
    font-weight: 600;
}

.pv-sidebar {
    background: var(--pv-indigo);
}

.pv-btn-primary {
    background: var(--pv-indigo);
    border-color: var(--pv-indigo);
    color: #fff;
}

.pv-btn-primary:hover {
    background: #131748;
    border-color: #131748;
}

.pv-link {
    color: #007FA8;
}

.pv-table thead th {
    color: var(--pv-text-secondary);
    font-size: .8125rem;
    font-weight: 600;
    background: #F8F9FB;
    border-bottom: 1px solid var(--pv-border);
}
```

Não copiar este CSS cegamente; usar como direção visual.

---

# 42. HTML5 semântico

Sempre que possível utilizar elementos nativos:

```html
<header>
<nav>
<main>
<section>
<article>
<aside>
<footer>

<table>
<thead>
<tbody>

<form>
<label>
<button>
```

Evitar transformar tudo em múltiplos `<div>` sem semântica.

---

# 43. Componentes Bootstrap recomendados

Usar Bootstrap como base para:

- Grid;
- Buttons;
- Forms;
- Tables;
- Navs;
- Tabs;
- Dropdown;
- Pagination;
- Modal;
- Offcanvas;
- Accordion;
- Alerts;
- Badges;
- Breadcrumb;
- Collapse;
- Tooltips quando necessários.

Personalizar visualmente através de CSS ProntoVia.

Não recriar manualmente componentes que Bootstrap já resolve bem.

---

# 44. Componentes a usar com moderação

## Cards

Usar apenas quando o agrupamento justifica.

## Toasts

Para feedback temporário.

## Tooltips

Para ações icon-only e termos pouco óbvios.

## Accordions

Para ajuda, FAQ ou informação secundária.

Não usar accordion para esconder informação essencial.

---

# 45. Acessibilidade

A interface deve funcionar sem depender apenas de cor.

Exemplo adequado:

```text
Reprovado
Aprovado
```

com cor como reforço.

Outras regras:

- focus visível;
- labels reais;
- `aria-label` em ações icon-only;
- `scope="col"` em tabelas;
- headings hierárquicos;
- contraste adequado;
- navegação por teclado.

---

# 46. Convenção de nomes visuais

Criar classes do projeto com prefixo `pv-`.

Exemplos:

```text
pv-sidebar
pv-page-header
pv-toolbar
pv-panel
pv-kpi
pv-table
pv-status
pv-empty-state
pv-section-title
pv-filter-bar
```

Bootstrap continua responsável pela base.

ProntoVia CSS controla a identidade.

---

# 47. Regra anti-saturação

Antes de adicionar qualquer novo elemento visual, responder:

1. Isto ajuda o utilizador a tomar uma decisão?
2. Isto melhora a compreensão?
3. Existe uma forma textual ou estrutural mais simples?
4. Já existe outro elemento na página com a mesma função?
5. Precisa realmente de cor?
6. Precisa realmente de um card?
7. Precisa realmente de um ícone?

Se a resposta for “não”, remover.

---

# 48. Regra para elementos de IA

Caso o ProntoVia introduza IA futuramente:

- identificar claramente onde a IA está presente;
- não transformar toda a interface numa estética “AI”;
- resultados gerados por IA devem ser distinguíveis;
- manter o restante sistema visualmente neutro.

A IA é uma capacidade, não a identidade visual do ProntoVia.

---

# 49. Direção por superfície

## ProntoVia Website

Mais espaço, fotografia, branding e apresentação.

Pode usar:
- hero;
- imagem;
- storytelling;
- secções maiores.

## ProntoVia App

Mais orientado à aprendizagem.

Pode usar:
- progresso;
- cartões de estudo;
- treino;
- feedback pedagógico.

## ProntoVia Escolas

Alta densidade controlada.

Priorizar:
- tabelas;
- filtros;
- resultados;
- ações;
- relatórios.

## ProntoVia Admin

Ainda mais operacional.

Priorizar:
- gestão;
- revisão;
- publicação;
- auditoria;
- tabelas;
- forms;
- estados.

Não aplicar o mesmo estilo de landing page às quatro superfícies.

---

# 50. Prioridade de refatoração visual

## Fase V1 — Fundação

1. tokens de cor;
2. tipografia;
3. spacing;
4. sidebar;
5. topbar;
6. page header;
7. buttons;
8. forms;
9. tables;
10. badges;
11. icons.

## Fase V2 — Templates

Criar templates para:

- dashboard;
- listagem;
- detalhe;
- formulário;
- relatório.

## Fase V3 — Módulos

Migrar progressivamente:

1. Dashboard;
2. Candidatos;
3. Turmas;
4. Provas;
5. Resultados;
6. Perguntas;
7. Sinais;
8. Escolas;
9. Pagamentos;
10. Configurações.

Não redesenhar cada página isoladamente.

---

# 51. Checklist de uma página pronta

Uma tela Web do ProntoVia só deve ser considerada visualmente concluída quando:

- [ ] possui breadcrumb quando necessário;
- [ ] possui título claro;
- [ ] possui no máximo uma ação primária;
- [ ] filtros estão agrupados;
- [ ] coleção de dados utiliza tabela quando apropriado;
- [ ] ações secundárias não dominam;
- [ ] ícones são consistentes;
- [ ] não existem gradientes desnecessários;
- [ ] não existem sombras fortes sem motivo;
- [ ] não existem cards aninhados;
- [ ] cores possuem significado;
- [ ] estados possuem texto;
- [ ] mobile/tablet continua utilizável;
- [ ] navegação por teclado funciona;
- [ ] empty state existe;
- [ ] loading state existe;
- [ ] informação importante está visível sem esforço.

---

# 52. Resumo executivo

A direção visual recomendada para as telas Web do ProntoVia é:

> **Bootstrap clássico modernizado, não “AI UI”.**

Na prática:

```text
HTML5 semântico
+ Bootstrap 5.3
+ Bootstrap Icons
+ Poppins
+ paleta ProntoVia
+ CSS próprio discreto
+ tabelas fortes
+ formulários convencionais
+ hierarquia clara
+ dashboards limitados
+ charts funcionais
+ poucas sombras
+ pouco radius
+ quase nenhum gradiente
```

A identidade ProntoVia deve vir de:

- cor;
- tipografia;
- organização;
- consistência;
- iconografia;
- qualidade das interações.

Não de efeitos visuais.

---

# 53. Referências de design pesquisadas

Esta direção foi construída tomando como referência práticas documentadas em:

- Bootstrap 5.3 — grid responsivo, tabelas, navegação e acessibilidade;
- Bootstrap Icons — iconografia SVG consistente e acessível;
- IBM Carbon Design System — dashboards, hierarquia, densidade, tabelas, gráficos e progressive disclosure;
- Atlassian Design System — foundations, consistência de ícones, spacing, color e layout.

Princípios particularmente relevantes:

- dashboards devem limitar métricas não essenciais;
- informação mais importante deve ocupar a posição e contraste mais fortes;
- tabelas são adequadas para grandes volumes de dados comparáveis;
- tabelas devem ter espaço horizontal suficiente;
- toolbar deve concentrar pesquisa, filtros e ações globais;
- paginação deve ser usada quando o volume não cabe numa única visão;
- ícones devem ter significado consistente;
- cor e espaçamento devem seguir tokens;
- Bootstrap permite uma base responsiva sem exigir interfaces visualmente extravagantes.

---

# 54. Princípio final

> **O ProntoVia Web deve parecer mais simples quanto mais complexo o sistema se torna.**

A quantidade de funcionalidades pode crescer.

A quantidade de ruído visual não deve crescer junto.
