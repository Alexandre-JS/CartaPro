@extends('layouts.admin')
@section('title','Dashboard')
@section('page-title', auth()->user()->isAdmin() ? 'Visão da plataforma' : 'Desempenho da escola')
@section('page-subtitle', auth()->user()->isAdmin() ? 'Acompanhe adoção, conteúdo e prioridades operacionais.' : 'Acompanhe resultados e identifique onde atuar primeiro.')
@section('content')
@php
    $isSchool = auth()->user()->isSchool();
@endphp

<section class="pv-dashboard" aria-labelledby="dashboard-title">
    <x-admin.page-header id="dashboard-title" :title="$isSchool ? 'Desempenho da escola' : 'Visão da plataforma'" :description="$isSchool ? 'Resultados dos últimos 30 dias e prioridades para a próxima ação.' : 'Adoção, utilização e saúde editorial da plataforma nos últimos 30 dias.'">
        <x-admin.button :href="$isSchool ? route('admin.results.index') : route('admin.approvals.index')"><i class="bi {{ $isSchool ? 'bi-bar-chart-line' : 'bi-check2-square' }}" aria-hidden="true"></i>{{ $isSchool ? 'Ver resultados' : 'Rever conteúdo' }}</x-admin.button>
    </x-admin.page-header>

    @if($isSchool)
        <section class="dashboard-kpis" aria-label="Indicadores da escola">
            @foreach([
                ['people', 'Alunos ativos', $studentsCount, $studentParticipation.'% participou em 30 dias', 'neutral'],
                ['graph-up-arrow', 'Média recente', $averageLast30.'%', $attemptsLast30Count.' provas submetidas', $averageLast30 >= 70 ? 'success' : 'warning'],
                ['check2-circle', 'Alunos prontos', $readyStudentsCount, 'Com o requisito de aptidão', 'success'],
                ['broadcast', 'Sessões em curso', $activeSessionsCount, $classroomsCount.' turmas cadastradas', 'warning'],
            ] as [$icon, $label, $value, $comparison, $tone])
                <article class="dashboard-kpi dashboard-kpi--{{ $tone }}"><div class="dashboard-kpi-label"><span>{{ $label }}</span><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></div><strong>{{ $value }}</strong><small>{{ $comparison }}</small></article>
            @endforeach
        </section>

        <section class="dashboard-primary-grid">
            <article class="dashboard-surface dashboard-performance">
                <div class="dashboard-section-head"><div><h3>Evolução de desempenho</h3><p>Submissões e média diária nos últimos sete dias.</p></div><a href="{{ route('admin.results.index') }}">Abrir análise <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
                @php($maxDaily = max(1, $dailySchoolActivity->max('count')))
                <div class="bar-chart" role="img" aria-label="Atividade de provas nos últimos sete dias: {{ $dailySchoolActivity->map(fn($day) => $day['date'].', '.$day['count'].' submissões, média '.$day['average'].'%')->join('; ') }}">@foreach($dailySchoolActivity as $day)<div class="bar-column"><span class="bar-value">{{ $day['count'] }}</span><div class="bar-track"><i style="height:{{ max(4, ($day['count'] / $maxDaily) * 100) }}%"></i></div><strong>{{ $day['label'] }}</strong><small>{{ $day['average'] }}%</small></div>@endforeach</div>
            </article>
            <aside class="dashboard-surface dashboard-attention" aria-labelledby="school-attention-title">
                <div class="dashboard-section-head"><div><h3 id="school-attention-title">Atenção necessária</h3><p>Temas a reforçar nas próximas aulas.</p></div><i class="bi bi-exclamation-circle" aria-hidden="true"></i></div>
                <ol class="dashboard-priority-list">@forelse($schoolWeakTopics as $topic)<li><span>{{ $loop->iteration }}</span><div><strong>{{ $topic['name'] }}</strong><small>{{ $topic['count'] }} ocorrências recentes</small></div></li>@empty<li class="is-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i><div><strong>Sem temas críticos</strong><small>Os temas aparecem depois das primeiras provas.</small></div></li>@endforelse</ol>
            </aside>
        </section>
        <section class="dashboard-action-strip" aria-label="Próxima ação recomendada"><i class="bi bi-lightning-charge" aria-hidden="true"></i><div><span>Próxima ação recomendada</span><strong>{{ $attemptsLast30Count ? 'Abra o painel da turma com menor desempenho.' : 'Crie uma prova e aplique-a à primeira turma.' }}</strong></div><a href="{{ $attemptsLast30Count ? route('admin.results.index') : route('admin.exams.create') }}">{{ $attemptsLast30Count ? 'Analisar turmas' : 'Criar prova' }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a></section>
    @else
        <section class="dashboard-kpis" aria-label="Indicadores da plataforma">
            @foreach([
                ['person-plus', 'Novas contas', $newMobileUsersCount, $mobileUsersCount.' contas no total', 'neutral'],
                ['clipboard-data', 'Exames realizados', $mobileExamsLast30Count, $mobileExamsCompletedCount.' realizados no total', 'success'],
                ['activity', 'Utilizadores ativos', $mobileEngagementRate.'%', 'Com atividade nos últimos 30 dias', 'neutral'],
                ['hourglass-split', 'Conteúdo por rever', $reviewCount, $publicationAgeDays === null ? 'Sem pacote publicado' : 'Pacote há '.(int) $publicationAgeDays.' dias', $reviewCount ? 'warning' : 'success'],
            ] as [$icon, $label, $value, $comparison, $tone])
                <article class="dashboard-kpi dashboard-kpi--{{ $tone }}"><div class="dashboard-kpi-label"><span>{{ $label }}</span><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></div><strong>{{ $value }}</strong><small>{{ $comparison }}</small></article>
            @endforeach
        </section>

        <section class="dashboard-primary-grid">
            <article class="dashboard-surface dashboard-performance">
                <div class="dashboard-section-head"><div><h3>Crescimento e utilização</h3><p>Novas contas e exames concluídos nos últimos seis meses.</p></div></div>
                @php($maxMonthly = max(1, $platformMonthlyTrend->max(fn($month) => max($month['users'], $month['exams']))))
                <div class="grouped-chart" role="img" aria-label="Novas contas e exames concluídos: {{ $platformMonthlyTrend->map(fn($month) => $month['label'].', '.$month['users'].' contas e '.$month['exams'].' exames')->join('; ') }}">@foreach($platformMonthlyTrend as $month)<div class="grouped-column"><div class="grouped-bars"><i class="users" style="height:{{ max(3, ($month['users'] / $maxMonthly) * 100) }}%" title="{{ $month['users'] }} contas"></i><i class="exams" style="height:{{ max(3, ($month['exams'] / $maxMonthly) * 100) }}%" title="{{ $month['exams'] }} exames"></i></div><strong>{{ $month['label'] }}</strong></div>@endforeach</div><div class="chart-legend"><span><i class="users"></i>Novas contas</span><span><i class="exams"></i>Exames concluídos</span></div>
            </article>
            <aside class="dashboard-surface dashboard-attention" aria-labelledby="platform-attention-title">
                <div class="dashboard-section-head"><div><h3 id="platform-attention-title">Atenção necessária</h3><p>Prioridades editoriais atuais.</p></div><i class="bi bi-exclamation-circle" aria-hidden="true"></i></div>
                <ul class="dashboard-status-list"><li><span class="status review">Em revisão</span><strong>{{ $reviewCount }}</strong></li><li><span class="status rejected">Rejeitadas</span><strong>{{ $rejectedCount }}</strong></li><li><span class="status draft">Rascunhos</span><strong>{{ $draftCount }}</strong></li><li><span>Última publicação</span><strong>{{ $publicationAgeDays === null ? 'Pendente' : (int) $publicationAgeDays.' dias' }}</strong></li></ul>
                <a class="dashboard-inline-link" href="{{ route('admin.questions.index') }}">Abrir banco de perguntas <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </aside>
        </section>

        <section class="dashboard-secondary-grid">
            <article class="dashboard-surface"><div class="dashboard-section-head"><div><h3>Atividade das escolas</h3><p>Submissões nos últimos 30 dias.</p></div><a href="{{ route('admin.schools.index') }}">Ver escolas <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div><div class="dashboard-compact-table" role="table" aria-label="Atividade das escolas">@forelse($schoolActivity as $school)<div role="row"><span role="cell"><i class="bi bi-building" aria-hidden="true"></i><strong>{{ $school->name }}</strong></span><span role="cell">{{ $school->attempts_count }} provas</span></div>@empty<x-admin.empty-state title="Sem atividade escolar" description="Ainda não houve provas submetidas pelas escolas." icon="people" />@endforelse</div></article>
            <article class="dashboard-surface"><div class="dashboard-section-head"><div><h3>Atividade editorial recente</h3><p>Últimas perguntas alteradas.</p></div></div><div class="activity">@forelse($recentQuestions as $question)<div class="activity-item"><span class="activity-icon"><i class="bi bi-question-lg" aria-hidden="true"></i></span><div><strong>{{ str($question->statement)->limit(55) }}</strong><small>{{ $question->topic->name }} · {{ $question->updated_at->diffForHumans() }}</small></div><x-admin.state :type="$question->status">{{ ['draft'=>'Rascunho','review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'][$question->status] }}</x-admin.state></div>@empty<x-admin.empty-state title="Sem atividade editorial" description="A atividade aparecerá depois da primeira pergunta." />@endforelse</div></article>
        </section>
    @endif
</section>
@endsection
