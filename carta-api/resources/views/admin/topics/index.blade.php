@extends('layouts.admin')
@section('title','Temas')
@section('page-title','Temas')
@section('page-subtitle','Organize as áreas de aprendizagem do CartaPro.')
@section('content')
<div class="toolbar"><div><h2>Temas de estudo</h2><p>{{ $topics->total() }} temas cadastrados</p></div><a class="btn" href="{{ route('admin.topics.create') }}">＋ Novo tema</a></div>
<section class="card table-card"><table class="data-table"><thead><tr><th>Ordem</th><th>Tema</th><th>Perguntas</th><th>Estado</th><th>Ações</th></tr></thead><tbody>
@forelse($topics as $topic)<tr><td>{{ $topic->sort_order }}</td><td><strong>{{ $topic->name }}</strong><br><small>{{ $topic->slug }}</small></td><td>{{ $topic->questions_count }}</td><td><span class="status {{ $topic->is_active ? 'active' : 'inactive' }}">{{ $topic->is_active ? 'Ativo' : 'Inativo' }}</span></td><td class="actions"><a class="btn light small" href="{{ route('admin.topics.edit',$topic) }}">Editar</a><form method="POST" action="{{ route('admin.topics.destroy',$topic) }}" onsubmit="return confirm('Remover este tema e todas as suas perguntas?')">@csrf @method('DELETE')<button class="btn danger small">Remover</button></form></td></tr>@empty<tr><td colspan="5" class="empty">Ainda não existem temas.</td></tr>@endforelse
</tbody></table></section><div class="pagination">{{ $topics->links() }}</div>
@endsection
