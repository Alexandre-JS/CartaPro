@extends('layouts.admin')
@section('title','Escolas')
@section('page-title','Escolas')
@section('page-subtitle','Gerencie as instituições parceiras e o seu acesso.')
@section('content')
<x-admin.page-header title="Escolas parceiras" description="Instituições, contactos e acesso à plataforma." :count="$schools->total()" count-label="escolas">
    <x-admin.button :href="route('admin.schools.create')">＋ Nova escola</x-admin.button>
</x-admin.page-header>

<x-admin.data-toolbar label="Pesquisar escolas">
    <input name="q" value="{{ request('q') }}" aria-label="Pesquisar escola" placeholder="Nome ou código da escola">
    <x-admin.button variant="secondary" type="submit">Pesquisar</x-admin.button>
    @if(request()->filled('q'))
        <x-admin.button variant="ghost" :href="route('admin.schools.index')">Limpar</x-admin.button>
    @endif
</x-admin.data-toolbar>

<x-admin.table caption="Escolas parceiras">
    <x-slot:head>
        <tr>
            <th scope="col">Código</th>
            <th scope="col">Escola</th>
            <th scope="col">Contacto</th>
            <th scope="col">Contas</th>
            <th scope="col">Estado</th>
            <th scope="col">Ações</th>
        </tr>
    </x-slot:head>
    @forelse($schools as $school)
        <tr>
            <td>{{ $school->code }}</td>
            <td><strong>{{ $school->name }}</strong><br><small>{{ $school->address ?: 'Sem endereço' }}</small></td>
            <td>{{ $school->phone ?: '—' }}<br><small>{{ $school->email }}</small></td>
            <td>{{ $school->users_count }}</td>
            <td><x-admin.state :type="$school->is_active ? 'active' : 'neutral'">{{ $school->is_active ? 'Ativa' : 'Inativa' }}</x-admin.state></td>
            <td class="actions">
                <x-admin.button variant="secondary" size="small" :href="route('admin.schools.edit',$school)">Editar</x-admin.button>
                <x-admin.button variant="danger" size="small" data-dialog-open="delete-school-{{ $school->id }}">Remover</x-admin.button>
            </td>
        </tr>
    @empty
        <x-admin.empty-state table :colspan="6" icon="people" title="Ainda não existem escolas" description="Crie a primeira escola para começar a organizar turmas e utilizadores." />
    @endforelse
</x-admin.table>

<x-admin.pagination :paginator="$schools" />

@foreach($schools as $school)
    <x-admin.dialog id="delete-school-{{ $school->id }}" title="Remover escola?" description="Esta ação não pode ser anulada." size="small">
        <p>Vai remover permanentemente <strong>{{ $school->name }}</strong>.</p>
        <x-slot:footer>
            <x-admin.button variant="secondary" data-dialog-close>Cancelar</x-admin.button>
            <form method="POST" action="{{ route('admin.schools.destroy',$school) }}">
                @csrf
                @method('DELETE')
                <x-admin.button variant="danger" type="submit">Remover escola</x-admin.button>
            </form>
        </x-slot:footer>
    </x-admin.dialog>
@endforeach
@endsection
