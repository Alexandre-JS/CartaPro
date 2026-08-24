@extends('layouts.admin')
@section('title', 'Turma '.$classroom->name)
@section('page-title', 'Turma '.$classroom->name)
@section('page-subtitle', 'Onde a turma falha e quem já está pronto para o exame.')
@section('content')

<section class="metric-grid">
    <article class="card metric-card"><span class="metric-icon blue">%</span><div><span>Média da turma</span><strong>{{ $summary['mediaPercentagem'] }}%</strong><small>{{ $summary['mediaValores'] }} valores</small></div></article>
    <article class="card metric-card"><span class="metric-icon green">✓</span><div><span>Taxa de aprovação</span><strong>{{ $summary['taxaAprovacao'] }}%</strong><small>{{ $summary['aprovacoes'] }} de {{ $summary['tentativas'] }} provas</small></div></article>
    <article class="card metric-card"><span class="metric-icon amber">👥</span><div><span>Alunos avaliados</span><strong>{{ $summary['alunosAvaliados'] }}/{{ $summary['alunosNaTurma'] }}</strong><small>{{ $summary['alunosNaTurma'] - $summary['alunosAvaliados'] }} sem provas</small></div></article>
    <article class="card metric-card"><span class="metric-icon blue">⏱</span><div><span>Tempo médio</span><strong>{{ intdiv($summary['tempoMedioSegundos'], 60) }} min</strong><small>Melhor: {{ $summary['melhorPercentagem'] }}%</small></div></article>
</section>

<div class="toolbar">
    <div>
        <h2>Onde reforçar na próxima aula</h2>
        <p>Temas ordenados pela taxa de acerto da turma, somando todas as provas aplicadas.</p>
    </div>
    <x-admin.button variant="secondary" :href="route('admin.results.index', ['classroom_id' => $classroom->id])">Ver provas individuais</x-admin.button>
</div>

<section class="card table-card">
    <table class="data-table">
        <thead><tr><th>Tema</th><th>Taxa da turma</th><th>Respostas certas</th><th>Erros</th><th>Prioridade</th></tr></thead>
        <tbody>
        @forelse($weakestTopics as $index => $topic)
            <tr>
                <td><strong>{{ str_replace('_', ' ', $topic['tema']) }}</strong></td>
                <td><strong>{{ $topic['taxa'] }}%</strong></td>
                <td>{{ $topic['acertos'] }}/{{ $topic['total'] }}</td>
                <td>{{ $topic['erros'] }}</td>
                <td><x-admin.state :type="$index === 0 ? 'review' : 'neutral'">{{ $index === 0 ? 'Reforçar primeiro' : 'Acompanhar' }}</x-admin.state></td>
            </tr>
        @empty
            <x-admin.empty-state table :colspan="5" title="Ainda não há provas submetidas" description="Os temas prioritários surgem após as primeiras avaliações da turma." />
        @endforelse
        </tbody>
    </table>
</section>

<div class="toolbar"><div><h2>Evolução por sessão</h2><p>Média da turma em cada prova aplicada, pela ordem em que foram feitas.</p></div></div>

<section class="card table-card">
    <table class="data-table">
        <thead><tr><th>Sessão</th><th>Prova</th><th>Submissões</th><th>Média</th><th>Aprovações</th></tr></thead>
        <tbody>
        @forelse($progress as $session)
            <tr>
                <td><strong>{{ $session['codigo'] }}</strong><br><small>{{ $session['inicio'] ? \Illuminate\Support\Carbon::parse($session['inicio'])->format('d/m/Y H:i') : '—' }}</small></td>
                <td>{{ $session['prova'] }}</td>
                <td>{{ $session['submissoes'] }}</td>
                <td><strong>{{ $session['media'] }}%</strong></td>
                <td>{{ $session['aprovacoes'] }}/{{ $session['submissoes'] }}</td>
            </tr>
        @empty
            <x-admin.empty-state table :colspan="5" title="Nenhuma sessão com resultados" />
        @endforelse
        </tbody>
    </table>
</section>

<div class="toolbar">
    <div>
        <h2>Prontidão dos alunos</h2>
        <p>Considera-se pronto quem tem {{ $requiredValidGrades }} notas iguais ou superiores a {{ $minimumValues }} valores.</p>
    </div>
</div>

<section class="card table-card">
    <table class="data-table">
        <thead><tr><th>Aluno</th><th>Provas</th><th>Média</th><th>Notas válidas</th><th>Faltam</th><th>Estado</th><th>Ações</th></tr></thead>
        <tbody>
        @forelse($readiness as $student)
            @php($labels = ['pronto' => ['Pronto', 'approved'], 'em_progresso' => ['Em progresso', 'review'], 'em_risco' => ['Em risco', 'rejected'], 'sem_provas' => ['Sem provas', 'inactive']])
            @php($label = $labels[$student['estado']])
            <tr>
                <td><strong>{{ $student['nome'] }}</strong></td>
                <td>{{ $student['tentativas'] }}</td>
                <td>{{ $student['mediaPercentagem'] }}%<br><small>{{ $student['mediaValores'] }} valores</small></td>
                <td>{{ $student['notasValidas'] }}</td>
                <td>{{ $student['faltam'] }}</td>
                <td><x-admin.state :type="$label[1]">{{ $label[0] }}</x-admin.state></td>
                <td class="actions"><x-admin.button variant="secondary" size="small" :href="route('admin.students.show', $student['id'])">Histórico</x-admin.button></td>
            </tr>
        @empty
            <x-admin.empty-state table :colspan="7" icon="people" title="Esta turma ainda não tem alunos ativos" />
        @endforelse
        </tbody>
    </table>
</section>

@endsection
