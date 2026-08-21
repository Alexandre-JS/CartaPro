@extends('layouts.admin')
@section('title','Utilizadores do aplicativo')
@section('page-title','Utilizadores do aplicativo')
@section('page-subtitle','Contas, planos e atividade dos cidadãos no CartaPro')
@section('content')
<div class="toolbar"><div><h2>Contas mobile</h2><p>{{ $users->total() }} utilizadores registados</p></div></div>
<form class="filters"><input name="q" value="{{ request('q') }}" placeholder="Nome, email ou telefone"><select name="status"><option value="">Todos os estados</option><option value="active" @selected(request('status') === 'active')>Ativos</option><option value="inactive" @selected(request('status') === 'inactive')>Inativos</option></select><button class="btn light">Filtrar</button></form>
<section class="card table-card"><table class="data-table"><thead><tr><th>Utilizador</th><th>Plano</th><th>Atividade</th><th>Desempenho</th><th>Último acesso</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($users as $mobileUser)
@php($digits = preg_replace('/\D+/', '', $mobileUser->phone)) @php($unlock = $unlocks->get((strlen($digits) === 9 ? '258' : '').$digits))
<tr><td><strong>{{ $mobileUser->name }}</strong><br><small>{{ $mobileUser->email }} · {{ $mobileUser->phone }}</small></td><td><span class="status {{ $unlock && $unlock->is_active && (!$unlock->expires_at || $unlock->expires_at->isFuture()) ? 'active' : 'inactive' }}">{{ $unlock?->plan ?? 'Grátis' }}</span></td><td>{{ $mobileUser->exams_count }} testes<br><small>{{ $mobileUser->read_contents_count }} conteúdos</small></td><td>{{ $mobileUser->answers_count }} respostas</td><td><small>{{ $mobileUser->last_seen_at ? \Illuminate\Support\Carbon::parse($mobileUser->last_seen_at)->diffForHumans() : 'Nunca' }}</small></td><td><span class="status {{ $mobileUser->is_active ? 'active' : 'inactive' }}">{{ $mobileUser->is_active ? 'Ativo' : 'Inativo' }}</span></td><td class="actions"><a class="btn light small" href="{{ route('admin.mobile-users.show', $mobileUser) }}">Ver</a></td></tr>
@empty<tr><td colspan="7" class="empty">Nenhum utilizador do aplicativo encontrado.</td></tr>@endforelse
</tbody></table></section><div class="pagination">{{ $users->links() }}</div>
@endsection
