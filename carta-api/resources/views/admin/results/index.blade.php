@extends('layouts.admin')
@section('title', 'Resultados')
@section('page-title', 'Resultados')
@section('page-subtitle', 'Desempenho por aluno, turma e prova.')
@section('content')
<section class="metric-grid">
    <article class="card metric-card"><span class="metric-icon blue">%</span><div><span>Média</span><strong>{{ $average }}%</strong></div></article>
    <article class="card metric-card"><span class="metric-icon green">✓</span><div><span>Notas ≥ {{ \App\Support\Grading::minimumAptitudeValues() }} valores</span><strong>{{ $validGradesCount }}</strong></div></article>
    <article class="card metric-card"><span class="metric-icon amber">Σ</span><div><span>Provas submetidas</span><strong>{{ $attemptsCount }}</strong></div></article>
</section>

<div class="toolbar">
    <div><h2>Análise por turma</h2><p>Onde a turma erra mais, evolução por sessão e quem já está pronto.</p></div>
    <form method="get" class="inline-form">
        <select name="classroom_id" aria-label="Filtrar por turma" onchange="this.form.submit()">
            <option value="">Todas as turmas</option>
            @foreach($classrooms as $classroom)
                <option value="{{ $classroom->id }}" @selected(request('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
            @endforeach
        </select>
        @if(request('classroom_id'))
            <x-admin.button :href="route('admin.results.classroom', request('classroom_id'))">Abrir painel da turma</x-admin.button>
        @endif
    </form>
</div>

<div class="toolbar"><div><h2 id="results-title">Resultados submetidos</h2></div><x-admin.button variant="secondary" :href="route('admin.results.export', request()->query())">Exportar CSV</x-admin.button></div>
<x-admin.table labelledby="results-title"><x-slot:head><tr><th scope="col">Aluno</th><th scope="col">Turma</th><th scope="col">Prova</th><th scope="col">Pontuação</th><th scope="col">Para aptidão</th><th scope="col">Temas fracos</th><th scope="col">Data</th><th scope="col">Ações</th></tr></x-slot:head>
@forelse($attempts as $attempt)
<tr><td><strong><a href="{{ route('admin.students.show',$attempt->student) }}">{{ $attempt->student->name }}</a></strong></td><td><a href="{{ route('admin.results.classroom', $attempt->session->classroom) }}">{{ $attempt->session->classroom->name }}</a></td><td>{{ $attempt->session->exam->name }}<br><small>Sessão {{ $attempt->session->code }}</small></td><td><strong>{{ $attempt->score }}/{{ $attempt->total }}</strong><br><small>{{ $attempt->percentage() }}% · {{ $attempt->gradeValues() }} valores</small></td><td><x-admin.state :type="$attempt->qualifiesForAptitude() ? 'approved' : 'neutral'">{{ $attempt->qualifiesForAptitude() ? 'Conta para aptidão' : 'Abaixo de '.\App\Support\Grading::minimumAptitudeValues() }}</x-admin.state></td><td>{{ implode(', ', $attempt->weak_topics ?? []) ?: '—' }}</td><td>{{ $attempt->submitted_at->format('d/m/Y H:i') }}</td><td class="actions"><x-admin.button variant="secondary" size="small" :href="route('admin.results.show', $attempt)">Conferir</x-admin.button><x-admin.button variant="secondary" size="small" :href="route('admin.students.show',$attempt->student)">Histórico</x-admin.button></td></tr>
@empty<x-admin.empty-state table :colspan="8" title="Ainda não existem resultados" description="Os resultados aparecem após a primeira submissão de uma prova." />@endforelse
</x-admin.table><x-admin.pagination :paginator="$attempts" />
@endsection
