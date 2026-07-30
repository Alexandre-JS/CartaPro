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
        <select name="classroom_id" onchange="this.form.submit()">
            <option value="">Todas as turmas</option>
            @foreach($classrooms as $classroom)
                <option value="{{ $classroom->id }}" @selected(request('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>
            @endforeach
        </select>
        @if(request('classroom_id'))
            <a class="btn" href="{{ route('admin.results.classroom', request('classroom_id')) }}">Abrir painel da turma</a>
        @endif
    </form>
</div>

<div class="toolbar"><div><h2>Resultados submetidos</h2></div><a class="btn light" href="{{ route('admin.results.export', request()->query()) }}">Exportar CSV</a></div>
<section class="card table-card"><table class="data-table"><thead><tr><th>Aluno</th><th>Turma</th><th>Prova</th><th>Pontuação</th><th>Para aptidão</th><th>Temas fracos</th><th>Data</th><th>Ações</th></tr></thead><tbody>
@forelse($attempts as $attempt)
<tr><td><strong><a href="{{ route('admin.students.show',$attempt->student) }}">{{ $attempt->student->name }}</a></strong></td><td><a href="{{ route('admin.results.classroom', $attempt->session->classroom) }}">{{ $attempt->session->classroom->name }}</a></td><td>{{ $attempt->session->exam->name }}<br><small>Sessão {{ $attempt->session->code }}</small></td><td><strong>{{ $attempt->score }}/{{ $attempt->total }}</strong><br><small>{{ $attempt->percentage() }}% · {{ $attempt->gradeValues() }} valores</small></td><td><span class="status {{ $attempt->qualifiesForAptitude() ? 'approved' : 'inactive' }}">{{ $attempt->qualifiesForAptitude() ? 'Conta para aptidão' : 'Abaixo de '.\App\Support\Grading::minimumAptitudeValues() }}</span></td><td>{{ implode(', ', $attempt->weak_topics ?? []) ?: '—' }}</td><td>{{ $attempt->submitted_at->format('d/m/Y H:i') }}</td><td class="actions"><a class="btn light small" href="{{ route('admin.results.show', $attempt) }}">Conferir</a><a class="btn light small" href="{{ route('admin.students.show',$attempt->student) }}">Histórico</a></td></tr>
@empty<tr><td class="empty" colspan="8">Ainda não existem resultados.</td></tr>@endforelse
</tbody></table></section><div class="pagination">{{ $attempts->links() }}</div>
@endsection
