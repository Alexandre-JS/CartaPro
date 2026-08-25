@extends('layouts.admin')
@section('title','Temas')
@section('page-title','Temas')
@section('page-subtitle','Organize as áreas de aprendizagem do ProntoVia.')
@section('content')
<div class="taxonomy-page">
    <x-admin.page-header id="topics-page-title" title="Temas de estudo" description="Agrupe perguntas, fichas e recomendações por área de aprendizagem." :count="$topics->total()" count-label="temas"><x-admin.button data-dialog-open="create-topic"><i class="bi bi-plus-lg" aria-hidden="true"></i>Novo tema</x-admin.button></x-admin.page-header>
    <x-admin.table class="topics-table" labelledby="topics-page-title" caption="Temas de estudo"><x-slot:head><tr><th scope="col">Ordem</th><th scope="col">Tema</th><th scope="col">Perguntas</th><th scope="col">Estado</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
        @forelse($topics as $topic)
            <tr><td>{{ $topic->sort_order }}</td><td class="taxonomy-main"><strong>{{ $topic->name }}</strong><small>{{ $topic->description ?: 'Sem descrição' }}</small></td><td><strong>{{ $topic->questions_count }}</strong><small class="taxonomy-meta">perguntas</small></td><td><x-admin.state :type="$topic->is_active ? 'approved' : 'neutral'">{{ $topic->is_active ? 'Ativo' : 'Inativo' }}</x-admin.state></td><td class="actions"><x-admin.row-actions :view-href="route('admin.topics.edit',$topic)" label="Ações do tema"><form method="POST" action="{{ route('admin.topics.destroy',$topic) }}" onsubmit="return confirm('Remover este tema e todas as suas perguntas?')" role="menuitem">@csrf @method('DELETE')<button type="submit" class="is-danger"><i class="bi bi-trash3" aria-hidden="true"></i>Remover</button></form></x-admin.row-actions></td></tr>
        @empty
            <x-admin.empty-state table :colspan="5" icon="collection" title="Ainda não existem temas" description="Crie o primeiro tema para organizar o conteúdo de aprendizagem." />
        @endforelse
    </x-admin.table><x-admin.pagination :paginator="$topics" />
</div>
<x-admin.dialog id="create-topic" title="Novo tema" description="Defina a área que será usada no conteúdo e nas recomendações."><form id="create-topic-form" method="POST" action="{{ route('admin.topics.store') }}" class="modal-form-grid">@csrf<x-admin.field name="name" label="Nome" required /><x-admin.field name="slug" label="Identificador" required placeholder="sinalizacao" /><x-admin.field name="description" label="Descrição" as="textarea" rows="3" class="modal-form-full" /><x-admin.field name="sort_order" label="Ordem" type="number" min="0" value="0" /><label class="pv-checkbox"><input type="checkbox" name="is_active" value="1" checked> Tema ativo</label></form><x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><x-admin.button type="submit" form="create-topic-form">Guardar tema</x-admin.button></x-slot:footer></x-admin.dialog>
@endsection
