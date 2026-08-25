@extends('layouts.admin')
@section('title','Utilizadores')
@section('page-title','Utilizadores do painel')
@section('page-subtitle','Contas, papéis e acesso das escolas.')
@section('content')
<div class="users-page">
    <x-admin.page-header id="users-page-title" title="Contas de acesso" description="Gira os utilizadores e mantenha cada papel ligado ao contexto certo." :count="$users->total()" count-label="utilizadores">
        <x-admin.button data-dialog-open="create-user"><i class="bi bi-plus-lg" aria-hidden="true"></i>Novo utilizador</x-admin.button>
    </x-admin.page-header>
    <form method="get" class="data-toolbar users-filters" aria-label="Filtrar utilizadores"><label class="pv-table-search"><i class="bi bi-search" aria-hidden="true"></i><input name="q" value="{{ request('q') }}" placeholder="Pesquisar por nome ou e-mail" aria-label="Nome ou e-mail"></label><label class="field"><span>Papel</span><select name="role"><option value="">Todos os papéis</option><option value="admin" @selected(request('role') === 'admin')>Administrador</option><option value="school" @selected(request('role') === 'school')>Escola</option></select></label><x-admin.button type="submit" variant="secondary"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</x-admin.button>@if(request()->hasAny(['q','role']))<x-admin.button variant="secondary" :href="route('admin.users.index')">Limpar</x-admin.button>@endif</form>
    <x-admin.table class="users-table" labelledby="users-page-title" caption="Contas de acesso"><x-slot:head><tr><th scope="col">Utilizador</th><th scope="col">Papel</th><th scope="col">Escola</th><th scope="col">Estado</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
        @forelse($users as $user)<tr><td class="user-main"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td><td>{{ $user->roleLabel() }}</td><td>{{ $user->school?->name ?: '—' }}</td><td><x-admin.state :type="$user->is_active ? 'approved' : 'neutral'">{{ $user->is_active ? 'Ativo' : 'Inativo' }}</x-admin.state></td><td class="actions"><x-admin.row-actions :view-href="route('admin.users.edit',$user)" label="Ações do utilizador"><form method="POST" action="{{ route('admin.users.destroy',$user) }}" onsubmit="return confirm('Remover este utilizador?')" role="menuitem">@csrf @method('DELETE')<button type="submit" class="is-danger"><i class="bi bi-trash3" aria-hidden="true"></i>Remover</button></form></x-admin.row-actions></td></tr>@empty<x-admin.empty-state table :colspan="5" icon="people" title="Ainda não existem utilizadores" description="Crie o primeiro utilizador para começar a gerir os acessos." />@endforelse
    </x-admin.table><x-admin.pagination :paginator="$users" />
</div>
<x-admin.dialog id="create-user" title="Novo utilizador" description="Defina o papel e, quando aplicável, a escola responsável.">
    <form id="create-user-form" method="POST" action="{{ route('admin.users.store') }}" class="modal-form-grid">@csrf
        <x-admin.field name="name" label="Nome" required />
        <x-admin.field name="email" label="E-mail" type="email" required />
        <x-admin.field as="select" name="role" label="Papel" required><option value="school_admin">Administrador da escola</option><option value="school_owner">Responsável da escola</option><option value="content_author">Autor de conteúdo</option><option value="content_reviewer">Revisor de conteúdo</option><option value="platform_admin">Administrador da plataforma</option></x-admin.field>
        <x-admin.field as="select" name="school_id" label="Escola"><option value="">Selecione</option>@foreach($schools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</x-admin.field>
        <x-admin.field name="password" label="Palavra-passe" type="password" required />
        <label class="pv-checkbox"><input type="checkbox" name="is_active" value="1" checked> Acesso ativo</label>
    </form>
    <x-slot:footer><x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button><x-admin.button type="submit" form="create-user-form">Guardar utilizador</x-admin.button></x-slot:footer>
</x-admin.dialog>
<script>document.querySelector('#create-user-form [name="role"]')?.addEventListener('change',function(){var school=this.form.querySelector('[name="school_id"]')?.closest('.field');if(school)school.hidden=!['school','school_owner','school_admin'].includes(this.value)});</script>
@endsection
