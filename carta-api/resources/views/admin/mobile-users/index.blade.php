@extends('layouts.admin')
@section('title','Utilizadores do aplicativo')
@section('page-title','Utilizadores do aplicativo')
@section('page-subtitle','Contas, planos e atividade dos candidatos no ProntoVia')
@section('content')
<x-admin.page-header id="mobile-users-title" title="Contas mobile" description="Pesquise contas, acompanhe atividade e gere o acesso dos candidatos." :count="$users->total()" count-label="utilizadores">
    <x-admin.button :href="route('admin.mobile-users.index')"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Atualizar</x-admin.button>
</x-admin.page-header>
<x-admin.data-toolbar class="mobile-users-filters" label="Pesquisar e filtrar utilizadores do aplicativo">
    <label class="pv-table-search"><i class="bi bi-search" aria-hidden="true"></i><span class="sr-only">Pesquisar utilizadores</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Nome, email ou telefone"></label>
    <select name="status" aria-label="Estado da conta"><option value="">Todos os estados</option><option value="active" @selected(request('status') === 'active')>Ativos</option><option value="inactive" @selected(request('status') === 'inactive')>Inativos</option></select>
    <x-admin.button type="submit"><i class="bi bi-funnel" aria-hidden="true"></i>Filtrar</x-admin.button>
    @if(request()->query())<x-admin.button variant="secondary" :href="route('admin.mobile-users.index')">Limpar</x-admin.button>@endif
</x-admin.data-toolbar>
<x-admin.table class="mobile-users-table" labelledby="mobile-users-title" caption="Contas mobile">
<x-slot:head><tr><th scope="col">Utilizador</th><th scope="col">Plano</th><th scope="col">Atividade</th><th scope="col">Estado</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
@forelse($users as $mobileUser)
@php($digits = preg_replace('/\D+/', '', $mobileUser->phone)) @php($unlock = $unlocks->get((strlen($digits) === 9 ? '258' : '').$digits))
<tr><td class="mobile-user-main"><strong>{{ $mobileUser->name }}</strong><small>{{ $mobileUser->email }} · {{ $mobileUser->phone }}</small></td><td><span class="status {{ $unlock && $unlock->is_active && (!$unlock->expires_at || $unlock->expires_at->isFuture()) ? 'active' : 'inactive' }}">{{ $unlock?->plan ?? 'Grátis' }}</span></td><td><strong>{{ $mobileUser->exams_count }} testes</strong><small>{{ $mobileUser->answers_count }} respostas · {{ $mobileUser->read_contents_count }} conteúdos</small><small>Último acesso: {{ $mobileUser->last_seen_at ? \Illuminate\Support\Carbon::parse($mobileUser->last_seen_at)->diffForHumans() : 'Nunca' }}</small></td><td><span class="status {{ $mobileUser->is_active ? 'active' : 'inactive' }}">{{ $mobileUser->is_active ? 'Ativo' : 'Inativo' }}</span></td><td class="actions"><x-admin.row-actions :view-href="route('admin.mobile-users.show', $mobileUser)" label="Ações do utilizador"><form method="POST" action="{{ route('admin.mobile-users.status', $mobileUser) }}" role="menuitem">@csrf @method('PATCH')<button type="submit" class="{{ $mobileUser->is_active ? 'is-danger' : '' }}"><i class="bi {{ $mobileUser->is_active ? 'bi-person-slash' : 'bi-person-check' }}" aria-hidden="true"></i>{{ $mobileUser->is_active ? 'Desativar' : 'Ativar' }}</button></form></x-admin.row-actions></td></tr>
@empty
<x-admin.empty-state table :colspan="5" icon="people" title="Nenhum utilizador encontrado" description="Experimente alterar a pesquisa ou os filtros aplicados." />
@endforelse
</x-admin.table>
<x-admin.pagination :paginator="$users" />
@endsection
