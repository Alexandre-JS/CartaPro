@extends('layouts.admin')
@section('title', 'Resultados')
@section('page-title', 'Resultados')
@section('page-subtitle', 'Desempenho por aluno, turma e prova.')
@section('content')
<div class="results-page">
    <x-admin.page-header id="results-page-title" title="Resultados submetidos" description="Leia o desempenho recente e identifique rapidamente quem precisa de apoio." :count="$attempts->total()" count-label="resultados">
        <x-admin.button variant="secondary" :href="route('admin.results.export', request()->query())"><i class="bi bi-download" aria-hidden="true"></i>Exportar CSV</x-admin.button>
    </x-admin.page-header>
    <section class="results-kpis" aria-label="Resumo dos resultados">
        <div><span>Média geral</span><strong>{{ $average }}%</strong><small>das provas submetidas</small></div>
        <div><span>Notas válidas</span><strong>{{ $validGradesCount }}</strong><small>contam para aptidão</small></div>
        <div><span>Provas submetidas</span><strong>{{ $attemptsCount }}</strong><small>no conjunto filtrado</small></div>
    </section>
    <form method="get" class="data-toolbar results-filters" aria-label="Filtrar resultados">
        <label class="field"><span>Turma</span><select name="classroom_id" onchange="this.form.submit()"><option value="">Todas as turmas</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected(request('classroom_id') == $classroom->id)>{{ $classroom->name }}</option>@endforeach</select></label>
        @if(request('classroom_id'))<x-admin.button variant="secondary" :href="route('admin.results.classroom', request('classroom_id'))"><i class="bi bi-bar-chart-line" aria-hidden="true"></i>Abrir painel da turma</x-admin.button>@endif
    </form>
    <x-admin.table class="results-table" labelledby="results-page-title" caption="Resultados submetidos">
        <x-slot:head><tr><th scope="col">Aluno</th><th scope="col">Prova e sessão</th><th scope="col">Turma</th><th scope="col">Resultado</th><th scope="col">Aptidão</th><th scope="col">Temas a reforçar</th><th scope="col" class="pv-actions-column">Ações</th></tr></x-slot:head>
        @forelse($attempts as $attempt)
            <tr>
                <td class="result-student"><strong><a href="{{ route('admin.students.show',$attempt->student) }}">{{ $attempt->student->name }}</a></strong><small>{{ $attempt->submitted_at->format('d/m/Y H:i') }}</small></td>
                <td class="result-exam"><strong>{{ $attempt->session->exam->name }}</strong><small>Sessão {{ $attempt->session->code }}</small></td>
                <td><a href="{{ route('admin.results.classroom', $attempt->session->classroom) }}">{{ $attempt->session->classroom->name }}</a></td>
                <td class="result-score"><strong>{{ $attempt->percentage() }}%</strong><small>{{ $attempt->score }}/{{ $attempt->total }} · {{ $attempt->gradeValues() }} valores</small></td>
                <td><x-admin.state :type="$attempt->qualifiesForAptitude() ? 'approved' : 'neutral'">{{ $attempt->qualifiesForAptitude() ? 'Nota válida' : 'Abaixo de '.\App\Support\Grading::minimumAptitudeValues() }}</x-admin.state></td>
                <td class="result-topics">{{ implode(', ', $attempt->weak_topics ?? []) ?: 'Nenhum' }}</td>
                <td class="actions"><x-admin.row-actions :view-href="route('admin.results.show', $attempt)" label="Ações do resultado"><a href="{{ route('admin.students.show',$attempt->student) }}" role="menuitem"><i class="bi bi-clock-history" aria-hidden="true"></i>Histórico do aluno</a></x-admin.row-actions></td>
            </tr>
        @empty
            <x-admin.empty-state table :colspan="7" title="Ainda não existem resultados" description="Os resultados aparecem após a primeira submissão de uma prova." />
        @endforelse
    </x-admin.table>
    <x-admin.pagination :paginator="$attempts" />
</div>
@endsection
