@extends('layouts.admin')
@section('title','Dashboard')
@section('page-title','Dashboard')
@section('page-subtitle','Bem-vindo(a) de volta, '.auth()->user()->name.'.')
@section('content')
@php
    $totalEditorial = max(1, $approvedCount + $reviewCount + $rejectedCount + $draftCount);
    $approvedAngle = ($approvedCount / $totalEditorial) * 360;
    $reviewAngle = $approvedAngle + ($reviewCount / $totalEditorial) * 360;
@endphp
<section class="metric-grid">
    @if(auth()->user()->isSchool())
    <article class="card metric-card"><span class="metric-icon green">?</span><div><span>Minhas perguntas</span><strong>{{ number_format($questionsCount,0,',',' ') }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon yellow">◷</span><div><span>Por aprovar</span><strong>{{ number_format($reviewCount,0,',',' ') }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon blue">▦</span><div><span>Turmas</span><strong>{{ $classroomsCount }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon green">▤</span><div><span>Provas</span><strong>{{ $examsCount }}</strong></div></article>
    @else
    <article class="card metric-card"><span class="metric-icon green">✓</span><div><span>Perguntas aprovadas</span><strong>{{ number_format($approvedCount,0,',',' ') }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon yellow">◷</span><div><span>Em revisão</span><strong>{{ number_format($reviewCount,0,',',' ') }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon blue">▦</span><div><span>Escolas ativas</span><strong>{{ number_format($schoolsCount,0,',',' ') }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon green">⬆</span><div><span>Último pacote</span><strong style="font-size:18px">{{ $lastPackage?->version ?? '—' }}</strong></div></article>
    @endif
</section>
@if(auth()->user()->isAdmin())
<div class="toolbar" style="margin-top:22px"><div><h2>Utilizadores da aplicação</h2><p>Utilização real da aplicação ProntoVia.</p></div></div>
<section class="metric-grid">
    <article class="card metric-card"><span class="metric-icon blue">♟</span><div><span>Contas mobile</span><strong>{{ $mobileUsersCount }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon green">✓</span><div><span>Contas ativas</span><strong>{{ $activeMobileUsersCount }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon yellow">?</span><div><span>Utilizadores com atividade</span><strong>{{ $mobileUsersWithActivityCount }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon green">▤</span><div><span>Exames realizados</span><strong>{{ $mobileExamsCompletedCount }}</strong></div></article>
</section>
@endif
<section class="dashboard-grid">
    <article class="card panel"><h3>Perguntas por estado</h3><div class="status-overview"><div class="donut" style="--approved:{{ $approvedAngle }}deg;--review:{{ $reviewAngle }}deg;@if(!$questionsCount) background:#edf0ed;@endif"><span><strong>{{ $questionsCount }}</strong><small>Total</small></span></div><div class="legend"><div><i class="dot green"></i><span>Aprovadas</span><strong>{{ $approvedCount }}</strong></div><div><i class="dot yellow"></i><span>Em revisão</span><strong>{{ $reviewCount }}</strong></div><div><i class="dot red"></i><span>Rejeitadas</span><strong>{{ $rejectedCount }}</strong></div><div><i class="dot gray"></i><span>Rascunhos</span><strong>{{ $draftCount }}</strong></div></div></div></article>
    <article class="card panel"><h3>Atividade recente</h3><div class="activity">@forelse($recentQuestions as $question)<div class="activity-item"><span class="activity-icon">?</span><div><strong>{{ str($question->statement)->limit(55) }}</strong><small>{{ $question->topic->name }} · {{ $question->updated_at->diffForHumans() }}</small></div><span class="status {{ $question->status }}">{{ ['draft'=>'Rascunho','review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'][$question->status] }}</span></div>@empty<div class="empty">A atividade aparecerá depois da primeira pergunta.</div>@endforelse</div></article>
</section>
@if(auth()->user()->isAdmin())<div class="toolbar"><div><h2>Operações prioritárias</h2><p>{{ $reviewCount }} pergunta(s) aguardam revisão.</p></div><div class="actions"><a class="btn light" href="{{ route('admin.approvals.index') }}">Rever pendentes</a><a class="btn" href="{{ route('admin.publications.index') }}">Publicar pacote</a></div></div>@endif
@endsection
