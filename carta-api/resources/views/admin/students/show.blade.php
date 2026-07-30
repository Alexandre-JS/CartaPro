@extends('layouts.admin')
@section('title','Histórico de '.$student->name)
@section('page-title','Histórico de '.$student->name)
@section('page-subtitle','Avaliações e progresso para aptidão.')
@section('content')
<div class="toolbar"><a class="btn light" href="{{ route('admin.classrooms.index') }}">← Voltar às turmas</a><span class="status {{ $isApt ? 'approved' : 'review' }}">{{ $isApt ? 'Apto' : 'Ainda não apto' }}</span></div>
<section class="metric-grid">
    <article class="card metric-card"><span class="metric-icon blue">▤</span><div><span>Avaliações realizadas</span><strong>{{ $student->attempts->count() }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon green">✓</span><div><span>Notas iguais ou superiores a 14</span><strong>{{ $validCount }}/3</strong></div></article>
    <article class="card metric-card"><span class="metric-icon yellow">20</span><div><span>Média geral</span><strong>{{ $averageValues }}</strong><small>valores</small></div></article>
    <article class="card metric-card"><span class="metric-icon {{ $isApt ? 'green' : 'yellow' }}">{{ $isApt ? '✓' : $remaining }}</span><div><span>Estado</span><strong style="font-size:18px">{{ $isApt ? 'Apto' : 'Faltam '.$remaining }}</strong><small>{{ $isApt ? 'Requisito atingido' : ($remaining === 1 ? 'nota válida' : 'notas válidas') }}</small></div></article>
</section>
<section class="card detail-card" style="margin-top:14px;max-width:none"><dl class="detail-grid"><div class="detail-field"><dt>Nome</dt><dd>{{ $student->name }}</dd></div><div class="detail-field"><dt>Identificador</dt><dd>{{ $student->identifier ?: '—' }}</dd></div><div class="detail-field"><dt>Turma</dt><dd>{{ $student->classroom->name }}</dd></div><div class="detail-field"><dt>Escola</dt><dd>{{ $student->classroom->school->name }}</dd></div></dl></section>
<div class="toolbar" style="margin-top:24px"><div><h2>Avaliações</h2><p>O estudante fica apto após três provas com nota mínima de 14 valores.</p></div></div>
<section class="card table-card"><table class="data-table"><thead><tr><th>Prova</th><th>Sessão</th><th>Acertos</th><th>Percentagem</th><th>Nota</th><th>Conta para aptidão</th><th>Data</th><th></th></tr></thead><tbody>
@forelse($student->attempts as $attempt)<tr><td><strong>{{ $attempt->session->exam->name }}</strong></td><td>{{ $attempt->session->code }}</td><td>{{ $attempt->score }}/{{ $attempt->total }}</td><td>{{ $attempt->percentage() }}%</td><td><strong>{{ $attempt->gradeValues() }} valores</strong></td><td><span class="status {{ $attempt->qualifiesForAptitude() ? 'approved' : 'inactive' }}">{{ $attempt->qualifiesForAptitude() ? 'Sim' : 'Não' }}</span></td><td>{{ $attempt->submitted_at->format('d/m/Y H:i') }}</td><td><a class="btn light small" href="{{ route('admin.results.show',$attempt) }}">Conferir</a></td></tr>@empty<tr><td colspan="8" class="empty">Este estudante ainda não realizou avaliações.</td></tr>@endforelse
</tbody></table></section>
@endsection
