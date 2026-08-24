@extends('layouts.admin')
@section('title','Dashboard')
@section('page-title', auth()->user()->isAdmin() ? 'Visão da plataforma' : 'Desempenho da escola')
@section('page-subtitle','Bem-vindo(a) de volta, '.auth()->user()->name.'.')
@section('content')
@php
    $totalEditorial = max(1, $approvedCount + $reviewCount + $rejectedCount + $draftCount);
    $approvedAngle = ($approvedCount / $totalEditorial) * 360;
    $reviewAngle = $approvedAngle + ($reviewCount / $totalEditorial) * 360;
@endphp

@if(auth()->user()->isSchool())
<div class="dashboard-intro"><div><span class="dashboard-eyebrow">Últimos 30 dias</span><h2>Quem precisa de apoio e quem já está pronto?</h2><p>Use os indicadores para preparar a próxima aula e acompanhar a evolução dos alunos.</p></div><div class="actions"><x-admin.button variant="secondary" :href="route('admin.classrooms.index')">Gerir turmas</x-admin.button><x-admin.button :href="route('admin.results.index')">Ver resultados</x-admin.button></div></div>
<section class="metric-grid analytics-metrics" aria-label="Indicadores da escola">
    <article class="card metric-card"><span class="metric-icon blue">A</span><div><span>Alunos ativos</span><strong>{{ $studentsCount }}</strong><small>{{ $studentParticipation }}% participou em 30 dias</small></div></article>
    <article class="card metric-card"><span class="metric-icon green">%</span><div><span>Média recente</span><strong>{{ $averageLast30 }}%</strong><small>{{ $attemptsLast30Count }} provas submetidas</small></div></article>
    <article class="card metric-card"><span class="metric-icon green">✓</span><div><span>Alunos prontos</span><strong>{{ $readyStudentsCount }}</strong><small>Com o requisito de aptidão</small></div></article>
    <article class="card metric-card"><span class="metric-icon yellow">▶</span><div><span>Sessões em curso</span><strong>{{ $activeSessionsCount }}</strong><small>{{ $classroomsCount }} turmas cadastradas</small></div></article>
</section>
<section class="analytics-grid">
    <article class="card analytics-panel"><div class="analytics-head"><div><h3>Atividade nos últimos 7 dias</h3><p>Submissões e média diária das provas.</p></div><x-admin.button variant="ghost" size="small" :href="route('admin.results.index')">Detalhes →</x-admin.button></div>
        @php($maxDaily = max(1, $dailySchoolActivity->max('count')))
        <div class="bar-chart" role="img" aria-label="Atividade de provas nos últimos sete dias: {{ $dailySchoolActivity->map(fn($day) => $day['date'].', '.$day['count'].' submissões, média '.$day['average'].'%')->join('; ') }}">@foreach($dailySchoolActivity as $day)<div class="bar-column"><span class="bar-value">{{ $day['count'] }}</span><div class="bar-track"><i style="height:{{ max(4, ($day['count'] / $maxDaily) * 100) }}%"></i></div><strong>{{ $day['label'] }}</strong><small>{{ $day['average'] }}%</small></div>@endforeach</div>
    </article>
    <article class="card analytics-panel"><div class="analytics-head"><div><h3>Temas a reforçar</h3><p>Mais recorrentes entre as respostas recentes.</p></div></div>
        <div class="rank-list">@forelse($schoolWeakTopics as $index => $topic)<div><span class="rank-number">{{ $index + 1 }}</span><strong>{{ $topic['name'] }}</strong><span>{{ $topic['count'] }} ocorrências</span></div>@empty<x-admin.empty-state title="Sem temas críticos" description="Os temas aparecem depois das primeiras provas submetidas." />@endforelse</div>
    </article>
</section>
<div class="dashboard-next card"><div><span class="dashboard-eyebrow">Próxima ação recomendada</span><h3>{{ $attemptsLast30Count ? 'Abra o painel da turma com menor desempenho.' : 'Crie uma prova e aplique-a à primeira turma.' }}</h3></div><x-admin.button :href="$attemptsLast30Count ? route('admin.results.index') : route('admin.exams.create')">{{ $attemptsLast30Count ? 'Analisar turmas' : 'Criar prova' }}</x-admin.button></div>
@else
<div class="dashboard-intro"><div><span class="dashboard-eyebrow">Plataforma ProntoVia</span><h2>Conteúdo, adoção e operação num só lugar.</h2><p>Indicadores dos últimos 30 dias, com prioridades editoriais visíveis.</p></div><div class="actions"><x-admin.button variant="secondary" :href="route('admin.mobile-users.index')">Ver utilizadores</x-admin.button><x-admin.button :href="route('admin.approvals.index')">Rever conteúdo</x-admin.button></div></div>
<section class="metric-grid analytics-metrics" aria-label="Indicadores da plataforma">
    <article class="card metric-card"><span class="metric-icon blue">＋</span><div><span>Novas contas mobile</span><strong>{{ $newMobileUsersCount }}</strong><small>{{ $mobileUsersCount }} contas no total</small></div></article>
    <article class="card metric-card"><span class="metric-icon green">▤</span><div><span>Exames em 30 dias</span><strong>{{ $mobileExamsLast30Count }}</strong><small>{{ $mobileExamsCompletedCount }} realizados no total</small></div></article>
    <article class="card metric-card"><span class="metric-icon blue">%</span><div><span>Utilizadores ativos</span><strong>{{ $mobileEngagementRate }}%</strong><small>Com respostas nos últimos 30 dias</small></div></article>
    <article class="card metric-card"><span class="metric-icon yellow">◷</span><div><span>Conteúdo por rever</span><strong>{{ $reviewCount }}</strong><small>{{ $publicationAgeDays === null ? 'Sem pacote publicado' : 'Pacote há '.(int) $publicationAgeDays.' dias' }}</small></div></article>
