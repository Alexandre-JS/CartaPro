@extends('layouts.admin')
@section('title','Histórico de '.$student->name)
@section('page-title','Histórico de '.$student->name)
@section('page-subtitle','Avaliações e progresso para aptidão.')
@section('content')
<div class="toolbar"><x-admin.button variant="secondary" :href="route('admin.classrooms.index')">← Voltar às turmas</x-admin.button><x-admin.state :type="$isApt ? 'approved' : 'review'">{{ $isApt ? 'Apto' : 'Ainda não apto' }}</x-admin.state></div>
<section class="metric-grid">
    <article class="card metric-card"><span class="metric-icon blue">▤</span><div><span>Avaliações realizadas</span><strong>{{ $student->attempts->count() }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon green">✓</span><div><span>Notas iguais ou superiores a 14</span><strong>{{ $validCount }}/3</strong></div></article>
    <article class="card metric-card"><span class="metric-icon yellow">20</span><div><span>Média geral</span><strong>{{ $averageValues }}</strong><small>valores</small></div></article>
    <article class="card metric-card"><span class="metric-icon {{ $isApt ? 'green' : 'yellow' }}">{{ $isApt ? '✓' : $remaining }}</span><div><span>Estado</span><strong style="font-size:18px">{{ $isApt ? 'Apto' : 'Faltam '.$remaining }}</strong><small>{{ $isApt ? 'Requisito atingido' : ($remaining === 1 ? 'nota válida' : 'notas válidas') }}</small></div></article>
</section>
<section class="card detail-card" style="margin-top:14px;max-width:none"><dl class="detail-grid"><div class="detail-field"><dt>Nome</dt><dd>{{ $student->name }}</dd></div><div class="detail-field"><dt>Identificador</dt><dd>{{ $student->identifier ?: '—' }}</dd></div><div class="detail-field"><dt>Turma</dt><dd>{{ $student->classroom->name }}</dd></div><div class="detail-field"><dt>Escola</dt><dd>{{ $student->classroom->school->name }}</dd></div></dl></section>
<div class="toolbar" style="margin-top:24px"><div><h2 id="student-attempts-title">Avaliações</h2><p>O estudante fica apto após três provas com nota mínima de 14 valores.</p></div></div>
<x-admin.table labelledby="student-attempts-title"><x-slot:head><tr><th scope="col">Prova</th><th scope="col">Sessão</th><th scope="col">Acertos</th><th scope="col">Percentagem</th><th scope="col">Nota</th><th scope="col">Conta para aptidão</th><th scope="col">Data</th><th scope="col">Ações</th></tr></x-slot:head>
@forelse($student->attempts as $attempt)<tr><td><strong>{{ $attempt->session->exam->name }}</strong></td><td>{{ $attempt->session->code }}</td><td>{{ $attempt->score }}/{{ $attempt->total }}</td><td>{{ $attempt->percentage() }}%</td><td><strong>{{ $attempt->gradeValues() }} valores</strong></td><td><x-admin.state :type="$attempt->qualifiesForAptitude() ? 'approved' : 'neutral'">{{ $attempt->qualifiesForAptitude() ? 'Sim' : 'Não' }}</x-admin.state></td><td>{{ $attempt->submitted_at->format('d/m/Y H:i') }}</td><td><x-admin.button variant="secondary" size="small" :href="route('admin.results.show',$attempt)">Conferir</x-admin.button></td></tr>@empty<x-admin.empty-state table :colspan="8" title="Sem avaliações realizadas" description="O histórico será apresentado depois da primeira prova submetida." />@endforelse
</x-admin.table>
@endsection
