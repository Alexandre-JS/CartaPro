@extends('layouts.admin')
@section('title','Escolas')
@section('page-title','Escolas')
@section('page-subtitle','Gerencie as instituições parceiras e o seu acesso.')
@section('content')
<div class="toolbar"><div><h2>Escolas parceiras</h2><p>{{ $schools->total() }} escolas cadastradas</p></div><a class="btn" href="{{ route('admin.schools.create') }}">＋ Nova escola</a></div>
<form class="filters"><input name="q" value="{{ request('q') }}" placeholder="Pesquisar escola"><button class="btn light">Pesquisar</button></form>
<section class="card table-card"><table class="data-table"><thead><tr><th>Código</th><th>Escola</th><th>Contacto</th><th>Contas</th><th>Estado</th><th>Ações</th></tr></thead><tbody>@forelse($schools as $school)<tr><td>{{ $school->code }}</td><td><strong>{{ $school->name }}</strong><br><small>{{ $school->address ?: 'Sem endereço' }}</small></td><td>{{ $school->phone ?: '—' }}<br><small>{{ $school->email }}</small></td><td>{{ $school->users_count }}</td><td><span class="status {{ $school->is_active ? 'active' : 'inactive' }}">{{ $school->is_active ? 'Ativa' : 'Inativa' }}</span></td><td class="actions"><a class="btn light small" href="{{ route('admin.schools.edit',$school) }}">Editar</a><form method="POST" action="{{ route('admin.schools.destroy',$school) }}" onsubmit="return confirm('Remover esta escola?')">@csrf @method('DELETE')<button class="btn danger small">Remover</button></form></td></tr>@empty<tr><td class="empty" colspan="6">Ainda não existem escolas.</td></tr>@endforelse</tbody></table></section><div class="pagination">{{ $schools->links() }}</div>
@endsection
