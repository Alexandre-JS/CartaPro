@extends('layouts.admin')
@section('title','Categorias de sinais')
@section('page-title','Categorias de sinais')
@section('page-subtitle','Organize sinais por categoria e, quando necessário, por uma subcategoria.')
@section('content')
<div class="taxonomy-page">
<x-admin.page-header id="sign-categories-title" title="Categorias de sinais" description="A hierarquia tem no máximo dois níveis para continuar simples no catálogo." :count="$categorias->count()" count-label="categorias"><x-admin.button data-dialog-open="create-sign-category"><i class="bi bi-plus-lg" aria-hidden="true"></i>Nova categoria</x-admin.button></x-admin.page-header>
<x-admin.table class="sign-categories-table" labelledby="sign-categories-title" caption="Categorias de sinais"><x-slot:head><tr><th scope="col">Categoria</th><th scope="col">Sinais</th><th scope="col">Ordem</th><th scope="col">Estado</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
@forelse($categorias as $categoria)
<tr><td class="taxonomy-main"><strong>{{ $categoria->name }}</strong><small>{{ $categoria->description ?: 'Categoria de topo' }}</small></td><td>{{ $categoria->signs_count }}</td><td>{{ $categoria->sort_order }}</td><td><x-admin.state :type="$categoria->is_active ? 'approved' : 'neutral'">{{ $categoria->is_active ? 'Ativa' : 'Inativa' }}</x-admin.state></td><td class="actions"><x-admin.row-actions :view-href="route('admin.sign-categories.edit',$categoria)" label="Ações da categoria"><a href="{{ route('admin.sign-categories.create', ['parent' => $categoria->id]) }}" role="menuitem"><i class="bi bi-diagram-3" aria-hidden="true"></i>Nova subcategoria</a><form method="POST" action="{{ route('admin.sign-categories.destroy',$categoria) }}" onsubmit="return confirm('Apagar {{ $categoria->name }}?')" role="menuitem">@csrf @method('DELETE')<button type="submit" class="is-danger"><i class="bi bi-trash3" aria-hidden="true"></i>Apagar</button></form></x-admin.row-actions></td></tr>
@foreach($categoria->children as $sub)
<tr class="taxonomy-child"><td class="taxonomy-main"><strong>↳ {{ $sub->name }}</strong><small>{{ $sub->description ?: 'Subcategoria' }}</small></td><td>{{ $sub->signs()->count() }}</td><td>{{ $sub->sort_order }}</td><td><x-admin.state :type="$sub->is_active ? 'approved' : 'neutral'">{{ $sub->is_active ? 'Ativa' : 'Inativa' }}</x-admin.state></td><td class="actions"><x-admin.row-actions :view-href="route('admin.sign-categories.edit',$sub)" label="Ações da subcategoria"><form method="POST" action="{{ route('admin.sign-categories.destroy',$sub) }}" onsubmit="return confirm('Apagar {{ $sub->name }}?')" role="menuitem">@csrf @method('DELETE')<button type="submit" class="is-danger"><i class="bi bi-trash3" aria-hidden="true"></i>Apagar</button></form></x-admin.row-actions></td></tr>
@endforeach
@empty
<x-admin.empty-state table :colspan="5" icon="diagram-3" title="Ainda não há categorias" description="Crie a primeira para começar a catalogar sinais." />
@endforelse
</x-admin.table>
</div>
<x-admin.dialog id="create-sign-category" title="Nova categoria de sinais" description="As subcategorias podem ser adicionadas depois a partir da linha da categoria principal."><form id="create-sign-category-form" method="POST" action="{{ route('admin.sign-categories.store') }}" class="modal-form-grid">@csrf<x-admin.field name="name" label="Nome" required /><x-admin.field name="slug" label="Identificador" placeholder="perigo" /><x-admin.field name="description" label="Descrição" as="textarea" rows="3" class="modal-form-full" /><x-admin.field name="sort_order" label="Ordem" type="number" min="0" value="0" /><label class="pv-checkbox"><input type="checkbox" name="is_active" value="1" checked> Categoria ativa</label></form><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><x-admin.button type="submit" form="create-sign-category-form">Guardar categoria</x-admin.button></x-slot:footer></x-admin.dialog>
@endsection
