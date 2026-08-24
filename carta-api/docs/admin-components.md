# Componentes do painel ProntoVia

Os componentes são Blade anónimos em `resources/views/components/admin` e usam a identidade definida em `public/css/admin.css`.

```blade
<x-admin.button variant="secondary" :href="$url">Editar</x-admin.button>
<x-admin.button variant="danger" type="submit" loading-label="A remover…">Remover</x-admin.button>

<x-admin.field name="name" label="Nome" :value="$model->name" hint="Nome apresentado ao aluno." required />
<x-admin.field as="select" name="status" label="Estado"><option value="active">Ativo</option></x-admin.field>
<x-admin.field as="textarea" name="notes" label="Notas" :value="$model->notes" full />

<x-admin.state type="approved">Aprovada</x-admin.state>

<x-admin.table caption="Perguntas publicadas">
    <x-slot:head><tr><th scope="col">Pergunta</th></tr></x-slot:head>
    <tr><td>Conteúdo</td></tr>
</x-admin.table>

<x-admin.empty-state title="Nenhum resultado" description="Altere os filtros." />
<x-admin.empty-state table :colspan="6" title="Tabela vazia" />

<x-admin.pagination :paginator="$records" />

<x-admin.button data-dialog-open="confirm-delete">Remover</x-admin.button>
<x-admin.dialog id="confirm-delete" title="Remover registo?" size="small">
    Conteúdo da confirmação.
    <x-slot:footer><x-admin.button data-dialog-close>Cancelar</x-admin.button></x-slot:footer>
</x-admin.dialog>
```

Variantes dos botões: `primary`, `secondary`, `danger`, `warning` e `ghost`. Tamanhos: `medium` e `small`. Estados semânticos aceitam `success/active/approved`, `warning/review/pending/progress`, `danger/error/rejected` e qualquer outro valor como neutro.