</section>
<section class="analytics-grid analytics-grid--wide">
    <article class="card analytics-panel"><div class="analytics-head"><div><h3>Crescimento e utilização</h3><p>Novas contas e exames concluídos por mês.</p></div></div>
        @php($maxMonthly = max(1, $platformMonthlyTrend->max(fn($month) => max($month['users'], $month['exams']))))
        <div class="grouped-chart" role="img" aria-label="Novas contas e exames concluídos: {{ $platformMonthlyTrend->map(fn($month) => $month['label'].', '.$month['users'].' contas e '.$month['exams'].' exames')->join('; ') }}">@foreach($platformMonthlyTrend as $month)<div class="grouped-column"><div class="grouped-bars"><i class="users" style="height:{{ max(3, ($month['users'] / $maxMonthly) * 100) }}%" title="{{ $month['users'] }} contas"></i><i class="exams" style="height:{{ max(3, ($month['exams'] / $maxMonthly) * 100) }}%" title="{{ $month['exams'] }} exames"></i></div><strong>{{ $month['label'] }}</strong></div>@endforeach</div><div class="chart-legend"><span><i class="users"></i>Novas contas</span><span><i class="exams"></i>Exames concluídos</span></div>
    </article>
    <article class="card analytics-panel"><div class="analytics-head"><div><h3>Atividade das escolas</h3><p>Submissões nos últimos 30 dias.</p></div><x-admin.button variant="ghost" size="small" :href="route('admin.schools.index')">Escolas →</x-admin.button></div>
        <div class="rank-list">@forelse($schoolActivity as $index => $school)<div><span class="rank-number">{{ $index + 1 }}</span><strong>{{ $school->name }}</strong><span>{{ $school->attempts_count }} provas</span></div>@empty<x-admin.empty-state title="Sem atividade escolar" description="Ainda não houve provas submetidas pelas escolas." icon="people" />@endforelse</div>
    </article>
</section>
<section class="dashboard-grid">
    <article class="card panel"><div class="analytics-head"><div><h3>Pipeline editorial</h3><p>Distribuição atual do banco de perguntas.</p></div><x-admin.button variant="ghost" size="small" :href="route('admin.questions.index')">Banco →</x-admin.button></div><div class="status-overview"><div class="donut" style="--approved:{{ $approvedAngle }}deg;--review:{{ $reviewAngle }}deg;@if(!$questionsCount) background:#edf0ed;@endif"><span><strong>{{ $questionsCount }}</strong><small>Total</small></span></div><div class="legend"><div><i class="dot green"></i><span>Aprovadas</span><strong>{{ $approvedCount }}</strong></div><div><i class="dot yellow"></i><span>Em revisão</span><strong>{{ $reviewCount }}</strong></div><div><i class="dot red"></i><span>Rejeitadas</span><strong>{{ $rejectedCount }}</strong></div><div><i class="dot gray"></i><span>Rascunhos</span><strong>{{ $draftCount }}</strong></div></div></div></article>
    <article class="card panel"><div class="analytics-head"><div><h3>Atividade editorial recente</h3><p>Últimas perguntas alteradas.</p></div></div><div class="activity">@forelse($recentQuestions as $question)<div class="activity-item"><span class="activity-icon">?</span><div><strong>{{ str($question->statement)->limit(55) }}</strong><small>{{ $question->topic->name }} · {{ $question->updated_at->diffForHumans() }}</small></div><x-admin.state :type="$question->status">{{ ['draft'=>'Rascunho','review'=>'Em revisão','approved'=>'Aprovada','rejected'=>'Rejeitada'][$question->status] }}</x-admin.state></div>@empty<x-admin.empty-state title="Sem atividade editorial" description="A atividade aparecerá depois da primeira pergunta." />@endforelse</div></article>
</section>
@endif
@endsection
